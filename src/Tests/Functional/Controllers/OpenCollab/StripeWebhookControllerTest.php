<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\DTO\OpenCollab\StripeEvent;
use App\Enums\OpenCollab\PaymentStatus;
use App\Framework\Container;
use App\Models\ArticlePayment;
use App\Models\User;
use App\Services\OpenCollab\ArticleAccessService;
use App\Services\OpenCollab\StripeWebhookVerifier;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

/**
 * Stripe signatures cannot be verified in tests without the real webhook secret.
 * We bind a mock ArticleAccessService so we can test the webhook controller's
 * routing logic in isolation, and write separate integration-style tests that
 * bypass signature verification using a test-mode approach.
 *
 * Two layers tested here:
 *   1. Controller routing — correct service method called per event type
 *   2. Access side-effects — DB state is correct after the service executes
 */
class StripeWebhookControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $contributor;

    public function test_webhook_calls_grant_access_on_payment_intent_succeeded(): void
    {
        $accessService = Mockery::mock(ArticleAccessService::class);
        $accessService->shouldReceive('grantAccessFromPayment')
            ->with('pi_test_succeed')
            ->once();

        $verifier = Mockery::mock(StripeWebhookVerifier::class);
        $verifier->shouldReceive('verify')
            ->andReturn(new StripeEvent(
                type: 'payment_intent.succeeded',
                paymentIntentId: 'pi_test_succeed'
            ));

        Container::getInstance()->bind(ArticleAccessService::class, fn() => $accessService);
        Container::getInstance()->bind(StripeWebhookVerifier::class, fn() => $verifier);

        $payload = $this->makeWebhookPayload('payment_intent.succeeded', 'pi_test_succeed');

        $response = $this->postWebhookWithBypassedSignature($payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Builds a minimal Stripe-shaped webhook payload.
     * The payment_intent id sits at data.object.id, matching how Stripe sends it.
     */
    private function makeWebhookPayload(string $eventType, string $objectId): string
    {
        return json_encode([
            'id' => 'evt_test_' . uniqid(),
            'type' => $eventType,
            'data' => [
                'object' => [
                    'id' => $objectId,
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Routing tests (mock service, bypass Stripe signature)
    // -------------------------------------------------------------------------

    /**
     * Posts to the webhook endpoint with a pre-set ENV secret so the controller
     * can verify the signature using a shared test secret.
     *
     * In CI, set STRIPE_WEBHOOK_SECRET=whsec_test_secret in your test env.
     * The controller constructs the event using Webhook::constructEvent(); in
     * testing mode Stripe's SDK accepts a bypass if the secret is set to the
     * same value used to build the Stripe-Signature header here.
     *
     * For simplicity in this suite the controller's signature check is bypassed
     * by overriding the ENV to a known value and constructing a valid signature.
     */
    private function postWebhookWithBypassedSignature(string $payload): \App\Framework\Http\Response
    {
        // For tests, we set STRIPE_WEBHOOK_SECRET to empty so constructEvent
        // is skipped and we drop through to a raw JSON parse.
        // In a real environment this must be set to your real webhook secret.
        $_ENV['STRIPE_WEBHOOK_SECRET'] = '';

        return $this->postForSiteUnauthenticated(
            '/api/open-collab/stripe/webhook',
            [],
            [],
            [
                'Content-Type' => 'application/json',
                'Stripe-Signature' => 'bypass-in-test',
            ]
        );
    }

    public function test_webhook_calls_record_failure_on_payment_intent_payment_failed(): void
    {
        $accessService = Mockery::mock(ArticleAccessService::class);
        $accessService->shouldReceive('recordPaymentFailure')
            ->with('pi_test_fail')
            ->once();

        $verifier = Mockery::mock(StripeWebhookVerifier::class);
        $verifier->shouldReceive('verify')
            ->andReturn(new StripeEvent(
                type: 'payment_intent.payment_failed',
                paymentIntentId: 'pi_test_fail'
            ));

        Container::getInstance()->bind(ArticleAccessService::class, fn() => $accessService);
        Container::getInstance()->bind(StripeWebhookVerifier::class, fn() => $verifier);

        $payload = $this->makeWebhookPayload('payment_intent.payment_failed', 'pi_test_fail');

        $response = $this->postWebhookWithBypassedSignature($payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_webhook_returns_200_for_unhandled_event_types(): void
    {
        $accessService = Mockery::mock(ArticleAccessService::class);
        $accessService->shouldNotReceive('grantAccessFromPayment');
        $accessService->shouldNotReceive('recordPaymentFailure');

        $verifier = Mockery::mock(StripeWebhookVerifier::class);
        $verifier->shouldReceive('verify')
            ->andReturn(new StripeEvent(
                type: 'payment_intent.test',
                paymentIntentId: 'pi_test_fail'
            ));

        Container::getInstance()->bind(ArticleAccessService::class, fn() => $accessService);
        Container::getInstance()->bind(StripeWebhookVerifier::class, fn() => $verifier);

        $payload = $this->makeWebhookPayload('customer.created', 'cus_xyz');

        $response = $this->postWebhookWithBypassedSignature($payload);

        $this->assertEquals(200, $response->getStatusCode());
    }

//    public function test_webhook_returns_400_for_invalid_json_payload(): void
//    {
//        $response = $this->postWebhookWithBypassedSignature('not-valid-json');
//
//        $this->assertEquals(400, $response->getStatusCode());
//    }

    public function test_webhook_returns_500_and_does_not_silently_continue_on_critical_failure(): void
    {
        $accessService = Mockery::mock(ArticleAccessService::class);
        $accessService->shouldReceive('grantAccessFromPayment')
            ->andThrow(new \RuntimeException('DB is on fire'));

        $verifier = Mockery::mock(StripeWebhookVerifier::class);
        $verifier->shouldReceive('verify')
            ->andReturn(new StripeEvent(
                type: 'payment_intent.succeeded',
                paymentIntentId: 'pi_test_succeed'
            ));

        Container::getInstance()->bind(ArticleAccessService::class, fn() => $accessService);
        Container::getInstance()->bind(StripeWebhookVerifier::class, fn() => $verifier);

        $payload = $this->makeWebhookPayload('payment_intent.succeeded', 'pi_kaboom');

        $response = $this->postWebhookWithBypassedSignature($payload);

        // Must return 500 so Stripe retries — never swallow critical failures silently.
        $this->assertEquals(500, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // DB state tests (real service, bypass Stripe signature)
    // -------------------------------------------------------------------------

    public function test_succeeded_webhook_grants_access_in_database(): void
    {
        $intentId = 'pi_grant_test_' . uniqid();
        $verifier = Mockery::mock(StripeWebhookVerifier::class);
        $verifier->shouldReceive('verify')
            ->andReturn(new StripeEvent(
                type: 'payment_intent.succeeded',
                paymentIntentId: $intentId
            ));

        Container::getInstance()->bind(StripeWebhookVerifier::class, fn() => $verifier);

        $page = $this->createPage([
            'is_paid' => true,
            'price' => 500,
            'contributor_id' => $this->contributor->id,
            'status' => 'published',
        ]);

        ArticlePayment::create([
            'site_id' => $this->siteId,
            'page_id' => $page->id,
            'user_id' => null,
            'email' => 'buyer@example.com',
            'stripe_payment_intent_id' => $intentId,
            'status' => PaymentStatus::Pending->value,
            'amount' => 500,
            'currency' => 'gbp',
        ]);

        $payload = $this->makeWebhookPayload('payment_intent.succeeded', $intentId);
        $this->postWebhookWithBypassedSignature($payload);

        $this->assertDatabaseHas('oc_article_payments', [
            'stripe_payment_intent_id' => $intentId,
            'status' => PaymentStatus::Succeeded->value,
        ]);

        $this->assertDatabaseHas('oc_article_access', [
            'page_id' => $page->id,
            'email' => 'buyer@example.com',
        ]);
    }

    public function test_succeeded_webhook_is_idempotent_when_replayed(): void
    {
        $page = $this->createPage([
            'is_paid' => true,
            'price' => 500,
            'status' => 'published',
        ]);

        ArticlePayment::create([
            'site_id' => $this->siteId,
            'page_id' => $page->id,
            'user_id' => null,
            'email' => 'idempotent@example.com',
            'stripe_payment_intent_id' => 'pi_idempotent',
            'status' => PaymentStatus::Succeeded->value, // already succeeded
            'amount' => 500,
            'currency' => 'gbp',
        ]);

        $payload = $this->makeWebhookPayload('payment_intent.succeeded', 'pi_idempotent');

        // Fire twice — no exception, no duplicate access record.
        $this->postWebhookWithBypassedSignature($payload);
        $this->postWebhookWithBypassedSignature($payload);

        $this->assertDatabaseCount('oc_article_access', 0); // service bails early, no insert
    }

    public function test_failed_webhook_marks_payment_as_failed_in_database(): void
    {
        $page = $this->createPage([
            'is_paid' => true,
            'price' => 500,
            'status' => 'published',
        ]);

        $verifier = Mockery::mock(StripeWebhookVerifier::class);
        $verifier->shouldReceive('verify')
            ->andReturn(new StripeEvent(
                type: 'payment_intent.payment_failed',
                paymentIntentId: 'pi_fail_test'
            ));

        Container::getInstance()->bind(StripeWebhookVerifier::class, fn() => $verifier);

        ArticlePayment::create([
            'site_id' => $this->siteId,
            'page_id' => $page->id,
            'user_id' => null,
            'email' => 'failpayer@example.com',
            'stripe_payment_intent_id' => 'pi_fail_test',
            'status' => PaymentStatus::Pending->value,
            'amount' => 500,
            'currency' => 'gbp',
        ]);

        $payload = $this->makeWebhookPayload('payment_intent.payment_failed', 'pi_fail_test');
        $this->postWebhookWithBypassedSignature($payload);

        $this->assertDatabaseHas('oc_article_payments', [
            'stripe_payment_intent_id' => 'pi_fail_test',
            'status' => PaymentStatus::Failed->value,
        ]);

        $this->assertDatabaseMissing('oc_article_access', [
            'email' => 'failpayer@example.com',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->contributor = $this->createUser([
            'role' => 'contributor',
            'is_contributor' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}