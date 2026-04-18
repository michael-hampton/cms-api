<?php

namespace App\Tests\Unit\Services\Members\Segmentation;

use App\Framework\Container;
use App\Framework\Queue\Dispatcher;
use App\Framework\Queue\PendingDispatch;
use App\Framework\Support\Collection;
use App\Jobs\ProcessMemberSegmentationJob;
use App\Jobs\SendCampaignJob;
use App\Models\Campaign;
use App\Models\Member;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Members\MemberSegmentationProfileRepository;
use App\Services\Members\Segmentation\CampaignCooldownChecker;
use App\Services\Members\Segmentation\CampaignMatcher;
use App\Services\Members\Segmentation\MemberSegmentResolver;
use App\Services\Members\Segmentation\SegmentPersister;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ProcessMemberSegmentationJobTest extends TestCase
{
    private MemberSegmentResolver|MockInterface $resolver;
    private SegmentPersister|MockInterface $persister;
    private CampaignMatcher|MockInterface $matcher;
    private CampaignCooldownChecker|MockInterface $cooldown;
    private MemberRepository|MockInterface $memberRepository;
    private MemberSegmentationProfileRepository|MockInterface $profileRepository;
    private Dispatcher|MockInterface $dispatcher;
    private PendingDispatch|MockInterface $pendingDispatch;

    public function test_does_nothing_when_member_not_found(): void
    {
        $this->memberRepository->shouldReceive('find')->with(999)->andReturnNull();

        $this->resolver->shouldNotReceive('resolve');

        $this->runJob(memberId: 999);
        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // Guard clauses
    // =========================================================================

    private function runJob(int $memberId = 1, int $siteId = 10): void
    {
        $job = new ProcessMemberSegmentationJob($memberId, $siteId);
        $job->__wakeup();
        $job->handle();
    }

    public function test_does_nothing_when_no_snapshot_exists(): void
    {
        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($this->makeMember(1));
        $this->profileRepository->shouldReceive('getLatestProfile')->with(1, 10)->andReturnNull();

        $this->resolver->shouldNotReceive('resolve');
        $this->persister->shouldNotReceive('persist');

        $this->runJob(memberId: 1);
        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // Happy path
    // =========================================================================

    private function makeMember(int $id): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = $id;
        return $member;
    }

    public function test_resolves_segments_and_persists_them(): void
    {
        $member = $this->makeMember(1);
        $profile = ['scores' => ['activity_score' => 90]];

        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($member);
        $this->profileRepository->shouldReceive('getLatestProfile')->with(1, 10)->andReturn($profile);

        $this->resolver->shouldReceive('resolve')
            ->once()
            ->with($profile)
            ->andReturn(['highly_active']);

        $this->persister->shouldReceive('persist')
            ->once()
            ->with(1, 10, ['highly_active']);

        $this->matcher->shouldReceive('match')
            ->with(['highly_active'])
            ->andReturn(new Collection());

        $this->runJob(memberId: 1, siteId: 10);
        $this->addToAssertionCount(1);
    }

    public function test_dispatches_send_campaign_job_for_eligible_campaign(): void
    {
        $member = $this->makeMember(1);
        $campaign = $this->makeCampaign(id: 5, segmentKey: 'churning');

        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($member);
        $this->profileRepository->shouldReceive('getLatestProfile')->with(1, 10)->andReturn([]);

        $this->resolver->shouldReceive('resolve')->andReturn(['churning']);
        $this->persister->shouldReceive('persist')->once();
        $this->matcher->shouldReceive('match')->andReturn(new Collection([$campaign]));
        $this->cooldown->shouldReceive('isEligible')->with(1, $campaign)->andReturn(true);
        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn($job) => $job instanceof SendCampaignJob && $job->campaignId === 5))
            ->andReturn($this->pendingDispatch);

        $this->runJob(memberId: 1, siteId: 10);
        $this->addToAssertionCount(1);
    }

    private function makeCampaign(int $id, string $segmentKey): Campaign
    {
        $segment = (object)['key' => $segmentKey];

        $campaign = Mockery::mock(Campaign::class)->makePartial();
        $campaign->id = $id;
        $campaign->setRelation('segment', $segment);
        return $campaign;
    }

    public function test_skips_campaign_within_cooldown(): void
    {
        $member = $this->makeMember(1);
        $campaign = $this->makeCampaign(id: 5, segmentKey: 'churning');

        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($member);
        $this->profileRepository->shouldReceive('getLatestProfile')->with(1, 10)->andReturn([]);

        $this->resolver->shouldReceive('resolve')->andReturn(['churning']);
        $this->persister->shouldReceive('persist')->once();
        $this->matcher->shouldReceive('match')->andReturn(new Collection([$campaign]));
        $this->cooldown->shouldReceive('isEligible')->with(1, $campaign)->andReturn(false);
        $this->dispatcher->shouldNotReceive('dispatch');

        $this->runJob(memberId: 1, siteId: 10);
        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_respects_max_campaigns_per_run_cap(): void
    {
        $member = $this->makeMember(1);
        $campaigns = collect([
            $this->makeCampaign(id: 1, segmentKey: 'churning'),
            $this->makeCampaign(id: 2, segmentKey: 'lurker'),
            $this->makeCampaign(id: 3, segmentKey: 'highly_active'),
            $this->makeCampaign(id: 4, segmentKey: 'new_user'),  // should be capped
        ]);

        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($member);
        $this->profileRepository->shouldReceive('getLatestProfile')->with(1, 10)->andReturn([]);

        $this->resolver->shouldReceive('resolve')->andReturn(['churning', 'lurker', 'highly_active', 'new_user']);
        $this->persister->shouldReceive('persist')->once();
        $this->matcher->shouldReceive('match')->andReturn($campaigns);
        $this->cooldown->shouldReceive('isEligible')->andReturn(true);
        $this->dispatcher->shouldReceive('dispatch')->times(3)->andReturn($this->pendingDispatch);

        $this->runJob(memberId: 1, siteId: 10);
        $this->addToAssertionCount(1);
    }

    public function test_does_not_dispatch_campaigns_when_no_segments_matched(): void
    {
        $member = $this->makeMember(1);

        $this->memberRepository->shouldReceive('find')->with(1)->andReturn($member);
        $this->profileRepository->shouldReceive('getLatestProfile')->with(1, 10)->andReturn([]);

        $this->resolver->shouldReceive('resolve')->andReturn([]);
        $this->persister->shouldReceive('persist')->once()->with(1, 10, []);
        $this->matcher->shouldNotReceive('match');
        $this->dispatcher->shouldNotReceive('dispatch');

        $this->runJob(memberId: 1, siteId: 10);
        $this->addToAssertionCount(1);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = Mockery::mock(MemberSegmentResolver::class);
        $this->persister = Mockery::mock(SegmentPersister::class);
        $this->matcher = Mockery::mock(CampaignMatcher::class);
        $this->cooldown = Mockery::mock(CampaignCooldownChecker::class);
        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->profileRepository = Mockery::mock(MemberSegmentationProfileRepository::class);
        $this->dispatcher = Mockery::mock(Dispatcher::class);
        $this->pendingDispatch = Mockery::mock(PendingDispatch::class);

        Container::getInstance()->instance(MemberRepository::class, $this->memberRepository);
        Container::getInstance()->instance(MemberSegmentationProfileRepository::class, $this->profileRepository);
        Container::getInstance()->instance(MemberSegmentResolver::class, $this->resolver);
        Container::getInstance()->instance(SegmentPersister::class, $this->persister);
        Container::getInstance()->instance(CampaignMatcher::class, $this->matcher);
        Container::getInstance()->instance(CampaignCooldownChecker::class, $this->cooldown);
        Container::getInstance()->instance(Dispatcher::class, $this->dispatcher);
    }
}
