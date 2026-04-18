<?php

namespace App\Tests\Unit\Services\Members\Segmentation;

use App\Framework\Container;
use App\Jobs\EvaluateMemberBadgesJob;
use App\Models\Member;
use App\Repositories\Members\MemberRepository;
use App\Services\Members\BadgeService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class EvaluateMemberBadgesJobTest extends TestCase
{
    private BadgeService|MockInterface $badgeService;
    private MemberRepository|MockInterface $memberRepository;

    public function test_calls_check_and_award_badges_with_resolved_member(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 42;

        $this->memberRepository->shouldReceive('find')->with(42)->andReturn($member);

        $this->badgeService
            ->shouldReceive('checkAndAwardBadges')
            ->once()
            ->with($member);

        $this->runJob(memberId: 42);
        $this->addToAssertionCount(1);
    }

    private function runJob(int $memberId): void
    {
        $job = new EvaluateMemberBadgesJob($memberId);
        $job->__wakeup();
        $job->handle();
    }

    public function test_does_nothing_when_member_not_found(): void
    {
        $this->memberRepository->shouldReceive('find')->with(999)->andReturnNull();

        $this->badgeService->shouldNotReceive('checkAndAwardBadges');

        $this->runJob(memberId: 999);
        $this->addToAssertionCount(1);
    }

    public function test_job_stores_member_id_as_public_property(): void
    {
        $job = new EvaluateMemberBadgesJob(memberId: 7);

        $this->assertSame(7, $job->memberId);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();
        $this->badgeService = Mockery::mock(BadgeService::class);
        $this->memberRepository = Mockery::mock(MemberRepository::class);

        Container::getInstance()->instance(BadgeService::class, $this->badgeService);
        Container::getInstance()->instance(MemberRepository::class, $this->memberRepository);
    }
}
