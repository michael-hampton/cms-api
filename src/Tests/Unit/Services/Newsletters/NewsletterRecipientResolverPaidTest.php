<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Models\Member;
use App\Models\MemberSubscriptionPreference;
use App\Models\Newsletter;
use App\Models\Subscription;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Newsletters\SubscriberRepository;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Newsletter\NewsletterRecipientResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

class NewsletterRecipientResolverPaidTest extends TestCase
{
    private NewsletterRecipientResolver $resolver;
    private $mockSubscriberRepo;
    private $mockPreferenceRepo;
    private $mockMemberRepo;
    private $mockSubscriptionRepo;

    public function test_paid_newsletter_excludes_member_without_subscription(): void
    {
        $newsletter = $this->createPaidNewsletter();
        $siteId = 1;

        $mockPref = $this->createMockPreference('notsub@example.com', 'weekly', false);

        $this->mockSubscriberRepo->shouldReceive('getConfirmedEmails')->andReturn([]);
        $this->mockPreferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->andReturn(collect([$mockPref]));

        $this->mockSubscriptionRepo->shouldReceive('getActiveSubscriptionForMember')
            ->with($mockPref->member->id, $siteId)
            ->andReturn(null);

        $this->mockMemberRepo->shouldReceive('findByEmails')->andReturn(collect([$mockPref->member]));
        $this->mockPreferenceRepo->shouldReceive('findByEmails')->andReturn(collect([$mockPref]));

        $result = $this->resolver->resolveForNewsletter($newsletter, $siteId);

        $this->assertEmpty($result['valid']);
    }

