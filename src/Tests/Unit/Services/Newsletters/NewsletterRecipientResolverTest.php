<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Models\Member;
use App\Models\Newsletter;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Services\Newsletter\NewsletterRecipientResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

class NewsletterRecipientResolverTest extends TestCase
{
    private NewsletterRecipientResolver $resolver;
    private $mockSubscriberRepository;
    private $mockPreferenceRepository;
    private $mockMemberRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockSubscriberRepository = Mockery::mock(SubscriberRepository::class);
        $this->mockPreferenceRepository = Mockery::mock(MemberSubscriptionPreferenceRepository::class);
        $this->mockMemberRepository = Mockery::mock(MemberRepository::class);

        $this->resolver = new NewsletterRecipientResolver(
            $this->mockSubscriberRepository,
            $this->mockPreferenceRepository,
            $this->mockMemberRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testResolveForNewsletterCombinesAllSources()
    {
        $newsletter = $this->createMockNewsletter('weekly');
        $siteId = 1;

        $legacyEmails = ['legacy1@example.com', 'legacy2@example.com'];

        $mockPref1 = $this->createMockPreference('member1@example.com', 'weekly', false);
        $mockPref2 = $this->createMockPreference('member2@example.com', 'weekly', false);

        $this->mockSubscriberRepository->shouldReceive('getConfirmedEmails')
            ->once()
            ->with($siteId)
            ->andReturn($legacyEmails);

        $this->mockPreferenceRepository->shouldReceive('getActiveSubscribersForSite')
            ->once()
            ->with($siteId)
            ->andReturn(collect([$mockPref1, $mockPref2]));

        $result = $this->resolver->resolveForNewsletter($newsletter, $siteId);

        $this->assertCount(4, $result);
        $this->assertContains('legacy1@example.com', $result);
        $this->assertContains('legacy2@example.com', $result);
        $this->assertContains('member1@example.com', $result);
        $this->assertContains('member2@example.com', $result);
    }

    public function testResolveForNewsletterFiltersByFrequency()
    {
        $newsletter = $this->createMockNewsletter('weekly');
        $siteId = 1;

        $mockPref1 = $this->createMockPreference('weekly@example.com', 'weekly', false);
        $mockPref2 = $this->createMockPreference('monthly@example.com', 'monthly', false);

        $this->mockSubscriberRepository->shouldReceive('getConfirmedEmails')
            ->andReturn([]);

        $this->mockPreferenceRepository->shouldReceive('getActiveSubscribersForSite')
            ->andReturn(collect([$mockPref1, $mockPref2]));

        $result = $this->resolver->resolveForNewsletter($newsletter, $siteId);

        $this->assertCount(1, $result);
        $this->assertContains('weekly@example.com', $result);
        $this->assertNotContains('monthly@example.com', $result);
    }

    public function testResolveForNewsletterExcludesOptedOut()
    {
        $newsletter = $this->createMockNewsletter('weekly');
        $siteId = 1;

        $mockPref1 = $this->createMockPreference('active@example.com', 'weekly', false);
        $mockPref2 = $this->createMockPreference('optedout@example.com', 'weekly', true);

        $this->mockSubscriberRepository->shouldReceive('getConfirmedEmails')
            ->andReturn([]);

        $this->mockPreferenceRepository->shouldReceive('getActiveSubscribersForSite')
            ->andReturn(collect([$mockPref1, $mockPref2]));

        $result = $this->resolver->resolveForNewsletter($newsletter, $siteId);

        $this->assertCount(1, $result);
        $this->assertContains('active@example.com', $result);
        $this->assertNotContains('optedout@example.com', $result);
    }

    public function testResolveForNewsletterDeduplicatesEmails()
    {
        $newsletter = $this->createMockNewsletter('weekly');
        $siteId = 1;

        $legacyEmails = ['duplicate@example.com', 'unique@example.com'];
        $mockPref = $this->createMockPreference('duplicate@example.com', 'weekly', false);

        $this->mockSubscriberRepository->shouldReceive('getConfirmedEmails')
            ->andReturn($legacyEmails);

        $this->mockPreferenceRepository->shouldReceive('getActiveSubscribersForSite')
            ->andReturn(collect([$mockPref]));

        $result = $this->resolver->resolveForNewsletter($newsletter, $siteId);

        $this->assertCount(2, $result);
        $this->assertEquals(2, count(array_unique($result)));
    }

    public function testFilterRecipientsAllowsValidEmails()
    {
        $newsletter = $this->createMockNewsletter('weekly');
        $siteId = 1;
        $emails = ['valid@example.com'];

        $mockMember = $this->createMockMember('valid@example.com', true, true);
        $mockPref = $this->createMockPreference('valid@example.com', 'weekly', false);

        $this->mockMemberRepository->shouldReceive('findByEmails')
            ->once()
            ->with($emails, $siteId)
            ->andReturn(collect([$mockMember]));

        $this->mockPreferenceRepository->shouldReceive('findByEmails')
            ->once()
            ->with($emails, $siteId)
            ->andReturn(collect([$mockPref]));

        $result = $this->resolver->filterRecipients($emails, $newsletter, $siteId);

        $this->assertCount(1, $result['valid']);
        $this->assertEmpty($result['skipped']);
        $this->assertContains('valid@example.com', $result['valid']);
    }

    public function testFilterRecipientsSkipsNewsletterDisabled()
    {
        $newsletter = $this->createMockNewsletter('weekly');
        $siteId = 1;
        $emails = ['disabled@example.com'];

        $mockMember = $this->createMockMember('disabled@example.com', false, true);

        $this->mockMemberRepository->shouldReceive('findByEmails')
            ->andReturn(collect([$mockMember]));

        $this->mockPreferenceRepository->shouldReceive('findByEmails')
            ->andReturn(collect([]));

        $result = $this->resolver->filterRecipients($emails, $newsletter, $siteId);

        $this->assertEmpty($result['valid']);
        $this->assertCount(1, $result['skipped']);
        $this->assertEquals('Newsletter preference disabled in global settings', $result['skipped']['disabled@example.com']);
    }

    public function testFilterRecipientsSkipsMarketingDisabled()
    {
        $newsletter = $this->createMockNewsletter('weekly');
        $siteId = 1;
        $emails = ['nomarketing@example.com'];

        $mockMember = $this->createMockMember('nomarketing@example.com', true, false);

        $this->mockMemberRepository->shouldReceive('findByEmails')
            ->andReturn(collect([$mockMember]));

        $this->mockPreferenceRepository->shouldReceive('findByEmails')
            ->andReturn(collect([]));

        $result = $this->resolver->filterRecipients($emails, $newsletter, $siteId);

        $this->assertEmpty($result['valid']);
        $this->assertCount(1, $result['skipped']);
        $this->assertEquals('Marketing emails disabled in global settings', $result['skipped']['nomarketing@example.com']);
    }

    public function testFilterRecipientsSkipsNewsletterOptOut()
    {
        $newsletter = $this->createMockNewsletter('weekly');
        $siteId = 1;
        $emails = ['optout@example.com'];

        $mockMember = $this->createMockMember('optout@example.com', true, true);
        $mockPref = $this->createMockPreference('optout@example.com', 'weekly', true);

        $this->mockMemberRepository->shouldReceive('findByEmails')
            ->andReturn(collect([$mockMember]));

        $this->mockPreferenceRepository->shouldReceive('findByEmails')
            ->andReturn(collect([$mockPref]));

        $result = $this->resolver->filterRecipients($emails, $newsletter, $siteId);

        $this->assertEmpty($result['valid']);
        $this->assertCount(1, $result['skipped']);
        $this->assertEquals('Opted out of this newsletter', $result['skipped']['optout@example.com']);
    }

    public function testFilterRecipientsAllowsNonMembers()
    {
        $newsletter = $this->createMockNewsletter('weekly');
        $siteId = 1;
        $emails = ['legacy@example.com'];

        $this->mockMemberRepository->shouldReceive('findByEmails')
            ->andReturn(collect([]));

        $this->mockPreferenceRepository->shouldReceive('findByEmails')
            ->andReturn(collect([]));

        $result = $this->resolver->filterRecipients($emails, $newsletter, $siteId);

        $this->assertCount(1, $result['valid']);
        $this->assertEmpty($result['skipped']);
    }

    public function testFilterRecipientsMixedResults()
    {
        $newsletter = $this->createMockNewsletter('weekly');
        $siteId = 1;
        $emails = ['valid@example.com', 'invalid@example.com', 'legacy@example.com'];

        $validMember = $this->createMockMember('valid@example.com', true, true);
        $invalidMember = $this->createMockMember('invalid@example.com', false, true);

        $this->mockMemberRepository->shouldReceive('findByEmails')
            ->andReturn(collect([$validMember, $invalidMember]));

        $this->mockPreferenceRepository->shouldReceive('findByEmails')
            ->andReturn(collect([]));

        $result = $this->resolver->filterRecipients($emails, $newsletter, $siteId);

        $this->assertCount(2, $result['valid']);
        $this->assertContains('valid@example.com', $result['valid']);
        $this->assertContains('legacy@example.com', $result['valid']);
        $this->assertCount(1, $result['skipped']);
        $this->assertArrayHasKey('invalid@example.com', $result['skipped']);
    }

    private function createMockNewsletter(string $interval): Newsletter
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->interval = $interval;
        return $newsletter;
    }

    private function createMockPreference(string $email, string $frequency, bool $optOut)
    {
        $pref = Mockery::mock();
        $pref->newsletter_frequency = $frequency;
        $pref->newsletter_opt_out = $optOut;
        $pref->member = Mockery::mock();
        $pref->member->email = $email;
        return $pref;
    }

    private function createMockMember(string $email, bool $newsletterPref, bool $marketingPref)
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->email = $email;
        $member->shouldReceive('getCommunicationPreference')
            ->with('newsletter', true)
            ->andReturn($newsletterPref);
        $member->shouldReceive('wantsMarketingEmails')
            ->andReturn($marketingPref);
        return $member;
    }
}