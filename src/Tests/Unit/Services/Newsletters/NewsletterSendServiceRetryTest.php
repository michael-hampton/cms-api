<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Models\MemberSubscriptionPreference;
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

class NewsletterSendServiceRetryTest extends FunctionalTestCase
{
    private NewsletterSendService $service;
    private $emailService;
    private $newsletterRepo;
    private $sendRepo;
    private $recipientRepo;
    private $preferenceRepository;

    public function testRetryWithInvalidSendId(): void
    {
        $this->sendRepo->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->retrySend(999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Send record not found', $result['error']);
    }

    public function testRetryWithNoRetryableRecipients(): void
    {
        $send = Mockery::mock(NewsletterSend::class)->makePartial();
        $send->id = 1;
        $send->newsletter_id = 1;
        $send->html_snapshot = '<html>Test</html>';

        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 1;
        $newsletter->shouldReceive('isAutomated')->andReturn(false);

        $this->sendRepo->shouldReceive('find')->once()->andReturn($send);
        $this->newsletterRepo->shouldReceive('find')->once()->andReturn($newsletter);
        $this->recipientRepo->shouldReceive('getRetryableRecipients')
            ->once()
            ->with(1, 3)
            ->andReturn([]);

        $result = $this->service->retrySend(1, 3);

        $this->assertFalse($result['success']);
        $this->assertEquals('No recipients available for retry', $result['error']);
    }

    public function testRetrySuccessfully(): void
    {
        $send = Mockery::mock(NewsletterSend::class)->makePartial();
        $send->id = 1;
        $send->newsletter_id = 1;
        $send->html_snapshot = '<html>Test</html>';

        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 1;
        $newsletter->title = 'Test Newsletter';
        $newsletter->shouldReceive('isAutomated')->andReturn(false);

        $recipient1 = Mockery::mock(NewsletterSendRecipient::class)->makePartial();
        $recipient1->id = 1;
        $recipient1->email = 'retry1@example.com';
        $recipient1->shouldReceive('update')->once();
        $recipient1->shouldReceive('markAsSent')->once();

        $recipient2 = Mockery::mock(NewsletterSendRecipient::class)->makePartial();
        $recipient2->id = 2;
        $recipient2->email = 'retry2@example.com';
        $recipient2->shouldReceive('update')->once();
        $recipient2->shouldReceive('markAsFailed')->once();

        $this->sendRepo->shouldReceive('find')->once()->andReturn($send);
        $this->newsletterRepo->shouldReceive('find')->once()->andReturn($newsletter);

        $this->recipientRepo->shouldReceive('getRetryableRecipients')
            ->once()
            ->andReturn([
                ['id' => 1, 'email' => 'retry1@example.com'],
                ['id' => 2, 'email' => 'retry2@example.com']
            ]);

        // Mock the find calls for each recipient
        $this->recipientRepo->shouldReceive('find')
            ->with(1)
            ->andReturn($recipient1);

        $prefMock = Mockery::mock(MemberSubscriptionPreference::class)->makePartial();
        $prefMock->unsubscribe_token = 'test';

        $this->preferenceRepository->shouldReceive('findByMemberEmail')
            ->twice()
            ->andReturn($prefMock);

        $this->recipientRepo->shouldReceive('find')
            ->with(2)
            ->andReturn($recipient2);

        $this->recipientRepo->shouldReceive('updateSendCounts')->once();

        $this->recipientRepo->shouldReceive('getStatistics')
            ->once()
            ->andReturn([
                'sent' => 1,
                'failed' => 1,
                'pending' => 0
            ]);

        $this->emailService->shouldReceive('send')
            ->once()
            ->with('retry1@example.com', Mockery::any(), Mockery::any())
            ->andReturn(true);

        $this->emailService->shouldReceive('send')
            ->once()
            ->with('retry2@example.com', Mockery::any(), Mockery::any())
            ->andThrow(new \Exception('SMTP error'));

        $result = $this->service->retrySend(1, 3, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['send_id']);
        $this->assertEquals(2, $result['retried']);
        $this->assertEquals(1, $result['sent']);
        $this->assertEquals(1, $result['failed']);
    }

    public function testRetryRespectsMaxAttempts(): void
    {
        $send = Mockery::mock(NewsletterSend::class)->makePartial();
        $send->id = 1;
        $send->newsletter_id = 1;

        $newsletter = Mockery::mock(Newsletter::class)->makePartial();

        $this->sendRepo->shouldReceive('find')->once()->andReturn($send);
        $this->newsletterRepo->shouldReceive('find')->once()->andReturn($newsletter);

        $this->recipientRepo->shouldReceive('getRetryableRecipients')
            ->once()
            ->with(1, 5)
            ->andReturn([]);

        $result = $this->service->retrySend(1, 5);

        $this->assertFalse($result['success']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->emailService = Mockery::mock(EmailService::class);
        $this->newsletterRepo = Mockery::mock(NewsletterRepository::class);
        $this->sendRepo = Mockery::mock(NewsletterSendRepository::class);
        $this->recipientRepo = Mockery::mock(NewsletterSendRecipientRepository::class);
        $this->preferenceRepository = Mockery::mock(MemberSubscriptionPreferenceRepository::class);

        $this->service = new NewsletterSendService(
            Mockery::mock(BlockParserService::class),
            $this->emailService,
            Mockery::mock(SubscriberRepository::class),
            $this->newsletterRepo,
            $this->sendRepo,
            $this->preferenceRepository,
            Mockery::mock(NewsletterPageBuilderService::class),
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