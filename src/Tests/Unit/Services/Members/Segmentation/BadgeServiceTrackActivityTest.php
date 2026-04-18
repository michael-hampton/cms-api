<?php

namespace App\Tests\Unit\Services\Members\Segmentation;

use App\Framework\Database\Database;
use App\Framework\Queue\Dispatcher;
use App\Framework\Queue\PendingDispatch;
use App\Jobs\EvaluateMemberBadgesJob;
use App\Models\Member;
use App\Models\MemberActivity;
use App\Repositories\Members\BadgeRepository;
use App\Services\Members\BadgeService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * Focused regression test for Ticket 2:
 * trackActivity() must dispatch EvaluateMemberBadgesJob
 * instead of calling checkAndAwardBadges() synchronously.
 */
class BadgeServiceTrackActivityTest extends TestCase
{
    private BadgeRepository|MockInterface $badgeRepository;
    private Database|MockInterface $database;
    private Dispatcher|MockInterface $dispatcher;
    private BadgeService $service;

    public function test_track_activity_dispatches_evaluate_badges_job(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $activity = Mockery::mock(MemberActivity::class)->makePartial();
        $activity->id = 10;

        $pendingDispatch = Mockery::mock(PendingDispatch::class);

        // Database::transaction executes its closure immediately in tests
        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->database
            ->shouldReceive('afterCommit')
            ->once()
            ->with(Mockery::on(function (callable $callback) {
                $callback();
                return true;
            }));

        $this->badgeRepository
            ->shouldReceive('createMemberActivity')
            ->once()
            ->andReturn($activity);

        $this->dispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn($job) => $job instanceof EvaluateMemberBadgesJob && $job->memberId === 1))
            ->andReturn($pendingDispatch);

        $pendingDispatch->shouldReceive('dispatch')->once();

        // points = 0 so awardPoints is NOT called — keeps test focused
        $this->service->trackActivity(
            member: $member,
            activityType: 'view',
            points: 0,
            siteId: 1,
        );

        $this->addToAssertionCount(1);
    }

    public function test_track_activity_does_not_call_check_and_award_badges_synchronously(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $activity = Mockery::mock(MemberActivity::class)->makePartial();
        $activity->id = 10;

        $pendingDispatch = Mockery::mock(PendingDispatch::class);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->database
            ->shouldReceive('afterCommit')
            ->once()
            ->with(Mockery::on(function (callable $callback) {
                $callback();
                return true;
            }));

        $this->badgeRepository
            ->shouldReceive('createMemberActivity')
            ->once()
            ->andReturn($activity);

        // If checkAndAwardBadges were called directly it would call getActiveBadgesForSite.
        // Asserting it is NEVER called proves we are fully async.
        $this->badgeRepository->shouldNotReceive('getActiveBadgesForSite');
        $this->dispatcher->shouldReceive('dispatch')->once()->andReturn($pendingDispatch);
        $pendingDispatch->shouldReceive('dispatch')->once();

        $this->service->trackActivity(
            member: $member,
            activityType: 'view',
            points: 0,
            siteId: 1,
        );

        $this->addToAssertionCount(1);
    }

    public function test_track_activity_returns_activity_model(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $activity = Mockery::mock(MemberActivity::class)->makePartial();
        $activity->id = 10;

        $pendingDispatch = Mockery::mock(PendingDispatch::class);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->database
            ->shouldReceive('afterCommit')
            ->once()
            ->with(Mockery::on(function (callable $callback) {
                $callback();
                return true;
            }));

        $this->badgeRepository
            ->shouldReceive('createMemberActivity')
            ->once()
            ->andReturn($activity);

        $this->dispatcher->shouldReceive('dispatch')->once()->andReturn($pendingDispatch);
        $pendingDispatch->shouldReceive('dispatch')->once();

        $result = $this->service->trackActivity(
            member: $member,
            activityType: 'comment',
            points: 0,
            siteId: 1,
        );

        $this->assertSame($activity, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->badgeRepository = Mockery::mock(BadgeRepository::class);
        $this->database = Mockery::mock(Database::class);
        $this->dispatcher = Mockery::mock(Dispatcher::class);
        $this->service = new BadgeService($this->badgeRepository, $this->database, $this->dispatcher);
    }
}
