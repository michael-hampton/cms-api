<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Models\Member;
use App\Models\MemberSubscriptionPreference;
use App\Models\Newsletter;
use App\Models\Subscriber;
use App\Repositories\MemberRepository;
use App\Repositories\MemberSubscriptionPreferenceRepository;
use App\Repositories\NewsletterRepository;
use App\Repositories\NewsletterSendRepository;
use App\Repositories\SubscriberRepository;
use App\Services\BlockParserService;
use App\Services\EmailService;
use App\Services\Newsletter\NewsletterPageBuilderService;
use App\Services\Newsletter\NewsletterSendService;
use App\Services\Subscriptions\MemberSubscriptionService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class NewsletterSendTest extends FunctionalTestCase
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

        $this->service = new NewsletterSendService(
            $this->parser,
            $this->emailService,
            $this->subscriberRepo,
            $this->newsletterRepo,
            $this->sendRepo,
            $this->preferenceRepo,
            $this->pageBuilderService,
            $this->memberRepository,
            $this->subscriptionService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_newsletter_to_legacy_subscribers(): void
    {
        // Arrange
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'heading', 'level' => 1, 'content' => 'Hello']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $subscriberEmails = ['legacy@example.com'];

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn($subscriberEmails);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([]));

        // Legacy subscriber has no member account
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
            ->with(1, Mockery::on(function ($data) {
                return isset($data['last_sent']);
            }))
            ->once();

        $this->sendRepo->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return $data['newsletter_id'] === 1 && $data['recipient_count'] === 1;
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
        $this->assertEmpty($result['failed']);
    }

    public function test_send_newsletter_to_members_with_preferences(): void
    {
        // Arrange
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
            'content_type' => Newsletter::CONTENT_TYPE_MANUAL
        ]);

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn([]);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([$preference]));

        // Mock member repository to return member with preferences
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
        $this->sendRepo->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return $data['newsletter_id'] === 1 && $data['recipient_count'] === 1;
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
    }

    public function test_send_newsletter_filters_by_frequency_preference(): void
    {
        // Arrange
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
            'content_type' => Newsletter::CONTENT_TYPE_MANUAL
        ]);

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn([]);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([$weeklyPreference, $dailyPreference]));

        // Mock member repository for weekly member
        $this->memberRepository->shouldReceive('findByEmail')
            ->with('weekly@example.com')
            ->once()
            ->andReturn($weeklyMember);

        $this->setupUnsubscribeTokenMocks('weekly@example.com', 'weekly-token');

        // Should only send to weekly member
        $this->emailService->shouldReceive('send')
            ->with('weekly@example.com', 'Weekly Newsletter', Mockery::type('string'))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')->once();
        $this->sendRepo->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return $data['recipient_count'] === 1;
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
    }

    public function test_send_newsletter_includes_unsubscribe_link(): void
    {
        // Arrange
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'content_type' => Newsletter::CONTENT_TYPE_MANUAL
        ]);

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn(['test@example.com']);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([]));

        // Legacy subscriber has no member account
        $this->memberRepository->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn(null);

        $this->setupUnsubscribeTokenMocks('test@example.com', 'unique-unsub-token');

        $this->emailService->shouldReceive('send')
            ->with('test@example.com', 'Test Newsletter', Mockery::on(function ($html) {
                return strpos($html, 'unique-unsub-token') !== false;
            }))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')->once();
        $this->sendRepo->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return $data['newsletter_id'] === 1 && $data['recipient_count'] === 1;
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
    }

    public function test_send_newsletter_deduplicates_subscribers(): void
    {
        // Arrange
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
            'content_type' => Newsletter::CONTENT_TYPE_MANUAL
        ]);

        // Both legacy and member have same email
        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn([$email]);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([$preference]));

        // Member lookup returns the member
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
        $this->sendRepo->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return $data['recipient_count'] === 1;
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
    }

    public function test_send_newsletter_excludes_inactive_preferences(): void
    {
        // Arrange
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'content_type' => Newsletter::CONTENT_TYPE_MANUAL
        ]);

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn([]);

        // getActiveSubscribersForSite should not return inactive preferences
        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([]));

        $this->emailService->shouldReceive('send')->never();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('No confirmed subscribers', $result['error']);
    }

    public function test_send_newsletter_updates_last_sent(): void
    {
        // Arrange
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'content_type' => Newsletter::CONTENT_TYPE_MANUAL
        ]);

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn(['test@example.com']);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([]));

        // Legacy subscriber
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

        $this->sendRepo->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return $data['newsletter_id'] === 1 && $data['recipient_count'] === 1;
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
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

        $this->sendRepo->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return $data['newsletter_id'] === 1
                    && $data['recipient_count'] === 1
                    && isset($data['sent_at']);
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
        $this->sendRepo->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return $data['recipient_count'] === 1;
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
        $this->assertCount(1, $result['failed']);
        $this->assertEquals('fail@example.com', $result['failed'][0]['email']);
        $this->assertEquals('Send failed', $result['failed'][0]['error']);
    }

    public function test_send_newsletter_with_no_subscribers(): void
    {
        // Arrange
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_DAILY,
            'content_type' => Newsletter::CONTENT_TYPE_MANUAL
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

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('No confirmed subscribers', $result['error']);
    }

    public function test_fails_when_automated_newsletter_has_no_matching_pages(): void
    {
        // Arrange
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Automated Newsletter',
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ], true); // automated

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

        // Should NOT update newsletter or create send record when no pages match
        $this->newsletterRepo->shouldReceive('update')->never();
        $this->sendRepo->shouldReceive('create')->never();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('No pages match newsletter criteria', $result['error']);
    }

    public function test_send_automated_newsletter_with_pages(): void
    {
        // Arrange
        $pages = collect([
            (object)['id' => 1, 'title' => 'Page 1'],
            (object)['id' => 2, 'title' => 'Page 2']
        ]);

        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Automated Newsletter',
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ], true); // automated

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

        $this->pageBuilderService->shouldReceive('getPagesForNewsletter')
            ->with(Mockery::type(Newsletter::class), $this->siteId)
            ->once()
            ->andReturn($pages);

        $this->pageBuilderService->shouldReceive('buildNewsletterHtml')
            ->with(Mockery::type(Newsletter::class), $pages)
            ->once()
            ->andReturn('<html>Newsletter content</html>');

        $this->setupUnsubscribeTokenMocks('test@example.com', 'unsub-1');

        $this->emailService->shouldReceive('send')
            ->with('test@example.com', 'Automated Newsletter', Mockery::type('string'))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')->once();
        $this->sendRepo->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return $data['newsletter_id'] === 1 && $data['recipient_count'] === 1;
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
        $this->assertEquals(2, $result['pages_included']);
    }

    public function test_send_due_newsletters(): void
    {
        // Arrange
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

        $this->newsletterRepo->shouldReceive('getDueNewsletters')
            ->with($this->siteId)
            ->once()
            ->andReturn([$newsletter1, $newsletter2]);

        // Setup mocks for each newsletter send
        foreach ([$newsletter1, $newsletter2] as $newsletter) {
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
            $this->sendRepo->shouldReceive('create')
                ->with(Mockery::on(function ($data) {
                    return $data['recipient_count'] === 1;
                }))
                ->once();
        }

        // Act
        $results = $this->service->sendDueNewsletters($this->siteId);

        // Assert
        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);
    }

    public function test_manual_newsletter_renders_blocks_correctly(): void
    {
        // Arrange
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
            ->with('test@example.com', 'Manual Newsletter', Mockery::on(function ($html) {
                // Verify HTML contains rendered blocks
                return strpos($html, '<h1>Newsletter Title</h1>') !== false
                    && strpos($html, '<p>This is paragraph content.</p>') !== false
                    && strpos($html, 'Click Me') !== false;
            }))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')->once();
        $this->sendRepo->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return $data['newsletter_id'] === 1 && $data['recipient_count'] === 1;
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
    }

    public function test_send_newsletter_respects_communication_preferences(): void
    {
        // Member 1: Has newsletter enabled
        $member1 = $this->createMockMember([
            'email' => 'opted-in@example.com',
            'communication_preferences' => ['newsletter' => true, 'marketing_emails' => true]
        ]);

        // Member 2: Has newsletter disabled
        $member2 = $this->createMockMember([
            'email' => 'opted-out@example.com',
            'communication_preferences' => ['newsletter' => false, 'marketing_emails' => true]
        ]);

        // Member 3: Has marketing emails disabled
        $member3 = $this->createMockMember([
            'email' => 'no-marketing@example.com',
            'communication_preferences' => ['newsletter' => true, 'marketing_emails' => false]
        ]);

        $pref1 = $this->createMockPreference([
            'newsletter_frequency' => 'weekly',
            'member' => $member1
        ]);

        $pref2 = $this->createMockPreference([
            'newsletter_frequency' => 'weekly',
            'member' => $member2
        ]);

        $pref3 = $this->createMockPreference([
            'newsletter_frequency' => 'weekly',
            'member' => $member3
        ]);

        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Weekly Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'content_type' => Newsletter::CONTENT_TYPE_MANUAL
        ]);

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn([]);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([$pref1, $pref2, $pref3]));

        // Mock member repository lookups
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

        // Should only send to member1
        $this->emailService->shouldReceive('send')
            ->with('opted-in@example.com', 'Weekly Newsletter', Mockery::type('string'))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')->once();
        $this->sendRepo->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return $data['recipient_count'] === 1;
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
    }

    public function test_send_newsletter_to_legacy_subscribers_without_preferences(): void
    {
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'heading', 'level' => 1, 'content' => 'Hello']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $subscriberEmails = ['legacy@example.com'];

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn($subscriberEmails);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([]));

        // Legacy subscriber has no member account
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
            ->with(1, Mockery::on(function ($data) {
                return isset($data['last_sent']);
            }))
            ->once();

        $this->sendRepo->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return $data['newsletter_id'] === 1 && $data['recipient_count'] === 1;
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
        $this->assertEmpty($result['failed']);
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

        $preference = Mockery::mock('stdClass');
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
}