    private function createPaidNewsletter(?string $slug = null): Newsletter
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->interval = 'weekly';
        $newsletter->slug = $slug;
        $newsletter->shouldReceive('isPremium')->andReturn(true);
        return $newsletter;
    }

    private function createMockPreference(string $email, string $frequency, bool $optOut, ?int $id = null)
    {
        $pref = Mockery::mock(MemberSubscriptionPreference::class)->makePartial();
        $pref->newsletter_frequency = $frequency;
        $pref->newsletter_opt_out = $optOut;
        $pref->member = Mockery::mock(Member::class)->makePartial();
        $pref->member->email = $email;
        $pref->member->id = $id ?? rand(1, 1000);
        $pref->member->shouldReceive('getCommunicationPreference')->andReturn(true);
        return $pref;
    }

    public function test_paid_newsletter_includes_member_with_active_subscription(): void
    {
        $newsletter = $this->createPaidNewsletter();
        $siteId = 1;

        $mockPref = $this->createMockPreference('paid@example.com', 'weekly', false);
        $mockSub = $this->createMockSubscription('active', 'paid');

        $this->mockSubscriberRepo->shouldReceive('getConfirmedEmails')->andReturn([]);
        $this->mockPreferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->andReturn(collect([$mockPref]));

        $this->mockSubscriptionRepo->shouldReceive('getActiveSubscriptionForMember')
            ->andReturn($mockSub);

        $this->mockMemberRepo->shouldReceive('findByEmails')->andReturn(collect([]));
        $this->mockPreferenceRepo->shouldReceive('findByEmails')->andReturn(collect([]));

        $result = $this->resolver->resolveForNewsletter($newsletter, $siteId);

        $this->assertContains('paid@example.com', $result['valid']);
    }

    private function createMockSubscription(
        string $status,
        string $type,
        bool   $eligible = true,
        bool   $hasAccess = true
    ): Subscription
    {
        $sub = Mockery::mock(Subscription::class)->makePartial();
        $sub->status = $status;
        $sub->type = $type;
        $sub->plan_name = 'Test Plan';
        $sub->shouldReceive('isEligibleForPaidNewsletter')->andReturn($eligible);
        $sub->shouldReceive('hasPremiumAccess')->andReturn($hasAccess);
        $sub->shouldReceive('canAccessNewsletter')->andReturn($eligible && $hasAccess ? \App\DTO\Newsletters\NewsletterAccessResult::allowed() : \App\DTO\Newsletters\NewsletterAccessResult::denied('reason', 'reason'));
        return $sub;
    }

    public function test_paid_newsletter_includes_member_in_grace_period(): void
    {
        $newsletter = $this->createPaidNewsletter();
        $siteId = 1;

        $mockPref = $this->createMockPreference('grace@example.com', 'weekly', false);
        $mockSub = $this->createMockSubscription('grace_period', 'paid');

        $this->mockSubscriberRepo->shouldReceive('getConfirmedEmails')->andReturn([]);
        $this->mockPreferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->andReturn(collect([$mockPref]));

        $this->mockSubscriptionRepo->shouldReceive('getActiveSubscriptionForMember')
            ->andReturn($mockSub);

        $this->mockMemberRepo->shouldReceive('findByEmails')->andReturn(collect([]));
        $this->mockPreferenceRepo->shouldReceive('findByEmails')->andReturn(collect([]));

        $result = $this->resolver->resolveForNewsletter($newsletter, $siteId);

        $this->assertContains('grace@example.com', $result['valid']);
    }

    public function test_paid_newsletter_includes_member_retrying_payment(): void
    {
        $newsletter = $this->createPaidNewsletter();
        $siteId = 1;

        $mockPref = $this->createMockPreference('retry@example.com', 'weekly', false);
        $mockSub = $this->createMockSubscription('retrying', 'paid');

        $this->mockSubscriberRepo->shouldReceive('getConfirmedEmails')->andReturn([]);
        $this->mockPreferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->andReturn(collect([$mockPref]));

        $this->mockSubscriptionRepo->shouldReceive('getActiveSubscriptionForMember')
            ->andReturn($mockSub);

        $this->mockMemberRepo->shouldReceive('findByEmails')->andReturn(collect([]));
        $this->mockPreferenceRepo->shouldReceive('findByEmails')->andReturn(collect([]));

        $result = $this->resolver->resolveForNewsletter($newsletter, $siteId);

        $this->assertContains('retry@example.com', $result['valid']);
    }

    public function test_paid_newsletter_excludes_expired_subscription(): void
    {
        $newsletter = $this->createPaidNewsletter();
        $siteId = 1;

        $mockPref = $this->createMockPreference('expired@example.com', 'weekly', false);
        $mockSub = $this->createMockSubscription('expired', 'paid', false);

        $this->mockSubscriberRepo->shouldReceive('getConfirmedEmails')->andReturn([]);
        $this->mockPreferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->andReturn(collect([$mockPref]));

        $this->mockSubscriptionRepo->shouldReceive('getActiveSubscriptionForMember')
            ->andReturn($mockSub);

        $this->mockMemberRepo->shouldReceive('findByEmails')->andReturn(collect([$mockPref->member]));
        $this->mockPreferenceRepo->shouldReceive('findByEmails')->andReturn(collect([$mockPref]));

        $result = $this->resolver->resolveForNewsletter($newsletter, $siteId);

        $this->assertEmpty($result['valid']);
    }

    public function test_paid_newsletter_excludes_cancelled_subscription(): void
    {
        $newsletter = $this->createPaidNewsletter();
        $siteId = 1;

        $mockPref = $this->createMockPreference('cancelled@example.com', 'weekly', false);
        $mockSub = $this->createMockSubscription('cancelled', 'paid', false);

        $this->mockSubscriberRepo->shouldReceive('getConfirmedEmails')->andReturn([]);
        $this->mockPreferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->andReturn(collect([$mockPref]));

        $this->mockSubscriptionRepo->shouldReceive('getActiveSubscriptionForMember')
            ->andReturn($mockSub);

        $this->mockMemberRepo->shouldReceive('findByEmails')->andReturn(collect([$mockPref->member]));
        $this->mockPreferenceRepo->shouldReceive('findByEmails')->andReturn(collect([$mockPref]));

        $result = $this->resolver->resolveForNewsletter($newsletter, $siteId);

        $this->assertEmpty($result['valid']);
    }

    public function test_paid_newsletter_with_access_level_checks_premium_access(): void
    {
        $newsletter = $this->createPaidNewsletter('insider');
        $siteId = 1;

        $mockPref = $this->createMockPreference('member@example.com', 'weekly', false);
        $mockSub = $this->createMockSubscription('active', 'paid', true, false);

        $this->mockSubscriberRepo->shouldReceive('getConfirmedEmails')->andReturn([]);
        $this->mockPreferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->andReturn(collect([$mockPref]));

        $this->mockSubscriptionRepo->shouldReceive('getActiveSubscriptionForMember')
            ->andReturn($mockSub);

        $this->mockMemberRepo->shouldReceive('findByEmails')->andReturn(collect([$mockPref->member]));
        $this->mockPreferenceRepo->shouldReceive('findByEmails')->andReturn(collect([$mockPref]));

        $result = $this->resolver->resolveForNewsletter($newsletter, $siteId);

        $this->assertEmpty($result['valid']);
    }

    public function test_paid_newsletter_with_matching_access_level_includes_member(): void
    {
        $newsletter = $this->createPaidNewsletter('insider');
        $siteId = 1;

        $mockPref = $this->createMockPreference('insider@example.com', 'weekly', false);
        $mockSub = $this->createMockSubscription('active', 'paid', true, true);

        $this->mockSubscriberRepo->shouldReceive('getConfirmedEmails')->andReturn([]);
        $this->mockPreferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->andReturn(collect([$mockPref]));

        $this->mockSubscriptionRepo->shouldReceive('getActiveSubscriptionForMember')
            ->andReturn($mockSub);

        $this->mockMemberRepo->shouldReceive('findByEmails')->andReturn(collect([]));
        $this->mockPreferenceRepo->shouldReceive('findByEmails')->andReturn(collect([]));

        $result = $this->resolver->resolveForNewsletter($newsletter, $siteId);

        $this->assertContains('insider@example.com', $result['valid']);
    }

    public function test_free_newsletter_includes_both_free_and_paid_users(): void
    {
        $newsletter = $this->createFreeNewsletter();
        $siteId = 1;

        $mockPref = $this->createMockPreference('member@example.com', 'weekly', false);

        $this->mockSubscriberRepo->shouldReceive('getConfirmedEmails')
            ->andReturn(['legacy@example.com']);
        $this->mockPreferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->andReturn(collect([$mockPref]));

        $this->mockMemberRepo->shouldReceive('findByEmails')->andReturn(collect([]));
        $this->mockPreferenceRepo->shouldReceive('findByEmails')->andReturn(collect([]));

        $result = $this->resolver->resolveForNewsletter($newsletter, $siteId);

        $this->assertContains('legacy@example.com', $result['valid']);
        $this->assertContains('member@example.com', $result['valid']);
    }

    private function createFreeNewsletter(): Newsletter
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->interval = 'weekly';
        $newsletter->shouldReceive('isPremium')->andReturn(false);
        return $newsletter;
    }

    public function test_paid_newsletter_tracks_all_skipped_reasons(): void
    {
        $newsletter = $this->createPaidNewsletter();
        $siteId = 1;

        // Setup multiple members with different failure reasons
        $noSubPref = $this->createMockPreference('nosub@example.com', 'weekly', false, 1);
        $expiredPref = $this->createMockPreference('expired@example.com', 'weekly', false, 2);
        $wrongAccessPref = $this->createMockPreference('wrongaccess@example.com', 'weekly', false, 3);

        $expiredSub = $this->createMockSubscription('expired', 'paid', false);
        $activeSubWrongAccess = $this->createMockSubscription('active', 'paid', true, false);

        $this->mockSubscriberRepo->shouldReceive('getConfirmedEmails')->andReturn([]);
        $this->mockPreferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->andReturn(collect([$noSubPref, $expiredPref, $wrongAccessPref]));

        // No subscription
        $this->mockSubscriptionRepo->shouldReceive('getActiveSubscriptionForMember')
            ->with(1, $siteId)
            ->andReturn(null);

        // Expired subscription
        $this->mockSubscriptionRepo->shouldReceive('getActiveSubscriptionForMember')
            ->with(2, $siteId)
            ->andReturn($expiredSub);

        // Wrong access level
        $this->mockSubscriptionRepo->shouldReceive('getActiveSubscriptionForMember')
            ->with(3, $siteId)
            ->andReturn($activeSubWrongAccess);

        $this->mockMemberRepo->shouldReceive('findByEmails')
            ->with(Mockery::any(), $siteId)
            ->andReturn(collect([$noSubPref->member, $expiredPref->member, $wrongAccessPref->member]));
        $this->mockPreferenceRepo->shouldReceive('findByEmails')
            ->with(Mockery::any(), $siteId)
            ->andReturn(collect([$noSubPref, $expiredPref, $wrongAccessPref]));

        $result = $this->resolver->resolveForNewsletter($newsletter, $siteId);

        $this->assertEmpty($result['valid']);
        $this->assertEmpty($result['skipped']); // Skipped tracking happens in eligibility phase (logged, not returned)
    }

    public function test_multiple_recipients_mixed_subscription_states(): void
    {
        $newsletter = $this->createPaidNewsletter();
        $siteId = 1;

        $activePref = $this->createMockPreference('active@example.com', 'weekly', false, 1);
        $gracePref = $this->createMockPreference('grace@example.com', 'weekly', false, 2);
        $retryPref = $this->createMockPreference('retry@example.com', 'weekly', false, 3);
        $expiredPref = $this->createMockPreference('expired@example.com', 'weekly', false, 4);

        $activeSub = $this->createMockSubscription('active', 'paid', true);
        $graceSub = $this->createMockSubscription('grace_period', 'paid', true);
        $retrySub = $this->createMockSubscription('retrying', 'paid', true);
        $expiredSub = $this->createMockSubscription('expired', 'paid', false);

        $this->mockSubscriberRepo->shouldReceive('getConfirmedEmails')->andReturn([]);
        $this->mockPreferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->andReturn(collect([$activePref, $gracePref, $retryPref, $expiredPref]));

        $this->mockSubscriptionRepo->shouldReceive('getActiveSubscriptionForMember')
            ->with(1, $siteId)->andReturn($activeSub);
        $this->mockSubscriptionRepo->shouldReceive('getActiveSubscriptionForMember')
            ->with(2, $siteId)->andReturn($graceSub);
        $this->mockSubscriptionRepo->shouldReceive('getActiveSubscriptionForMember')
            ->with(3, $siteId)->andReturn($retrySub);
        $this->mockSubscriptionRepo->shouldReceive('getActiveSubscriptionForMember')
            ->with(4, $siteId)->andReturn($expiredSub);

        $this->mockMemberRepo->shouldReceive('findByEmails')
            ->with(Mockery::any(), $siteId)
            ->andReturn(collect([$activePref->member, $gracePref->member, $retryPref->member, $expiredPref->member]));
        $this->mockPreferenceRepo->shouldReceive('findByEmails')
            ->with(Mockery::any(), $siteId)
            ->andReturn(collect([$activePref, $gracePref, $retryPref, $expiredPref]));

        $result = $this->resolver->resolveForNewsletter($newsletter, $siteId);

        $this->assertCount(3, $result['valid']);
        $this->assertContains('active@example.com', $result['valid']);
        $this->assertContains('grace@example.com', $result['valid']);
        $this->assertContains('retry@example.com', $result['valid']);
        $this->assertNotContains('expired@example.com', $result['valid']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockSubscriberRepo = Mockery::mock(SubscriberRepository::class);
        $this->mockPreferenceRepo = Mockery::mock(MemberSubscriptionPreferenceRepository::class);
        $this->mockMemberRepo = Mockery::mock(MemberRepository::class);
        $this->mockSubscriptionRepo = Mockery::mock(SubscriptionRepository::class);

        $this->resolver = new NewsletterRecipientResolver(
            $this->mockSubscriberRepo,
            $this->mockPreferenceRepo,
            $this->mockMemberRepo,
            $this->mockSubscriptionRepo
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}