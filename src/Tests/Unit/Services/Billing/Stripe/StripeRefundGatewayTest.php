<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Services\Billing\Stripe\StripeRefundGateway;
use Mockery;
use PHPUnit\Framework\TestCase;
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

    public function test_refund_maps_internal_customer_request_reason_to_stripe_reason(): void
    {
        $refundService = Mockery::mock(RefundService::class);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->refunds = $refundService;

        $refund = Refund::constructFrom([
            'id' => 're_test',
            'amount' => 1439,
            'status' => 'succeeded',
        ]);

        $refundService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn(array $params) => $params['payment_intent'] === 'pi_test'
                && $params['amount'] === 1439
                && $params['reason'] === 'requested_by_customer'
            ))
            ->andReturn($refund);

        $result = (new StripeRefundGateway($stripe))->refund('pi_test', 14.39, [
            'reason' => 'customer_request',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('re_test', $result['refund_id']);
    }

    public function test_refund_preserves_valid_stripe_reason(): void
    {
        $refundService = Mockery::mock(RefundService::class);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->refunds = $refundService;

        $refund = Refund::constructFrom([
            'id' => 're_duplicate',
            'amount' => 1000,
            'status' => 'succeeded',
        ]);

        $refundService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn(array $params) => $params['reason'] === 'duplicate'))
            ->andReturn($refund);

        $result = (new StripeRefundGateway($stripe))->refund('ch_test', 10.00, [
            'reason' => 'duplicate',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('re_duplicate', $result['refund_id']);
    }
}
