<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Repositories\SubscriptionRepository;
use App\Services\Payment\StripePaymentProcessor;
use App\Services\Subscriptions\SubscriptionBillingService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class SubscriptionBillingServiceTest extends FunctionalTestCase
{
    private $subscriptionRepository;
    private $stripeProcessor;
    private $databaseMock;
    private SubscriptionBillingService $service;

    public function test_update_billing_date_throws_exception_when_subscription_not_found(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription not found');

        $this->service->updateBillingDate(999, 15);
    }

    public function test_update_billing_date_throws_exception_for_non_stripe_subscription(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('hasStripeSubscription')
            ->once()
            ->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Can only update billing date for Stripe subscriptions');

        $this->service->updateBillingDate(1, 15);
    }

    public function test_update_billing_date_throws_exception_for_inactive_subscription(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('hasStripeSubscription')
            ->once()
            ->andReturn(true);
        $subscription->status = 'cancelled';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Can only update billing date for active subscriptions');

        $this->service->updateBillingDate(1, 15);
    }

    public function test_update_billing_date_validates_day_range(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('hasStripeSubscription')
            ->once()
            ->andReturn(true);
        $subscription->status = 'active';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Day must be between 1 and 31');

        $this->service->updateBillingDate(1, 32);
    }

    public function test_update_billing_date_throws_exception_when_stripe_update_fails(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('hasStripeSubscription')
            ->once()
            ->andReturn(true);
        $subscription->shouldReceive('getStripeSubscriptionId')
            ->once()
            ->andReturn('sub_123');
        $subscription->status = 'active';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->stripeProcessor->shouldReceive('updateBillingCycleAnchor')
            ->with('sub_123', 15, true)
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Stripe API error'
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Stripe API error');

        $this->service->updateBillingDate(1, 15);
    }

    public function test_update_billing_date_successfully(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('hasStripeSubscription')
            ->once()
            ->andReturn(true);
        $subscription->shouldReceive('getStripeSubscriptionId')
            ->once()
            ->andReturn('sub_123');
        $subscription->status = 'active';
        $subscription->metadata = ['existing' => 'data'];
        $subscription->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->stripeProcessor->shouldReceive('updateBillingCycleAnchor')
            ->with('sub_123', 15, true)
            ->once()
            ->andReturn([
                'success' => true,
                'new_billing_date' => '2026-02-15',
                'subscription' => (object)['id' => 'sub_123']
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->with(1, m::on(function ($data) {
                return isset($data['next_billing_date'])
                    && $data['next_billing_date'] === '2026-02-15 00:00:00'
                    && isset($data['metadata']['billing_day_of_month'])
                    && $data['metadata']['billing_day_of_month'] === 15
                    && isset($data['metadata']['last_billing_update']);
            }))
            ->once();

        $result = $this->service->updateBillingDate(1, 15);

        $this->assertTrue($result['success']);
        $this->assertEquals('2026-02-15', $result['new_billing_date']);
        $this->assertEquals('Billing date updated successfully', $result['message']);
    }

    public function test_update_billing_date_without_proration(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('hasStripeSubscription')
            ->once()
            ->andReturn(true);
        $subscription->shouldReceive('getStripeSubscriptionId')
            ->once()
            ->andReturn('sub_123');
        $subscription->status = 'active';
        $subscription->metadata = [];
        $subscription->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->stripeProcessor->shouldReceive('updateBillingCycleAnchor')
            ->with('sub_123', 15, false)
            ->once()
            ->andReturn([
                'success' => true,
                'new_billing_date' => '2026-02-15'
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once();

        $result = $this->service->updateBillingDate(1, 15, false);

        $this->assertTrue($result['success']);
    }

    public function test_preview_billing_date_change_returns_error_when_subscription_not_found(): void
    {
        $this->subscriptionRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->service->previewBillingDateChange(999, 15);

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscription not found', $result['message']);
    }

    public function test_preview_billing_date_change_returns_error_for_non_stripe_subscription(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('hasStripeSubscription')
            ->once()
            ->andReturn(false);

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $result = $this->service->previewBillingDateChange(1, 15);

        $this->assertFalse($result['success']);
        $this->assertEquals('Can only preview billing date changes for Stripe subscriptions', $result['message']);
    }

    public function test_preview_billing_date_change_successfully(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('hasStripeSubscription')
            ->once()
            ->andReturn(true);
        $subscription->shouldReceive('getStripeSubscriptionId')
            ->once()
            ->andReturn('sub_123');

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $expectedPreview = [
            'success' => true,
            'current_period_end' => '2026-02-01',
            'new_billing_date' => '2026-02-15',
            'proration_amount' => 5.50,
            'is_credit' => false,
            'days_difference' => 14
        ];

        $this->stripeProcessor->shouldReceive('calculateBillingDateProration')
            ->with('sub_123', 15)
            ->once()
            ->andReturn($expectedPreview);

        $result = $this->service->previewBillingDateChange(1, 15);

        $this->assertTrue($result['success']);
        $this->assertEquals('2026-02-15', $result['new_billing_date']);
        $this->assertEquals(5.50, $result['proration_amount']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->stripeProcessor = m::mock(StripePaymentProcessor::class);
        $this->databaseMock = m::mock(Database::class);

        $this->service = new SubscriptionBillingService(
            $this->subscriptionRepository,
            $this->stripeProcessor,
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}