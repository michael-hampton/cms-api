<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\MemberSubscriptionPreference;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Models\NewsletterSendRecipient;
use App\Models\Subscriber;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendRecipientRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Services\Cms\BlockParserService;
use App\Services\Members\EmailService;
use App\Services\Newsletter\NewsletterPageBuilderService;
use App\Services\Newsletter\NewsletterSendService;
use App\Services\Subscriptions\MemberSubscriptionService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class NewsletterSendServiceTest extends FunctionalTestCase
{
    private NewsletterSendService $service;
    private $emailService;
    private $subscriberRepo;
    private $newsletterRepo;
    private $sendRepo;
    private $preferenceRepo;
    private $pageBuilderService;
    private $parser;

    private $memberRepository;
    private $subscriptionService;
    private $newsletterSendRecipientRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = Mockery::mock(BlockParserService::class);
        $this->emailService = Mockery::mock(EmailService::class);
        $this->subscriberRepo = Mockery::mock(SubscriberRepository::class);
        $this->newsletterRepo = Mockery::mock(NewsletterRepository::class);
        $this->sendRepo = Mockery::mock(NewsletterSendRepository::class);
        $this->preferenceRepo = Mockery::mock(MemberSubscriptionPreferenceRepository::class);
        $this->pageBuilderService = Mockery::mock(NewsletterPageBuilderService::class);
        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->subscriptionService = Mockery::mock(MemberSubscriptionService::class);
        $this->newsletterSendRecipientRepository = Mockery::mock(NewsletterSendRecipientRepository::class);

        $this->service = new NewsletterSendService(
            $this->parser,
            $this->emailService,
            $this->subscriberRepo,
            $this->newsletterRepo,
            $this->sendRepo,
            $this->preferenceRepo,
            $this->pageBuilderService,
            $this->memberRepository,
            $this->subscriptionService,
            $this->newsletterSendRecipientRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_newsletter_to_legacy_subscribers(): void
    {
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'heading', 'level' => 1, 'content' => 'Hello']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $subscriberEmails = ['legacy@example.com'];

        $this->setupBasicSendExpectations($subscriberEmails);

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('legacy@example.com')
            ->once()
            ->andReturn(null);

        $this->setupUnsubscribeTokenMocks('legacy@example.com', 'unsub-token-1');

        $this->emailService->shouldReceive('send')
            ->with('legacy@example.com', 'Test Newsletter', Mockery::type('string'))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')
            ->with(1, Mockery::on(fn($data) => isset($data['last_sent'])))
            ->once();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
        $this->assertEquals(0, $result['failed']);
    }

    public function test_send_newsletter_to_members_with_preferences(): void
    {
        $member = $this->createMockMember(['email' => 'member@example.com']);
        $preference = $this->createMockPreference([
            'newsletter_frequency' => 'weekly',
            'member' => $member
        ]);

        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Member Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $this->setupBasicSendExpectations(['member@example.com'], [$preference]);

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('member@example.com')
            ->once()
            ->andReturn($member);

        $this->setupUnsubscribeTokenMocks('member@example.com', 'member-token');

        $this->emailService->shouldReceive('send')
            ->with('member@example.com', 'Member Newsletter', Mockery::type('string'))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')->once();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
    }

    public function test_send_newsletter_filters_by_frequency_preference(): void
    {
        $weeklyMember = $this->createMockMember(['email' => 'weekly@example.com']);
        $dailyMember = $this->createMockMember(['email' => 'daily@example.com']);

        $weeklyPreference = $this->createMockPreference([
            'newsletter_frequency' => 'weekly',
            'member' => $weeklyMember
        ]);

        $dailyPreference = $this->createMockPreference([
            'newsletter_frequency' => 'daily',
            'member' => $dailyMember
        ]);

        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Weekly Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Weekly content']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $this->setupBasicSendExpectations(['weekly@example.com'], [$weeklyPreference, $dailyPreference]);

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('weekly@example.com')
            ->once()
            ->andReturn($weeklyMember);

        $this->setupUnsubscribeTokenMocks('weekly@example.com', 'weekly-token');

        $this->emailService->shouldReceive('send')
            ->with('weekly@example.com', 'Weekly Newsletter', Mockery::type('string'))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')->once();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
    }

    public function test_send_newsletter_includes_unsubscribe_link(): void
    {
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $this->setupBasicSendExpectations(['test@example.com']);

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn(null);

        $this->setupUnsubscribeTokenMocks('test@example.com', 'unique-unsub-token');

        $this->emailService->shouldReceive('send')
            ->with('test@example.com', 'Test Newsletter', Mockery::on(function ($html) {
                return str_contains($html, 'unique-unsub-token');
            }))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')->once();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
    }

    public function test_send_newsletter_deduplicates_subscribers(): void
    {
        $email = 'duplicate@example.com';
        $member = $this->createMockMember(['email' => $email]);
        $preference = $this->createMockPreference([
            'newsletter_frequency' => 'weekly',
            'member' => $member
        ]);

        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        // Both legacy subscriber and member preference have same email
        $this->setupBasicSendExpectations([$email], [$preference], [$email]);

        $this->memberRepository->shouldReceive('findByEmail')
            ->with($email)
            ->once()
            ->andReturn($member);

        $this->setupUnsubscribeTokenMocks($email, 'member-token');

        // Should only send once despite duplicate
        $this->emailService->shouldReceive('send')
            ->with($email, 'Test Newsletter', Mockery::type('string'))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')->once();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
    }

    public function test_send_newsletter_excludes_inactive_preferences(): void
    {
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn([]);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([]));

        $this->emailService->shouldReceive('send')->never();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('No confirmed subscribers', $result['error']);
    }

    public function test_send_newsletter_updates_last_sent(): void
    {
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $this->setupBasicSendExpectations(['test@example.com']);

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn(null);

        $this->setupUnsubscribeTokenMocks('test@example.com', 'unsub-1');

        $this->emailService->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return isset($data['last_sent']) && !empty($data['last_sent']);
            }))
            ->once();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
    }

    public function test_send_newsletter_records_send_history(): void
    {
        // Arrange
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'content_type' => Newsletter::CONTENT_TYPE_MANUAL
        ]);

        $this->setCreateExpectations();
        $this->setRecipientExpectations(['test@example.com']);

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn(['test@example.com']);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([]));

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn(null);

        $this->setupUnsubscribeTokenMocks('test@example.com', 'unsub-1');

        $this->emailService->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')->once();

        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return str_contains($data['html_snapshot'], 'Content');
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
    }

    public function test_send_newsletter_handles_send_failures(): void
    {
        // Arrange
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'content_type' => Newsletter::CONTENT_TYPE_MANUAL
        ]);

        $this->setCreateExpectations();
        $this->setRecipientExpectations(['success@example.com', 'fail@example.com'], 1);

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn(['success@example.com', 'fail@example.com']);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([]));

        // Both are legacy subscribers
        $this->memberRepository->shouldReceive('findByEmail')
            ->with('success@example.com')
            ->once()
            ->andReturn(null);

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('fail@example.com')
            ->once()
            ->andReturn(null);

        $this->setupUnsubscribeTokenMocks('success@example.com', 'unsub-1');
        $this->setupUnsubscribeTokenMocks('fail@example.com', 'unsub-2');

        $this->emailService->shouldReceive('send')
            ->with('success@example.com', Mockery::any(), Mockery::any())
            ->once()
            ->andReturn(true);

        $this->emailService->shouldReceive('send')
            ->with('fail@example.com', Mockery::any(), Mockery::any())
            ->once()
            ->andThrow(new \Exception('Send failed'));

        $this->newsletterRepo->shouldReceive('update')->once();

        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return str_contains($data['html_snapshot'], 'Content');
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
        $this->assertEquals(1, $result['failed']);
    }

    public function test_send_newsletter_with_no_subscribers(): void
    {
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_DAILY,
        ]);

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn([]);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([]));

        $this->emailService->shouldReceive('send')->never();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('No confirmed subscribers', $result['error']);
    }

    public function test_fails_when_automated_newsletter_has_no_matching_pages(): void
    {
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Automated Newsletter',
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ], true);

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn(['test@example.com']);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([]));

        $this->pageBuilderService->shouldReceive('getPagesForNewsletter')
            ->with(Mockery::type(Newsletter::class), $this->siteId)
            ->once()
            ->andReturn(collect([]));

        $this->emailService->shouldReceive('send')->never();
        $this->newsletterRepo->shouldReceive('update')->never();
        $this->sendRepo->shouldReceive('create')->never();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('No pages match newsletter criteria', $result['error']);
    }

    public function test_send_automated_newsletter_with_pages(): void
    {
        $pages = collect([
            (object)['id' => 1, 'title' => 'Page 1', 'subtitle' => 'Subtitle 1', 'slug' => 'page-1'],
            (object)['id' => 2, 'title' => 'Page 2', 'subtitle' => 'Subtitle 2', 'slug' => 'page-2']
        ]);

        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Automated Newsletter',
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ], true);

        $this->setupBasicSendExpectations(['test@example.com']);

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn(null);

        $this->pageBuilderService->shouldReceive('getPagesForNewsletter')
            ->with(Mockery::type(Newsletter::class), $this->siteId)
            ->once()
            ->andReturn($pages);

        $this->pageBuilderService->shouldReceive('buildNewsletterHtml')
            ->with(Mockery::any(), Mockery::any(), null, false, 1)
            ->once()
            ->andReturn('<html>Newsletter content</html>');

        $this->setupUnsubscribeTokenMocks('test@example.com', 'unsub-1');

        $this->emailService->shouldReceive('send')
            ->with('test@example.com', 'Automated Newsletter', Mockery::type('string'))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')->once();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
        $this->assertEquals(2, $result['pages_included']);
    }

    public function test_send_due_newsletters(): void
    {
        $newsletter1 = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Newsletter 1',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content 1']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $newsletter2 = $this->createMockNewsletter([
            'id' => 2,
            'title' => 'Newsletter 2',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content 2']]),
            'interval' => Newsletter::INTERVAL_DAILY,
        ]);

        $mock = Mockery::mock(NewsletterSend::class)->makePartial();
        $mock->id = 1;

        $this->sendRepo->shouldReceive('create')
            ->twice()
            ->andReturn($mock);

        $this->sendRepo->shouldReceive('update')
            ->twice();

        $this->newsletterRepo->shouldReceive('getDueNewsletters')
            ->with($this->siteId)
            ->once()
            ->andReturn([$newsletter1, $newsletter2]);

        // Per-newsletter expectations
        for ($i = 0; $i < 2; $i++) {
            $this->setupSendRecipientExpectations(['test@example.com']);

            $this->subscriberRepo->shouldReceive('getConfirmedEmails')
                ->with($this->siteId)
                ->once()
                ->andReturn(['test@example.com']);

            $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
                ->with($this->siteId)
                ->once()
                ->andReturn(collect([]));

            $this->memberRepository->shouldReceive('findByEmail')
                ->with('test@example.com')
                ->once()
                ->andReturn(null);

            $this->setupUnsubscribeTokenMocks('test@example.com', 'unsub-token');

            $this->emailService->shouldReceive('send')
                ->once()
                ->andReturn(true);

            $this->newsletterRepo->shouldReceive('update')->once();
        }

        $results = $this->service->sendDueNewsletters($this->siteId);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);
    }

    public function test_manual_newsletter_renders_blocks_correctly(): void
    {
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Manual Newsletter',
            'content' => json_encode([
                ['type' => 'heading', 'level' => 1, 'content' => 'Newsletter Title'],
                ['type' => 'paragraph', 'content' => 'This is paragraph content.'],
                ['type' => 'button', 'content' => 'Click Me', 'url' => 'https://example.com'],
            ]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $this->setupBasicSendExpectations(['test@example.com']);

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn(null);

        $this->setupUnsubscribeTokenMocks('test@example.com', 'unsub-token');

        $this->emailService->shouldReceive('send')
            ->with('test@example.com', 'Manual Newsletter', Mockery::on(function ($html) {
                return str_contains($html, '<h1>Newsletter Title</h1>')
                    && str_contains($html, '<p>This is paragraph content.</p>')
                    && str_contains($html, 'Click Me');
            }))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')->once();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
    }

    public function test_send_newsletter_respects_communication_preferences(): void
    {
        $member1 = $this->createMockMember([
            'email' => 'opted-in@example.com',
            'communication_preferences' => ['newsletter' => true, 'marketing_emails' => true]
        ]);
        $member1->shouldReceive('getCommunicationPreference')
            ->with('newsletter', true)
            ->andReturn(true);
        $member1->shouldReceive('wantsMarketingEmails')
            ->andReturn(true);

        $member2 = $this->createMockMember([
            'email' => 'opted-out@example.com',
            'communication_preferences' => ['newsletter' => false, 'marketing_emails' => true]
        ]);
        $member2->shouldReceive('getCommunicationPreference')
            ->with('newsletter', true)
            ->andReturn(false);

        $member3 = $this->createMockMember([
            'email' => 'no-marketing@example.com',
            'communication_preferences' => ['newsletter' => true, 'marketing_emails' => false]
        ]);
        $member3->shouldReceive('getCommunicationPreference')
            ->with('newsletter', true)
            ->andReturn(true);
        $member3->shouldReceive('wantsMarketingEmails')
            ->andReturn(false);

        $pref1 = $this->createMockPreference(['newsletter_frequency' => 'weekly', 'member' => $member1]);
        $pref2 = $this->createMockPreference(['newsletter_frequency' => 'weekly', 'member' => $member2]);
        $pref3 = $this->createMockPreference(['newsletter_frequency' => 'weekly', 'member' => $member3]);

        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Weekly Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $this->setupBasicSendExpectations(['opted-in@example.com'], [$pref1, $pref2, $pref3]);

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('opted-in@example.com')
            ->once()
            ->andReturn($member1);

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('opted-out@example.com')
            ->once()
            ->andReturn($member2);

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('no-marketing@example.com')
            ->once()
            ->andReturn($member3);

        $this->setupUnsubscribeTokenMocks('opted-in@example.com', 'token-1');

        $this->emailService->shouldReceive('send')
            ->with('opted-in@example.com', 'Weekly Newsletter', Mockery::type('string'))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')->once();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
        $this->assertCount(2, $result['skipped']);
    }

    public function test_send_newsletter_to_legacy_subscribers_without_preferences(): void
    {
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'heading', 'level' => 1, 'content' => 'Hello']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $subscriberEmails = ['legacy1@example.com', 'legacy2@example.com'];

        $this->setupBasicSendExpectations($subscriberEmails);

        foreach ($subscriberEmails as $email) {
            $this->memberRepository->shouldReceive('findByEmail')
                ->with($email)
                ->once()
                ->andReturn(null);

            $this->setupUnsubscribeTokenMocks($email, 'token-' . $email);

            $this->emailService->shouldReceive('send')
                ->with($email, 'Test Newsletter', Mockery::type('string'))
                ->once()
                ->andReturn(true);
        }

        $this->newsletterRepo->shouldReceive('update')->once();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['recipients']);
    }


    // Helper methods

    private function createMockNewsletter(array $attributes, bool $isAutomated = false)
    {
        $defaults = [
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
        ];

        $data = array_merge($defaults, $attributes);

        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->shouldReceive('isAutomated')
            ->andReturn($isAutomated);

        foreach ($data as $key => $value) {
            $newsletter->$key = $value;
        }

        return $newsletter;
    }

    private function createMockMember(array $attributes): object
    {
        $defaults = [
            'id' => 1,
            'email' => 'member@example.com',
            'first_name' => 'Test',
            'last_name' => 'User'
        ];

        $data = array_merge($defaults, $attributes);

        $member = Mockery::mock(Member::class)->makePartial();;
        foreach ($data as $key => $value) {
            $member->$key = $value;
        }

        return $member;
    }

    private function createMockPreference(array $attributes): object
    {
        $defaults = [
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => 'token-' . uniqid(),
            'member' => $this->createMockMember([])
        ];

        $data = array_merge($defaults, $attributes);

        $preference = Mockery::mock(MemberSubscriptionPreference::class)->makePartial();;
        foreach ($data as $key => $value) {
            $preference->$key = $value;
        }

        return $preference;
    }

    private function setupUnsubscribeTokenMocks(string $email, string $token): void
    {
        // Create a proper mock of MemberSubscriptionPreference
        $preference = Mockery::mock(MemberSubscriptionPreference::class)->makePartial();
        $preference->unsubscribe_token = $token;

        // Mock preference lookup
        $this->preferenceRepo->shouldReceive('findByMemberEmail')
            ->with($email, $this->siteId)
            ->andReturn($preference);

        // Create a proper mock of Subscriber for fallback
        $subscriber = Mockery::mock(Subscriber::class)->makePartial();
        $subscriber->unsubscribe_token = $token;

        // Mock subscriber lookup as fallback
        $this->subscriberRepo->shouldReceive('findByEmail')
            ->with($email, $this->siteId)
            ->andReturn($subscriber);
    }

    public function test_send_newsletter_captures_recipients_list(): void
    {
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'heading', 'level' => 1, 'content' => 'Hello']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $subscriberEmails = ['user1@example.com', 'user2@example.com', 'user3@example.com'];

        $this->setupBasicSendExpectations($subscriberEmails);

        foreach ($subscriberEmails as $email) {
            $this->memberRepository->shouldReceive('findByEmail')
                ->with($email)
                ->once()
                ->andReturn(null);

            $this->setupUnsubscribeTokenMocks($email, 'unsub-token-' . $email);

            $this->emailService->shouldReceive('send')
                ->with($email, 'Test Newsletter', Mockery::type('string'))
                ->once()
                ->andReturn(true);
        }

        $this->newsletterRepo->shouldReceive('update')
            ->with(1, Mockery::on(fn($data) => isset($data['last_sent'])))
            ->once();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['recipients']);
    }

    public function test_send_newsletter_captures_html_snapshot(): void
    {
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'heading', 'level' => 1, 'content' => 'Hello']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $subscriberEmails = ['test@example.com'];

        $this->setupBasicSendExpectations($subscriberEmails);

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn(null);

        $this->setupUnsubscribeTokenMocks('test@example.com', 'unsub-token');

        $this->emailService->shouldReceive('send')
            ->with('test@example.com', 'Test Newsletter', Mockery::type('string'))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')
            ->with(1, Mockery::type('array'))
            ->once();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
    }

    public function test_send_newsletter_excludes_failed_recipients_from_list(): void
    {
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'heading', 'level' => 1, 'content' => 'Hello']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $subscriberEmails = ['success@example.com', 'fail@example.com'];

        // Set up basic expectations - note we pass 1 as the forceSentCount
        $this->setupBasicSendExpectations($subscriberEmails, [], null, 1);

        // Set up member lookups
        foreach ($subscriberEmails as $email) {
            $this->memberRepository->shouldReceive('findByEmail')
                ->with($email)
                ->once()
                ->andReturn(null);

            $this->setupUnsubscribeTokenMocks($email, 'unsub-token-' . $email);
        }

        // Success email sends successfully
        $this->emailService->shouldReceive('send')
            ->with('success@example.com', 'Test Newsletter', Mockery::type('string'))
            ->once()
            ->andReturn(true);

        // Fail email throws exception
        $this->emailService->shouldReceive('send')
            ->with('fail@example.com', 'Test Newsletter', Mockery::type('string'))
            ->once()
            ->andThrow(new \Exception('Email service error'));

        $this->newsletterRepo->shouldReceive('update')
            ->with(1, Mockery::type('array'))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']); // Only successful sends
        $this->assertEquals(1, $result['failed']); // One failed
    }

    public function test_automated_newsletter_captures_page_snapshot(): void
    {
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Automated Newsletter',
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ], true);

        $pages = collect([
            (object)['id' => 1, 'title' => 'Page 1', 'subtitle' => 'Sub 1', 'slug' => 'page-1'],
        ]);

        $this->setupBasicSendExpectations(['test@example.com']);

        $this->pageBuilderService->shouldReceive('getPagesForNewsletter')
            ->with($newsletter, $this->siteId)
            ->once()
            ->andReturn($pages);

        $this->pageBuilderService->shouldReceive('buildNewsletterHtml')
            ->once()
            ->andReturn('<html>Newsletter content</html>');

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn(null);

        $this->setupUnsubscribeTokenMocks('test@example.com', 'unsub-token');

        $this->emailService->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')->once();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
    }

    public function test_personalized_html_replaces_placeholders(): void
    {
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ], true);

        $pages = collect([
            (object)['id' => 1, 'title' => 'Page 1', 'subtitle' => 'Sub 1', 'slug' => 'page-1'],
        ]);

        $this->setupBasicSendExpectations(['user@example.com']);

        $this->pageBuilderService->shouldReceive('getPagesForNewsletter')
            ->once()
            ->andReturn($pages);

        $this->pageBuilderService->shouldReceive('buildNewsletterHtml')
            ->once()
            ->andReturn('<html>Link: /track?send_id={{SEND_ID}}&e={{TRACKING_EMAIL}}</html>');

        $this->memberRepository->shouldReceive('findByEmail')
            ->once()
            ->andReturn(null);

        $this->setupUnsubscribeTokenMocks('user@example.com', 'token');

        $this->emailService->shouldReceive('send')
            ->with('user@example.com', 'Test Newsletter', Mockery::on(function ($html) {
                return !str_contains($html, '{{SEND_ID}}')
                    && !str_contains($html, '{{TRACKING_EMAIL}}')
                    && str_contains($html, 'send_id=')
                    && preg_match('/[a-f0-9]{32}/', $html);
            }))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')->once();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
    }

    public function test_send_newsletter_includes_tracking_links(): void
    {
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Automated Newsletter',
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ], true);

        $subscriberEmails = ['test@example.com'];

        $mockPages = collect([
            (object)['id' => 1, 'title' => 'Page 1', 'subtitle' => 'Subtitle 1', 'slug' => 'page-1'],
        ]);

        $this->setupBasicSendExpectations($subscriberEmails);

        $this->pageBuilderService->shouldReceive('getPagesForNewsletter')
            ->with($newsletter, $this->siteId)
            ->once()
            ->andReturn($mockPages);

        $this->pageBuilderService->shouldReceive('buildNewsletterHtml')
            ->with(
                $newsletter,
                Mockery::type(Collection::class),
                null,
                false,
                Mockery::type('int')
            )
            ->once()
            ->andReturn('<html>Newsletter with {{SEND_ID}} and {{TRACKING_EMAIL}}</html>');

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn(null);

        $this->setupUnsubscribeTokenMocks('test@example.com', 'unsub-token');

        $this->emailService->shouldReceive('send')
            ->with('test@example.com', 'Automated Newsletter', Mockery::on(function ($html) {
                return !str_contains($html, '{{SEND_ID}}')
                    && !str_contains($html, '{{TRACKING_EMAIL}}')
                    && preg_match('/\d+/', $html)
                    && preg_match('/[a-f0-9]{32}/', $html);
            }))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')
            ->with(1, Mockery::type('array'))
            ->once();

        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['send_id']);
    }

    private function createMockAutomatedNewsletter(array $attributes = []): Newsletter
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->shouldReceive('isAutomated')->andReturn(true);

        foreach ($attributes as $key => $value) {
            $newsletter->$key = $value;
        }

        return $newsletter;
    }

    private function setCreateExpectations(): void
    {

        $mock = Mockery::mock(NewsletterSend::class)->makePartial();
        $mock->id = 1;

        $this->sendRepo->shouldReceive('create')
            ->once()
            ->andReturn($mock);
    }

    private function setRecipientExpectations(array $subscriberEmails, ?int $forceSentCount = null): void
    {
        $mapped = array_map(function ($email) {
            $mock = Mockery::mock(NewsletterSendRecipient::class)->makePartial();
            $mock->id = 1;
            $mock->email = $email;

            $mock->shouldReceive('update')->andReturn(true);
            $mock->shouldReceive('markAsSent')->andReturn(true);
            return $mock;
        }, $subscriberEmails);

        $this->newsletterSendRecipientRepository->shouldReceive('createRecipients')
            ->once()
            ->with(1, $subscriberEmails)
            ->andReturn($mapped);

        $this->newsletterSendRecipientRepository->shouldReceive('updateSendCounts')
            ->once();

        // Use the forced count if provided, otherwise default to the total count
        $sentCount = $forceSentCount ?? count($subscriberEmails);
        $failedCount = count($subscriberEmails) - $sentCount;

        $this->newsletterSendRecipientRepository->shouldReceive('getStatistics')
            ->once()
            ->with(1)
            ->andReturn([
                'sent' => $sentCount,
                'failed' => $failedCount,
                'pending' => 0
            ]);
    }

    /**
     * Set up basic send expectations including send record creation and recipient handling
     */
    private function setupBasicSendExpectations(
        array $subscriberEmails,
        array $memberPreferences = [],
        ?array $legacySubscribers = null, // Change to nullable
        ?int   $forceSentCount = null
    ): void
    {
        // Default legacy subscribers to same as subscriber emails if not provided
        $legacySubscribers = $legacySubscribers ?? $subscriberEmails;

        // Create send record
        $mock = Mockery::mock(NewsletterSend::class)->makePartial();
        $mock->id = 1;
        $this->sendRepo->shouldReceive('create')->once()->andReturn($mock);

        // Expect html_snapshot update
        $this->sendRepo->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(fn($data) => isset($data['html_snapshot'])));

        // Set up subscriber/preference expectations
        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn($legacySubscribers);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect($memberPreferences));

        // Set up recipient expectations
        $this->setupSendRecipientExpectations($subscriberEmails, $forceSentCount);
    }

    /**
     * Set up recipient creation and statistics expectations
     */
    private function setupSendRecipientExpectations(array $subscriberEmails, ?int $forceSentCount = null): void
    {
        $mapped = array_map(function ($email) {
            $mock = Mockery::mock(NewsletterSendRecipient::class)->makePartial();
            $mock->id = 1;
            $mock->email = $email;
            $mock->shouldReceive('update')->andReturn(true);
            $mock->shouldReceive('markAsSent')->andReturn(true);
            $mock->shouldReceive('markAsFailed')->andReturn(true);
            return $mock;
        }, $subscriberEmails);

        $this->newsletterSendRecipientRepository->shouldReceive('createRecipients')
            ->once()
            ->with(1, $subscriberEmails)
            ->andReturn($mapped);

        $this->newsletterSendRecipientRepository->shouldReceive('updateSendCounts')->once();

        $sentCount = $forceSentCount ?? count($subscriberEmails);
        $failedCount = count($subscriberEmails) - $sentCount;

        $this->newsletterSendRecipientRepository->shouldReceive('getStatistics')
            ->once()
            ->with(1)
            ->andReturn([
                'sent' => $sentCount,
                'failed' => $failedCount,
                'pending' => 0
            ]);
    }
}