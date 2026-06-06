<?php

namespace App\Tests\Unit\Services\Billing\Payments;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Billing\PaymentRepository;
use App\Services\Billing\Payments\PaymentRecorder;
use Mockery;
use PHPUnit\Framework\TestCase;

class PaymentRecorderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_record_subscription_stripe_payment_persists_member_id(): void
    {
        $repository = Mockery::mock(PaymentRepository::class);
        $recorder = new PaymentRecorder($repository);

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 10;
        $subscription->member_id = 44;
        $subscription->site_id = 2;
        $subscription->price_paid_cents = 1299;
        $subscription->price = 12.99;
        $subscription->currency = 'gbp';

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 3;
        $plan->billing_period = 'monthly';
        $plan->currency = 'gbp';

        $payment = Mockery::mock(Payment::class)->makePartial();

        $repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn(array $data) => $data['subscription_id'] === 10
                && $data['member_id'] === 44
                && $data['site_id'] === 2
                && $data['payment_intent_id'] === 'pi_member_recorded'
                && $data['transaction_id'] === 'pi_member_recorded'
            ))
            ->andReturn($payment);

        $result = $recorder->recordSubscriptionStripePayment($subscription, $plan, [
            'amount_cents' => 1299,
            'payment_intent_id' => 'pi_member_recorded',
            'transaction_id' => 'pi_member_recorded',
            'stripe_invoice_id' => 'in_member_recorded',
            'status' => 'completed',
            'stripe_subscription_id' => 'sub_member_recorded',
            'stripe_customer_id' => 'cus_member_recorded',
        ]);

        $this->assertSame($payment, $result);
    }
}
