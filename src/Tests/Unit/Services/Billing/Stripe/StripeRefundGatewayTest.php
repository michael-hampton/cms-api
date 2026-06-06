<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\DTO\Payments\StripeRefundResult;
use App\Exceptions\Payments\RefundGatewayException;
use App\Services\Billing\Stripe\StripeRefundGateway;
use Mockery;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\ApiErrorException;
use Stripe\Refund;
use Stripe\Service\RefundService;
use Stripe\StripeClient;

class StripeRefundGatewayTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── refundPaymentIntent (new typed surface) ───────────────────────────────

    public function test_refundPaymentIntent_returns_typed_dto_on_success(): void
    {
        [$gateway, $refundService] = $this->makeGateway();

        $stripeRefund = Refund::constructFrom([
            'id'       => 're_typed_123',
            'amount'   => 5000,
            'status'   => 'succeeded',
            'currency' => 'gbp',
        ]);

        $refundService
            ->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn (array $params) =>
                    $params['payment_intent'] === 'pi_test' &&
                    $params['amount'] === 5000 &&
                    $params['metadata'] === ['order_id' => '1']
                ),
                []
            )
            ->andReturn($stripeRefund);

        $result = $gateway->refundPaymentIntent(
            paymentIntentId: 'pi_test',
            amountCents: 5000,
            currency: 'gbp',
            metadata: ['order_id' => '1'],
        );

        $this->assertInstanceOf(StripeRefundResult::class, $result);
        $this->assertSame('re_typed_123', $result->refundId);
        $this->assertSame('succeeded', $result->status);
        $this->assertSame(5000, $result->amountCents);
        $this->assertSame('gbp', $result->currency);
    }

    public function test_refundPaymentIntent_throws_RefundGatewayException_on_stripe_error(): void
    {
        [$gateway, $refundService] = $this->makeGateway();

        $apiError = new class('Your card has insufficient funds.') extends ApiErrorException {};

        $refundService->shouldReceive('create')->once()->andThrow($apiError);

        $this->expectException(RefundGatewayException::class);
        $this->expectExceptionMessage('Stripe refund failed: Your card has insufficient funds.');

        $gateway->refundPaymentIntent('pi_fail', 1000, 'gbp');
    }

    public function test_refundPaymentIntent_sends_amount_in_minor_units(): void
    {
        [$gateway, $refundService] = $this->makeGateway();

        $stripeRefund = Refund::constructFrom([
            'id'       => 're_minor',
            'amount'   => 1499,
            'status'   => 'succeeded',
            'currency' => 'gbp',
        ]);

        $refundService
            ->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn (array $params) =>
                    $params['payment_intent'] === 'pi_pence' &&
                    $params['amount'] === 1499
                ),
                []
            )
            ->andReturn($stripeRefund);

        $result = $gateway->refundPaymentIntent(
            paymentIntentId: 'pi_pence',
            amountCents: 1499,
            currency: 'gbp',
        );

        $this->assertSame(1499, $result->amountCents);
    }

    public function test_refundPaymentIntent_sends_empty_metadata_when_not_provided(): void
    {
        [$gateway, $refundService] = $this->makeGateway();

        $stripeRefund = Refund::constructFrom([
            'id'       => 're_no_meta',
            'amount'   => 1000,
            'status'   => 'succeeded',
            'currency' => 'gbp',
        ]);

        $refundService
            ->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn (array $params) =>
                    $params['payment_intent'] === 'pi_no_meta' &&
                    $params['amount'] === 1000 &&
                    $params['metadata'] === []
                ),
                []
            )
            ->andReturn($stripeRefund);

        $gateway->refundPaymentIntent(
            paymentIntentId: 'pi_no_meta',
            amountCents: 1000,
            currency: 'gbp',
        );

        $this->assertTrue(true);
    }

    public function test_refundPaymentIntent_wraps_original_exception_as_previous(): void
    {
        [$gateway, $refundService] = $this->makeGateway();

        $original = new class('Network error.') extends ApiErrorException {};

        $refundService->shouldReceive('create')->once()->andThrow($original);

        try {
            $gateway->refundPaymentIntent('pi_wrap', 500, 'gbp');
            $this->fail('Expected RefundGatewayException was not thrown');
        } catch (RefundGatewayException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }

    // ── Legacy refund() surface (array return) ────────────────────────────────

    public function test_refund_maps_internal_customer_request_reason_to_stripe_reason(): void
    {
        [$gateway, $refundService] = $this->makeGateway();

        $stripeRefund = Refund::constructFrom([
            'id'     => 're_test',
            'amount' => 1439,
            'status' => 'succeeded',
        ]);

        $refundService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn(array $p) =>
                $p['payment_intent'] === 'pi_test' &&
                $p['amount']         === 1439 &&
                $p['reason']         === 'requested_by_customer'
            ))
            ->andReturn($stripeRefund);

        $result = $gateway->refund('pi_test', 14.39, ['reason' => 'customer_request']);

        $this->assertTrue($result['success']);
        $this->assertSame('re_test', $result['refund_id']);
    }

    public function test_refund_preserves_valid_stripe_reason(): void
    {
        [$gateway, $refundService] = $this->makeGateway();

        $stripeRefund = Refund::constructFrom([
            'id'     => 're_duplicate',
            'amount' => 1000,
            'status' => 'succeeded',
        ]);

        $refundService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn(array $p) => $p['reason'] === 'duplicate'))
            ->andReturn($stripeRefund);

        $result = $gateway->refund('ch_test', 10.00, ['reason' => 'duplicate']);

        $this->assertTrue($result['success']);
        $this->assertSame('re_duplicate', $result['refund_id']);
    }

    public function test_refund_routes_pi_prefix_to_payment_intent_param(): void
    {
        [$gateway, $refundService] = $this->makeGateway();

        $stripeRefund = Refund::constructFrom(['id' => 're_pi', 'amount' => 500, 'status' => 'succeeded']);

        $refundService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn(array $p) =>
                isset($p['payment_intent']) && !isset($p['charge'])
            ))
            ->andReturn($stripeRefund);

        $result = $gateway->refund('pi_abc123', 5.00);

        $this->assertTrue($result['success']);
    }

    public function test_refund_routes_ch_prefix_to_charge_param(): void
    {
        [$gateway, $refundService] = $this->makeGateway();

        $stripeRefund = Refund::constructFrom(['id' => 're_ch', 'amount' => 500, 'status' => 'succeeded']);

        $refundService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn(array $p) =>
                isset($p['charge']) && !isset($p['payment_intent'])
            ))
            ->andReturn($stripeRefund);

        $result = $gateway->refund('ch_abc123', 5.00);

        $this->assertTrue($result['success']);
    }

    public function test_refund_returns_failure_array_on_stripe_error(): void
    {
        [$gateway, $refundService] = $this->makeGateway();

        // Mockery cannot intercept getMessage() — it is defined on PHP's built-in
        // \Exception and bypasses Mockery's proxy entirely. Use a real anonymous class
        // so both getMessage() (inherited from \Exception) and getStripeCode()
        // (overridden here) return genuine values without any mocking.
        $apiError = new class('Card declined') extends ApiErrorException {
            public function getStripeCode(): ?string
            {
                return 'card_declined';
            }
        };

        $refundService->shouldReceive('create')->once()->andThrow($apiError);

        $result = $gateway->refund('pi_err', 10.00);

        $this->assertFalse($result['success']);
        $this->assertSame('Card declined', $result['message']);
        $this->assertSame('card_declined', $result['error_code']);
    }

    public function test_refundPaymentIntent_sends_idempotency_key_when_provided(): void
    {
        [$gateway, $refundService] = $this->makeGateway();

        $stripeRefund = Refund::constructFrom([
            'id'       => 're_idem',
            'amount'   => 5000,
            'status'   => 'succeeded',
            'currency' => 'gbp',
        ]);

        $refundService
            ->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn (array $params) =>
                    $params['payment_intent'] === 'pi_test' &&
                    $params['amount'] === 5000 &&
                    $params['metadata'] === ['order_id' => '1']
                ),
                Mockery::on(fn (array $options) =>
                    $options['idempotency_key'] === 'order_refund_1'
                )
            )
            ->andReturn($stripeRefund);

        $result = $gateway->refundPaymentIntent(
            paymentIntentId: 'pi_test',
            amountCents: 5000,
            currency: 'gbp',
            metadata: ['order_id' => '1'],
            idempotencyKey: 'order_refund_1',
        );

        $this->assertInstanceOf(StripeRefundResult::class, $result);
        $this->assertSame('re_idem', $result->refundId);
        $this->assertSame('succeeded', $result->status);
        $this->assertSame(5000, $result->amountCents);
        $this->assertSame('gbp', $result->currency);
    }

    public function test_refundPaymentIntent_does_not_send_idempotency_options_when_not_provided(): void
    {
        [$gateway, $refundService] = $this->makeGateway();

        $stripeRefund = Refund::constructFrom([
            'id'       => 're_no_idem',
            'amount'   => 1000,
            'status'   => 'succeeded',
            'currency' => 'gbp',
        ]);

        $refundService
            ->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn (array $params) =>
                    $params['payment_intent'] === 'pi_no_idem' &&
                    $params['amount'] === 1000 &&
                    $params['metadata'] === []
                ),
                []
            )
            ->andReturn($stripeRefund);

        $result = $gateway->refundPaymentIntent(
            paymentIntentId: 'pi_no_idem',
            amountCents: 1000,
            currency: 'gbp',
        );

        $this->assertSame('re_no_idem', $result->refundId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @return array{StripeRefundGateway, Mockery\MockInterface}
     */
    private function makeGateway(): array
    {
        $refundService = Mockery::mock(RefundService::class);
        $stripe        = Mockery::mock(StripeClient::class);
        $stripe->refunds = $refundService;

        return [new StripeRefundGateway($stripe), $refundService];
    }
}