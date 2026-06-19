<?php

namespace App\Tests\Support;

use App\Models\Payment;
use App\Models\Subscription;

trait MocksSubscriptionModels
{
    protected function mockSubscription(array $attributes = [], array $methods = []): Subscription
    {
        $attributes = array_merge([
            'id' => 1,
            'member_id' => 1,
            'site_id' => 1,
            'status' => 'active',
            'auto_renew' => false,
            'cancel_at_period_end' => false,
            'start_date' => null,
            'end_date' => null,
            'next_billing_date' => null,
            'pause_until' => null,
            'payment_subscription_id' => null,
            'delivery_type' => 'digital',
            'includes_digital_access' => false,
            'premium_access' => [],
        ], $attributes);

        $mockedMethods = array_values(array_unique(array_merge(['getAttribute'], array_keys($methods))));

        $subscription = $this->getMockBuilder(Subscription::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();

        $subscription->method('getAttribute')
            ->willReturnCallback(static fn(string $key) => $attributes[$key] ?? null);

        foreach ($methods as $method => $returnValue) {
            $subscription->method($method)->willReturn($returnValue);
        }

        return $subscription;
    }

    protected function mockPayment(array $attributes = []): Payment
    {
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAttribute'])
            ->getMock();

        $payment->method('getAttribute')
            ->willReturnCallback(static fn(string $key) => $attributes[$key] ?? null);

        return $payment;
    }
}
