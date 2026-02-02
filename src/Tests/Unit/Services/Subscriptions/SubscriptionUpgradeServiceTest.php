<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Exceptions\Subscriptions\InactiveSubscriptionException;
use App\Exceptions\Subscriptions\InvalidUpgradePlanException;
use App\Exceptions\Subscriptions\MissingStripePriceException;
use App\Exceptions\Subscriptions\PaymentFailedException;
use App\Exceptions\Subscriptions\PlanMismatchException;
use App\Exceptions\Subscriptions\SubscriptionNotFoundException;
use App\Exceptions\Subscriptions\UnauthorizedException;
use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPremiumAccess;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
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

        $_ENV['APP_ENV'] = 'production';
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
        $_ENV['APP_ENV'] = 'testing';
    }

    public function testGetUpgradeOptionsReturnsAvailableUpgrades(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->plan_id = 1;
        $subscription->id = 1;
        $subscription->plan_name = 'Basic';
        $subscription->price = 19.99;
        $subscription->currency = 'USD';

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->description = 'Premium features';
        $upgradePlan->features = ['Feature 1', 'Feature 2'];
        $upgradePlan->price = 39.99;
        $upgradePlan->billing_period = 'monthly';

        $subscription->shouldReceive('getAvailableUpgrades')->andReturn([
            [
                'plan' => $upgradePlan,
                'new_access' => [
                    ['type' => 'newsletter', 'identifier' => 'insider']
                ]
            ]
        ]);

        $mockCollection = Mockery::mock();
        $mockCollection->shouldReceive('map')->andReturn(collect([]));
        $subscription->shouldReceive('premiumAccess->get')->andReturn($mockCollection);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

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

        $this->expectException(SubscriptionNotFoundException::class);
        $this->expectExceptionMessage('Subscription not found');

        $this->service->getUpgradeOptions(999);
    }

    public function testGetUpgradeOptionsReturnsNotEligibleWhenCannotUpgrade(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('getAvailableUpgrades')->andReturn([]);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $result = $this->service->getUpgradeOptions(1);

        $this->assertFalse($result['can_upgrade']);
        $this->assertEquals('No upgrade options available', $result['reason']);
        $this->assertEmpty($result['options']);
    }

    public function testGetUpgradeOptionsReturnsNoOptionsWhenNoUpgradePlansAvailable(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->plan_id = 1;
        $subscription->shouldReceive('getAvailableUpgrades')->andReturn([]);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

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
        $subscription->shouldReceive('isActive')->andReturn(true);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(null);

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $access1->id = 1;

        $subscription->shouldReceive('grantPremiumAccess')
            ->with('newsletter', 'insider', null)
            ->once()
            ->andReturn($access1);

        $subscription->shouldReceive('grantLowerTierPlans')
            ->once()
            ->andReturn([]);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider']
        ]);

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
            ->twice()
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
        $this->assertEquals('Successfully upgraded subscription', $result['message']);
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

        $this->planRepository->shouldReceive('find')
            ->once()
            ->andReturnNull();

        $this->expectException(SubscriptionNotFoundException::class);
        $this->expectExceptionMessage('Subscription not found');

        $this->service->upgradeSubscription(999, 2, []);
    }

    public function testUpgradeSubscriptionThrowsExceptionWhenNotActive(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('isActive')->andReturn(false);

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
            ->andReturn(null);

        $this->expectException(InactiveSubscriptionException::class);
        $this->expectExceptionMessage('Subscription is not active');

        $this->service->upgradeSubscription(1, 2, []);
    }

    public function testUpgradeSubscriptionThrowsExceptionWhenNotEligible(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('isActive')->andReturn(false);

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

        $this->planRepository->shouldReceive('find')
            ->once()
            ->andReturnNull();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription is not active');

        $this->service->upgradeSubscription(1, 2, []);
    }

    public function testUpgradeSubscriptionThrowsExceptionForInvalidUpgradePlan(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->plan_id = 1;
        $subscription->shouldReceive('isActive')->andReturn(true);

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

        $this->expectException(InvalidUpgradePlanException::class);
        $this->expectExceptionMessage('Invalid upgrade plan');

        $this->service->upgradeSubscription(1, 2, []);
    }

    public function testUpgradeSubscriptionThrowsExceptionForMismatchedUpgradePlan(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->plan_id = 1;
        $subscription->shouldReceive('isActive')->andReturn(true);

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

        $this->expectException(PlanMismatchException::class);
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
        $subscription->shouldReceive('isActive')->andReturn(true);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(null);

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $access1->id = 1;

        $subscription->shouldReceive('grantPremiumAccess')
            ->with('newsletter', 'insider', null)
            ->once()
            ->andReturn($access1);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider']
        ]);

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
            ->times(2)
            ->andReturn($subscription);

        $subscription->shouldReceive('grantLowerTierPlans')
            ->once()
            ->andReturn([]);

        $this->planRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($upgradePlan);

        $this->stripeProcessor
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->with(Mockery::on(function ($args) {
                return abs($args['amount'] - 20.00) < 0.01
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
            ->twice()
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
        $subscription->shouldReceive('isActive')->andReturn(true);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(null);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([]);

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

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Payment declined');

        $this->service->upgradeSubscription(1, 2, []);
    }

    public function testPreviewUpgradeReturnsDetailedInformation(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->plan_id = 1;
        $subscription->plan_name = 'Basic';
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->includes_digital_access = false;
        $subscription->plan = null;

        $subscription->shouldReceive('isPrint')->andReturn(true);
        $subscription->shouldReceive('getAttribute')->with('plan')->andReturn(null);
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(new \DateTime('+15 days'));
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));
        $subscription->shouldReceive('__get')->with('plan')->andReturn(null);
        $subscription->shouldReceive('premiumAccess')->andReturn(collect([]));
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->features = ['Premium Feature 1', 'Premium Feature 2'];
        $upgradePlan->delivery_type = 'both';
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider']
        ]);

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
        $this->assertTrue($result['upgrade_plan']['includes_digital']);
        $this->assertEquals(20.00, $result['pricing']['price_difference']);
        $this->assertArrayHasKey('benefits', $result);
        $this->assertArrayHasKey('is_estimate', $result['pricing']);
        $this->assertFalse($result['pricing']['is_estimate']); // Not Stripe
    }

    public function testPreviewUpgradeThrowsExceptionForInvalidSubscription(): void
    {
        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $this->expectException(SubscriptionNotFoundException::class);
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

        $this->expectException(InvalidUpgradePlanException::class);
        $this->expectExceptionMessage('Upgrade plan not found');

        $this->service->previewUpgrade(1, 999);
    }

    public function testUpgradeCalculatesProrationForStripeSubscriptions(): void
    {
        $startDate = new \DateTime('-20 days');
        $nextBilling = new \DateTime('+10 days');

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->plan_id = 1;
        $subscription->price = 30.00;
        $subscription->currency = 'USD';

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 60.00;
        $upgradePlan->features = [];
        $upgradePlan->delivery_type = 'both';
        $upgradePlan->billing_period = 'monthly';
        $upgradePlan->description = '';

        $subscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn($startDate);
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn($nextBilling);
        $subscription->shouldReceive('getAvailableUpgrades')->andReturn([
            [
                'plan' => $upgradePlan,
                'new_access' => [
                    ['type' => 'newsletter', 'identifier' => 'insider']
                ]
            ]
        ]);

        $mockCollection = Mockery::mock();
        $mockCollection->shouldReceive('map')->andReturn(collect([]));
        $subscription->shouldReceive('premiumAccess->get')->andReturn($mockCollection);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $result = $this->service->getUpgradeOptions(1);

        // Should prorate based on remaining time (10 days out of 30 total days)
        // Price difference = 30.00, Prorated = (30.00 / 30) * 10 = 10.00
        $this->assertNotNull($result['options'][0]['price_difference']);
        $this->assertLessThan(15.00, $result['options'][0]['price_difference']);
        $this->assertGreaterThan(5.00, $result['options'][0]['price_difference']);
        $this->assertTrue($result['options'][0]['is_estimate']); // Stripe subscriptions are estimates
    }

    public function testUpgradeGrantsImmediateDigitalAccess(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->site_id = 1;
        $subscription->includes_digital_access = false;
        $subscription->shouldReceive('isActive')->andReturn(true);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(null);

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $access1->id = 1;

        $subscription->shouldReceive('grantPremiumAccess')
            ->with('newsletter', 'insider', null)
            ->once()
            ->andReturn($access1);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium with Insider';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider']
        ]);

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

        $subscription->shouldReceive('grantLowerTierPlans')
            ->once()
            ->andReturn([]);

        $this->planRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($upgradePlan);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                // Check if either update contains includes_digital_access
                return !isset($data['includes_digital_access']) || $data['includes_digital_access'] === true;
            }))
            ->twice()
            ->andReturn($upgradePlan);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->stripeProcessor
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn(['success' => true]);

        $result = $this->service->upgradeSubscription(1, 2, []);

        $this->assertTrue($result['success']);
    }

    public function testUpgradeSkipsPaymentWhenNoPriceDifference(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->price = 39.99;
        $subscription->currency = 'USD';
        $subscription->shouldReceive('isActive')->andReturn(true);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(null);

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $access1->id = 1;

        $subscription->shouldReceive('grantPremiumAccess')
            ->with('newsletter', 'insider', null)
            ->once()
            ->andReturn($access1);

        $subscription->shouldReceive('grantLowerTierPlans')
            ->once()
            ->andReturn([]);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99; // Same price
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider']
        ]);

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
            ->twice()
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

    /** New tests ***/
    public function testGetUpgradeOptionsReturnsMultiplePremiumAccess(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->plan_name = 'Basic';
        $subscription->price = 19.99;
        $subscription->currency = 'USD';

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->description = 'Premium features';
        $upgradePlan->features = ['Feature 1', 'Feature 2'];
        $upgradePlan->price = 39.99;
        $upgradePlan->billing_period = 'monthly';

        $subscription->shouldReceive('getAvailableUpgrades')->andReturn([
            [
                'plan' => $upgradePlan,
                'new_access' => [
                    ['type' => 'newsletter', 'identifier' => 'insider'],
                    ['type' => 'archive', 'identifier' => 'full']
                ]
            ]
        ]);

        $mockCollection = Mockery::mock();
        $mockCollection->shouldReceive('map')->andReturn(collect([]));
        $subscription->shouldReceive('premiumAccess->get')->andReturn($mockCollection);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $result = $this->service->getUpgradeOptions(1);

        $this->assertTrue($result['can_upgrade']);
        $this->assertCount(1, $result['options']);
        $this->assertCount(2, $result['options'][0]['premium_access']);
        $this->assertEquals('insider', $result['options'][0]['premium_access'][0]['identifier']);
        $this->assertEquals('full', $result['options'][0]['premium_access'][1]['identifier']);
    }

    public function testGetUpgradeOptionsFiltersbyPremiumType(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->currency = 'USD';
        $subscription->price = 19.99;

        $insiderPlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $insiderPlan->id = 2;
        $insiderPlan->name = 'Insider Only';
        $insiderPlan->price = 29.99;
        $insiderPlan->features = [];
        $insiderPlan->billing_period = 'monthly';
        $insiderPlan->description = '';

        $archivePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $archivePlan->id = 3;
        $archivePlan->name = 'Archive Only';
        $archivePlan->price = 24.99;
        $archivePlan->features = [];
        $archivePlan->billing_period = 'monthly';
        $archivePlan->description = '';

        $subscription->shouldReceive('getAvailableUpgrades')->andReturn([
            [
                'plan' => $insiderPlan,
                'new_access' => [
                    ['type' => 'newsletter', 'identifier' => 'insider']
                ]
            ],
            [
                'plan' => $archivePlan,
                'new_access' => [
                    ['type' => 'archive', 'identifier' => 'full']
                ]
            ]
        ]);

        $mockCollection = Mockery::mock();
        $mockCollection->shouldReceive('map')->andReturn(collect([]));
        $subscription->shouldReceive('premiumAccess->get')->andReturn($mockCollection);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $result = $this->service->getUpgradeOptions(1, 'newsletter', 'insider');

        $this->assertTrue($result['can_upgrade']);
        $this->assertCount(1, $result['options']);
        $this->assertEquals('Insider Only', $result['options'][0]['plan_name']);
    }

    public function testUpgradeSubscriptionGrantsMultiplePremiumAccess(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->site_id = 1;
        $subscription->shouldReceive('isActive')->andReturn(true);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(null);

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $access1->id = 1;

        $access2 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $access2->id = 2;

        $subscription->shouldReceive('grantPremiumAccess')
            ->with('newsletter', 'insider', null)
            ->once()
            ->andReturn($access1);

        $subscription->shouldReceive('grantPremiumAccess')
            ->with('newsletter', 'tech-weekly', null)
            ->once()
            ->andReturn($access2);

        $subscription->shouldReceive('grantLowerTierPlans')
            ->once()
            ->andReturn([]);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium Bundle';
        $upgradePlan->price = 49.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider'],
            ['type' => 'newsletter', 'identifier' => 'tech-weekly']
        ]);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->times(2)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($upgradePlan);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->with(1, Mockery::type('array'))
            ->twice()
            ->andReturn($upgradePlan);

        $this->stripeProcessor
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn(['success' => true]);

        $result = $this->service->upgradeSubscription(1, 2, []);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['premium_access_granted']);
    }

    public function testUpgradeSubscriptionSetsBackwardCompatibilityFlag(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->site_id = 1;
        $subscription->shouldReceive('isActive')->andReturn(true);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(null);

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $access1->id = 1;

        $subscription->shouldReceive('grantPremiumAccess')
            ->with('newsletter', 'insider', null)
            ->once()
            ->andReturn($access1);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Insider Plan';
        $upgradePlan->price = 29.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider']
        ]);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->times(2)
            ->andReturn($subscription);

        $subscription->shouldReceive('grantLowerTierPlans')
            ->once()
            ->andReturn([]);

        $this->planRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($upgradePlan);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return !isset($data['includes_digital_access']) || $data['includes_digital_access'] === true;
            }))
            ->twice()
            ->andReturn($upgradePlan);

        $this->stripeProcessor
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn(['success' => true]);

        $result = $this->service->upgradeSubscription(1, 2, []);

        $this->assertTrue($result['success']);
    }

    public function testPreviewUpgradeShowsPremiumAccessBenefits(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->plan_id = 1;
        $subscription->plan_name = 'Basic';
        $subscription->price = 19.99;
        $subscription->plan = null;
        $subscription->currency = 'USD';

        $subscription->shouldReceive('isPrint')->andReturn(true);
        $subscription->shouldReceive('getAttribute')->with('plan')->andReturn(null);
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(new \DateTime('+15 days'));
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));
        $subscription->shouldReceive('__get')->with('plan')->andReturn(null);
        $subscription->shouldReceive('premiumAccess')->andReturn(collect([]));

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium Bundle';
        $upgradePlan->price = 49.99;
        $upgradePlan->features = ['Feature 1'];
        $upgradePlan->delivery_type = 'both';
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider'],
            ['type' => 'newsletter', 'identifier' => 'tech-weekly'],
            ['type' => 'archive', 'identifier' => 'full']
        ]);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($upgradePlan);

        $result = $this->service->previewUpgrade(1, 2);

        $this->assertArrayHasKey('benefits', $result);
        $this->assertGreaterThan(0, count($result['benefits']));
    }

    public function testUpgradeSubscriptionGrantsLowerTierAccess(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->site_id = 1;
        $subscription->shouldReceive('isActive')->andReturn(true);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(null);

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $access1->id = 1;

        $subscription->shouldReceive('grantPremiumAccess')
            ->once()
            ->andReturn($access1);

        // Mock lower-tier access grant
        $subscription->shouldReceive('grantLowerTierPlans')
            ->once()
            ->andReturn([
                [
                    'plan' => 'Basic Plan',
                    'access' => $access1
                ]
            ]);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'premium']
        ]);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->times(2)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($upgradePlan);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($upgradePlan);

        $this->stripeProcessor
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn(['success' => true]);

        $result = $this->service->upgradeSubscription(1, 2, []);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('lower_tier_access_granted', $result);
        $this->assertCount(1, $result['lower_tier_access_granted']);
    }

    public function testUpgradeQuoteMarksStripeSubscriptionsAsEstimate(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(new \DateTime('+15 days'));
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->price = 39.99;

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($upgradePlan);

        $subscription->shouldReceive('isPrint')->andReturn(false);
        $subscription->plan = null;
        $subscription->includes_digital_access = true;
        $subscription->plan_name = 'Basic';
        $subscription->shouldReceive('premiumAccess')->andReturn(collect([]));
        $upgradePlan->features = [];
        $upgradePlan->name = 'Premium';
        $upgradePlan->delivery_type = 'digital';
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([]);

        $result = $this->service->previewUpgrade(1, 2);

        $this->assertTrue($result['pricing']['is_estimate']);
        $this->assertNotNull($result['pricing']['estimate_note']);
    }

    public function testValidateUpgradeThrowsForUnauthorizedMember(): void
    {
        $member = new \stdClass();
        $member->id = 999;

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->member_id = 1;
        $subscription->shouldReceive('isActive')->andReturn(true);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
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

        $this->expectException(UnauthorizedException::class);

        $this->service->upgradeSubscription(1, 2, ['member' => $member]);
    }

    public function testMissingStripePriceIdThrowsException(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->shouldReceive('isActive')->andReturn(true);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $subscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_123');
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime('-15 days'));
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(null);
        $subscription->shouldReceive('grantPremiumAccess')->andReturn(Mockery::mock(SubscriptionPremiumAccess::class));
        $subscription->shouldReceive('grantLowerTierPlans')->andReturn([]);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->stripe_price_id = null; // Missing!
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider']
        ]);

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
            ->times(2)
            ->andReturn($upgradePlan);

        $this->stripeProcessor
            ->shouldReceive('createPaymentIntent')
            ->andReturn(['success' => true]);

        $this->expectException(MissingStripePriceException::class);

        $this->service->upgradeSubscription(1, 2, []);
    }

    public function testUpgradeWithBenefitConfiguration(): void
    {
        $benefitMap = [
            'newsletter:insider' => [
                'icon' => '🔓',
                'title' => 'Test Benefit',
                'description' => 'Test Description'
            ]
        ];

        $service = new SubscriptionUpgradeService(
            $this->subscriptionRepository,
            $this->planRepository,
            $this->stripeProcessor,
            $this->database,
            $benefitMap
        );

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->plan_name = 'Basic';
        $subscription->includes_digital_access = false;
        $subscription->plan = null;
        $subscription->shouldReceive('isPrint')->andReturn(false);
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);
        $subscription->shouldReceive('getAttribute')->with('next_billing_date')->andReturn(null);
        $subscription->shouldReceive('getAttribute')->with('start_date')->andReturn(new \DateTime());
        $subscription->shouldReceive('premiumAccess')->andReturn(collect([]));

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->features = [];
        $upgradePlan->delivery_type = 'digital';
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider']
        ]);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->andReturn($upgradePlan);

        $result = $service->previewUpgrade(1, 2);

        $this->assertArrayHasKey('benefits', $result);
        $this->assertCount(1, $result['benefits']);
        $this->assertEquals('Test Benefit', $result['benefits'][0]['title']);
    }

}