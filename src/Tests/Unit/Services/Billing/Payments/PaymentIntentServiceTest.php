<?php

namespace App\Tests\Unit\Services\Billing\Payments;

use App\DTO\Stripe\CreatePaymentIntentDto;
use App\DTO\Stripe\PaymentIntentResultDto;
use App\Models\Member;
use App\Models\Order;
use App\Services\Billing\Payments\PaymentIntentService;
use App\Services\Billing\Stripe\StripeCustomerGateway;
use App\Services\Billing\Stripe\StripePaymentIntentGateway;
use Mockery;
use PHPUnit\Framework\TestCase;

class PaymentIntentServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_reuses_existing_payment_intent_when_order_state_matches(): void
    {
        $order = $this->makeOrder(total: 25.50, paymentIntentId: 'pi_existing');
        $member = $this->makeMember();
        $subscriptions = $this->makeSubscriptions([11]);
        $metadata = $this->expectedMetadata($order, $subscriptions, $member, 1);

        $paymentIntentGateway = Mockery::mock(StripePaymentIntentGateway::class);
        $customerGateway = Mockery::mock(StripeCustomerGateway::class);

        $customerGateway->shouldReceive('getOrCreate')
            ->once()
            ->with($member)
            ->andReturn('cus_123');

        $paymentIntentGateway->shouldReceive('retrieve')
            ->once()
            ->with('pi_existing')
            ->andReturn(new PaymentIntentResultDto(
                success: true,
                paymentIntentId: 'pi_existing',
                clientSecret: 'secret_existing',
                status: 'requires_payment_method',
                customerId: 'cus_123',
                amountCents: 2550,
                currency: 'gbp',
                metadata: $metadata,
            ));

        $paymentIntentGateway->shouldReceive('update')->never();
        $paymentIntentGateway->shouldReceive('createWithCustomer')->never();

        $result = (new PaymentIntentService($paymentIntentGateway, $customerGateway))
            ->createForOrder($order, $subscriptions, $member, 1);

        $this->assertTrue($result['success']);
        $this->assertSame('pi_existing', $result['payment_intent_id']);
        $this->assertSame('secret_existing', $result['client_secret']);
    }

    public function test_it_updates_existing_payment_intent_when_order_state_changed_and_intent_is_editable(): void
    {
        $order = $this->makeOrder(total: 27.00, paymentIntentId: 'pi_existing');
        $member = $this->makeMember();
        $subscriptions = $this->makeSubscriptions([11]);

        $paymentIntentGateway = Mockery::mock(StripePaymentIntentGateway::class);
        $customerGateway = Mockery::mock(StripeCustomerGateway::class);

        $customerGateway->shouldReceive('getOrCreate')->once()->andReturn('cus_123');

        $paymentIntentGateway->shouldReceive('retrieve')
            ->once()
            ->with('pi_existing')
            ->andReturn(new PaymentIntentResultDto(
                success: true,
                paymentIntentId: 'pi_existing',
                clientSecret: 'secret_existing',
                status: 'requires_payment_method',
                customerId: 'cus_123',
                amountCents: 2550,
                currency: 'gbp',
                metadata: ['checkout_hash' => 'old_hash'],
            ));

        $paymentIntentGateway->shouldReceive('update')
            ->once()
            ->with('pi_existing', Mockery::on(function (CreatePaymentIntentDto $dto): bool {
                return $dto->amountCents === 2700
                    && $dto->currency === 'gbp'
                    && $dto->stripeCustomerId === 'cus_123'
                    && !empty($dto->metadata['checkout_hash']);
            }))
            ->andReturn(new PaymentIntentResultDto(
                success: true,
                paymentIntentId: 'pi_existing',
                clientSecret: 'secret_updated',
                status: 'requires_payment_method',
                customerId: 'cus_123',
                amountCents: 2700,
                currency: 'gbp',
            ));

        $paymentIntentGateway->shouldReceive('createWithCustomer')->never();

        $result = (new PaymentIntentService($paymentIntentGateway, $customerGateway))
            ->createForOrder($order, $subscriptions, $member, 1);

        $this->assertTrue($result['success']);
        $this->assertSame('pi_existing', $result['payment_intent_id']);
        $this->assertSame('secret_updated', $result['client_secret']);
    }

    public function test_it_replaces_existing_payment_intent_when_intent_is_not_reusable(): void
    {
        $order = $this->makeOrder(total: 25.50, paymentIntentId: 'pi_existing');
        $member = $this->makeMember();
        $subscriptions = $this->makeSubscriptions([11]);

        $paymentIntentGateway = Mockery::mock(StripePaymentIntentGateway::class);
        $customerGateway = Mockery::mock(StripeCustomerGateway::class);

        $customerGateway->shouldReceive('getOrCreate')->once()->andReturn('cus_123');

        $paymentIntentGateway->shouldReceive('retrieve')
            ->once()
            ->with('pi_existing')
            ->andReturn(new PaymentIntentResultDto(
                success: true,
                paymentIntentId: 'pi_existing',
                clientSecret: 'secret_existing',
                status: 'succeeded',
                customerId: 'cus_123',
                amountCents: 2550,
                currency: 'gbp',
            ));

        $paymentIntentGateway->shouldReceive('update')->never();
        $paymentIntentGateway->shouldReceive('createWithCustomer')
            ->once()
            ->with(Mockery::type(CreatePaymentIntentDto::class))
            ->andReturn(new PaymentIntentResultDto(
                success: true,
                paymentIntentId: 'pi_new',
                clientSecret: 'secret_new',
                status: 'requires_payment_method',
                customerId: 'cus_123',
                amountCents: 2550,
                currency: 'gbp',
            ));

        $result = (new PaymentIntentService($paymentIntentGateway, $customerGateway))
            ->createForOrder($order, $subscriptions, $member, 1);

        $this->assertTrue($result['success']);
        $this->assertSame('pi_new', $result['payment_intent_id']);
    }

    private function makeOrder(float $total, ?string $paymentIntentId): Order
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 10;
        $order->total = $total;
        $order->currency = 'GBP';
        $order->payment_intent_id = $paymentIntentId;

        return $order;
    }

    private function makeMember(): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 5;

        return $member;
    }

    private function makeSubscriptions(array $ids): array
    {
        return array_map(
            fn (int $id): array => ['subscription' => (object) ['id' => $id]],
            $ids,
        );
    }

    private function expectedMetadata(Order $order, array $subscriptions, Member $member, int $siteId): array
    {
        $subscriptionIds = array_map(fn ($s) => $s['subscription']->id, $subscriptions);

        $metadata = [
            'order_id'               => $order->id,
            'subscription_count'     => count($subscriptions),
            'subscription_ids'       => implode(',', $subscriptionIds),
            'member_id'              => $member->id,
            'multiple_subscriptions' => count($subscriptionIds) > 1,
            'site_id'                => $siteId,
        ];

        $metadata['checkout_hash'] = hash('sha256', json_encode([
            'order_id' => $order->id,
            'total' => (int) round($order->total * 100),
            'currency' => strtolower((string) $order->currency),
            'metadata' => $metadata,
        ], JSON_THROW_ON_ERROR));

        return $metadata;
    }
}
