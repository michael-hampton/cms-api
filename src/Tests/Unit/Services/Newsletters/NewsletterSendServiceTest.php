<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Framework\Database\Database;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Models\NewsletterSnapshot;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendRecipientRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;
use App\Repositories\Newsletters\NewsletterSnapshotRepository;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Services\Cms\Pages\BlockParserService;
use App\Services\Members\EmailService;
use App\Services\Newsletter\NewsletterContentBuilder;
use App\Services\Newsletter\NewsletterDispatcher;
use App\Services\Newsletter\NewsletterPageBuilderService;
use App\Services\Newsletter\NewsletterRecipientResolver;
use App\Services\Newsletter\NewsletterSendService;
use App\Services\Newsletter\NewsletterViewTokenService;
use Mockery;
use PHPUnit\Framework\TestCase;

class NewsletterSendServiceTest extends TestCase
{
    private NewsletterSendService $service;
    private $mockParser;
    private $mockEmailService;
    private $mockSubscriberRepository;
    private $mockNewsletterRepository;
    private $mockSendRepository;
    private $mockPreferenceRepository;
    private $mockPageBuilderService;
    private $mockMemberRepository;
    private $mockRecipientRepository;
    private $mockContentBuilder;
    private $mockRecipientResolver;
    private $mockDispatcher;
    private $mockDatabase;
    private $mockSnapshotRepository;
    private $mockViewTokenService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockParser = Mockery::mock(BlockParserService::class);
        $this->mockEmailService = Mockery::mock(EmailService::class);
        $this->mockSubscriberRepository = Mockery::mock(SubscriberRepository::class);
        $this->mockNewsletterRepository = Mockery::mock(NewsletterRepository::class);
        $this->mockSendRepository = Mockery::mock(NewsletterSendRepository::class);
        $this->mockPreferenceRepository = Mockery::mock(MemberSubscriptionPreferenceRepository::class);
        $this->mockPageBuilderService = Mockery::mock(NewsletterPageBuilderService::class);
        $this->mockMemberRepository = Mockery::mock(MemberRepository::class);
        $this->mockRecipientRepository = Mockery::mock(NewsletterSendRecipientRepository::class);
        $this->mockContentBuilder = Mockery::mock(NewsletterContentBuilder::class);
        $this->mockRecipientResolver = Mockery::mock(NewsletterRecipientResolver::class);
        $this->mockDispatcher = Mockery::mock(NewsletterDispatcher::class);
        $this->mockDatabase = Mockery::mock(Database::class);
        $this->mockSnapshotRepository = Mockery::mock(NewsletterSnapshotRepository::class);
        $this->mockViewTokenService = Mockery::mock(NewsletterViewTokenService::class);

