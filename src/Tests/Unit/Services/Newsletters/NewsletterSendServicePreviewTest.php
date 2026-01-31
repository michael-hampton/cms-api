<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Models\NewsletterSendRecipient;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendRecipientRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Services\Cms\Pages\BlockParserService;
use App\Services\Members\EmailService;
use App\Services\Newsletter\NewsletterPageBuilderService;
use App\Services\Newsletter\NewsletterSendService;
use App\Services\Subscriptions\MemberSubscriptionService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class NewsletterSendServicePreviewTest extends FunctionalTestCase
{
    private NewsletterSendService $service;
    private $emailService;
    private $newsletterRepo;
    private $sendRepo;
    private $recipientRepo;
    private $pageBuilderService;

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

        $this->sendRepo->shouldReceive('create')
            ->once()
            ->andReturn($sendRecord);

        $this->sendRepo->shouldReceive('update')
            ->once()
            ->with(1, Mockery::subset(['html_snapshot' => '<p>Test</p>']));

        $recipient1 = Mockery::mock(NewsletterSendRecipient::class)->makePartial();
        $recipient1->id = 1;
        $recipient1->email = 'preview1@example.com';
        $recipient1->shouldReceive('update')->once();
        $recipient1->shouldReceive('markAsSent')->once();

        $recipient2 = Mockery::mock(NewsletterSendRecipient::class)->makePartial();
        $recipient2->id = 2;
        $recipient2->email = 'preview2@example.com';
        $recipient2->shouldReceive('update')->once();
        $recipient2->shouldReceive('markAsSent')->once();

        $this->recipientRepo->shouldReceive('createRecipients')
            ->once()
            ->with(1, $emails)
            ->andReturn([$recipient1, $recipient2]);

        $this->recipientRepo->shouldReceive('updateSendCounts')
            ->once();

        $this->recipientRepo->shouldReceive('getStatistics')
            ->once()
            ->andReturn([
                'sent' => 2,
                'failed' => 0,
                'pending' => 0
            ]);

        $this->emailService->shouldReceive('send')
            ->twice()
            ->andReturn(true);

        $result = $this->service->previewNewsletter($newsletter, $emails, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['preview']);
        $this->assertEquals(2, $result['recipients']);
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

        // 2. Mock the PageBuilder calls
        $this->pageBuilderService->shouldReceive('getPagesForNewsletter')
            ->once()
            ->andReturn(collect([(object)['id' => 1, 'title' => 'Page', 'subtitle' => 'Sub', 'slug' => 'slug']]));

        $this->pageBuilderService->shouldReceive('buildNewsletterHtml')
            ->once()
            ->andReturn('<html><body class="email-body">Original Content</body></html>');

        $sendRecord = Mockery::mock(\App\Models\NewsletterSend::class)->makePartial();
        $sendRecord->id = 1;
        $this->sendRepo->shouldReceive('create')->once()->andReturn($sendRecord);

        // 3. FIX: The snapshot will NOT be "<p>Test</p>".
        // It will be the HTML with the injected PREVIEW notice.
        $this->sendRepo->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function ($data) {
                return isset($data['html_snapshot']) &&
                    str_contains($data['html_snapshot'], 'PREVIEW') &&
                    str_contains($data['html_snapshot'], 'Original Content');
            }));

        $recipient = Mockery::mock(\App\Models\NewsletterSendRecipient::class)->makePartial();
        $recipient->id = 1;
        $recipient->email = 'test@example.com';
        $recipient->shouldReceive('update')->once();
        $recipient->shouldReceive('markAsSent')->once();

        $this->recipientRepo->shouldReceive('createRecipients')->once()->andReturn([$recipient]);
        $this->recipientRepo->shouldReceive('updateSendCounts')->once()->with(1);
        $this->recipientRepo->shouldReceive('getStatistics')->once()->andReturn(['sent' => 1, 'failed' => 0, 'pending' => 0]);

        $this->emailService->shouldReceive('send')
            ->once()
            ->withArgs(function ($email, $subject, $html) {
                return $email === 'test@example.com'
                    && str_contains($subject, '[PREVIEW]')
                    && str_contains($html, 'Unsubscribe');
            })
            ->andReturn(true);

        $result = $this->service->previewNewsletter($newsletter, $emails, $this->siteId);

        $this->assertTrue($result['success']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->emailService = Mockery::mock(EmailService::class);
        $this->newsletterRepo = Mockery::mock(NewsletterRepository::class);
        $this->sendRepo = Mockery::mock(NewsletterSendRepository::class);
        $this->recipientRepo = Mockery::mock(NewsletterSendRecipientRepository::class);
        $this->pageBuilderService = Mockery::mock(NewsletterPageBuilderService::class);

        $this->service = new NewsletterSendService(
            Mockery::mock(BlockParserService::class),
            $this->emailService,
            Mockery::mock(SubscriberRepository::class),
            $this->newsletterRepo,
            $this->sendRepo,
            Mockery::mock(MemberSubscriptionPreferenceRepository::class),
            $this->pageBuilderService,
            Mockery::mock(MemberRepository::class),
            Mockery::mock(MemberSubscriptionService::class),
            $this->recipientRepo
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}