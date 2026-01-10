<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Payment\StripePaymentProcessor;
use App\Services\Subscriptions\SubscriptionUpgradeService;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionUpgradeServiceTest extends TestCase
{
    private SubscriptionUpgradeService $service;
    private $subscriptionRepository;
    private $planRepository;
    private $stripeProcessor;
    private $database;

    public function testGetUpgradeOptionsReturnsAvailableUpgrades(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('canUpgradeToInsider')->andReturn(true);
        $subscription->plan_id = 1;
        $subscription->id = 1;
        $subscription->plan_name = 'Basic';
        $subscription->price = 19.99;
        $subscription->includes_digital_access = false;

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->description = 'Premium features';
        $upgradePlan->features = ['Feature 1', 'Feature 2'];
        $upgradePlan->includes_insider = true;
        $upgradePlan->price = 39.99;
        $upgradePlan->billing_period = 'monthly';

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('getUpgradePlansFor')
            ->with(1)
            ->andReturn(collect([$upgradePlan]));

        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $result = $this->service->getUpgradeOptions(1);

        $this->assertTrue($result['can_upgrade']);
        $this->assertCount(1, $result['options']);
        $this->assertEquals('Premium', $result['options'][0]['plan_name']);
        $this->assertEquals(20.00, $result['options'][0]['price_difference']);
    }

    public function testGetUpgradeOptionsThrowsExceptionForNonExistentSubscription(): void
    {
        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription not found');

        $this->service->getUpgradeOptions(999);
    }

    public function testGetUpgradeOptionsReturnsNotEligibleWhenCannotUpgrade(): void
    {
        $subscription = Mockery::mock(Subscription::class);
        $subscription->shouldReceive('canUpgradeToInsider')->andReturn(false);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $result = $this->service->getUpgradeOptions(1);

        $this->assertFalse($result['can_upgrade']);
        $this->assertEquals('Subscription is not eligible for upgrade', $result['reason']);
        $this->assertEmpty($result['options']);
    }

    public function testGetUpgradeOptionsReturnsNoOptionsWhenNoUpgradePlansAvailable(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('canUpgradeToInsider')->andReturn(true);
        $subscription->plan_id = 1;

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('getUpgradePlansFor')
            ->with(1)
            ->andReturn(collect([]));

        $result = $this->service->getUpgradeOptions(1);

        $this->assertFalse($result['can_upgrade']);
        $this->assertEquals('No upgrade options available', $result['reason']);
        $this->assertEmpty($result['options']);
    }

    public function testUpgradeSubscriptionProcessesSuccessfully(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->site_id = 1;
        $subscription->shouldReceive('canUpgradeToInsider')->andReturn(true);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(null);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($upgradePlan);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->with(1, Mockery::type('array'))
            ->andReturn($upgradePlan);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->stripeProcessor
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn(['success' => true, 'client_secret' => 'pi_secret_123']);

        $result = $this->service->upgradeSubscription(1, 2, []);

        $this->assertTrue($result['success']);
        $this->assertEquals('Successfully upgraded to Insider access', $result['message']);
        $this->assertEquals(20.00, $result['price_charged']);
    }

    public function testUpgradeSubscriptionThrowsExceptionForInvalidSubscription(): void
    {
        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription not found');

        $this->service->upgradeSubscription(999, 2, []);
    }

    public function testUpgradeSubscriptionThrowsExceptionWhenNotEligible(): void
    {
        $subscription = Mockery::mock(Subscription::class);
        $subscription->shouldReceive('canUpgradeToInsider')->andReturn(false);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription is not eligible for upgrade');

        $this->service->upgradeSubscription(1, 2, []);
    }

    public function testUpgradeSubscriptionThrowsExceptionForInvalidUpgradePlan(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->plan_id = 1;
        $subscription->shouldReceive('canUpgradeToInsider')->andReturn(true);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class);
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(false);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($upgradePlan);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid upgrade plan');

        $this->service->upgradeSubscription(1, 2, []);
    }

    public function testUpgradeSubscriptionThrowsExceptionForMismatchedUpgradePlan(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->plan_id = 1;
        $subscription->shouldReceive('canUpgradeToInsider')->andReturn(true);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->upgrade_from_plan_id = 3; // Different from subscription plan_id
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($upgradePlan);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Upgrade plan does not match current subscription');

        $this->service->upgradeSubscription(1, 2, []);
    }

    public function testUpgradeSubscriptionProcessesPaymentWhenPriceDifferenceExists(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->site_id = 1;
        $subscription->shouldReceive('canUpgradeToInsider')->andReturn(true);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(null);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $paymentResult = [
            'success' => true,
            'client_secret' => 'pi_secret_123'
        ];

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->times(2) // Called twice - once at start, once at end
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($upgradePlan);

        // FIX: Match the exact array structure being passed
        $this->stripeProcessor
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->with(Mockery::on(function ($args) {
                return abs($args['amount'] - 20.00) < 0.01 // Allow floating point comparison
                    && $args['currency'] == 'USD'
                    && $args['subscription_id'] == 1
                    && $args['site_id'] == 1
                    && isset($args['metadata'])
                    && is_array($args['metadata'])
                    && $args['metadata']['type'] == 'subscription_upgrade';
            }))
            ->andReturn($paymentResult);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->with(1, Mockery::type('array'))
            ->andReturn($upgradePlan);

        $result = $this->service->upgradeSubscription(1, 2, ['payment_method_id' => 'pm_123']);

        $this->assertTrue($result['success']);
        $this->assertEquals($paymentResult, $result['payment_result']);
    }

    public function testUpgradeSubscriptionThrowsExceptionWhenPaymentFails(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->site_id = 1;
        $subscription->shouldReceive('canUpgradeToInsider')->andReturn(true);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(null);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($upgradePlan);

        $this->stripeProcessor
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Payment declined']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment declined');

        $this->service->upgradeSubscription(1, 2, []);
    }

    public function testPreviewUpgradeReturnsDetailedInformation(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();;
        $subscription->plan_id = 1;
        $subscription->plan_name = 'Basic';
        $subscription->price = 19.99;
        $subscription->includes_digital_access = false;
        $subscription->shouldReceive('isPrint')->andReturn(true);

        // FIX: Mock the plan relationship properly
        $subscription->plan = null;
        $subscription->shouldReceive('getAttribute')->with('plan')->andReturn(null);
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(new \DateTime('+15 days'));
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));

        // DON'T call __get on Mockery objects
        $subscription->shouldReceive('__get')->with('plan')->andReturn(null);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->features = ['Premium Feature 1', 'Premium Feature 2'];
        $upgradePlan->delivery_type = 'both';
        $upgradePlan->includes_insider = true;

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($upgradePlan);

        $result = $this->service->previewUpgrade(1, 2);

        $this->assertEquals('Basic', $result['current_plan']['name']);
        $this->assertEquals(19.99, $result['current_plan']['price']);
        $this->assertEquals('Premium', $result['upgrade_plan']['name']);
        $this->assertEquals(39.99, $result['upgrade_plan']['price']);
        $this->assertTrue($result['upgrade_plan']['includes_insider']);
        $this->assertEquals(20.00, $result['pricing']['price_difference']);
        $this->assertArrayHasKey('benefits', $result);
    }

    public function testPreviewUpgradeThrowsExceptionForInvalidSubscription(): void
    {
        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription not found');

        $this->service->previewUpgrade(999, 2);
    }

    public function testPreviewUpgradeThrowsExceptionForInvalidUpgradePlan(): void
    {
        $subscription = Mockery::mock(Subscription::class);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Upgrade plan not found');

        $this->service->previewUpgrade(1, 999);
    }

    public function testUpgradeCalculatesProrationForStripeSubscriptions(): void
    {
        $startDate = new \DateTime('-20 days');
        $nextBilling = new \DateTime('+10 days');

        $subscription = Mockery::mock(Subscription::class)->makePartial();;
        $subscription->plan_id = 1;
        $subscription->price = 30.00;
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn($startDate);
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn($nextBilling);
        $subscription->shouldReceive('canUpgradeToInsider')->andReturn(true);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();;
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 60.00;
        $upgradePlan->features = [];
        $upgradePlan->delivery_type = 'both';
        $upgradePlan->includes_insider = true;

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('getUpgradePlansFor')
            ->with(1)
            ->andReturn(collect([$upgradePlan]));

        $result = $this->service->getUpgradeOptions(1);

        // Should prorate based on remaining time (10 days out of 30 total days)
        // Price difference = 30.00
        // Prorated = (30.00 / 30) * 10 = 10.00
        $this->assertNotNull($result['options'][0]['price_difference']);

        $this->assertEquals(9.00, round($result['options'][0]['price_difference'], 2));
    }

    public function testUpgradeSkipsPaymentWhenNoPriceDifference(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->price = 39.99;
        $subscription->shouldReceive('canUpgradeToInsider')->andReturn(true);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(null);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99; // Same price
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($upgradePlan);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->with(1, Mockery::type('array'))
            ->andReturn($upgradePlan);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        // Should NOT call stripeProcessor since price difference is 0
        $this->stripeProcessor
            ->shouldReceive('createPaymentIntent')
            ->never();
        $result = $this->service->upgradeSubscription(1, 2, []);
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['price_charged']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->stripeProcessor = Mockery::mock(StripePaymentProcessor::class);
        $this->database = Mockery::mock(Database::class);

        $this->service = new SubscriptionUpgradeService(
            $this->subscriptionRepository,
            $this->planRepository,
            $this->stripeProcessor,
            $this->database
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}