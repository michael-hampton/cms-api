<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Models\StripeWebhookEvent;
use App\Services\OpenCollab\StripeWebhookVerifier;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookControllerTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $_ENV['STRIPE_WEBHOOK_SECRET'] = 'whsec_functional_test_secret';
        parent::setUp();
    }

    private function bindStripe(array $payload)
    {
        \App\Framework\Container::getInstance()->bind(StripeWebhookVerifier::class, function () use ($payload) {
            $mock = Mockery::mock(StripeWebhookVerifier::class);

            if (empty($payload['success'])) {
                $mock->shouldReceive('verify')
                    ->andThrow(new SignatureVerificationException('failed'));
            } else {
                $mock->shouldReceive('verify')
                    ->andReturn((object)['success' => true, 'id' => $payload['id'], 'type' => $payload['type']]);
            }

            return $mock;
        });
    }

    public function test_webhook_returns_200_and_persists_event(): void
    {
        $payload = [
            'id' => 'evt_func_1',
            'type' => 'customer.created',
            'data' => ['object' => ['id' => 'cus_1']],
            'success' => true
        ];

        $this->bindStripe($payload);

        $response = $this->postWebhook($payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_func_1',
            'type' => 'customer.created',
        ]);
    }

    public function test_duplicate_webhooks_are_idempotent(): void
    {
        $payload = [
            'id' => 'evt_func_dupe_1',
            'type' => 'customer.created',
            'data' => ['object' => ['id' => 'cus_2']],
            'success' => true
        ];

        $this->bindStripe($payload);

        $first = $this->postWebhook($payload);
        $second = $this->postWebhook($payload);

        $this->assertEquals(200, $first->getStatusCode());
        $this->assertEquals(200, $second->getStatusCode());
        $this->assertEquals(
            1,
            StripeWebhookEvent::where('stripe_event_id', 'evt_func_dupe_1')->count()
        );
    }

    public function test_unknown_event_returns_200_safely(): void
    {
        $payload = [
            'id' => 'evt_func_unknown_1',
            'type' => 'foo.bar.unknown',
            'data' => ['object' => ['id' => 'obj_1']],
            'success' => true
        ];

        $this->bindStripe($payload);

        $response = $this->postWebhook($payload);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_invalid_signature_rejected(): void
    {
        $payload = [
            'id' => 'evt_func_bad_sig',
            'type' => 'customer.created',
            'data' => ['object' => ['id' => 'cus_3']],
        ];

        $this->bindStripe($payload);

        $response = $this->postWebhook($payload, "t=" . time() . ",v1=invalid");

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_malformed_payload_rejected(): void
    {
        $this->markTestSkipped('Malformed raw-body case covered in verifier-level tests.');
    }

    private function postWebhook(array $payload, ?string $signature = null): \App\Framework\Http\Response
    {
        $json = json_encode($payload);
        $sig = $signature ?? $this->signatureForPayload($json);

        return $this->postForSiteUnauthenticated(
            '/api/open-collab/stripe/webhook',
            $payload,
            [],
            [
                'Content-Type' => 'application/json',
                'Stripe-Signature' => $sig,
            ]
        );
    }

    private function signatureForPayload(string $payload): string
    {
        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $secret = (string)($_ENV['STRIPE_WEBHOOK_SECRET'] ?? '');
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$timestamp},v1={$signature}";
    }
}

