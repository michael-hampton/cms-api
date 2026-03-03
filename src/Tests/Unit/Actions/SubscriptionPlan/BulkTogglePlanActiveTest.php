<?php

namespace App\Tests\Unit\Actions\SubscriptionPlan;

use App\Actions\SubscriptionPlan\BulkTogglePlanActive;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class BulkTogglePlanActiveTest extends TestCase
{
    private MockInterface $planRepository;
    private BulkTogglePlanActive $action;

    public function testBulkActivatesPlans(): void
    {
        $plan1 = Mockery::mock(SubscriptionPlan::class);
        $plan2 = Mockery::mock(SubscriptionPlan::class);

        $this->planRepository->shouldReceive('find')->with(1)->once()->andReturn($plan1);
        $this->planRepository->shouldReceive('find')->with(2)->once()->andReturn($plan2);

        $this->planRepository->shouldReceive('update')
            ->with(1, ['is_active' => true])->once()->andReturn($plan1);
        $this->planRepository->shouldReceive('update')
            ->with(2, ['is_active' => true])->once()->andReturn($plan2);

        $result = $this->action->handle([1, 2], true);

        $this->assertEquals([1, 2], $result['updated']);
        $this->assertEmpty($result['failed']);
        $this->assertEquals(2, $result['total']);
    }

    public function testBulkDeactivatesPlans(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class);

        $this->planRepository->shouldReceive('find')->with(5)->once()->andReturn($plan);
        $this->planRepository->shouldReceive('update')
            ->with(5, ['is_active' => false])->once()->andReturn($plan);

        $result = $this->action->handle([5], false);

        $this->assertEquals([5], $result['updated']);
        $this->assertEmpty($result['failed']);
        $this->assertEquals(1, $result['total']);
    }

    public function testSkipsMissingPlan(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class);

        $this->planRepository->shouldReceive('find')->with(1)->once()->andReturn($plan);
        $this->planRepository->shouldReceive('find')->with(99)->once()->andReturn(null);

        $this->planRepository->shouldReceive('update')
            ->with(1, ['is_active' => true])->once()->andReturn($plan);
        $this->planRepository->shouldReceive('update')->with(99, Mockery::any())->never();

        $result = $this->action->handle([1, 99], true);

        $this->assertEquals([1], $result['updated']);
        $this->assertCount(1, $result['failed']);
        $this->assertEquals(99, $result['failed'][0]['id']);
        $this->assertEquals('Plan not found', $result['failed'][0]['reason']);
        $this->assertEquals(2, $result['total']);
    }

    public function testHandlesUpdateFailure(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class);

        $this->planRepository->shouldReceive('find')->with(3)->once()->andReturn($plan);
        $this->planRepository->shouldReceive('update')
            ->with(3, ['is_active' => true])->once()->andReturn(null);

        $result = $this->action->handle([3], true);

        $this->assertEmpty($result['updated']);
        $this->assertCount(1, $result['failed']);
        $this->assertEquals('Update failed', $result['failed'][0]['reason']);
    }

    public function testHandlesException(): void
    {
        $this->planRepository->shouldReceive('find')->with(7)->once()
            ->andThrow(new \Exception('DB connection lost'));
        $this->planRepository->shouldReceive('update')->never();

        $result = $this->action->handle([7], true);

        $this->assertEmpty($result['updated']);
        $this->assertCount(1, $result['failed']);
        $this->assertEquals('DB connection lost', $result['failed'][0]['reason']);
    }

    public function testReturnsCorrectTotals(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class);

        $this->planRepository->shouldReceive('find')->with(1)->andReturn($plan);
        $this->planRepository->shouldReceive('find')->with(2)->andReturn(null);
        $this->planRepository->shouldReceive('find')->with(3)->andReturn($plan);

        $this->planRepository->shouldReceive('update')->with(1, Mockery::any())->andReturn($plan);
        $this->planRepository->shouldReceive('update')->with(3, Mockery::any())->andReturn(null);

        $result = $this->action->handle([1, 2, 3], true);

        $this->assertCount(1, $result['updated']);  // plan 1
        $this->assertCount(2, $result['failed']);    // plan 2 (not found), plan 3 (update failed)
        $this->assertEquals(3, $result['total']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->action = new BulkTogglePlanActive($this->planRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}