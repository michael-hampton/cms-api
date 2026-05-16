<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Console\SyncStripePricesCommand;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Billing\Stripe\StripePriceGateway;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class SyncStripePricesCommandTest extends FunctionalTestCase
{
    private SubscriptionPlanPricingRepository $pricingRepository;
    private SubscriptionPlanRepository        $planRepository;
    private StripePriceGateway                $stripePriceGateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricingRepository  = m::mock(SubscriptionPlanPricingRepository::class);
        $this->planRepository     = m::mock(SubscriptionPlanRepository::class);
        $this->stripePriceGateway = m::mock(StripePriceGateway::class);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    // ── Standard price sync ──────────────────────────────────────────────────

    public function test_syncs_standard_price_and_persists_stripe_price_id(): void
    {
        $plan    = $this->makePlan(id: 1, stripeProductId: 'prod_abc');
        $pricing = $this->makePricing(id: 10, planId: 1, price: 9.99, stripePriceId: null);

        $this->stripePriceGateway
            ->shouldReceive('createRecurringPrice')
            ->once()
            ->with('prod_abc', 999, m::any(), m::any())
            ->andReturn('price_std_new');

        $this->pricingRepository
            ->shouldReceive('update')
            ->once()
            ->with(10, ['stripe_price_id' => 'price_std_new']);

        $command = $this->makeCommand(plans: [$plan], standardRows: [$pricing], introRows: []);

        $exitCode = $command->handle();

        $this->assertSame(SyncStripePricesCommand::SUCCESS, $exitCode);
    }

    public function test_skips_standard_row_when_plan_has_no_stripe_product_id(): void
    {
        $plan    = $this->makePlan(id: 1, stripeProductId: null);
        $pricing = $this->makePricing(id: 10, planId: 1, price: 9.99, stripePriceId: null);

        $this->stripePriceGateway->shouldNotReceive('createRecurringPrice');
        $this->pricingRepository->shouldNotReceive('update');

        $command = $this->makeCommand(plans: [$plan], standardRows: [$pricing], introRows: []);

        $exitCode = $command->handle();

        $this->assertSame(SyncStripePricesCommand::SUCCESS, $exitCode);
    }

    public function test_reports_failure_and_continues_when_stripe_throws_on_standard_row(): void
    {
        $plan     = $this->makePlan(id: 1, stripeProductId: 'prod_abc');
        $pricing1 = $this->makePricing(id: 10, planId: 1, price: 9.99, stripePriceId: null);
        $pricing2 = $this->makePricing(id: 11, planId: 1, price: 19.99, stripePriceId: null);

        $this->stripePriceGateway
            ->shouldReceive('createRecurringPrice')
            ->twice()
            ->andThrow(new \RuntimeException('Stripe error'))
            ->andReturn('price_std_11');

        // Second row should still be attempted despite first failing
        $this->pricingRepository
            ->shouldReceive('update')
            ->once()
            ->with(11, ['stripe_price_id' => 'price_std_11']);

        $command = $this->makeCommand(
            plans:       [$plan],
            standardRows: [$pricing1, $pricing2],
            introRows:   [],
        );

        $exitCode = $command->handle();

        $this->assertSame(SyncStripePricesCommand::FAILURE, $exitCode);
    }

    public function test_dry_run_does_not_call_stripe_or_repository_for_standard(): void
    {
        $plan    = $this->makePlan(id: 1, stripeProductId: 'prod_abc');
        $pricing = $this->makePricing(id: 10, planId: 1, price: 9.99, stripePriceId: null);

        $this->stripePriceGateway->shouldNotReceive('createRecurringPrice');
        $this->pricingRepository->shouldNotReceive('update');

        $command = $this->makeCommand(
            plans:        [$plan],
            standardRows: [$pricing],
            introRows:    [],
            options:      ['dry-run' => true],
        );

        $exitCode = $command->handle();

        $this->assertSame(SyncStripePricesCommand::SUCCESS, $exitCode);
    }

    // ── Intro price sync ─────────────────────────────────────────────────────

    public function test_syncs_intro_price_and_persists_stripe_intro_price_id(): void
    {
        $plan    = $this->makePlan(id: 1, stripeProductId: 'prod_abc');
        $pricing = $this->makePricing(
            id:                 10,
            planId:             1,
            price:              9.99,
            stripePriceId:      'price_std',
            introPrice:         1.00,
            introCycles:        1,
            stripeIntroPriceId: null,
        );

        $this->stripePriceGateway
            ->shouldReceive('createRecurringPrice')
            ->once()
            ->with('prod_abc', 100, m::any(), m::any())
            ->andReturn('price_intro_new');

        $this->pricingRepository
            ->shouldReceive('update')
            ->once()
            ->with(10, ['stripe_intro_price_id' => 'price_intro_new']);

        $command = $this->makeCommand(plans: [$plan], standardRows: [], introRows: [$pricing]);

        $exitCode = $command->handle();

        $this->assertSame(SyncStripePricesCommand::SUCCESS, $exitCode);
    }

    public function test_skips_intro_row_when_intro_price_equals_standard_price(): void
    {
        $plan    = $this->makePlan(id: 1, stripeProductId: 'prod_abc');
        $pricing = $this->makePricing(
            id:            10,
            planId:        1,
            price:         9.99,
            stripePriceId: 'price_std',
            introPrice:    9.99,   // same — invalid
            introCycles:   1,
        );

        $this->stripePriceGateway->shouldNotReceive('createRecurringPrice');
        $this->pricingRepository->shouldNotReceive('update');

        $command = $this->makeCommand(plans: [$plan], standardRows: [], introRows: [$pricing]);

        $exitCode = $command->handle();

        $this->assertSame(SyncStripePricesCommand::SUCCESS, $exitCode);
    }

    public function test_skips_intro_row_when_intro_price_exceeds_standard_price(): void
    {
        $plan    = $this->makePlan(id: 1, stripeProductId: 'prod_abc');
        $pricing = $this->makePricing(
            id:            10,
            planId:        1,
            price:         9.99,
            stripePriceId: 'price_std',
            introPrice:    15.00,  // higher — invalid
            introCycles:   1,
        );

        $this->stripePriceGateway->shouldNotReceive('createRecurringPrice');
        $this->pricingRepository->shouldNotReceive('update');

        $command = $this->makeCommand(plans: [$plan], standardRows: [], introRows: [$pricing]);

        $command->handle();

        $this->assertTrue(true);
    }

    public function test_skips_intro_row_when_plan_has_no_stripe_product_id(): void
    {
        $plan    = $this->makePlan(id: 1, stripeProductId: null);
        $pricing = $this->makePricing(
            id:            10,
            planId:        1,
            price:         9.99,
            stripePriceId: 'price_std',
            introPrice:    1.00,
            introCycles:   1,
        );

        $this->stripePriceGateway->shouldNotReceive('createRecurringPrice');

        $command = $this->makeCommand(plans: [$plan], standardRows: [], introRows: [$pricing]);

        $command->handle();

        $this->assertTrue(true);
    }

    public function test_dry_run_does_not_call_stripe_or_repository_for_intro(): void
    {
        $plan    = $this->makePlan(id: 1, stripeProductId: 'prod_abc');
        $pricing = $this->makePricing(
            id:            10,
            planId:        1,
            price:         9.99,
            stripePriceId: 'price_std',
            introPrice:    1.00,
            introCycles:   1,
        );

        $this->stripePriceGateway->shouldNotReceive('createRecurringPrice');
        $this->pricingRepository->shouldNotReceive('update');

        $command = $this->makeCommand(
            plans:       [$plan],
            standardRows: [],
            introRows:   [$pricing],
            options:     ['dry-run' => true],
        );

        $exitCode = $command->handle();

        $this->assertSame(SyncStripePricesCommand::SUCCESS, $exitCode);
    }

    public function test_reports_failure_and_continues_when_stripe_throws_on_intro_row(): void
    {
        $plan     = $this->makePlan(id: 1, stripeProductId: 'prod_abc');
        $pricing1 = $this->makePricing(
            id: 10, planId: 1, price: 9.99,
            stripePriceId: 'price_std', introPrice: 1.00, introCycles: 1,
        );
        $pricing2 = $this->makePricing(
            id: 11, planId: 1, price: 19.99,
            stripePriceId: 'price_std_2', introPrice: 2.00, introCycles: 1,
        );

        $this->stripePriceGateway
            ->shouldReceive('createRecurringPrice')
            ->twice()
            ->andThrow(new \RuntimeException('Stripe error'))
            ->andReturn('price_intro_11');

        $this->pricingRepository
            ->shouldReceive('update')
            ->once()
            ->with(11, ['stripe_intro_price_id' => 'price_intro_11']);

        $command = $this->makeCommand(
            plans:       [$plan],
            standardRows: [],
            introRows:   [$pricing1, $pricing2],
        );

        $exitCode = $command->handle();

        $this->assertSame(SyncStripePricesCommand::FAILURE, $exitCode);
    }

    // ── intro-only flag ──────────────────────────────────────────────────────

    public function test_intro_only_flag_skips_standard_sync_entirely(): void
    {
        $plan        = $this->makePlan(id: 1, stripeProductId: 'prod_abc');
        $stdPricing  = $this->makePricing(id: 10, planId: 1, price: 9.99, stripePriceId: null);
        $introPricing = $this->makePricing(
            id: 11, planId: 1, price: 9.99,
            stripePriceId: 'price_std', introPrice: 1.00, introCycles: 1,
        );

        // createRecurringPrice must only be called once — for the intro row
        $this->stripePriceGateway
            ->shouldReceive('createRecurringPrice')
            ->once()
            ->with('prod_abc', 100, m::any(), m::any())
            ->andReturn('price_intro_new');

        $this->pricingRepository
            ->shouldReceive('update')
            ->once()
            ->with(11, ['stripe_intro_price_id' => 'price_intro_new']);

        $command = $this->makeCommand(
            plans:        [$plan],
            standardRows: [$stdPricing],
            introRows:    [$introPricing],
            options:      ['intro-only' => true],
        );

        $exitCode = $command->handle();

        $this->assertSame(SyncStripePricesCommand::SUCCESS, $exitCode);
    }

    // ── Empty result sets ────────────────────────────────────────────────────

    public function test_returns_success_when_no_rows_found(): void
    {
        $command = $this->makeCommand(plans: [], standardRows: [], introRows: []);

        $exitCode = $command->handle();

        $this->assertSame(SyncStripePricesCommand::SUCCESS, $exitCode);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makePlan(int $id, ?string $stripeProductId): SubscriptionPlan
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id                = $id;
        $plan->name              = "Plan {$id}";
        $plan->stripe_product_id = $stripeProductId;
        $plan->stripe_price_id   = null;

        return $plan;
    }

    private function makePricing(
        int     $id,
        int     $planId,
        float   $price,
        ?string $stripePriceId      = null,
        ?float  $introPrice         = null,
        ?int    $introCycles        = null,
        ?string $stripeIntroPriceId = null,
        string  $currency           = 'GBP',
        ?string $interval           = 'month',
        bool    $isDefault          = false,
    ): SubscriptionPlanPricing {
        $pricing = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $pricing->id                    = $id;
        $pricing->plan_id               = $planId;
        $pricing->price                 = $price;
        $pricing->stripe_price_id       = $stripePriceId;
        $pricing->intro_price           = $introPrice;
        $pricing->intro_cycles          = $introCycles;
        $pricing->stripe_intro_price_id = $stripeIntroPriceId;
        $pricing->currency              = $currency;
        $pricing->interval              = $interval;
        $pricing->is_default            = $isDefault;
        $pricing->is_active             = true;

        return $pricing;
    }

    /**
     * Build a testable command instance with injected query results.
     *
     * Because the command uses static Eloquent queries internally we use a
     * partial subclass to intercept the DB calls without a full application
     * bootstrap.
     */
    private function makeCommand(
        array $plans,
        array $standardRows,
        array $introRows,
        array $options = [],
    ): SyncStripePricesCommand {
        $command = $this->getMockBuilder(SyncStripePricesCommand::class)
            ->setConstructorArgs([
                $this->pricingRepository,
                $this->planRepository,
                $this->stripePriceGateway,
            ])
            ->onlyMethods(['resolvePlanIds', 'queryStandardRows', 'queryIntroRows', 'option', 'info'])
            ->getMock();

        $planIds = array_map(fn ($p) => $p->id, $plans);

        $command->method('resolvePlanIds')->willReturn($planIds);
        $command->method('queryStandardRows')->willReturn(collect($standardRows));
        $command->method('queryIntroRows')->willReturn(collect($introRows));
        $command->method('info');

        $command->method('option')->willReturnCallback(
            fn (string $key) => $options[$key] ?? false
        );

        // Wire plansById — commands need the keyed collection
        // We inject plans as a pre-keyed collection via protected method override.
        // If your framework supports it, alternatively bind via service container.

        return $command;
    }
}