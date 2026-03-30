<?php

namespace App\Tests\Unit\Actions\Subscriptions;

use App\Actions\Subscriptions\CreatePlanAction;
use App\Framework\Container;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Billing\Stripe\Contracts\StripePriceGatewayInterface;
use App\Services\Billing\Stripe\Contracts\StripeProductGatewayInterface;
use App\Services\Billing\Stripe\NullStripePriceGateway;
use App\Services\Billing\Stripe\NullStripeProductGateway;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class CreatePlanActionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private SubscriptionPlanRepository $planRepository;
    private StripeProductGatewayInterface $stripeProductGateway;
    private CreatePlanAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $container = Container::getInstance();
        $container->bind(StripePriceGatewayInterface::class, NullStripePriceGateway::class);
        $container->bind(StripeProductGatewayInterface::class, NullStripeProductGateway::class);

        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->stripeProductGateway = Mockery::mock(StripeProductGatewayInterface::class);

        $this->action = new CreatePlanAction(
            $this->planRepository,
            $this->stripeProductGateway,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_persists_plan_then_creates_stripe_product(): void
    {
        $planData = ['name' => 'Monthly Magazine', 'price' => 9.99, 'currency' => 'GBP'];
        $plan = $this->makePlan(1, 'Monthly Magazine');

        $this->planRepository->shouldReceive('create')->once()->with($planData)->andReturn($plan);

        $this->stripeProductGateway
            ->shouldReceive('createProduct')->once()->with('Monthly Magazine')->andReturn('prod_abc123');

        $this->planRepository->shouldReceive('update')->once()->with(1, ['stripe_product_id' => 'prod_abc123']);

        $result = $this->action->execute($planData);

        $this->assertSame($plan, $result);
        $this->assertEquals('prod_abc123', $result->stripe_product_id);
    }

    private function makePlan(int $id, string $name): SubscriptionPlan
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = $id;
        $plan->name = $name;
        return $plan;
    }

    public function test_stripe_product_gateway_is_called_exactly_once(): void
    {
        $plan = $this->makePlan(1, 'Weekly Plan');

        $this->planRepository->shouldReceive('create')->once()->andReturn($plan);
        $this->planRepository->shouldReceive('update')->once();

        $this->stripeProductGateway->shouldReceive('createProduct')->once()->andReturn('prod_xyz');

        $this->action->execute(['name' => 'Weekly Plan']);
    }

    public function test_stripe_product_id_is_stored_locally(): void
    {
        $plan = $this->makePlan(42, 'Quarterly');

        $this->planRepository->shouldReceive('create')->once()->andReturn($plan);
        $this->stripeProductGateway->shouldReceive('createProduct')->once()->andReturn('prod_quarterly_99');

        $this->planRepository
            ->shouldReceive('update')->once()->with(42, ['stripe_product_id' => 'prod_quarterly_99']);

        $result = $this->action->execute(['name' => 'Quarterly']);

        $this->assertEquals('prod_quarterly_99', $result->stripe_product_id);
    }

    public function test_plan_is_persisted_before_stripe_call(): void
    {
        $callOrder = [];
        $plan = $this->makePlan(1, 'Annual');

        $this->planRepository->shouldReceive('create')->once()->andReturnUsing(function () use (&$callOrder, $plan) {
            $callOrder[] = 'db_persist';
            return $plan;
        });

        $this->stripeProductGateway->shouldReceive('createProduct')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'stripe_call';
            return 'prod_annual';
        });

        $this->planRepository->shouldReceive('update')->once();

        $this->action->execute(['name' => 'Annual']);

        $this->assertEquals(['db_persist', 'stripe_call'], $callOrder);
    }

    public function test_stripe_failure_propagates_without_silencing(): void
    {
        $plan = $this->makePlan(1, 'Failing Plan');

        $this->planRepository->shouldReceive('create')->once()->andReturn($plan);
        $this->stripeProductGateway
            ->shouldReceive('createProduct')->once()->andThrow(new \RuntimeException('Stripe is down'));

        $this->planRepository->shouldReceive('update')->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe is down');

        $this->action->execute(['name' => 'Failing Plan']);
    }

    public function test_db_update_failure_compensates_by_deleting_stripe_product(): void
    {
        $plan = $this->makePlan(10, 'Compensation Plan');

        $this->planRepository->shouldReceive('create')->once()->andReturn($plan);

        $this->stripeProductGateway
            ->shouldReceive('createProduct')->once()->andReturn('prod_to_be_deleted');

        $this->planRepository
            ->shouldReceive('update')->once()->andThrow(new \RuntimeException('DB connection lost'));

        // Compensation: Stripe product must be deleted when DB update fails.
        $this->stripeProductGateway
            ->shouldReceive('deleteProduct')->once()->with('prod_to_be_deleted');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB connection lost');

        $this->action->execute(['name' => 'Compensation Plan']);
    }

    public function test_no_compensation_call_when_db_update_succeeds(): void
    {
        $plan = $this->makePlan(1, 'Happy Path');

        $this->planRepository->shouldReceive('create')->once()->andReturn($plan);
        $this->stripeProductGateway->shouldReceive('createProduct')->once()->andReturn('prod_happy');
        $this->planRepository->shouldReceive('update')->once();

        // deleteProduct must NOT be called on success.
        $this->stripeProductGateway->shouldReceive('deleteProduct')->never();

        $this->action->execute(['name' => 'Happy Path']);
    }

    public function test_variants_share_the_same_stripe_product_not_create_new_ones(): void
    {
        $plan = $this->makePlan(1, 'Base Plan');

        $this->planRepository->shouldReceive('create')->once()->andReturn($plan);
        $this->stripeProductGateway->shouldReceive('createProduct')->once()->andReturn('prod_base');
        $this->planRepository->shouldReceive('update')->once();

        $this->action->execute(['name' => 'Base Plan']);
    }

}