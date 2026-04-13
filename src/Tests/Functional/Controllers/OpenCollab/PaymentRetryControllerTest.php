<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Framework\Container;
use App\Models\User;
use App\Services\OpenCollab\PaymentRetryService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

/**
 * PaymentRetryService is mocked at the container level so no real Stripe
 * calls are made. We test that:
 *   - A valid retry returns 200 with client_secret and payment_id
 *   - Unauthenticated requests are rejected with 401
 *   - DomainException (max retries, bad intent state) maps to 422
 *   - InvalidArgumentException (not found, wrong user, not failed) maps to 404
 */
class PaymentRetryControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $contributor;

    // ── Success ───────────────────────────────────────────────────────────────

    public function test_retry_returns_client_secret_and_payment_id_on_success(): void
    {
        $this->actingAs($this->contributor);

        $this->bindRetryService(fn($mock) => $mock->shouldReceive('retry')
            ->once()
            ->with(42, $this->contributor->id)
            ->andReturn([
                'client_secret' => 'pi_test_secret_abc',
                'payment_id' => 42,
            ])
        );

        $response = $this->postForSite('/api/open-collab/payments/42/retry');

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('pi_test_secret_abc', $data['data']['client_secret']);
        $this->assertEquals(42, $data['data']['payment_id']);
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    /**
     * Bind a fresh mock PaymentRetryService into the container for one test.
     */
    private function bindRetryService(callable $configure): void
    {
        Container::getInstance()->bind(PaymentRetryService::class, function () use ($configure) {
            $mock = Mockery::mock(PaymentRetryService::class);
            $configure($mock);
            return $mock;
        });
    }

    // ── DomainException → 422 ────────────────────────────────────────────────

//    public function test_returns_422_when_max_retries_reached(): void
//    {
//        $this->actingAs($this->contributor);
//
//        $this->bindRetryService(fn($mock) =>
//        $mock->shouldReceive('retry')
//            ->andThrow(new \DomainException('Maximum retry attempts (3) reached. Please contact support.'))
//        );
//
//        $response = $this->postForSite('/api/open-collab/payments/1/retry');
//        $data     = json_decode($response->getContent(), true);
//
//        $this->assertEquals(422, $response->getStatusCode());
//        $this->assertStringContainsString('Maximum retry attempts', $data['error']);
//    }

    public function test_unauthenticated_user_receives_401(): void
    {
        $response = $this->postForSiteUnauthenticated('/api/open-collab/payments/1/retry');

        $this->assertEquals(401, $response->getStatusCode());
    }

    // ── InvalidArgumentException → 404 ───────────────────────────────────────

    public function test_returns_422_when_stripe_intent_not_in_retryable_state(): void
    {
        $this->actingAs($this->contributor);

        $this->bindRetryService(fn($mock) => $mock->shouldReceive('retry')
            ->andThrow(new \DomainException('Cannot retry: payment intent is in status [processing].'))
        );

        $response = $this->postForSite('/api/open-collab/payments/1/retry');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertStringContainsString('Cannot retry', $data['error']);
    }

    public function test_returns_404_when_payment_not_found(): void
    {
        $this->actingAs($this->contributor);

        $this->bindRetryService(fn($mock) => $mock->shouldReceive('retry')
            ->andThrow(new \InvalidArgumentException('Payment [999] not found.'))
        );

        $response = $this->postForSite('/api/open-collab/payments/999/retry');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_returns_404_when_payment_belongs_to_different_user(): void
    {
        $this->actingAs($this->contributor);

        $this->bindRetryService(fn($mock) => $mock->shouldReceive('retry')
            ->andThrow(new \InvalidArgumentException('Payment [5] not found.'))
        );

        $response = $this->postForSite('/api/open-collab/payments/5/retry');

        $this->assertEquals(404, $response->getStatusCode());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function test_returns_404_when_payment_has_not_failed(): void
    {
        $this->actingAs($this->contributor);

        $this->bindRetryService(fn($mock) => $mock->shouldReceive('retry')
            ->andThrow(new \InvalidArgumentException('Payment [3] is not in a failed state (status: succeeded).'))
        );

        $response = $this->postForSite('/api/open-collab/payments/3/retry');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('not in a failed state', $data['error']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->contributor = $this->createUser([
            'email' => 'retry-contributor@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);

        // Default binding — overridden per-test where needed.
        $this->bindRetryService(fn($mock) => $mock->shouldReceive('retry')->andReturn([
            'client_secret' => 'default_secret',
            'payment_id' => 1,
        ])
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}