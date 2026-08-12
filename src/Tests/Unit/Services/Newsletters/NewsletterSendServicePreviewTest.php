<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Framework\Database\Database;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Models\NewsletterSendRecipient;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendRecipientRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;
use App\Repositories\Newsletters\NewsletterSnapshotRepository;
use App\Repositories\Newsletters\SubscriberRepository;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Services\Cms\Pages\BlockParserService;
use App\Services\MemberInsights\EmailService;
use App\Services\Newsletter\NewsletterContentBuilder;
use App\Services\Newsletter\NewsletterDispatcher;
use App\Services\Newsletter\NewsletterPageBuilderService;
use App\Services\Newsletter\NewsletterRecipientResolver;
use App\Services\Newsletter\NewsletterSendService;
use App\Services\Newsletter\NewsletterViewTokenService;
use App\Tests\Unit\UnitTestCase;
use Mockery;

class NewsletterSendServicePreviewTest extends UnitTestCase
{
    protected int $siteId = 1;

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
    private NewsletterSnapshotRepository $mockSnapshotRepository;
    private NewsletterViewTokenService $mockViewTokenService;

    protected function setUp(): void
    {
        $this->siteId = 1;

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
            $this->mockViewTokenService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testPreviewWithNoEmails(): void
    {
        $newsletter = $this->createMockNewsletter();

        $result = $this->service->previewNewsletter($newsletter, [], $this->siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('No preview email addresses provided', $result['error']);
    }

    private function createMockNewsletter(): Newsletter
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 1;
        $newsletter->title = 'Test Newsletter';
        $newsletter->content = json_encode([['type' => 'paragraph', 'content' => 'Test']]);
        $newsletter->shouldReceive('isAutomated')->andReturn(false);

        return $newsletter;
    }

    public function testPreviewWithInvalidEmail(): void
    {
        $newsletter = $this->createMockNewsletter();

        $result = $this->service->previewNewsletter(
            $newsletter,
            ['valid@example.com', 'invalid-email'],
            $this->siteId
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid email address', $result['error']);
    }

    public function testPreviewWithTooManyRecipients(): void
    {
        $newsletter = $this->createMockNewsletter();
        $emails = array_map(fn($i) => "test{$i}@example.com", range(1, 11));

        $result = $this->service->previewNewsletter($newsletter, $emails, $this->siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Maximum 10 preview recipients allowed', $result['error']);
    }

    public function testPreviewSuccessfully(): void
    {
        $newsletter = $this->createMockNewsletter();
        $emails = ['preview1@example.com', 'preview2@example.com'];

        $sendRecord = Mockery::mock(NewsletterSend::class)->makePartial();
        $sendRecord->id = 1;

        $this->mockSendRepository->shouldReceive('create')
            ->once()
            ->andReturn($sendRecord);

        $recipient1 = Mockery::mock(NewsletterSendRecipient::class)->makePartial();
        $recipient1->id = 1;
        $recipient1->email = 'preview1@example.com';

        $recipient2 = Mockery::mock(NewsletterSendRecipient::class)->makePartial();
        $recipient2->id = 2;
        $recipient2->email = 'preview2@example.com';

        $this->mockDatabase->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->mockRecipientRepository->shouldReceive('createRecipients')
            ->once()
            ->with(1, $emails)
            ->andReturn([$recipient1, $recipient2]);

        $this->mockDispatcher->shouldReceive('dispatch')
            ->once()
            ->andReturn(['success' => true, 'sent' => 2, 'failed' => 0]);

        $this->mockRecipientRepository->shouldReceive('getStatistics')
            ->once()
            ->andReturn([
                'sent' => 2,
                'failed' => 0,
                'pending' => 0
            ]);

        $this->mockContentBuilder->shouldReceive('build')
            ->once()
            ->andReturn(['html' => '<p>Test</p>', 'success' => true]);

        $result = $this->service->previewNewsletter($newsletter, $emails, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['preview']);
        $this->assertEquals(0, $result['failed']);
    }

    public function testPreviewAddsPreviewNotice(): void
    {
        // 1. Create a fresh mock instead of using the helper to avoid conflicting expectations
        $newsletter = Mockery::mock(\App\Models\Newsletter::class)->makePartial();
        $newsletter->id = 1;
        $newsletter->title = 'Test Newsletter';
        $newsletter->shouldReceive('isAutomated')->andReturn(true); // Ensure this path is taken

        $emails = ['test@example.com'];

        $this->mockDispatcher->shouldReceive('dispatch')
            ->once()
            ->andReturn(['success' => true, 'sent' => 1, 'failed' => 0]);

        $this->mockDatabase->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $sendRecord = Mockery::mock(\App\Models\NewsletterSend::class)->makePartial();
        $sendRecord->id = 1;
        $this->mockSendRepository->shouldReceive('create')->once()->andReturn($sendRecord);


        $recipient = Mockery::mock(\App\Models\NewsletterSendRecipient::class)->makePartial();
        $recipient->id = 1;
        $recipient->email = 'test@example.com';

        $this->mockRecipientRepository->shouldReceive('createRecipients')->once()->andReturn([$recipient]);
        $this->mockRecipientRepository->shouldReceive('getStatistics')->once()->andReturn(['sent' => 1, 'failed' => 0, 'pending' => 0]);

        $this->mockContentBuilder->shouldReceive('build')->once()->andReturn(['html' => '<p>Test</p>', 'success' => true]);

        $result = $this->service->previewNewsletter($newsletter, $emails, $this->siteId);

        $this->assertTrue($result['success']);
    }
}