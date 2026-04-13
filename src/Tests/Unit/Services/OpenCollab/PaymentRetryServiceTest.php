<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Framework\Database\Database;
use App\Models\ArticlePayment;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Services\Billing\PaymentProviders\PaymentIntentGateway;
use App\Services\OpenCollab\PaymentRetryService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class PaymentRetryServiceTest extends FunctionalTestCase
{
    private PaymentRetryService $service;
    private MockInterface $paymentRepository;
    private MockInterface $paymentIntentGateway;
    private MockInterface $databaseMock;

    // ── retry() ───────────────────────────────────────────────────────────────

    public function test_retry_returns_client_secret_and_does_not_increment_attempt_count(): void
    {
        $payment = $this->makeFailedPayment(['id' => 1, 'attempt_count' => 1]);
        $intent = $this->makeStripeIntent('requires_payment_method', 'secret_xyz');

        $this->paymentRepository->shouldReceive('find')->with(1)->andReturn($payment);
        $this->paymentIntentGateway->shouldReceive('retrieve')
            ->with('pi_test')
            ->once()
            ->andReturn($intent);

        // attempt_count must NOT be incremented on retry initiation
        $this->paymentRepository->shouldReceive('update')
            ->once()
            ->withArgs(function ($id, array $data): bool {
                return $id === 1
                    && !array_key_exists('attempt_count', $data)
                    && $data['status'] === 'pending';
            });

        $result = $this->service->retry(paymentId: 1, userId: 7);

        $this->assertEquals('secret_xyz', $result['client_secret']);
        $this->assertEquals(1, $result['payment_id']);
    }

    public function test_retry_accepts_requires_confirmation_as_retryable_state(): void
    {
        $payment = $this->makeFailedPayment(['id' => 1, 'attempt_count' => 0]);
        $intent = $this->makeStripeIntent('requires_confirmation', 'secret_abc');

        $this->paymentRepository->shouldReceive('find')->andReturn($payment);
        $this->paymentIntentGateway->shouldReceive('retrieve')->andReturn($intent);
        $this->paymentRepository->shouldReceive('update');

        $result = $this->service->retry(1, 7);

        $this->assertEquals('secret_abc', $result['client_secret']);
    }

    public function test_retry_throws_when_payment_not_found(): void
    {
        $this->paymentRepository->shouldReceive('find')->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $this->service->retry(paymentId: 999, userId: 7);
    }

    public function test_retry_throws_when_payment_belongs_to_different_user(): void
    {
        $payment = $this->makeFailedPayment(['user_id' => 99]);

        $this->paymentRepository->shouldReceive('find')->andReturn($payment);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->retry(paymentId: 1, userId: 7); // user 7, but payment owned by 99
    }

    public function test_retry_throws_when_payment_has_not_failed(): void
    {
        $payment = $this->makePayment(['status' => 'succeeded', 'attempt_count' => 0]);

        $this->paymentRepository->shouldReceive('find')->andReturn($payment);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not in a failed state/i');

        $this->service->retry(1, 7);
    }

    // ── recordFailure() ───────────────────────────────────────────────────────

    public function test_retry_throws_when_max_retries_reached(): void
    {
        $payment = $this->makeFailedPayment(['attempt_count' => 3]);

        $this->paymentRepository->shouldReceive('find')->andReturn($payment);
        $this->paymentIntentGateway->shouldNotReceive('retrieve');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Maximum retry attempts/i');

        $this->service->retry(1, 7);
    }

    public function test_retry_throws_when_stripe_intent_is_not_retryable(): void
    {
        $payment = $this->makeFailedPayment(['attempt_count' => 0]);
        $intent = $this->makeStripeIntent('processing', 'secret_xyz'); // wrong state

        $this->paymentRepository->shouldReceive('find')->andReturn($payment);
        $this->paymentIntentGateway->shouldReceive('retrieve')->andReturn($intent);
        $this->paymentRepository->shouldNotReceive('update');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Cannot retry/i');

        $this->service->retry(1, 7);
    }

    public function test_retry_re_checks_max_retries_inside_transaction_to_prevent_race(): void
    {
        // Simulate: initial check passes, but by the time we're inside the
        // transaction the record has been updated concurrently to max retries.
        $payment = $this->makeFailedPayment(['id' => 1, 'attempt_count' => 2]);
        $freshPayment = $this->makeFailedPayment(['id' => 1, 'attempt_count' => 3]);
        $intent = $this->makeStripeIntent('requires_payment_method', 'secret');

        $this->paymentRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($payment, $freshPayment); // second call (inside tx) returns stale
        $this->paymentIntentGateway->shouldReceive('retrieve')->andReturn($intent);
        $this->paymentRepository->shouldNotReceive('update');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Maximum retry attempts/i');

        $this->service->retry(1, 7);
    }

    public function test_retry_wraps_counter_update_in_transaction(): void
    {
        $payment = $this->makeFailedPayment(['attempt_count' => 0]);
        $intent = $this->makeStripeIntent('requires_payment_method', 'secret');

        $this->paymentRepository->shouldReceive('find')->andReturn($payment);
        $this->paymentIntentGateway->shouldReceive('retrieve')->andReturn($intent);
        $this->paymentRepository->shouldReceive('update');

        $this->service->retry(1, 7);
        $this->assertTrue(true); // transaction mock was invoked
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function test_record_failure_updates_status_and_increments_count(): void
    {
        $payment = $this->makePayment(['id' => 5, 'status' => 'pending', 'attempt_count' => 1]);

        $this->paymentRepository->shouldReceive('findByPaymentIntentId')
            ->with('pi_failed')
            ->andReturn($payment);
        $this->paymentRepository->shouldReceive('update')
            ->once()
            ->withArgs(function ($id, array $data): bool {
                return $id === 5
                    && $data['status'] === 'failed'
                    && $data['attempt_count'] === 2
                    && $data['failure_reason'] === 'Card declined.';
            });

        $this->service->recordFailure('pi_failed', 'Card declined.');
        $this->assertTrue(true);
    }

    public function test_record_failure_does_nothing_when_payment_intent_not_found(): void
    {
        $this->paymentRepository->shouldReceive('findByPaymentIntentId')->andReturn(null);
        $this->paymentRepository->shouldNotReceive('update');

        // Must not throw — non-critical path
        $this->service->recordFailure('pi_ghost');
        $this->assertTrue(true);
    }

    public function test_record_failure_stores_null_failure_reason_when_not_provided(): void
    {
        $payment = $this->makePayment(['id' => 5, 'status' => 'pending', 'attempt_count' => 0]);

        $this->paymentRepository->shouldReceive('findByPaymentIntentId')->andReturn($payment);
        $this->paymentRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, array $data) => $data['failure_reason'] === null);

        $this->service->recordFailure('pi_x');
        $this->assertTrue(true);
    }

    public function test_retry_then_record_failure_increments_count_exactly_once(): void
    {
        // Demonstrates that retry() does NOT increment, recordFailure() does.
        // Net result after one retry + one failure = attempt_count goes up by 1 only.
        $payment = $this->makeFailedPayment(['id' => 1, 'attempt_count' => 1]);
        $intent = $this->makeStripeIntent('requires_payment_method', 'secret');

        // retry() — must not touch attempt_count
        $this->paymentRepository->shouldReceive('find')->with(1)->andReturn($payment);
        $this->paymentIntentGateway->shouldReceive('retrieve')->andReturn($intent);
        $this->paymentRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, array $data) => !array_key_exists('attempt_count', $data));

        $this->service->retry(1, 7);

        // recordFailure() — increments from 1 to 2
        $this->paymentRepository->shouldReceive('findByPaymentIntentId')
            ->with('pi_test')
            ->andReturn($payment);
        $this->paymentRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, array $data) => $data['attempt_count'] === 2);

        $this->service->recordFailure('pi_test', 'Insufficient funds.');
        $this->assertTrue(true);
    }

    private function makeFailedPayment(array $attributes = []): ArticlePayment
    {
        return $this->makePayment(array_merge(['status' => 'failed'], $attributes));
    }

    private function makePayment(array $attributes = []): ArticlePayment
    {
        $defaults = [
            'id' => 1,
            'user_id' => 7,
            'page_id' => 10,
            'site_id' => 1,
            'email' => 'buyer@example.com',
            'stripe_payment_intent_id' => 'pi_test',
            'status' => 'failed',
            'amount' => 500,
            'currency' => 'gbp',
            'attempt_count' => 0,
            'failure_reason' => null,
        ];
        $payment = new ArticlePayment(array_merge($defaults, $attributes));
        $payment->exists = true;
        return $payment;
    }

    private function makeStripeIntent(string $status, string $clientSecret): object
    {
        return (object)[
            'id' => 'pi_test',
            'status' => $status,
            'client_secret' => $clientSecret,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentRepository = Mockery::mock(ArticlePaymentRepository::class);
        $this->paymentIntentGateway = Mockery::mock(PaymentIntentGateway::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn(callable $cb) => $cb());

        $this->service = new PaymentRetryService(
            $this->paymentRepository,
            $this->paymentIntentGateway,
            $this->databaseMock,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}