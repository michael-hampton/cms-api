<?php

namespace App\Tests\Unit\Services;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\SubscriptionModalService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class SubscriptionModalServiceTest extends FunctionalTestCase
{
    private $planRepository;
    private $subscriptionRepository;
    private $service;

    public function testShouldShowModalForNonMember(): void
    {
        $result = $this->service->shouldShowModal(null, 1);

        $this->assertTrue($result);
    }

    public function testShouldShowModalReturnsFalseWhenMemberHasActiveSubscription(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $this->subscriptionRepository->shouldReceive('shouldShowSubscriptionModal')
            ->with(1, 1)
            ->once()
            ->andReturn(false);

        $result = $this->service->shouldShowModal($member, 1);

        $this->assertFalse($result);
    }

    public function testShouldShowModalReturnsTrueWhenAppropriate(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $plans = Mockery::mock(Collection::class);
        $plans->shouldReceive('count')->andReturn(1);

        $this->subscriptionRepository->shouldReceive('shouldShowSubscriptionModal')
            ->with(1, 1)
            ->once()
            ->andReturn(true);

        $this->planRepository->shouldReceive('getActivePlans')
            ->with(1)
            ->once()
            ->andReturn($plans);

        $result = $this->service->shouldShowModal($member, 1);

        $this->assertTrue($result);
    }

    public function testGetModalDataReturnsCorrectStructure(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $plans = Mockery::mock(Collection::class);;
        $plans->shouldReceive('count')->andReturn(2);
        $plans->shouldReceive('take')->with(3)->andReturn($plans);

        $this->subscriptionRepository->shouldReceive('shouldShowSubscriptionModal')
            ->andReturn(true);

        $this->planRepository->shouldReceive('getActivePlans')
            ->andReturn($plans);

//        $this->planRepository->shouldReceive('getFeaturedPlans')
//            ->with(1)
//            ->once()
//            ->andReturn($plans);

        $result = $this->service->getModalData($member, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('show_modal', $result);
        $this->assertArrayHasKey('plans', $result);
        $this->assertArrayHasKey('member', $result);
        $this->assertTrue($result['show_modal']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);

        $this->service = new SubscriptionModalService(
            $this->planRepository,
            $this->subscriptionRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}