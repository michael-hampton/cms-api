<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Services\Subscriptions\StripeSubscriptionReconciler;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stripe\Exception\ApiConnectionException;
use Stripe\StripeClient;

class StripeSubscriptionReconcilerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private StripeSubscriptionReconciler $reconciler;
    private $stripe;
    private $logger;

    public function testReconcileUpdateNeeded(): void
    {
        $subscription = Mockery::mock(Subscription::class);
        $subscription->shouldReceive('setAttribute')->andReturnNull();
        $subscription->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $subscription->shouldReceive('getAttribute')->with('payment_subscription_id')->andReturn('sub_123');
        $subscription->shouldReceive('getAttribute')->andReturnNull();
        $subscription->shouldReceive('relationLoaded')->andReturn(false);
        $subscription->id = 1;
        $subscription->payment_subscription_id = 'sub_123';
        $subscription->status = SubscriptionStatus::PAST_DUE->value;

        $stripeSub = $this->makeStripeSubscription([
            'id' => 'sub_123',
            'status' => 'active',
            'current_period_start' => 1672531200,
            'current_period_end' => 1675209600,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
        ]);

        $this->stripe->subscriptions = Mockery::mock();
        $this->stripe->subscriptions->shouldReceive('retrieve')
            ->once()
            ->with('sub_123', ['expand' => ['latest_invoice']])
            ->andReturn($stripeSub);

        $subscription->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return isset($arg['status']) && $arg['status'] === SubscriptionStatus::ACTIVE->value;
            }));

        $this->logger->shouldReceive('info')->once();

        $result = $this->reconciler->reconcile($subscription);

        $this->assertEquals('updated', $result['action']);
    }

    public function testReconcileNoChanges(): void
    {
        $subscription = Mockery::mock(Subscription::class);
        $subscription->shouldReceive('setAttribute')->andReturnNull();
        $subscription->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $subscription->shouldReceive('getAttribute')->with('payment_subscription_id')->andReturn('sub_123');
        $subscription->shouldReceive('getAttribute')->with('status')->andReturn(SubscriptionStatus::ACTIVE->value);
        $subscription->shouldReceive('getAttribute')->with('current_period_start')->andReturn('2023-01-01 00:00:00');
        $subscription->shouldReceive('getAttribute')->with('current_period_end')->andReturn('2023-02-01 00:00:00');
        $subscription->shouldReceive('getAttribute')->with('auto_renew')->andReturn(true);
        $subscription->shouldReceive('getAttribute')->with('cancelled_at')->andReturn(null);
        $subscription->shouldReceive('getAttribute')->with('end_date')->andReturn('2023-02-01 00:00:00');
        $subscription->shouldReceive('getAttribute')->andReturnNull();
        $subscription->shouldReceive('relationLoaded')->andReturn(false);
        $subscription->id = 1;
        $subscription->payment_subscription_id = 'sub_123';

        $stripeSub = $this->makeStripeSubscription([
            'id' => 'sub_123',
            'status' => 'active',
            'current_period_start' => 1672531200,
            'current_period_end' => 1675209600,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
        ]);

        $this->stripe->subscriptions = Mockery::mock();
        $this->stripe->subscriptions->shouldReceive('retrieve')
            ->once()
            ->andReturn($stripeSub);

        $subscription->shouldNotReceive('update');

        $result = $this->reconciler->reconcile($subscription);

        $this->assertEquals('skipped', $result['action']);
    }

    public function testReconcileStripeError(): void
    {
        $subscription = Mockery::mock(Subscription::class);
        $subscription->shouldReceive('setAttribute')->andReturnNull();
        $subscription->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $subscription->shouldReceive('getAttribute')->with('payment_subscription_id')->andReturn('sub_123');
        $subscription->shouldReceive('getAttribute')->andReturnNull();
        $subscription->shouldReceive('relationLoaded')->andReturn(false);
        $subscription->id = 1;
        $subscription->payment_subscription_id = 'sub_123';

        $this->stripe->subscriptions = Mockery::mock();
        $this->stripe->subscriptions->shouldReceive('retrieve')
            ->once()
            ->andThrow(new ApiConnectionException('Connection failed'));

        $this->logger->shouldReceive('error')->once();

        $result = $this->reconciler->reconcile($subscription);

        $this->assertEquals('failed', $result['action']);
        $this->assertEquals('Connection failed', $result['error']);
    }

    public function testReconcileSkipsWhenStripeIdMissing(): void
    {
        $subscription = Mockery::mock(Subscription::class);
        $subscription->shouldReceive('setAttribute')->andReturnNull();
        $subscription->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $subscription->shouldReceive('getAttribute')->with('payment_subscription_id')->andReturn(null);
        $subscription->shouldReceive('getAttribute')->andReturnNull();
        $subscription->shouldReceive('relationLoaded')->andReturn(false);
        $subscription->id = 1;
        $subscription->payment_subscription_id = null;

        $subscription->shouldNotReceive('update');

        $result = $this->reconciler->reconcile($subscription);

        $this->assertEquals('skipped', $result['action']);
        $this->assertSame('', $result['stripe_subscription_id']);
    }

    public function testReconcileSkipsReplacedLocalStatus(): void
    {
        $subscription = Mockery::mock(Subscription::class);
        $subscription->shouldReceive('setAttribute')->andReturnNull();
        $subscription->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $subscription->shouldReceive('getAttribute')->with('payment_subscription_id')->andReturn('sub_123');
        $subscription->shouldReceive('getAttribute')->with('status')->andReturn(SubscriptionStatus::REPLACED->value);
        $subscription->shouldReceive('getAttribute')->andReturnNull();
        $subscription->shouldReceive('relationLoaded')->andReturn(false);
        $subscription->id = 1;
        $subscription->payment_subscription_id = 'sub_123';
        $subscription->status = SubscriptionStatus::REPLACED->value;

        $this->stripe->subscriptions = Mockery::mock();
        $this->stripe->subscriptions->shouldNotReceive('retrieve');
        $subscription->shouldNotReceive('update');

        $result = $this->reconciler->reconcile($subscription);

        $this->assertEquals('skipped', $result['action']);
    }

    public function testReconcileMapsPausedStatus(): void
    {
        $subscription = Mockery::mock(Subscription::class);
        $subscription->shouldReceive('setAttribute')->andReturnNull();
        $subscription->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $subscription->shouldReceive('getAttribute')->with('payment_subscription_id')->andReturn('sub_123');
        $subscription->shouldReceive('getAttribute')->andReturnNull();
        $subscription->shouldReceive('relationLoaded')->andReturn(false);
        $subscription->id = 1;
        $subscription->payment_subscription_id = 'sub_123';
        $subscription->status = SubscriptionStatus::ACTIVE->value;

        $stripeSub = $this->makeStripeSubscription([
            'id' => 'sub_123',
            'status' => 'paused',
            'current_period_start' => 1672531200,
            'current_period_end' => 1675209600,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
        ]);

        $this->stripe->subscriptions = Mockery::mock();
        $this->stripe->subscriptions->shouldReceive('retrieve')
            ->once()
            ->andReturn($stripeSub);

        $subscription->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return isset($arg['status']) && $arg['status'] === SubscriptionStatus::PAUSED->value;
            }));

        $this->logger->shouldReceive('info')->once();

        $result = $this->reconciler->reconcile($subscription);

        $this->assertEquals('updated', $result['action']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripe = Mockery::mock(StripeClient::class);
        $this->logger = Mockery::mock(Logger::class);

        $this->reconciler = new StripeSubscriptionReconciler(
            $this->logger,
            $this->stripe
        );
    }

    /**
     * Plain object — never construct Stripe\Subscription here. constructFrom()
     * resolves ObjectTypes::mapping, which autoloads every Stripe resource
     * class and OOMs under suite memory pressure (128M IDE default).
     */
    private function makeStripeSubscription(array $values): stdClass
    {
        $stripeSub = new stdClass();
        foreach ($values as $key => $value) {
            $stripeSub->{$key} = $value;
        }

        return $stripeSub;
    }
}
