<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Framework\Database\Database;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendRecipientRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Services\Cms\Pages\BlockParserService;
use App\Services\Members\EmailService;
use App\Services\Newsletter\NewsletterContentBuilder;
use App\Services\Newsletter\NewsletterDispatcher;
use App\Services\Newsletter\NewsletterPageBuilderService;
use App\Services\Newsletter\NewsletterRecipientResolver;
use App\Services\Newsletter\NewsletterSendService;
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
            $this->mockDatabase
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testSendNewsletterSuccessfully()
    {
        $siteId = 1;
        $newsletter = $this->createMockNewsletter();

        $subscribers = ['user1@example.com', 'user2@example.com'];
        $contentResult = [
            'success' => true,
            'html' => '<p>Newsletter content</p>{{UNSUBSCRIBE_LINK}}',
            'pages' => []
        ];

        $mockSendRecord = $this->createMockSendRecord();
        $mockRecipients = [
            (object)['id' => 1, 'email' => 'user1@example.com'],
            (object)['id' => 2, 'email' => 'user2@example.com']
        ];

        // Mock content building
        $this->mockContentBuilder->shouldReceive('build')
            ->once()
            ->with($newsletter, $siteId, false, null)
            ->andReturn($contentResult);

        // Mock recipient resolution - NEW STRUCTURE
        $this->mockRecipientResolver->shouldReceive('resolveForNewsletter')
            ->once()
            ->with($newsletter, $siteId)
            ->andReturn([
                'valid' => $subscribers,
                'skipped' => []
            ]);

        // Mock transaction
        $this->mockDatabase->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Mock send record creation
        $this->mockSendRepository->shouldReceive('create')
            ->once()
            ->andReturn($mockSendRecord);

        // Mock recipient record creation
        $this->mockRecipientRepository->shouldReceive('createRecipients')
            ->once()
            ->andReturn($mockRecipients);

        // Mock dispatch
        $this->mockDispatcher->shouldReceive('dispatch')
            ->once()
            ->andReturn([
                'success' => true,
                'sent' => 2,
                'failed' => 0
            ]);

        // Mock newsletter update
        $this->mockNewsletterRepository->shouldReceive('update')
            ->once();

        // Mock statistics
        $this->mockRecipientRepository->shouldReceive('getStatistics')
            ->once()
            ->andReturn([
                'sent' => 2,
                'failed' => 0,
                'pending' => 0
            ]);

        $result = $this->service->sendNewsletter($newsletter, $siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['recipients']);
        $this->assertEquals(0, $result['failed']);
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

    public function testSendNewsletterHandlesPartialFailure()
    {
        $siteId = 1;
        $newsletter = $this->createMockNewsletter();
        $subscribers = ['user1@example.com', 'user2@example.com'];

        $this->mockContentBuilder->shouldReceive('build')->andReturn([
            'success' => true,
            'html' => 'content',
            'pages' => []
        ]);

        $this->mockRecipientResolver->shouldReceive('resolveForNewsletter')->andReturn([
            'valid' => $subscribers,
            'skipped' => []
        ]);

        $this->mockDatabase->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->mockSendRepository->shouldReceive('create')->andReturn($this->createMockSendRecord());
        $this->mockRecipientRepository->shouldReceive('createRecipients')->andReturn([]);

        $this->mockDispatcher->shouldReceive('dispatch')
            ->once()
            ->andReturn([
                'success' => false,
                'sent' => 1,
                'failed' => 1
            ]);

        $this->mockNewsletterRepository->shouldReceive('update')->once();
        $this->mockRecipientRepository->shouldReceive('getStatistics')->andReturn([
            'sent' => 1,
            'failed' => 1,
            'pending' => 0
        ]);

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

    public function testSendNewsletterTracksSkippedRecipients()
    {
        $siteId = 1;
        $newsletter = $this->createMockNewsletter();

        $contentResult = [
            'success' => true,
            'html' => '<p>Content</p>{{UNSUBSCRIBE_LINK}}',
            'pages' => []
        ];

        $this->mockContentBuilder->shouldReceive('build')
            ->andReturn($contentResult);

        $this->mockRecipientResolver->shouldReceive('resolveForNewsletter')
            ->andReturn([
                'valid' => ['valid@example.com'],
                'skipped' => ['skipped@example.com' => 'Marketing emails disabled in global settings']
            ]);

        $this->mockDatabase->shouldReceive('transaction')
            ->andReturnUsing(fn($cb) => $cb());

        $this->mockSendRepository->shouldReceive('create')
            ->andReturn($this->createMockSendRecord());

        $this->mockRecipientRepository->shouldReceive('createRecipients')
            ->andReturn([]);

        $this->mockDispatcher->shouldReceive('dispatch')
            ->andReturn(['success' => true, 'sent' => 1, 'failed' => 0]);

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
}