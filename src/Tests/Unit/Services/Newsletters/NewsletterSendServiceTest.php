<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\MemberSubscriptionPreference;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Models\Subscriber;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Newsletters\NewsletterRepository;
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

        $this->setCreateExpectations();

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

        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return $data['recipient_count'] == 1
                    && !empty($data['recipients'])
                    && str_contains($data['html_snapshot'], 'Hello');
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

        $this->setCreateExpectations();

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
        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return $data['recipient_count'] == 1
                    && !empty($data['recipients'])
                    && str_contains($data['html_snapshot'], 'Content');
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

        $this->setCreateExpectations();

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

        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return $data['recipient_count'] == 1
                    && !empty($data['recipients'])
                    && str_contains($data['html_snapshot'], 'Weekly content');
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

        $this->setCreateExpectations();

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

        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return $data['recipient_count'] == 1
                    && !empty($data['recipients'])
                    && str_contains($data['html_snapshot'], 'Content');
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

        $this->setCreateExpectations();;

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

        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return $data['recipient_count'] == 1
                    && !empty($data['recipients'])
                    && str_contains($data['html_snapshot'], 'Content');
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

        $this->setCreateExpectations();

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

        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return $data['recipient_count'] == 1
                    && !empty($data['recipients'])
                    && str_contains($data['html_snapshot'], 'Content');
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

        $this->setCreateExpectations();

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
                return $data['recipient_count'] == 1
                    && !empty($data['recipients'])
                    && str_contains($data['html_snapshot'], 'Content');
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
                return $data['recipient_count'] == 1
                    && !empty($data['recipients'])
                    && str_contains($data['html_snapshot'], 'Content');
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

        $this->setCreateExpectations();

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
            ->with(Mockery::any(), Mockery::any(), null, false, 1)
            ->once()
            ->andReturn('<html>Newsletter content</html>');

        $this->setupUnsubscribeTokenMocks('test@example.com', 'unsub-1');

        $this->emailService->shouldReceive('send')
            ->with('test@example.com', 'Automated Newsletter', Mockery::type('string'))
            ->once()
            ->andReturn(true);

        $this->newsletterRepo->shouldReceive('update')->once();

        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return $data['recipient_count'] == 1
                    && !empty($data['recipients'])
                    && str_contains($data['html_snapshot'], 'Newsletter content');
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

        $mock = Mockery::mock(NewsletterSend::class)->makePartial();
        $mock->id = 1;

        $this->sendRepo->shouldReceive('create')
            ->twice()
            ->andReturn($mock, $mock);

        $this->newsletterRepo->shouldReceive('getDueNewsletters')
            ->with($this->siteId)
            ->once()
            ->andReturn([$newsletter1, $newsletter2]);

        $expected = ['Content 1', 'Content 2'];

        $this->sendRepo->shouldReceive('update')
            ->twice()
            ->with(
                Mockery::type('int'),
                Mockery::on(function ($data) use (&$expected) {
                    $content = array_shift($expected);

                    return $data['recipient_count'] === 1
                        && !empty($data['recipients'])
                        && str_contains($data['html_snapshot'], $content);
                })
            );

