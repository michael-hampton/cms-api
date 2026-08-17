<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Stripe\CreatePaymentIntentDto;
use App\DTO\Stripe\PaymentIntentResultDto;
use App\Enums\Subscriptions\SubscriptionDeliveryType;
use App\Enums\Subscriptions\SubscriptionType;
use App\Exceptions\Subscriptions\InactiveSubscriptionException;
use App\Exceptions\Subscriptions\InvalidUpgradePlanException;
use App\Exceptions\Subscriptions\PaymentFailedException;
use App\Exceptions\Subscriptions\PlanMismatchException;
use App\Exceptions\Subscriptions\SubscriptionNotFoundException;
use App\Exceptions\Subscriptions\UnauthorizedException;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPremiumAccess;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Billing\Stripe\StripePaymentIntentGateway;
use App\Services\Subscriptions\Calculators\UpgradeProrationCalculator;
use App\Services\Subscriptions\PremiumAccessGrantService;
use App\Services\Subscriptions\StripeSubscriptionUpgradeService;
use App\Services\Subscriptions\SubscriptionUpgradeService;
use App\Services\Subscriptions\UpgradeBenefitsService;
use App\Services\Subscriptions\ValueObjects\UpgradeQuote;
use App\Services\ValueObjects\Money;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionUpgradeServiceTest extends TestCase
{
    private SubscriptionUpgradeService $service;
    private $subscriptionRepository;
    private $planRepository;
    private $stripeProcessor;
    private $database;
    private $prorationCalculator;
    private $stripeUpgradeService;
    private $premiumAccessService;
    private $benefitsService;
    private StripePaymentIntentGateway $stripePaymentIntentGateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->stripeProcessor = Mockery::mock(StripePaymentProcessor::class);
        $this->database = Mockery::mock(Database::class);
        $this->prorationCalculator = Mockery::mock(UpgradeProrationCalculator::class);
        $this->stripeUpgradeService = Mockery::mock(StripeSubscriptionUpgradeService::class);
        $this->premiumAccessService = Mockery::mock(PremiumAccessGrantService::class);
        $this->benefitsService = Mockery::mock(UpgradeBenefitsService::class);
        $this->stripePaymentIntentGateway = Mockery::mock(StripePaymentIntentGateway::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->service = new SubscriptionUpgradeService(
            $this->subscriptionRepository,
            $this->planRepository,
            $this->stripePaymentIntentGateway,
            $this->database,
            $this->prorationCalculator,
            $this->stripeUpgradeService,
            $this->premiumAccessService,
            $this->benefitsService,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $quote = new UpgradeQuote(
            Money::fromDecimal(20.00, 'USD'),
            false,
            null,
            false
        );

        $this->prorationCalculator
            ->shouldReceive('calculateUpgradeQuote')
            ->with($subscription, $upgradePlan)
            ->andReturn($quote);

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

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $access1->id = 1;

        $subscription->shouldReceive('grantLowerTierPlans')
            ->once()
            ->andReturn([]);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $quote = new UpgradeQuote(
            Money::fromDecimal(20.00, 'USD'),
            false,
            null,
            false
        );

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

        $this->prorationCalculator
            ->shouldReceive('calculateUpgradeQuote')
            ->with($subscription, $upgradePlan)
            ->andReturn($quote);

        $dto = new PaymentIntentResultDto(true, 'test', 'pi_secret_123');

        $this->stripePaymentIntentGateway
            ->shouldReceive('create')
            ->once()
            ->andReturn($dto);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->with(1, Mockery::type('array'))
            ->once()
            ->andReturn($upgradePlan);

        $this->premiumAccessService
            ->shouldReceive('grantPremiumAccess')
            ->with($subscription, $upgradePlan, 1)
            ->once()
            ->andReturn([$access1]);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->stripeUpgradeService
            ->shouldReceive('updateSubscriptionPlan')
            ->with($subscription, $upgradePlan)
            ->once();

        $result = $this->service->upgradeSubscription(1, 2, []);

        $this->assertTrue($result['success']);
        $this->assertEquals('Successfully upgraded subscription', $result['message']);
        $this->assertEquals(20.00, $result['price_charged']);
    }

    public function testUpgradeSubscriptionReturnsFailureWhenStripeSyncFails(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->site_id = 1;
        $subscription->shouldReceive('isActive')->andReturn(true);
        $subscription->shouldReceive('grantLowerTierPlans')->once()->andReturn([]);

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $access1->id = 1;

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $quote = new UpgradeQuote(
            Money::fromDecimal(20.00, 'USD'),
            false,
            null,
            false
        );

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $this->subscriptionRepository->shouldReceive('find')->with(1)->andReturn($subscription);
        $this->planRepository->shouldReceive('find')->with(2)->andReturn($upgradePlan);
        $this->prorationCalculator->shouldReceive('calculateUpgradeQuote')->with($subscription, $upgradePlan)->andReturn($quote);
        $this->stripePaymentIntentGateway->shouldReceive('create')->once()->andReturn(
            new PaymentIntentResultDto(true, 'test', 'pi_secret_123')
        );
        $this->subscriptionRepository->shouldReceive('update')->with(1, Mockery::type('array'))->once()->andReturn($upgradePlan);
        $this->premiumAccessService->shouldReceive('grantPremiumAccess')->with($subscription, $upgradePlan, 1)->once()->andReturn([$access1]);

        $this->stripeUpgradeService
            ->shouldReceive('updateSubscriptionPlan')
            ->with($subscription, $upgradePlan)
            ->once()
            ->andThrow(new \RuntimeException('Stripe unavailable'));

        $result = $this->service->upgradeSubscription(1, 2, []);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['stripe_sync_failed']);
        $this->assertStringContainsString('Stripe sync failed', $result['message']);
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

    public function testUpgradeSubscriptionThrowsForSamePlan(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 2;
        $subscription->shouldReceive('isActive')->andReturn(true);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $this->database->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->subscriptionRepository->shouldReceive('find')->andReturn($subscription);
        $this->planRepository->shouldReceive('find')->andReturn($upgradePlan);

        $this->expectException(InvalidUpgradePlanException::class);
        $this->expectExceptionMessage('Cannot upgrade to the same plan');

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

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();

        $subscription->shouldReceive('grantLowerTierPlans')->once()->andReturn([]);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $quote = new UpgradeQuote(Money::fromDecimal(20.00, 'USD'), false, null, false);
        $paymentResult = new PaymentIntentResultDto(true, 'test', 'pi_secret_123');

        $this->database->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->subscriptionRepository->shouldReceive('find')->times(2)->andReturn($subscription);
        $this->planRepository->shouldReceive('find')->andReturn($upgradePlan);
        $this->prorationCalculator->shouldReceive('calculateUpgradeQuote')->andReturn($quote);

        $this->stripePaymentIntentGateway
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::type(CreatePaymentIntentDto::class))
            ->andReturn($paymentResult);

        $this->subscriptionRepository->shouldReceive('update')->once()->andReturn($upgradePlan);
        $this->premiumAccessService->shouldReceive('grantPremiumAccess')->andReturn([$access1]);
        $this->stripeUpgradeService->shouldReceive('updateSubscriptionPlan')->once();

        $result = $this->service->upgradeSubscription(1, 2, ['payment_method_id' => 'pm_123']);

        $this->assertTrue($result['success']);
        $this->assertSame($paymentResult->toLegacyArray(), $result['payment_result']);
        $this->assertEquals(20.00, $result['price_charged']);
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

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $quote = new UpgradeQuote(Money::fromDecimal(20.00, 'USD'), false, null, false);

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->subscriptionRepository->shouldReceive('find')->with(1)->andReturn($subscription);
        $this->planRepository->shouldReceive('find')->with(2)->andReturn($upgradePlan);
        $this->prorationCalculator->shouldReceive('calculateUpgradeQuote')->andReturn($quote);

        $failedResult = new PaymentIntentResultDto(false, 'Payment declined');

        $this->stripePaymentIntentGateway
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::type(CreatePaymentIntentDto::class))
            ->andReturn($failedResult);

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Payment failed');

        $this->service->upgradeSubscription(1, 2, []);
    }

    // Regression coverage: resolveUpgradePayment() previously short-circuited
    // to `return true` under APP_ENV=testing whenever a payment_intent_id
    // was supplied, entirely bypassing paymentIntentGateway->retrieve() and
    // validateCompletedPayment() — meaning the amount-match, currency-match,
    // and completed-status checks below had zero test coverage even though
    // they gate real money movement. These tests exercise that path
    // directly via the already-mockable gateway interface.

    private function upgradeScenario(): array
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->site_id = 1;
        $subscription->shouldReceive('isActive')->andReturn(true);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $quote = new UpgradeQuote(Money::fromDecimal(20.00, 'USD'), false, null, false);

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->subscriptionRepository->shouldReceive('find')->with(1)->andReturn($subscription);
        $this->planRepository->shouldReceive('find')->with(2)->andReturn($upgradePlan);
        $this->prorationCalculator->shouldReceive('calculateUpgradeQuote')->andReturn($quote);

        return [$subscription, $upgradePlan];
    }

    public function testUpgradeAcceptsAlreadyCreatedPaymentIntentWhenAmountAndCurrencyMatch(): void
    {
        [$subscription, $upgradePlan] = $this->upgradeScenario();

        $result = new PaymentIntentResultDto(
            success: true,
            paymentIntentId: 'pi_123',
            status: 'succeeded',
            amountCents: 2000,
            currency: 'usd',
        );

        $this->stripePaymentIntentGateway
            ->shouldReceive('retrieve')
            ->once()
            ->with('pi_123')
            ->andReturn($result);
        $this->stripePaymentIntentGateway->shouldNotReceive('create');

        $this->subscriptionRepository->shouldReceive('update')->once()->andReturn($upgradePlan);
        $this->premiumAccessService->shouldReceive('grantPremiumAccess')->once()->andReturn([]);
        $subscription->shouldReceive('grantLowerTierPlans')->once()->andReturn([]);
        $this->stripeUpgradeService->shouldReceive('updateSubscriptionPlan')->once();

        $result = $this->service->upgradeSubscription(1, 2, ['payment_intent_id' => 'pi_123']);

        $this->assertTrue($result['success']);
    }

    public function testUpgradeRejectsPaymentIntentWithMismatchedAmount(): void
    {
        $this->upgradeScenario();

        $result = new PaymentIntentResultDto(
            success: true,
            paymentIntentId: 'pi_123',
            status: 'succeeded',
            amountCents: 999, // quote is 2000
            currency: 'usd',
        );

        $this->stripePaymentIntentGateway
            ->shouldReceive('retrieve')
            ->once()
            ->with('pi_123')
            ->andReturn($result);

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Payment amount does not match the upgrade quote.');

        $this->service->upgradeSubscription(1, 2, ['payment_intent_id' => 'pi_123']);
    }

    public function testUpgradeRejectsPaymentIntentWithMismatchedCurrency(): void
    {
        $this->upgradeScenario();

        $result = new PaymentIntentResultDto(
            success: true,
            paymentIntentId: 'pi_123',
            status: 'succeeded',
            amountCents: 2000,
            currency: 'gbp', // subscription is USD
        );

        $this->stripePaymentIntentGateway
            ->shouldReceive('retrieve')
            ->once()
            ->with('pi_123')
            ->andReturn($result);

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Payment currency does not match the subscription.');

        $this->service->upgradeSubscription(1, 2, ['payment_intent_id' => 'pi_123']);
    }

    public function testUpgradeRejectsIncompletePaymentIntent(): void
    {
        $this->upgradeScenario();

        $result = new PaymentIntentResultDto(
            success: true,
            paymentIntentId: 'pi_123',
            status: 'requires_action',
            amountCents: 2000,
            currency: 'usd',
        );

        $this->stripePaymentIntentGateway
            ->shouldReceive('retrieve')
            ->once()
            ->with('pi_123')
            ->andReturn($result);

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Payment has not completed successfully.');

        $this->service->upgradeSubscription(1, 2, ['payment_intent_id' => 'pi_123']);
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

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->features = ['Premium Feature 1', 'Premium Feature 2'];
        $upgradePlan->delivery_type = SubscriptionDeliveryType::PRINT_AND_DIGITAL->value;

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($upgradePlan);

        $quote = new UpgradeQuote(
            Money::fromDecimal(20.00, 'USD'),
            false,
            null,
            false
        );

        $this->prorationCalculator
            ->shouldReceive('calculateUpgradeQuote')
            ->with($subscription, $upgradePlan)
            ->andReturn($quote);

        $benefits = [
            ['icon' => '🔓', 'title' => 'Unlock Insider', 'description' => 'Access premium content']
        ];

        $this->benefitsService
            ->shouldReceive('getUpgradeBenefits')
            ->with($subscription, $upgradePlan)
            ->andReturn($benefits);

        $result = $this->service->previewUpgrade(1, 2);

        $this->assertEquals('Basic', $result['current_plan']['name']);
        $this->assertEquals(19.99, $result['current_plan']['price']);
        $this->assertEquals('Premium', $result['upgrade_plan']['name']);
        $this->assertEquals(39.99, $result['upgrade_plan']['price']);
        $this->assertTrue($result['upgrade_plan']['includes_digital']);
        $this->assertEquals(20.00, $result['pricing']['price_difference']);
        $this->assertEquals(20.00, $result['pricing']['immediate_charge']);
        $this->assertArrayHasKey('benefits', $result);
        $this->assertFalse($result['pricing']['is_estimate']);
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

    public function testUpgradeSubscriptionSkipsPaymentWhenNoPriceDifference(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->price = 39.99;
        $subscription->currency = 'USD';
        $subscription->shouldReceive('isActive')->andReturn(true);

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $subscription->shouldReceive('grantLowerTierPlans')->once()->andReturn([]);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $quote = new UpgradeQuote(Money::fromCents(0, 'USD'), false, null, false);

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->subscriptionRepository->shouldReceive('find')->with(1)->andReturn($subscription);
        $this->planRepository->shouldReceive('find')->with(2)->andReturn($upgradePlan);
        $this->prorationCalculator->shouldReceive('calculateUpgradeQuote')->andReturn($quote);
        $this->subscriptionRepository->shouldReceive('update')->with(1, Mockery::type('array'))->once()->andReturn($upgradePlan);
        $this->premiumAccessService->shouldReceive('grantPremiumAccess')->andReturn([$access1]);
        $this->subscriptionRepository->shouldReceive('find')->with(1)->andReturn($subscription);
        $this->stripeUpgradeService->shouldReceive('updateSubscriptionPlan')->once();

        // Amount is zero — gateway must NOT be called
        $this->stripePaymentIntentGateway->shouldReceive('create')->never();

        $result = $this->service->upgradeSubscription(1, 2, []);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['price_charged']);
    }

    public function testUpgradeCalculatesProrationForStripeSubscriptions(): void
    {
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

        // Prorated quote for Stripe subscription
        $quote = new UpgradeQuote(
            Money::fromDecimal(10.00, 'USD'), // Prorated amount
            true,  // is prorated
            10,    // remaining days
            true   // is estimate
        );

        $this->prorationCalculator
            ->shouldReceive('calculateUpgradeQuote')
            ->with($subscription, $upgradePlan)
            ->andReturn($quote);

        $result = $this->service->getUpgradeOptions(1);

        $this->assertNotNull($result['options'][0]['price_difference']);
        $this->assertEquals(10.00, $result['options'][0]['price_difference']);
        $this->assertTrue($result['options'][0]['is_estimate']);
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

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $access1->id = 1;

        $subscription->shouldReceive('grantLowerTierPlans')->once()->andReturn([]);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium with Insider';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $quote = new UpgradeQuote(Money::fromDecimal(20.00, 'USD'), false, null, false);
        $paymentResult = new PaymentIntentResultDto(true, 'test', 'pi_secret_123');

        $this->database->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->subscriptionRepository->shouldReceive('find')->times(2)->andReturn($subscription);
        $this->planRepository->shouldReceive('find')->andReturn($upgradePlan);
        $this->prorationCalculator->shouldReceive('calculateUpgradeQuote')->andReturn($quote);

        $this->stripePaymentIntentGateway
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::type(CreatePaymentIntentDto::class))
            ->andReturn($paymentResult);

        $this->subscriptionRepository->shouldReceive('update')->once()->andReturn($upgradePlan);

        $this->premiumAccessService
            ->shouldReceive('grantPremiumAccess')
            ->with($subscription, $upgradePlan, 1)
            ->once()
            ->andReturn([$access1]);

        $this->stripeUpgradeService->shouldReceive('updateSubscriptionPlan')->once();

        $result = $this->service->upgradeSubscription(1, 2, []);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['premium_access_granted']);
    }

    public function testUpgradeSkipsPaymentWhenNoPriceDifference(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 1;
        $subscription->price = 39.99;
        $subscription->currency = 'USD';
        $subscription->shouldReceive('isActive')->andReturn(true);

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $subscription->shouldReceive('grantLowerTierPlans')->once()->andReturn([]);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $quote = new UpgradeQuote(Money::fromCents(0, 'USD'), false, null, false);

        $this->database->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->subscriptionRepository->shouldReceive('find')->times(2)->andReturn($subscription);
        $this->planRepository->shouldReceive('find')->andReturn($upgradePlan);
        $this->prorationCalculator->shouldReceive('calculateUpgradeQuote')->andReturn($quote);

        $this->stripePaymentIntentGateway->shouldReceive('create')->never();

        $this->subscriptionRepository->shouldReceive('update')->once()->andReturn($upgradePlan);
        $this->premiumAccessService->shouldReceive('grantPremiumAccess')->andReturn([$access1]);
        $this->stripeUpgradeService->shouldReceive('updateSubscriptionPlan')->once();

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

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $quote = new UpgradeQuote(
            Money::fromDecimal(20.00, 'USD'),
            false,
            null,
            false
        );

        $this->prorationCalculator
            ->shouldReceive('calculateUpgradeQuote')
            ->andReturn($quote);

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

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $quote = new UpgradeQuote(
            Money::fromDecimal(10.00, 'USD'),
            false,
            null,
            false
        );

        $this->prorationCalculator
            ->shouldReceive('calculateUpgradeQuote')
            ->once()
            ->andReturn($quote);

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

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $access2 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();

        $subscription->shouldReceive('grantLowerTierPlans')->once()->andReturn([]);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium Bundle';
        $upgradePlan->price = 49.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $quote = new UpgradeQuote(Money::fromDecimal(30.00, 'USD'), false, null, false);
        $paymentResult = new PaymentIntentResultDto(true, 'test', 'pi_secret_123');

        $this->database->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->subscriptionRepository->shouldReceive('find')->times(2)->andReturn($subscription);
        $this->planRepository->shouldReceive('find')->andReturn($upgradePlan);
        $this->prorationCalculator->shouldReceive('calculateUpgradeQuote')->andReturn($quote);

        $this->stripePaymentIntentGateway
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::type(CreatePaymentIntentDto::class))
            ->andReturn($paymentResult);

        $this->subscriptionRepository->shouldReceive('update')->once()->andReturn($upgradePlan);

        $this->premiumAccessService
            ->shouldReceive('grantPremiumAccess')
            ->with($subscription, $upgradePlan, 1)
            ->once()
            ->andReturn([$access1, $access2]);

        $this->stripeUpgradeService->shouldReceive('updateSubscriptionPlan')->once();

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

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();
        $access1->id = 1;

        $subscription->shouldReceive('grantLowerTierPlans')->once()->andReturn([]);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Insider Plan';
        $upgradePlan->price = 29.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $quote = new UpgradeQuote(Money::fromDecimal(10.00, 'USD'), false, null, false);
        $paymentResult = new PaymentIntentResultDto(true, 'test', 'pi_secret_123');

        $this->database->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->subscriptionRepository->shouldReceive('find')->times(2)->andReturn($subscription);
        $this->planRepository->shouldReceive('find')->andReturn($upgradePlan);
        $this->prorationCalculator->shouldReceive('calculateUpgradeQuote')->andReturn($quote);

        $this->stripePaymentIntentGateway
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::type(CreatePaymentIntentDto::class))
            ->andReturn($paymentResult);

        $this->subscriptionRepository->shouldReceive('update')->once()->andReturn($upgradePlan);

        $this->premiumAccessService
            ->shouldReceive('grantPremiumAccess')
            ->with($subscription, $upgradePlan, 1)
            ->once()
            ->andReturn([$access1]);

        $this->stripeUpgradeService->shouldReceive('updateSubscriptionPlan')->once();

        $result = $this->service->upgradeSubscription(1, 2, []);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['premium_access_granted']);
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
        $subscription->includes_digital_access = false;

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

        $quote = new UpgradeQuote(
            Money::fromDecimal(30.00, 'USD'),
            false,
            null,
            false
        );

        $this->prorationCalculator
            ->shouldReceive('calculateUpgradeQuote')
            ->andReturn($quote);

        $benefits = [
            [
                'icon' => '🔓',
                'title' => 'Unlock Insider Newsletter',
                'description' => 'Premium content'
            ],
            [
                'icon' => '💻',
                'title' => 'Tech Weekly Newsletter',
                'description' => 'Weekly insights'
            ],
            [
                'icon' => '📚',
                'title' => 'Full Archive Access',
                'description' => 'Complete archive'
            ]
        ];

        $this->benefitsService
            ->shouldReceive('getUpgradeBenefits')
            ->with($subscription, $upgradePlan)
            ->andReturn($benefits);

        $result = $this->service->previewUpgrade(1, 2);

        $this->assertArrayHasKey('benefits', $result);
        $this->assertCount(3, $result['benefits']);
        $this->assertEquals('Unlock Insider Newsletter', $result['benefits'][0]['title']);
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

        $access1 = Mockery::mock(SubscriptionPremiumAccess::class)->makePartial();

        $lowerTierAccess = [['plan' => 'Basic Plan', 'access' => $access1]];
        $subscription->shouldReceive('grantLowerTierPlans')->once()->andReturn($lowerTierAccess);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->id = 2;
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->upgrade_from_plan_id = 1;
        $upgradePlan->shouldReceive('isUpgradePlan')->andReturn(true);

        $quote = new UpgradeQuote(Money::fromDecimal(20.00, 'USD'), false, null, false);
        $paymentResult = new PaymentIntentResultDto(true, 'test', 'pi_secret_123');

        $this->database->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->subscriptionRepository->shouldReceive('find')->times(2)->andReturn($subscription);
        $this->planRepository->shouldReceive('find')->andReturn($upgradePlan);
        $this->prorationCalculator->shouldReceive('calculateUpgradeQuote')->andReturn($quote);

        $this->stripePaymentIntentGateway
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::type(CreatePaymentIntentDto::class))
            ->andReturn($paymentResult);

        $this->subscriptionRepository->shouldReceive('update')->once()->andReturn($upgradePlan);
        $this->premiumAccessService->shouldReceive('grantPremiumAccess')->andReturn([$access1]);
        $this->stripeUpgradeService->shouldReceive('updateSubscriptionPlan')->once();

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
        $subscription->plan_name = 'Basic';
        $subscription->includes_digital_access = true;
        $subscription->plan = null;
        $subscription->shouldReceive('isPrint')->andReturn(false);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->features = [];
        $upgradePlan->delivery_type = SubscriptionType::DIGITAL->value;

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->with(2)
            ->andReturn($upgradePlan);

        $quote = new UpgradeQuote(
            Money::fromDecimal(10.00, 'USD'),
            true,
            15,
            true // is estimate
        );

        $this->prorationCalculator
            ->shouldReceive('calculateUpgradeQuote')
            ->andReturn($quote);

        $this->benefitsService
            ->shouldReceive('getUpgradeBenefits')
            ->andReturn([]);

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

    public function testUpgradeWithBenefitConfiguration(): void
    {
        $benefitMap = [
            'newsletter:insider' => [
                'icon'        => '🔓',
                'title'       => 'Test Benefit',
                'description' => 'Test Description',
            ]
        ];

        $benefitsService = new UpgradeBenefitsService($benefitMap);

        $service = new SubscriptionUpgradeService(
            $this->subscriptionRepository,
            $this->planRepository,
            $this->stripePaymentIntentGateway,   // was: $this->stripeProcessor
            $this->database,
            $this->prorationCalculator,
            $this->stripeUpgradeService,
            $this->premiumAccessService,
            $benefitsService,
            $this->logger,
        );

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->price = 19.99;
        $subscription->currency = 'USD';
        $subscription->plan_name = 'Basic';
        $subscription->includes_digital_access = false;
        $subscription->plan = null;
        $subscription->shouldReceive('isPrint')->andReturn(false);

        $upgradePlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $upgradePlan->name = 'Premium';
        $upgradePlan->price = 39.99;
        $upgradePlan->features = [];
        $upgradePlan->delivery_type = SubscriptionType::DIGITAL->value;
        $upgradePlan->shouldReceive('getPremiumAccessGrants')->andReturn([
            ['type' => 'newsletter', 'identifier' => 'insider']
        ]);

        $currentAccess = collect([]);
        $subscription->shouldReceive('premiumAccess')->andReturn($currentAccess);

        $this->subscriptionRepository->shouldReceive('find')->andReturn($subscription);
        $this->planRepository->shouldReceive('find')->andReturn($upgradePlan);

        $quote = new UpgradeQuote(Money::fromDecimal(20.00, 'USD'), false, null, false);
        $this->prorationCalculator->shouldReceive('calculateUpgradeQuote')->andReturn($quote);

        $result = $service->previewUpgrade(1, 2);

        $this->assertArrayHasKey('benefits', $result);
        $this->assertCount(1, $result['benefits']);
        $this->assertEquals('Test Benefit', $result['benefits'][0]['title']);
        $this->assertEquals('🔓', $result['benefits'][0]['icon']);
    }

}