        $this->service = new NewsletterSendService(
            $this->mockParser,
            $this->mockEmailService,
            $this->mockSubscriberRepository,
            $this->mockNewsletterRepository,
            $this->mockSendRepository,
            $this->mockPreferenceRepository,
            $this->mockPageBuilderService,
            $this->mockMemberRepository,
            $this->mockRecipientRepository,
            $this->mockContentBuilder,
            $this->mockRecipientResolver,
            $this->mockDispatcher,
            $this->mockDatabase,
            $this->mockSnapshotRepository,
            $this->mockViewTokenService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testSendNewsletterSuccessfully(): void
    {
        $siteId = 1;
        $newsletter = $this->createMockNewsletter();

        $subscribers = ['user1@example.com', 'user2@example.com'];
        $contentResult = [
            'success' => true,
            'html' => '<p>Newsletter content</p>' . NewsletterPageBuilderService::VIEW_IN_BROWSER_PLACEHOLDER,
            'pages' => [],
        ];

        $mockSendRecord = $this->createMockSendRecord();
        $mockRecipients = [
            (object)['id' => 1, 'email' => 'user1@example.com'],
            (object)['id' => 2, 'email' => 'user2@example.com'],
        ];
        $mockSnapshot = $this->createMockSnapshot();

        $this->mockContentBuilder->shouldReceive('build')
            ->once()
            ->with($newsletter, $siteId, false, null)
            ->andReturn($contentResult);

        $this->mockRecipientResolver->shouldReceive('resolveForNewsletter')
            ->once()
            ->with($newsletter, $siteId)
            ->andReturn(['valid' => $subscribers, 'skipped' => []]);

        $this->mockDatabase->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        // Snapshot is created inside the transaction
        $this->mockSnapshotRepository->shouldReceive('createSnapshot')
            ->once()
            ->andReturn($mockSnapshot);

        // View token is generated from the snapshot
        $this->mockViewTokenService->shouldReceive('generateTokenForSnapshot')
            ->once()
            ->with($mockSnapshot->id)
            ->andReturn('abc123viewtoken');

        $this->mockSendRepository->shouldReceive('create')
            ->once()
            ->andReturn($mockSendRecord);

        $this->mockRecipientRepository->shouldReceive('createRecipients')
            ->once()
            ->andReturn($mockRecipients);

        $this->mockDispatcher->shouldReceive('dispatch')
            ->once()
            ->andReturn(['success' => true, 'sent' => 2, 'failed' => 0]);

        $this->mockNewsletterRepository->shouldReceive('update')->once();

        $this->mockRecipientRepository->shouldReceive('getStatistics')
            ->once()
            ->andReturn(['sent' => 2, 'failed' => 0, 'pending' => 0]);

        $result = $this->service->sendNewsletter($newsletter, $siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['recipients']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEquals($mockSnapshot->id, $result['snapshot_id']);
    }

    public function testSendNewsletterInjectsSnapshotTokenIntoHtml(): void
    {
        $siteId = 1;
        $newsletter = $this->createMockNewsletter();
        $snapshot = $this->createMockSnapshot();
        $token = 'unique-snapshot-token-xyz';

        $htmlWithPlaceholder = 'Before ' . NewsletterPageBuilderService::VIEW_IN_BROWSER_PLACEHOLDER . ' After';

        $this->mockContentBuilder->shouldReceive('build')
            ->andReturn(['success' => true, 'html' => $htmlWithPlaceholder, 'pages' => []]);

        $this->mockRecipientResolver->shouldReceive('resolveForNewsletter')
            ->andReturn(['valid' => ['user@example.com'], 'skipped' => []]);

        $this->mockDatabase->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->mockSnapshotRepository->shouldReceive('createSnapshot')->andReturn($snapshot);

        $this->mockViewTokenService->shouldReceive('generateTokenForSnapshot')
            ->with($snapshot->id)
            ->andReturn($token);

        // The HTML passed to create() and dispatch() must contain the token-specific
        // placeholder, NOT the generic VIEW_IN_BROWSER_PLACEHOLDER.
        $this->mockSendRepository->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) use ($token) {
                return str_contains($data['html_snapshot'], $token)
                    && !str_contains($data['html_snapshot'], NewsletterPageBuilderService::VIEW_IN_BROWSER_PLACEHOLDER);
            })
            ->andReturn($this->createMockSendRecord());

        $this->mockRecipientRepository->shouldReceive('createRecipients')->andReturn([]);

        $this->mockDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($send, $recipients, $newsletter, $siteId, $html) use ($token) {
                return str_contains($html, $token)
                    && !str_contains($html, NewsletterPageBuilderService::VIEW_IN_BROWSER_PLACEHOLDER);
            })
            ->andReturn(['success' => true]);

        $this->mockNewsletterRepository->shouldReceive('update')->once();
        $this->mockRecipientRepository->shouldReceive('getStatistics')
            ->andReturn(['sent' => 1, 'failed' => 0, 'pending' => 0]);

