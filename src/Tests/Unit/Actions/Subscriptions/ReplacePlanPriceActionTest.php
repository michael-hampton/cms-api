<?php

namespace App\Tests\Unit\Actions\Subscriptions;

use App\Actions\Subscriptions\ReplacePlanPriceAction;
use App\Framework\Container;
use App\Framework\Database\Database;
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

class ReplacePlanPriceActionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private SubscriptionPlanRepository $planRepository;
    private SubscriptionPlanPricingRepository $pricingRepository;
    private StripePriceGatewayInterface $stripePriceGateway;
    private Database $database;
    private PricingCurrencyValidator $currencyValidator;
    private PlanPricingDomainGuard $domainGuard;
    private ReplacePlanPriceAction $action;

    public function test_it_deactivates_old_price_and_creates_new_one(): void
    {
        $oldPricing = $this->makePricing(1, 1, true, 'price_old', false, 1);
        $newPricing = $this->makePricing(2, 1, true, 'price_new', false, 1);
        $plan = $this->makePlan(1, 'prod_abc');

        $this->scaffoldHappyPath($oldPricing, $plan, 'price_new_stripe', $newPricing);

        $this->pricingRepository
            ->shouldReceive('update')->once()
            ->with(1, ['is_active' => false, 'replaced_by_price_id' => 2]);

        $result = $this->action->execute(1, ['amount_cents' => 1999, 'currency' => 'gbp', 'interval' => 'month']);

        $this->assertSame($newPricing, $result);
    }

    // ---------------------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------------------

    private function makePricing(
        int    $id,
        int    $planId,
        bool   $isActive,
        string $stripePriceId,
        bool   $isDefault,
        int    $sortOrder,
    ): SubscriptionPlanPricing
    {
        $pricing = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();
        $pricing->id = $id;
        $pricing->plan_id = $planId;
        $pricing->is_active = $isActive;
        $pricing->stripe_price_id = $stripePriceId;
        $pricing->is_default = $isDefault;
        $pricing->sort_order = $sortOrder;
        return $pricing;
    }

    private function makePlan(int $id, ?string $stripeProductId): SubscriptionPlan
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = $id;
        $plan->stripe_product_id = $stripeProductId;
        return $plan;
    }

    /**
     * Wire up all the standard happy-path expectations except `update`,
     * so individual tests can assert on it precisely.
     */
    private function scaffoldHappyPath(
        SubscriptionPlanPricing $oldPricing,
        SubscriptionPlan        $plan,
        string                  $stripePriceId,
        SubscriptionPlanPricing $newPricing,
    ): void
    {
        $this->pricingRepository->shouldReceive('find')->andReturn($oldPricing);
        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->currencyValidator->shouldReceive('validate')->andReturn('gbp');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();
        $this->domainGuard->shouldReceive('assertUniqueSortOrder')->once();

        $this->stripePriceGateway
            ->shouldReceive('createRecurringPrice')->once()
            ->andReturn($stripePriceId);

        $this->database->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->pricingRepository
            ->shouldReceive('create')->once()
            ->with(Mockery::on(fn($d) => $d['stripe_price_id'] === $stripePriceId && $d['is_active'] === true))
            ->andReturn($newPricing);
    }

    public function test_old_price_is_marked_inactive(): void
    {
        $oldPricing = $this->makePricing(5, 2, true, 'price_old5', false, 1);
        $newPricing = $this->makePricing(6, 2, true, 'price_new6', false, 1);
        $plan = $this->makePlan(2, 'prod_plan2');

        $this->scaffoldHappyPath($oldPricing, $plan, 'price_6', $newPricing);

        $this->pricingRepository
            ->shouldReceive('update')->once()
            ->with(5, Mockery::on(fn($d) => $d['is_active'] === false));

        $this->action->execute(5, ['amount_cents' => 500, 'currency' => 'gbp', 'interval' => 'month']);
    }

    public function test_new_price_row_is_created_with_correct_stripe_id_and_active_flag(): void
    {
        $oldPricing = $this->makePricing(10, 3, true, 'price_old10', false, 1);
        $newPricing = $this->makePricing(11, 3, true, 'price_new11', false, 1);
        $plan = $this->makePlan(3, 'prod_plan3');

        $this->pricingRepository->shouldReceive('find')->andReturn($oldPricing);
        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->currencyValidator->shouldReceive('validate')->andReturn('gbp');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();
        $this->domainGuard->shouldReceive('assertUniqueSortOrder')->once();
        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->andReturn('price_brand_new');
        $this->database->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->pricingRepository
            ->shouldReceive('create')->once()
            ->with(Mockery::on(fn($d) => $d['stripe_price_id'] === 'price_brand_new' && $d['is_active'] === true))
            ->andReturn($newPricing);

        $this->pricingRepository->shouldReceive('update')->once();

        $this->action->execute(10, ['amount_cents' => 2500, 'currency' => 'gbp', 'interval' => 'year']);
    }

    public function test_new_stripe_price_is_created_not_old_one_modified(): void
    {
        // The old price_id must never be touched — Stripe prices are immutable.
        $oldPricing = $this->makePricing(20, 4, true, 'price_immutable_old', false, 1);
        $newPricing = $this->makePricing(21, 4, true, 'price_newly_created', false, 1);
        $plan = $this->makePlan(4, 'prod_plan4');

        $this->scaffoldHappyPath($oldPricing, $plan, 'price_newly_created', $newPricing);

        $this->pricingRepository->shouldReceive('update')->once();

        // createRecurringPrice must be called exactly once (create, not update).
        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->never(); // already set in scaffoldHappyPath
        $this->action->execute(20, ['amount_cents' => 799, 'currency' => 'gbp', 'interval' => 'month']);
    }

    // ---------------------------------------------------------------------------
    // Currency validation
    // ---------------------------------------------------------------------------

    public function test_all_db_writes_happen_inside_transaction(): void
    {
        $oldPricing = $this->makePricing(30, 5, true, 'price_old30', false, 1);
        $newPricing = $this->makePricing(31, 5, true, 'price_new31', false, 1);
        $plan = $this->makePlan(5, 'prod_plan5');

        $this->pricingRepository->shouldReceive('find')->andReturn($oldPricing);
        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->currencyValidator->shouldReceive('validate')->andReturn('gbp');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();
        $this->domainGuard->shouldReceive('assertUniqueSortOrder')->once();
        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->andReturn('price_31');

        $insideTransaction = false;

        $this->database
            ->shouldReceive('transaction')->once()
            ->andReturnUsing(function ($cb) use (&$insideTransaction) {
                $insideTransaction = true;
                return $cb();
            });

        $this->pricingRepository
            ->shouldReceive('create')
            ->andReturnUsing(function () use (&$insideTransaction, $newPricing) {
                $this->assertTrue($insideTransaction, 'create() must be called inside transaction');
                return $newPricing;
            });

        $this->pricingRepository
            ->shouldReceive('update')
            ->andReturnUsing(function () use (&$insideTransaction) {
                $this->assertTrue($insideTransaction, 'update() must be called inside transaction');
            });

        $this->action->execute(30, ['amount_cents' => 100, 'currency' => 'gbp', 'interval' => 'month']);
    }

    public function test_stripe_call_happens_before_transaction(): void
    {
        $callOrder = [];
        $oldPricing = $this->makePricing(40, 6, true, 'price_old40', false, 1);
        $newPricing = $this->makePricing(41, 6, true, 'price_new41', false, 1);
        $plan = $this->makePlan(6, 'prod_plan6');

        $this->pricingRepository->shouldReceive('find')->andReturn($oldPricing);
        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->currencyValidator->shouldReceive('validate')->andReturn('gbp');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();
        $this->domainGuard->shouldReceive('assertUniqueSortOrder')->once();

        $this->stripePriceGateway
            ->shouldReceive('createRecurringPrice')
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'stripe';
                return 'price_new41';
            });

        $this->database
            ->shouldReceive('transaction')
            ->andReturnUsing(function ($cb) use (&$callOrder) {
                $callOrder[] = 'transaction';
                return $cb();
            });

        $this->pricingRepository->shouldReceive('create')->andReturn($newPricing);
        $this->pricingRepository->shouldReceive('update')->once();

        $this->action->execute(40, ['amount_cents' => 200, 'currency' => 'gbp', 'interval' => 'month']);

        $this->assertEquals(['stripe', 'transaction'], $callOrder);
    }

    // ---------------------------------------------------------------------------
    // Domain guard
    // ---------------------------------------------------------------------------

    public function test_it_rejects_unsupported_currency_before_any_stripe_or_db_call(): void
    {
        $oldPricing = $this->makePricing(50, 7, true, 'price_50', false, 1);
        $plan = $this->makePlan(7, 'prod_plan7');

        $this->pricingRepository->shouldReceive('find')->andReturn($oldPricing);
        $this->planRepository->shouldReceive('find')->andReturn($plan);

        $this->currencyValidator
            ->shouldReceive('validate')->with('dogecoin')
            ->andThrow(new \InvalidArgumentException('Currency "dogecoin" is not supported.'));

        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->never();
        $this->database->shouldReceive('transaction')->never();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"dogecoin" is not supported');

        $this->action->execute(50, ['amount_cents' => 100, 'currency' => 'dogecoin', 'interval' => 'month']);
    }

    public function test_normalised_currency_is_passed_to_stripe_and_stored(): void
    {
        $oldPricing = $this->makePricing(55, 7, true, 'price_55', false, 1);
        $newPricing = $this->makePricing(56, 7, true, 'price_56', false, 1);
        $plan = $this->makePlan(7, 'prod_plan7');

        $this->pricingRepository->shouldReceive('find')->andReturn($oldPricing);
        $this->planRepository->shouldReceive('find')->andReturn($plan);

        // Validator normalises 'GBP' → 'gbp'
        $this->currencyValidator->shouldReceive('validate')->with('GBP')->andReturn('gbp');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();
        $this->domainGuard->shouldReceive('assertUniqueSortOrder')->once();

        // Stripe must receive normalised lowercase currency.
        $this->stripePriceGateway
            ->shouldReceive('createRecurringPrice')
            ->with('prod_plan7', 999, 'gbp', 'month')
            ->andReturn('price_normalised');

        $this->database->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        // Stored row must also use normalised currency.
        $this->pricingRepository
            ->shouldReceive('create')
            ->with(Mockery::on(fn($d) => $d['currency'] === 'gbp'))
            ->andReturn($newPricing);

        $this->pricingRepository->shouldReceive('update')->once();

        $this->action->execute(55, ['amount_cents' => 999, 'currency' => 'GBP', 'interval' => 'month']);
    }

    // ---------------------------------------------------------------------------
    // Failure cases
    // ---------------------------------------------------------------------------

    public function test_it_blocks_second_default_price_excluding_the_row_being_replaced(): void
    {
        $oldPricing = $this->makePricing(60, 8, true, 'price_60', true, 1);
        $plan = $this->makePlan(8, 'prod_plan8');

        $this->pricingRepository->shouldReceive('find')->andReturn($oldPricing);
        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->currencyValidator->shouldReceive('validate')->andReturn('gbp');

        // Guard must be called with excludePricingId = 60 (the row being replaced).
        $this->domainGuard
            ->shouldReceive('assertNoDefaultConflict')
            ->with(8, true, 60)
            ->once()
            ->andThrow(new \DomainException('Plan 8 already has an active default price.'));

        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->never();
        $this->database->shouldReceive('transaction')->never();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already has an active default price');

        $this->action->execute(60, [
            'amount_cents' => 999,
            'currency' => 'gbp',
            'interval' => 'month',
            'is_default' => true,
        ]);
    }

    public function test_it_blocks_duplicate_sort_order_excluding_the_row_being_replaced(): void
    {
        $oldPricing = $this->makePricing(65, 9, true, 'price_65', false, 3);
        $plan = $this->makePlan(9, 'prod_plan9');

        $this->pricingRepository->shouldReceive('find')->andReturn($oldPricing);
        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->currencyValidator->shouldReceive('validate')->andReturn('gbp');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();

        // Guard called with the existing sort_order (3) and excludePricingId = 65.
        $this->domainGuard
            ->shouldReceive('assertUniqueSortOrder')
            ->with(9, 3, 65)
            ->once()
            ->andThrow(new \DomainException('Plan 9 already has an active price at sort_order 3.'));

        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->never();
        $this->database->shouldReceive('transaction')->never();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('sort_order 3');

        $this->action->execute(65, ['amount_cents' => 100, 'currency' => 'gbp', 'interval' => 'month']);
    }

    public function test_it_throws_when_pricing_not_found(): void
    {
        $this->pricingRepository->shouldReceive('find')->with(999)->once()->andReturn(null);

        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->never();
        $this->database->shouldReceive('transaction')->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PlanPricing 999 not found');

        $this->action->execute(999, ['amount_cents' => 100, 'currency' => 'gbp', 'interval' => 'month']);
    }

    public function test_it_throws_when_pricing_is_already_inactive(): void
    {
        $inactivePricing = $this->makePricing(50, 7, false, 'price_inactive', false, 1);

        $this->pricingRepository->shouldReceive('find')->with(50)->once()->andReturn($inactivePricing);

        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->never();
        $this->database->shouldReceive('transaction')->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already inactive');

        $this->action->execute(50, ['amount_cents' => 100, 'currency' => 'gbp', 'interval' => 'month']);
    }

    public function test_it_throws_when_plan_has_no_stripe_product_id(): void
    {
        $pricing = $this->makePricing(60, 8, true, 'price_60', false, 1);
        $plan = $this->makePlan(8, null);

        $this->pricingRepository->shouldReceive('find')->andReturn($pricing);
        $this->planRepository->shouldReceive('find')->andReturn($plan);

        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->never();
        $this->database->shouldReceive('transaction')->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('stripe_product_id');

        $this->action->execute(60, ['amount_cents' => 100, 'currency' => 'gbp', 'interval' => 'month']);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    public function test_stripe_failure_prevents_any_db_writes(): void
    {
        $oldPricing = $this->makePricing(70, 9, true, 'price_old70', false, 1);
        $plan = $this->makePlan(9, 'prod_plan9');

        $this->pricingRepository->shouldReceive('find')->andReturn($oldPricing);
        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->currencyValidator->shouldReceive('validate')->andReturn('gbp');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();
        $this->domainGuard->shouldReceive('assertUniqueSortOrder')->once();

        $this->stripePriceGateway
            ->shouldReceive('createRecurringPrice')
            ->andThrow(new \RuntimeException('Stripe error'));

        $this->database->shouldReceive('transaction')->never();
        $this->pricingRepository->shouldReceive('create')->never();
        $this->pricingRepository->shouldReceive('update')->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe error');

        $this->action->execute(70, ['amount_cents' => 100, 'currency' => 'gbp', 'interval' => 'month']);
    }

    public function test_transaction_rollback_on_db_create_failure(): void
    {
        $oldPricing = $this->makePricing(80, 10, true, 'price_old80', false, 1);
        $plan = $this->makePlan(10, 'prod_plan10');

        $this->pricingRepository->shouldReceive('find')->andReturn($oldPricing);
        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->currencyValidator->shouldReceive('validate')->andReturn('gbp');
        $this->domainGuard->shouldReceive('assertNoDefaultConflict')->once();
        $this->domainGuard->shouldReceive('assertUniqueSortOrder')->once();
        $this->stripePriceGateway->shouldReceive('createRecurringPrice')->andReturn('price_80');

        // Simulate the transaction propagating the DB exception.
        $this->database
            ->shouldReceive('transaction')
            ->andReturnUsing(function ($cb) {
                return $cb(); // let the exception escape (real DB would rollback)
            });

        $this->pricingRepository
            ->shouldReceive('create')
            ->andThrow(new \RuntimeException('DB write failed'));

        $this->pricingRepository->shouldReceive('update')->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB write failed');

        $this->action->execute(80, ['amount_cents' => 100, 'currency' => 'gbp', 'interval' => 'month']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->pricingRepository = Mockery::mock(SubscriptionPlanPricingRepository::class);
        $this->stripePriceGateway = Mockery::mock(StripePriceGatewayInterface::class);
        $this->database = Mockery::mock(Database::class);
        $this->currencyValidator = Mockery::mock(PricingCurrencyValidator::class);
        $this->domainGuard = Mockery::mock(PlanPricingDomainGuard::class);

        $container = Container::getInstance();
        $container->bind(StripePriceGatewayInterface::class, NullStripePriceGateway::class);
        $container->bind(StripeProductGatewayInterface::class, NullStripeProductGateway::class);

        $this->action = new ReplacePlanPriceAction(
            $this->planRepository,
            $this->pricingRepository,
            $this->stripePriceGateway,
            $this->database,
            $this->currencyValidator,
            $this->domainGuard,
        );
    }
}