// Per-newsletter expectations
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

        $this->setCreateExpectations();

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

        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return $data['recipient_count'] == 1
                    && !empty($data['recipients'])
                    && str_contains($data['html_snapshot'], 'Newsletter Title');
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

        $this->setCreateExpectations();

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn([]);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([$pref1, $pref2, $pref3]));

        $this->preferenceRepo->shouldReceive('findByMemberEmail')
            ->with('opted-in@example.com', 1)
            ->once()
            ->andReturn($pref1);

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

        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return $data['recipient_count'] == 1
                    && !empty($data['recipients'])
                    && str_contains($data['html_snapshot'], 'Content');
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

        $this->setCreateExpectations();

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

        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return $data['recipient_count'] == 1
                    && !empty($data['recipients'])
                    && str_contains($data['html_snapshot'], 'Hello');
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
        // Arrange
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'heading', 'level' => 1, 'content' => 'Hello']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $subscriberEmails = ['user1@example.com', 'user2@example.com', 'user3@example.com'];

        $this->setCreateExpectations();

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn($subscriberEmails);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([]));

        // Setup member mocks
        foreach ($subscriberEmails as $email) {
            $this->memberRepository->shouldReceive('findByEmail')
                ->with($email)
                ->once()
                ->andReturn(null);

            $this->setupUnsubscribeTokenMocks($email, 'unsub-token-' . $email);
        }

        // Email service should be called for each subscriber
        foreach ($subscriberEmails as $email) {
            $this->emailService->shouldReceive('send')
                ->with($email, 'Test Newsletter', Mockery::type('string'))
                ->once();
        }

        // Newsletter update
        $this->newsletterRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return isset($data['last_sent']);
            }))
            ->once();

        // Send repository should capture recipients
        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return $data['recipient_count'] == 3
                    && !empty($data['recipients'])
                    && str_contains($data['html_snapshot'], 'Hello');
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['recipients']);
    }

    public function test_send_newsletter_captures_html_snapshot(): void
    {
        // Arrange
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'heading', 'level' => 1, 'content' => 'Hello']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $subscriberEmails = ['test@example.com'];

        $this->setCreateExpectations();

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn($subscriberEmails);

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
            ->with('test@example.com', 'Test Newsletter', Mockery::type('string'))
            ->once();

        $this->newsletterRepo->shouldReceive('update')
            ->with(1, Mockery::type('array'))
            ->once();

        // Capture the HTML snapshot
        $capturedHtml = null;

        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) use (&$capturedHtml) {
                $capturedHtml = $data['html_snapshot'];

                return $data['recipient_count'] == 1
                    && !empty($data['recipients'])
                    && str_contains($data['html_snapshot'], 'Hello');
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertNotNull($capturedHtml);
        $this->assertStringContainsString('Hello', $capturedHtml);
    }

    public function test_send_newsletter_excludes_failed_recipients_from_list(): void
    {
        // Arrange
        $newsletter = $this->createMockNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'heading', 'level' => 1, 'content' => 'Hello']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $subscriberEmails = ['success@example.com', 'fail@example.com'];

        $this->setCreateExpectations();

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn($subscriberEmails);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([]));

        foreach ($subscriberEmails as $email) {
            $this->memberRepository->shouldReceive('findByEmail')
                ->with($email)
                ->once()
                ->andReturn(null);

            $this->setupUnsubscribeTokenMocks($email, 'unsub-token-' . $email);
        }

        // First email succeeds
        $this->emailService->shouldReceive('send')
            ->with('success@example.com', 'Test Newsletter', Mockery::type('string'))
            ->once();

        // Second email fails
        $this->emailService->shouldReceive('send')
            ->with('fail@example.com', 'Test Newsletter', Mockery::type('string'))
            ->andThrow(new \Exception('Email service error'))
            ->once();

        $this->newsletterRepo->shouldReceive('update')
            ->with(1, Mockery::type('array'))
            ->once();

        // Only successful recipient should be in the list
        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {

                return $data['recipient_count'] == 1
                    && !empty($data['recipients'])
                    && str_contains($data['html_snapshot'], 'Hello')
                    && in_array('success@example.com', $data['recipients']);
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
        $this->assertCount(1, $result['failed']);
    }

    public function test_automated_newsletter_captures_page_snapshot(): void
    {
        // Arrange
        $newsletter = $this->createMockAutomatedNewsletter([
            'id' => 1,
            'title' => 'Automated Newsletter',
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $subscriberEmails = ['test@example.com'];

        $mockPages = collect([
            (object)['id' => 1, 'title' => 'Page 1', 'subtitle' => 'Subtitle 1', 'slug' => 'page-1'],
            (object)['id' => 2, 'title' => 'Page 2', 'subtitle' => 'Subtitle 2', 'slug' => 'page-2'],
        ]);

        $this->setCreateExpectations();

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn($subscriberEmails);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([]));

        $this->pageBuilderService->shouldReceive('getPagesForNewsletter')
            ->with($newsletter, $this->siteId)
            ->once()
            ->andReturn($mockPages);

        $this->pageBuilderService->shouldReceive('buildNewsletterHtml')
            ->with($newsletter, Mockery::type(Collection::class), null, false, 1)
            ->once()
            ->andReturn('<html>Newsletter content</html>');

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn(null);

        $this->setupUnsubscribeTokenMocks('test@example.com', 'unsub-token');

        $this->emailService->shouldReceive('send')
            ->with('test@example.com', 'Automated Newsletter', Mockery::type('string'))
            ->once();

        $this->newsletterRepo->shouldReceive('update')
            ->with(1, Mockery::type('array'))
            ->once();

        // Verify page snapshot is captured
        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) use (&$capturedHtml) {
                $capturedHtml = $data['html_snapshot'];

                return $data['recipient_count'] == 1
                    && !empty($data['recipients'])
                    && str_contains($data['html_snapshot'], 'Newsletter content');
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['pages_included']);
    }

    public function test_personalized_html_replaces_placeholders(): void
    {
        // Arrange
        $newsletter = $this->createMockAutomatedNewsletter([
            'id' => 1,
            'title' => 'Test Newsletter',
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $subscriberEmails = ['user@example.com'];

        $mockPages = collect([
            (object)['id' => 1, 'title' => 'Page 1', 'subtitle' => '', 'slug' => 'page-1'],
        ]);

        $baseHtml = '<a href="/newsletters/track-view?send_id={{SEND_ID}}&page_id=1&e={{TRACKING_EMAIL}}&redirect=%2Fpage-1">Link</a>';

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->once()
            ->andReturn($subscriberEmails);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->once()
            ->andReturn(collect([]));

        $this->pageBuilderService->shouldReceive('getPagesForNewsletter')
            ->once()
            ->andReturn($mockPages);

        $this->pageBuilderService->shouldReceive('buildNewsletterHtml')
            ->once()
            ->andReturn($baseHtml);

        $this->memberRepository->shouldReceive('findByEmail')
            ->once()
            ->andReturn(null);

        $this->setupUnsubscribeTokenMocks('user@example.com', 'token');

        $capturedHtml = null;
        $this->emailService->shouldReceive('send')
            ->with('user@example.com', 'Test Newsletter', Mockery::on(function ($html) use (&$capturedHtml) {
                $capturedHtml = $html;
                return true;
            }))
            ->once();

        $this->newsletterRepo->shouldReceive('update')->once();

        $mockSend = Mockery::mock(NewsletterSend::class)->makePartial();
        $mockSend->id = 456;
        $this->sendRepo->shouldReceive('create')->once()->andReturn($mockSend);
        $this->sendRepo->shouldReceive('update')->once();

        // Act
        $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertNotNull($capturedHtml);
        $this->assertStringNotContainsString('{{SEND_ID}}', $capturedHtml);
        $this->assertStringNotContainsString('{{TRACKING_EMAIL}}', $capturedHtml);
        $this->assertStringContainsString('send_id=456', $capturedHtml);
        $this->assertStringContainsString('e=' . md5('user@example.com'), $capturedHtml);
    }

    public function test_send_newsletter_includes_tracking_links(): void
    {
        // Arrange
        $newsletter = $this->createMockAutomatedNewsletter([
            'id' => 1,
            'title' => 'Automated Newsletter',
            'interval' => Newsletter::INTERVAL_WEEKLY,
        ]);

        $subscriberEmails = ['test@example.com'];

        $mockPages = collect([
            (object)['id' => 1, 'title' => 'Page 1', 'subtitle' => 'Subtitle 1', 'slug' => 'page-1'],
        ]);

        $this->subscriberRepo->shouldReceive('getConfirmedEmails')
            ->with($this->siteId)
            ->once()
            ->andReturn($subscriberEmails);

        $this->preferenceRepo->shouldReceive('getActiveSubscribersForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn(collect([]));

        $this->pageBuilderService->shouldReceive('getPagesForNewsletter')
            ->with($newsletter, $this->siteId)
            ->once()
            ->andReturn($mockPages);

        // Expect buildNewsletterHtml to be called WITH sendId
        $this->pageBuilderService->shouldReceive('buildNewsletterHtml')
            ->with(
                $newsletter,
                Mockery::type(Collection::class),
                null,
                false,
                Mockery::type('int') // sendId should be passed
            )
            ->once()
            ->andReturn('<html>Newsletter with {{SEND_ID}} and {{TRACKING_EMAIL}}</html>');

        $this->memberRepository->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn(null);

        $this->setupUnsubscribeTokenMocks('test@example.com', 'unsub-token');

        // Verify personalized HTML is sent (with placeholders replaced)
        $this->emailService->shouldReceive('send')
            ->with('test@example.com', 'Automated Newsletter', Mockery::on(function ($html) {
                // Should have placeholders replaced
                return strpos($html, '{{SEND_ID}}') === false
                    && strpos($html, '{{TRACKING_EMAIL}}') === false
                    && preg_match('/\d+/', $html) // Should contain actual sendId
                    && preg_match('/[a-f0-9]{32}/', $html); // Should contain hashed email
            }))
            ->once();

        $this->newsletterRepo->shouldReceive('update')
            ->with(1, Mockery::type('array'))
            ->once();

        // sendRepository should be called twice: create and update
        $mockSend = Mockery::mock(NewsletterSend::class)->makePartial();
        $mockSend->id = 1;
        $this->sendRepo->shouldReceive('create')
            ->once()
            ->andReturn($mockSend);

        $this->sendRepo->shouldReceive('update')
            ->with(1, Mockery::on(function ($data) {
                return isset($data['recipient_count'])
                    && isset($data['recipients'])
                    && isset($data['html_snapshot']);
            }))
            ->once();

        // Act
        $result = $this->service->sendNewsletter($newsletter, $this->siteId);

        // Assert
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
}