        $this->service->sendNewsletter($newsletter, $siteId);
        $this->assertTrue(true);
    }

    public function testSendNewsletterCreatesSnapshotInsideTransaction(): void
    {
        $siteId = 1;
        $newsletter = $this->createMockNewsletter();
        $snapshot = $this->createMockSnapshot();

        $this->mockContentBuilder->shouldReceive('build')
            ->andReturn(['success' => true, 'html' => 'content', 'pages' => []]);

        $this->mockRecipientResolver->shouldReceive('resolveForNewsletter')
            ->andReturn(['valid' => ['user@example.com'], 'skipped' => []]);

        $transactionCalled = false;
        $snapshotCreatedInsideTransaction = false;

        $this->mockDatabase->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use (&$transactionCalled, &$snapshotCreatedInsideTransaction) {
                $transactionCalled = true;
                return $callback();
            });

        $this->mockSnapshotRepository->shouldReceive('createSnapshot')
            ->once()
            ->andReturnUsing(function () use ($snapshot, &$snapshotCreatedInsideTransaction, &$transactionCalled) {
                // Verify snapshot is only created after transaction has started
                $snapshotCreatedInsideTransaction = $transactionCalled;
                return $snapshot;
            });

        $this->mockViewTokenService->shouldReceive('generateTokenForSnapshot')
            ->andReturn('token123');

        $this->mockSendRepository->shouldReceive('create')->andReturn($this->createMockSendRecord());
        $this->mockRecipientRepository->shouldReceive('createRecipients')->andReturn([]);
        $this->mockDispatcher->shouldReceive('dispatch')->andReturn(['success' => true]);
        $this->mockNewsletterRepository->shouldReceive('update')->once();
        $this->mockRecipientRepository->shouldReceive('getStatistics')
            ->andReturn(['sent' => 1, 'failed' => 0, 'pending' => 0]);

        $this->service->sendNewsletter($newsletter, $siteId);

        $this->assertTrue($snapshotCreatedInsideTransaction, 'Snapshot must be created inside the DB transaction');
    }


    public function testSendNewsletterPreventsDuplicateSend()
    {
        $siteId = 1;
        $newsletter = $this->createMockNewsletter();
        $newsletter->last_sent = (new \DateTimeImmutable('-30 minutes'))->format('Y-m-d H:i:s');

        $result = $this->service->sendNewsletter($newsletter, $siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Newsletter already sent recently', $result['error']);
    }

    public function testSendNewsletterFailsWithNoSubscribers()
    {
        $siteId = 1;
        $newsletter = $this->createMockNewsletter();

        $this->mockContentBuilder->shouldReceive('build')
            ->once()
            ->andReturn([
                'success' => true,
                'html' => 'content',
                'pages' => []
            ]);

        $this->mockRecipientResolver->shouldReceive('resolveForNewsletter')
            ->once()
            ->andReturn([
                'valid' => [],
                'skipped' => []
            ]);

        $result = $this->service->sendNewsletter($newsletter, $siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('No eligible recipients', $result['error']);
    }

    public function testSendNewsletterFailsWhenContentBuildingFails()
    {
        $siteId = 1;
        $newsletter = $this->createMockNewsletter();

        $this->mockContentBuilder->shouldReceive('build')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'No pages match newsletter criteria'
            ]);

        $result = $this->service->sendNewsletter($newsletter, $siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('No pages match newsletter criteria', $result['error']);
    }

    public function testSendNewsletterHandlesPartialFailure(): void
    {
        $siteId = 1;
        $newsletter = $this->createMockNewsletter();
        $snapshot = $this->createMockSnapshot();

        $this->mockContentBuilder->shouldReceive('build')
            ->andReturn(['success' => true, 'html' => 'content', 'pages' => []]);

        $this->mockRecipientResolver->shouldReceive('resolveForNewsletter')
            ->andReturn(['valid' => ['user1@example.com', 'user2@example.com'], 'skipped' => []]);

        $this->mockDatabase->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->mockSnapshotRepository->shouldReceive('createSnapshot')->andReturn($snapshot);
        $this->mockViewTokenService->shouldReceive('generateTokenForSnapshot')->andReturn('token');
        $this->mockSendRepository->shouldReceive('create')->andReturn($this->createMockSendRecord());
        $this->mockRecipientRepository->shouldReceive('createRecipients')->andReturn([]);

        $this->mockDispatcher->shouldReceive('dispatch')
            ->once()
            ->andReturn(['success' => false, 'sent' => 1, 'failed' => 1]);

        $this->mockNewsletterRepository->shouldReceive('update')->once();
        $this->mockRecipientRepository->shouldReceive('getStatistics')
            ->andReturn(['sent' => 1, 'failed' => 1, 'pending' => 0]);

        $result = $this->service->sendNewsletter($newsletter, $siteId);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['partial_failure']);
        $this->assertEquals(1, $result['recipients']);
        $this->assertEquals(1, $result['failed']);
    }

    public function testPreviewNewsletterSuccessfully()
    {
        $siteId = 1;
        $newsletter = $this->createMockNewsletter();
        $previewEmails = ['preview@example.com'];

        $this->mockContentBuilder->shouldReceive('build')
            ->once()
            ->with($newsletter, $siteId, true, null)
            ->andReturn([
                'success' => true,
                'html' => 'content',
                'pages' => []
            ]);

        $this->mockDatabase->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $mockSendRecord = $this->createMockSendRecord();
        $mockSendRecord->is_preview = true;

        $this->mockSendRepository->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return $data['is_preview'] === true;
            }))
            ->andReturn($mockSendRecord);

        $this->mockRecipientRepository->shouldReceive('createRecipients')->andReturn([]);
        $this->mockDispatcher->shouldReceive('dispatch')
            ->with(Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), true)
            ->andReturn(['success' => true, 'sent' => 1, 'failed' => 0]);

        $this->mockRecipientRepository->shouldReceive('getStatistics')->andReturn([
            'sent' => 1,
            'failed' => 0,
            'pending' => 0
        ]);

        $result = $this->service->previewNewsletter($newsletter, $previewEmails, $siteId);

        $this->assertTrue($result['success']);
    }

    public function testPreviewNewsletterStripsViewInBrowserPlaceholder(): void
    {
        $siteId = 1;
        $newsletter = $this->createMockNewsletter();
        $previewEmails = ['preview@example.com'];

        $htmlWithPlaceholder = 'Header ' . NewsletterPageBuilderService::VIEW_IN_BROWSER_PLACEHOLDER . ' Body {{UNSUBSCRIBE_LINK}}';

        $this->mockContentBuilder->shouldReceive('build')
            ->andReturn(['success' => true, 'html' => $htmlWithPlaceholder, 'pages' => []]);

        $this->mockDatabase->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->mockSendRepository->shouldReceive('create')->andReturn($this->createMockSendRecord());
        $this->mockRecipientRepository->shouldReceive('createRecipients')->andReturn([]);

        // The dispatcher must receive HTML without the placeholder or unsubscribe link
        $this->mockDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($send, $recipients, $newsletter, $siteId, $html) {
                return !str_contains($html, NewsletterPageBuilderService::VIEW_IN_BROWSER_PLACEHOLDER)
                    && !str_contains($html, '{{UNSUBSCRIBE_LINK}}');
            })
            ->andReturn(['success' => true]);

        $this->mockRecipientRepository->shouldReceive('getStatistics')
            ->andReturn(['sent' => 1, 'failed' => 0, 'pending' => 0]);

        $this->service->previewNewsletter($newsletter, $previewEmails, $siteId);
        $this->assertTrue(true);
    }

    public function testPreviewNewsletterDoesNotCreateSnapshot(): void
    {
        $newsletter = $this->createMockNewsletter();
        $previewEmails = ['preview@example.com'];

        $this->mockContentBuilder->shouldReceive('build')
            ->andReturn(['success' => true, 'html' => 'content', 'pages' => []]);

        $this->mockDatabase->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->mockSendRepository->shouldReceive('create')->andReturn($this->createMockSendRecord());
        $this->mockRecipientRepository->shouldReceive('createRecipients')->andReturn([]);
        $this->mockDispatcher->shouldReceive('dispatch')->andReturn(['success' => true]);
        $this->mockRecipientRepository->shouldReceive('getStatistics')
            ->andReturn(['sent' => 1, 'failed' => 0, 'pending' => 0]);

        // Snapshot repository must NOT be called for preview sends
        $this->mockSnapshotRepository->shouldNotReceive('createSnapshot');
        $this->mockViewTokenService->shouldNotReceive('generateTokenForSnapshot');

        $this->service->previewNewsletter($newsletter, $previewEmails, 1);
        $this->assertTrue(true);
    }


    public function testPreviewNewsletterRejectsInvalidEmail()
    {
        $newsletter = $this->createMockNewsletter();
        $previewEmails = ['invalid-email'];

        $result = $this->service->previewNewsletter($newsletter, $previewEmails);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid email address', $result['error']);
    }

    public function testPreviewNewsletterRejectsTooManyRecipients()
    {
        $newsletter = $this->createMockNewsletter();
        $previewEmails = array_fill(0, 11, 'user@example.com');

        $result = $this->service->previewNewsletter($newsletter, $previewEmails);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Maximum 10', $result['error']);
    }

    public function testRetrySendSuccessfully()
    {
        $sendId = 1;
        $siteId = 1;

        $mockSend = $this->createMockSendRecord();
        $mockSend->is_preview = false;

        $newsletter = $this->createMockNewsletter();
        $retryableRecipients = [(object)['id' => 1, 'email' => 'retry@example.com']];

        $this->mockSendRepository->shouldReceive('find')
            ->once()
            ->with($sendId)
            ->andReturn($mockSend);

        $this->mockNewsletterRepository->shouldReceive('find')
            ->once()
            ->andReturn($newsletter);

        $this->mockRecipientRepository->shouldReceive('getRetryableRecipients')
            ->once()
            ->andReturn($retryableRecipients);

        $this->mockDatabase->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->mockDispatcher->shouldReceive('dispatch')
            ->once()
            ->andReturn(['success' => true, 'sent' => 1, 'failed' => 0]);

        $this->mockRecipientRepository->shouldReceive('getStatistics')
            ->once()
            ->andReturn(['sent' => 1, 'failed' => 0, 'pending' => 0]);

        $result = $this->service->retrySend($sendId, 3, $siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['retried']);
    }

    public function testRetrySendRejectsPreviewSend()
    {
        $sendId = 1;

        $mockSend = $this->createMockSendRecord();
        $mockSend->is_preview = true;

        $this->mockSendRepository->shouldReceive('find')->andReturn($mockSend);

        $result = $this->service->retrySend($sendId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cannot retry preview sends', $result['error']);
    }

    public function testRetrySendFailsWhenNoRecipientsAvailable()
    {
        $sendId = 1;

        $mockSend = $this->createMockSendRecord();
        $mockSend->is_preview = false;

        $this->mockSendRepository->shouldReceive('find')->andReturn($mockSend);
        $this->mockNewsletterRepository->shouldReceive('find')->andReturn($this->createMockNewsletter());
        $this->mockRecipientRepository->shouldReceive('getRetryableRecipients')->andReturn([]);

        $result = $this->service->retrySend($sendId);

        $this->assertFalse($result['success']);
        $this->assertEquals('No recipients available for retry', $result['error']);
    }

    public function testSendNewsletterTracksSkippedRecipients(): void
    {
        $siteId = 1;
        $newsletter = $this->createMockNewsletter();
        $snapshot = $this->createMockSnapshot();

        $this->mockContentBuilder->shouldReceive('build')
            ->andReturn(['success' => true, 'html' => 'content', 'pages' => []]);

        $this->mockRecipientResolver->shouldReceive('resolveForNewsletter')
            ->andReturn([
                'valid' => ['valid@example.com'],
                'skipped' => ['skipped@example.com' => 'Marketing emails disabled in global settings'],
            ]);

        $this->mockDatabase->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->mockSnapshotRepository->shouldReceive('createSnapshot')->andReturn($snapshot);
        $this->mockViewTokenService->shouldReceive('generateTokenForSnapshot')->andReturn('token');
        $this->mockSendRepository->shouldReceive('create')->andReturn($this->createMockSendRecord());
        $this->mockRecipientRepository->shouldReceive('createRecipients')->andReturn([]);
        $this->mockDispatcher->shouldReceive('dispatch')->andReturn(['success' => true]);
        $this->mockNewsletterRepository->shouldReceive('update')->once();
        $this->mockRecipientRepository->shouldReceive('getStatistics')
            ->andReturn(['sent' => 1, 'failed' => 0, 'pending' => 0]);

        $result = $this->service->sendNewsletter($newsletter, $siteId);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['skipped']);
        $this->assertEquals('skipped@example.com', $result['skipped'][0]['email']);
        $this->assertEquals('Marketing emails disabled in global settings', $result['skipped'][0]['reason']);
    }

    private function createMockNewsletter(): Newsletter
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 1;
        $newsletter->title = 'Test Newsletter';
        $newsletter->interval = 'weekly';
        $newsletter->last_sent = null;
        $newsletter->shouldReceive('isAutomated')->andReturn(false);
        return $newsletter;
    }

    private function createMockSendRecord(): NewsletterSend
    {
        $send = Mockery::mock(NewsletterSend::class)->makePartial();
        $send->id = 1;
        $send->newsletter_id = 1;
        $send->html_snapshot = 'content';
        $send->is_preview = false;
        return $send;
    }

    private function createMockSnapshot(): NewsletterSnapshot
    {
        $snapshot = Mockery::mock(NewsletterSnapshot::class)->makePartial();
        $snapshot->id = 42;
        return $snapshot;
    }
}