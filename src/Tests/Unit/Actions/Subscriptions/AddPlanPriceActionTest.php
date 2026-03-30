<?php

namespace App\Tests\Unit\Actions\Subscriptions;

use App\Actions\Subscriptions\AddPlanPriceAction;
use App\Framework\Container;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Billing\Stripe\Contracts\StripePriceGatewayInterface;
use App\Services\Billing\Stripe\Contracts\StripeProductGatewayInterface;
use App\Services\Billing\Stripe\NullStripePriceGateway;
use App\Services\Billing\Stripe\NullStripeProductGateway;
use App\Services\Subscriptions\PlanPricingDomainGuard;
use App\Services\Subscriptions\Validators\PricingCurrencyValidator;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class AddPlanPriceActionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private SubscriptionPlanRepository $planRepository;
    private SubscriptionPlanPricingRepository $pricingRepository;
    private StripePriceGatewayInterface $stripePriceGateway;
    private PricingCurrencyValidator $currencyValidator;
    private PlanPricingDomainGuard $domainGuard;
    private AddPlanPriceAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $container = Container::getInstance();
        $container->bind(StripePriceGatewayInterface::class, NullStripePriceGateway::class);
        $container->bind(StripeProductGatewayInterface::class, NullStripeProductGateway::class);

        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->pricingRepository = Mockery::mock(SubscriptionPlanPricingRepository::class);
        $this->stripePriceGateway = Mockery::mock(StripePriceGatewayInterface::class);
        $this->currencyValidator = Mockery::mock(PricingCurrencyValidator::class);
        $this->domainGuard = Mockery::mock(PlanPricingDomainGuard::class);

        $this->action = new AddPlanPriceAction(
            $this->planRepository,
            $this->pricingRepository,
            $this->stripePriceGateway,
            $this->currencyValidator,
            $this->domainGuard,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_creates_stripe_price_and_stores_id_locally(): void
    {
        $plan = $this->makePlan(1, 'prod_abc');
        $pricing = $this->makePricing(10, null);

        $this->currencyValidator->shouldReceive('validate')->with('GBP')->once()->andReturn('gbp');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();
        $this->planRepository->shouldReceive('find')->with(1)->once()->andReturn($plan);

        $this->pricingRepository
            ->shouldReceive('create')->once()
            ->with(Mockery::on(fn($d) => $d['stripe_price_id'] === null && $d['plan_id'] === 1 && $d['currency'] === 'gbp'))
            ->andReturn($pricing);

        $this->stripePriceGateway
            ->shouldReceive('createRecurringPrice')->once()
            ->with('prod_abc', 999, 'gbp', 'month')
            ->andReturn('price_xyz');

        $this->pricingRepository->shouldReceive('update')->once()->with(10, ['stripe_price_id' => 'price_xyz']);
        $this->planRepository->shouldReceive('update')->once()->with(1, ['stripe_price_id' => 'price_xyz']);


        $result = $this->action->execute(1, ['amount_cents' => 999, 'currency' => 'GBP', 'interval' => 'month', 'is_default' => false]);

        $this->assertSame($pricing, $result);
        $this->assertEquals('price_xyz', $result->stripe_price_id);
    }

    // ---------------------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------------------

    private function makePlan(int $id, ?string $stripeProductId): SubscriptionPlan
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = $id;
        $plan->stripe_product_id = $stripeProductId;
        return $plan;
    }

    private function makePricing(int $id, ?string $stripePriceId): SubscriptionPlanPricing
    {
        $pricing = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();
        $pricing->id = $id;
        $pricing->stripe_price_id = $stripePriceId;
        return $pricing;
    }

    public function test_stripe_price_gateway_is_called_exactly_once(): void
    {
        $plan = $this->makePlan(5, 'prod_plan5');
        $pricing = $this->makePricing(20, null);

        $this->currencyValidator->shouldReceive('validate')->andReturn('usd');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();
        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->pricingRepository->shouldReceive('create')->andReturn($pricing);
        $this->planRepository->shouldReceive('update')->once();
        $this->pricingRepository->shouldReceive('update')->once();

        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->once()->andReturn('price_once');

        $this->action->execute(5, ['amount_cents' => 500, 'currency' => 'usd', 'interval' => 'month']);
    }

    // ---------------------------------------------------------------------------
    // Currency validation
    // ---------------------------------------------------------------------------

    public function test_stripe_price_id_is_persisted_correctly(): void
    {
        $plan = $this->makePlan(2, 'prod_plan2');
        $pricing = $this->makePricing(99, null);

        $this->currencyValidator->shouldReceive('validate')->andReturn('eur');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();
        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->pricingRepository->shouldReceive('create')->andReturn($pricing);
        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->andReturn('price_stored_correctly');

        $this->pricingRepository
            ->shouldReceive('update')->once()
            ->with(99, ['stripe_price_id' => 'price_stored_correctly']);

        $this->planRepository
            ->shouldReceive('update')->once()
            ->with(2, ['stripe_price_id' => 'price_stored_correctly']);

        $result = $this->action->execute(2, ['amount_cents' => 1999, 'currency' => 'eur', 'interval' => 'year']);

        $this->assertEquals('price_stored_correctly', $result->stripe_price_id);
    }

    public function test_it_rejects_unsupported_currency_before_any_db_or_stripe_call(): void
    {
        $this->currencyValidator
            ->shouldReceive('validate')->with('bitcoin')->once()
            ->andThrow(new \InvalidArgumentException('Currency "bitcoin" is not supported.'));

        $this->planRepository->shouldReceive('find')->never();
        $this->pricingRepository->shouldReceive('create')->never();
        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->never();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"bitcoin" is not supported');

        $this->action->execute(1, ['amount_cents' => 100, 'currency' => 'bitcoin', 'interval' => 'month']);
    }

    // ---------------------------------------------------------------------------
    // Domain guard
    // ---------------------------------------------------------------------------

    public function test_currency_is_normalised_before_being_stored_and_sent_to_stripe(): void
    {
        $plan = $this->makePlan(3, 'prod_plan3');
        $pricing = $this->makePricing(5, null);

        $this->currencyValidator->shouldReceive('validate')->with('GBP')->once()->andReturn('gbp');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();
        $this->planRepository->shouldReceive('find')->andReturn($plan);

        $this->pricingRepository
            ->shouldReceive('create')->once()
            ->with(Mockery::on(fn($d) => $d['currency'] === 'gbp'))
            ->andReturn($pricing);

        $this->stripePriceGateway
            ->shouldReceive('createRecurringPrice')->once()
            ->with('prod_plan3', 500, 'gbp', 'month')
            ->andReturn('price_gbp');

        $this->pricingRepository->shouldReceive('update')->once();
        $this->planRepository->shouldReceive('update')->once();

        $this->action->execute(3, ['amount_cents' => 500, 'currency' => 'GBP', 'interval' => 'month']);
    }

    public function test_it_rejects_second_default_price_for_same_plan(): void
    {
        $this->currencyValidator->shouldReceive('validate')->andReturn('gbp');

        $this->domainGuard
            ->shouldReceive('assertNoDefaultConflict')->once()
            ->andThrow(new \DomainException('Plan 1 already has an active default price.'));

        $this->planRepository->shouldReceive('find')->never();
        $this->pricingRepository->shouldReceive('create')->never();
        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->never();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already has an active default price');

        $this->action->execute(1, ['amount_cents' => 999, 'currency' => 'gbp', 'interval' => 'month', 'is_default' => true]);
    }

    // ---------------------------------------------------------------------------
    // Failure paths
    // ---------------------------------------------------------------------------

    public function test_it_rejects_duplicate_sort_order_for_same_plan(): void
    {
        $this->currencyValidator->shouldReceive('validate')->andReturn('gbp');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();
        $this->domainGuard
            ->shouldReceive('assertUniqueSortOrder')->once()
            ->andThrow(new \DomainException('Plan 1 already has an active price at sort_order 2.'));

        $this->planRepository->shouldReceive('find')->never();
        $this->pricingRepository->shouldReceive('create')->never();
        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->never();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('sort_order 2');

        $this->action->execute(1, ['amount_cents' => 999, 'currency' => 'gbp', 'interval' => 'month', 'sort_order' => 2]);
    }

    public function test_it_throws_when_plan_not_found(): void
    {
        $this->currencyValidator->shouldReceive('validate')->andReturn('gbp');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();
        $this->planRepository->shouldReceive('find')->with(999)->once()->andReturn(null);
        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Plan 999 not found');

        $this->action->execute(999, ['amount_cents' => 100, 'currency' => 'gbp', 'interval' => 'month']);
    }

    public function test_it_throws_when_plan_has_no_stripe_product_id(): void
    {
        $plan = $this->makePlan(3, null);

        $this->currencyValidator->shouldReceive('validate')->andReturn('gbp');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();
        $this->planRepository->shouldReceive('find')->with(3)->once()->andReturn($plan);

        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->never();
        $this->pricingRepository->shouldReceive('create')->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not have a stripe_product_id');

        $this->action->execute(3, ['amount_cents' => 100, 'currency' => 'gbp', 'interval' => 'month']);
    }

    public function test_stripe_failure_propagates_and_does_not_store_price_id(): void
    {
        $plan = $this->makePlan(1, 'prod_valid');
        $pricing = $this->makePricing(5, null);

        $this->currencyValidator->shouldReceive('validate')->andReturn('gbp');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();
        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->pricingRepository->shouldReceive('create')->andReturn($pricing);

        $this->stripePriceGateway
            ->shouldReceive('createRecurringPrice')
            ->andThrow(new \RuntimeException('Stripe rate limit'));

        $this->pricingRepository->shouldReceive('update')->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe rate limit');

        $this->action->execute(1, ['amount_cents' => 500, 'currency' => 'gbp', 'interval' => 'month']);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    public function test_pricing_row_is_created_with_null_stripe_price_id_initially(): void
    {
        $plan = $this->makePlan(7, 'prod_seven');
        $pricing = $this->makePricing(77, null);

        $this->currencyValidator->shouldReceive('validate')->andReturn('gbp');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();
        $this->planRepository->shouldReceive('find')->andReturn($plan);

        $this->pricingRepository
            ->shouldReceive('create')->once()
            ->with(Mockery::on(fn($data) => array_key_exists('stripe_price_id', $data) && $data['stripe_price_id'] === null))
            ->andReturn($pricing);

        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->andReturn('price_later');
        $this->pricingRepository->shouldReceive('update')->once();
        $this->planRepository->shouldReceive('update')->once();

        $this->action->execute(7, ['amount_cents' => 999, 'currency' => 'gbp', 'interval' => 'month']);
    }
}