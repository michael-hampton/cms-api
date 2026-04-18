<?php

namespace App\Tests\Unit\Services\Members\Segmentation;

use App\Framework\Container;
use App\Framework\Queue\Dispatcher;
use App\Framework\Queue\PendingDispatch;
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

class SegmentationPipelineTest extends TestCase
{
    private MemberRepository|MockInterface $memberRepository;
    private MemberSegmentationProfileRepository|MockInterface $profileRepository;
    private MemberSegmentResolver|MockInterface $resolver;
    private SegmentPersister|MockInterface $persister;
    private CampaignMatcher|MockInterface $matcher;
    private CampaignCooldownChecker|MockInterface $cooldownChecker;
    private Dispatcher|MockInterface $dispatcher;

    public function test_pipeline_persists_resolved_segments_from_member_stats_profile(): void
    {
        $member = $this->makeMember(5);
        $profile = ['trends' => ['7d_change' => -25]];

        $this->memberRepository->shouldReceive('find')->with(5)->andReturn($member);
        $this->profileRepository->shouldReceive('getLatestProfile')->with(5, 3)->andReturn($profile);
        $this->resolver->shouldReceive('resolve')->with($profile)->andReturn(['churning']);
        $this->persister->shouldReceive('persist')->once()->with(5, 3, ['churning']);
        $this->matcher->shouldReceive('match')->with(['churning'])->andReturn(collect());
        $this->dispatcher->shouldNotReceive('dispatch');

        $this->runJob(5, 3);
        $this->addToAssertionCount(1);
    }

    private function makeMember(int $id): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = $id;

        return $member;
    }

    private function runJob(int $memberId, int $siteId): void
    {
        $job = new ProcessMemberSegmentationJob($memberId, $siteId);
        $job->__wakeup();
        $job->handle();
    }

    public function test_pipeline_skips_when_no_member_stat_profile_exists(): void
    {
        $member = $this->makeMember(5);

        $this->memberRepository->shouldReceive('find')->with(5)->andReturn($member);
        $this->profileRepository->shouldReceive('getLatestProfile')->with(5, 3)->andReturnNull();
        $this->resolver->shouldNotReceive('resolve');
        $this->persister->shouldNotReceive('persist');
        $this->matcher->shouldNotReceive('match');

        $this->runJob(5, 3);
        $this->addToAssertionCount(1);
    }

    public function test_pipeline_dispatches_one_send_job_per_eligible_campaign_up_to_cap(): void
    {
        $member = $this->makeMember(5);
        $campaigns = collect([
            $this->makeCampaign(10, 'churning'),
            $this->makeCampaign(11, 'lurker'),
            $this->makeCampaign(12, 'high_value'),
            $this->makeCampaign(13, 'reactivation'),
        ]);

        $pending = Mockery::mock(PendingDispatch::class);

        $this->memberRepository->shouldReceive('find')->with(5)->andReturn($member);
        $this->profileRepository->shouldReceive('getLatestProfile')->with(5, 3)->andReturn(['flags' => ['lurker_profile']]);
        $this->resolver->shouldReceive('resolve')->andReturn(['churning', 'lurker', 'high_value', 'reactivation']);
        $this->persister->shouldReceive('persist')->once();
        $this->matcher->shouldReceive('match')->once()->andReturn($campaigns);
        $this->cooldownChecker->shouldReceive('isEligible')->times(3)->andReturn(true);
        $this->dispatcher->shouldReceive('dispatch')
            ->times(3)
            ->with(Mockery::on(fn($job) => $job instanceof SendCampaignJob))
            ->andReturn($pending);

        $this->runJob(5, 3);
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->profileRepository = Mockery::mock(MemberSegmentationProfileRepository::class);
        $this->resolver = Mockery::mock(MemberSegmentResolver::class);
        $this->persister = Mockery::mock(SegmentPersister::class);
        $this->matcher = Mockery::mock(CampaignMatcher::class);
        $this->cooldownChecker = Mockery::mock(CampaignCooldownChecker::class);
        $this->dispatcher = Mockery::mock(Dispatcher::class);

        Container::getInstance()->instance(MemberRepository::class, $this->memberRepository);
        Container::getInstance()->instance(MemberSegmentationProfileRepository::class, $this->profileRepository);
        Container::getInstance()->instance(MemberSegmentResolver::class, $this->resolver);
        Container::getInstance()->instance(SegmentPersister::class, $this->persister);
        Container::getInstance()->instance(CampaignMatcher::class, $this->matcher);
        Container::getInstance()->instance(CampaignCooldownChecker::class, $this->cooldownChecker);
        Container::getInstance()->instance(Dispatcher::class, $this->dispatcher);
    }
}
