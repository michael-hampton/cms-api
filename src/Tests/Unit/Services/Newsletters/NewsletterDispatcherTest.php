<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Models\Member;
use App\Models\MemberSubscriptionPreference;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Models\NewsletterSendRecipient;
use App\Models\Subscriber;
use App\Repositories\Newsletters\NewsletterSendRecipientRepository;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Services\Members\EmailService;
use App\Services\Newsletter\NewsletterDispatcher;
use Mockery;
use PHPUnit\Framework\TestCase;

class NewsletterDispatcherTest extends TestCase
{
    private NewsletterDispatcher $dispatcher;
    private $mockEmailService;
    private $mockRecipientRepository;
    private $mockPreferenceRepository;
    private $mockSubscriberRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockEmailService = Mockery::mock(EmailService::class);
        $this->mockRecipientRepository = Mockery::mock(NewsletterSendRecipientRepository::class);
        $this->mockPreferenceRepository = Mockery::mock(MemberSubscriptionPreferenceRepository::class);
        $this->mockSubscriberRepository = Mockery::mock(SubscriberRepository::class);

        $this->dispatcher = new NewsletterDispatcher(
            $this->mockEmailService,
            $this->mockRecipientRepository,
            $this->mockPreferenceRepository,
            $this->mockSubscriberRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testDispatchWithEmptyRecipients()
    {
        $sendRecord = $this->createMockSendRecord();
        $newsletter = $this->createMockNewsletter();

        $result = $this->dispatcher->dispatch(
            $sendRecord,
            [],
            $newsletter,
            1,
            '<p>Content</p>{{UNSUBSCRIBE_LINK}}',
            false
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(0, $result['failed']);
    }

    public function testDispatchSuccessfullyWithRealRecipients()
    {
        $sendRecord = $this->createMockSendRecord();
        $newsletter = $this->createMockNewsletter(false);
        $siteId = 1;

        $recipient1 = $this->createMockRecipient(1, 'user1@example.com');
        $recipient2 = $this->createMockRecipient(2, 'user2@example.com');
        $recipients = [$recipient1, $recipient2];

        $mockPref = Mockery::mock(MemberSubscriptionPreference::class)->makePartial();
        $mockPref->unsubscribe_token = 'token123';
        $mockPref->member = Mockery::mock(Member::class)->makePartial();
        $mockPref->member->email = 'user1@example.com';

        $mockSubscriber = Mockery::mock(Subscriber::class)->makePartial();
        $mockSubscriber->unsubscribe_token = 'token456';
        $mockSubscriber->email = 'user2@example.com';

        // Mock bulk token fetching
        $this->mockPreferenceRepository->shouldReceive('findByEmails')
            ->once()
            ->with(['user1@example.com', 'user2@example.com'], $siteId)
            ->andReturn(collect([$mockPref]));

        $this->mockSubscriberRepository->shouldReceive('findByEmails')
            ->once()
            ->with(['user2@example.com'], $siteId)
            ->andReturn(collect([$mockSubscriber]));

        // Mock recipient repository finds
        $this->mockRecipientRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($recipient1);

        $this->mockRecipientRepository->shouldReceive('find')
            ->with(2)
            ->andReturn($recipient2);

        // Mock email sending
        $this->mockEmailService->shouldReceive('send')
            ->twice()
            ->andReturn(true);

        // Mock marking as sent
        $recipient1->shouldReceive('markAsSent')->once();
        $recipient2->shouldReceive('markAsSent')->once();

        // Mock update send counts
        $this->mockRecipientRepository->shouldReceive('updateSendCounts')
            ->once()
            ->with($sendRecord->id);

        $result = $this->dispatcher->dispatch(
            $sendRecord,
            $recipients,
            $newsletter,
            $siteId,
            '<p>Content</p>{{UNSUBSCRIBE_LINK}}',
            false
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['sent']);
        $this->assertEquals(0, $result['failed']);
    }

    public function testDispatchHandlesFailures()
    {
        $sendRecord = $this->createMockSendRecord();
        $newsletter = $this->createMockNewsletter(false);
        $siteId = 1;

        $recipient = $this->createMockRecipient(1, 'user@example.com');

        $this->mockPreferenceRepository->shouldReceive('findByEmails')
            ->andReturn(collect([]));

        $this->mockSubscriberRepository->shouldReceive('findByEmails')
            ->andReturn(collect([]));

        $this->mockRecipientRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($recipient);

        // Email sending fails
        $this->mockEmailService->shouldReceive('send')
            ->once()
            ->andThrow(new \Exception('SMTP error'));

        $recipient->shouldReceive('markAsFailed')
            ->once()
            ->with('SMTP error');

        $this->mockRecipientRepository->shouldReceive('updateSendCounts')
            ->once();

        $result = $this->dispatcher->dispatch(
            $sendRecord,
            [$recipient],
            $newsletter,
            $siteId,
            '<p>Content</p>{{UNSUBSCRIBE_LINK}}',
            false
        );

        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
    }

    public function testDispatchWithPreviewMode()
    {
        $sendRecord = $this->createMockSendRecord();
        $newsletter = $this->createMockNewsletter(false);
        $siteId = 1;

        $recipient = $this->createMockRecipient(1, 'preview@example.com');

        $this->mockRecipientRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($recipient);

        // Preview mode - no database token calls
        $this->mockPreferenceRepository->shouldNotReceive('findByEmails');
        $this->mockSubscriberRepository->shouldNotReceive('findByEmails');

        $recipient->shouldReceive('update')->never();

        $this->mockEmailService->shouldReceive('send')
            ->once()
            ->with(
                'preview@example.com',
                '[PREVIEW] Test Newsletter',
                Mockery::any()
            );

        $recipient->shouldReceive('markAsSent')->once();

        $this->mockRecipientRepository->shouldReceive('updateSendCounts')
            ->once();

        $result = $this->dispatcher->dispatch(
            $sendRecord,
            [$recipient],
            $newsletter,
            $siteId,
            '<p>Content</p>{{UNSUBSCRIBE_LINK}}',
            true
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['sent']);
    }

    public function testDispatchWithAutomatedNewsletter()
    {
        $sendRecord = $this->createMockSendRecord();
        $newsletter = $this->createMockNewsletter(true);
        $siteId = 1;

        $recipient = $this->createMockRecipient(1, 'user@example.com');

        $this->mockPreferenceRepository->shouldReceive('findByEmails')
            ->andReturn(collect([]));

        $this->mockSubscriberRepository->shouldReceive('findByEmails')
            ->andReturn(collect([]));

        $this->mockRecipientRepository->shouldReceive('find')
            ->andReturn($recipient);

        $this->mockEmailService->shouldReceive('send')
            ->once()
            ->with(
                'user@example.com',
                'Test Newsletter',
                Mockery::on(function ($html) use ($sendRecord) {
                    return str_contains($html, hash('sha256', 'user@example.com'))
                        && str_contains($html, (string)$sendRecord->id);
                })
            );

        $recipient->shouldReceive('markAsSent')->once();

        $this->mockRecipientRepository->shouldReceive('updateSendCounts')
            ->once();

        $result = $this->dispatcher->dispatch(
            $sendRecord,
            [$recipient],
            $newsletter,
            $siteId,
            '<p>Content</p>{{TRACKING_EMAIL}}{{SEND_ID}}{{UNSUBSCRIBE_LINK}}',
            false
        );

        $this->assertTrue($result['success']);
    }

    public function testDispatchUpdatesUnsubscribeTokenWhenMissing()
    {
        $sendRecord = $this->createMockSendRecord();
        $newsletter = $this->createMockNewsletter(false);
        $siteId = 1;

        $recipient = $this->createMockRecipient(1, 'user@example.com');
        $recipient->unsubscribe_token = null;

        $mockPref = Mockery::mock(MemberSubscriptionPreference::class)->makePartial();
        $mockPref->unsubscribe_token = 'new_token';
        $mockPref->member = Mockery::mock(Member::class)->makePartial();
        $mockPref->member->email = 'user@example.com';

        $this->mockPreferenceRepository->shouldReceive('findByEmails')
            ->andReturn(collect([$mockPref]));

        $this->mockRecipientRepository->shouldReceive('find')
            ->andReturn($recipient);

        $recipient->shouldReceive('update')
            ->once()
            ->with(['unsubscribe_token' => 'new_token']);

        $this->mockEmailService->shouldReceive('send')->once();
        $recipient->shouldReceive('markAsSent')->once();

        $this->mockRecipientRepository->shouldReceive('updateSendCounts')
            ->once();

        $result = $this->dispatcher->dispatch(
            $sendRecord,
            [$recipient],
            $newsletter,
            $siteId,
            '<p>Content</p>{{UNSUBSCRIBE_LINK}}',
            false
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['sent']);
    }

    public function testDispatchProcessesInBatches()
    {
        $sendRecord = $this->createMockSendRecord();
        $newsletter = $this->createMockNewsletter(false);
        $siteId = 1;

        // Create 150 recipients (more than batch size of 100)
        $recipients = [];
        $emails = [];
        for ($i = 1; $i <= 150; $i++) {
            $email = "user{$i}@example.com";
            $recipient = $this->createMockRecipient($i, $email);
            $recipients[] = $recipient;
            $emails[] = $email;

            $this->mockRecipientRepository->shouldReceive('find')
                ->with($i)
                ->andReturn($recipient);

            $recipient->shouldReceive('markAsSent')->once();
        }

        $this->mockPreferenceRepository->shouldReceive('findByEmails')
            ->once()
            ->with($emails, $siteId)
            ->andReturn(collect([]));

        $this->mockSubscriberRepository->shouldReceive('findByEmails')
            ->once()
            ->with($emails, $siteId)
            ->andReturn(collect([]));

        $this->mockEmailService->shouldReceive('send')
            ->times(150);

        $this->mockRecipientRepository->shouldReceive('updateSendCounts')
            ->once();

        $result = $this->dispatcher->dispatch(
            $sendRecord,
            $recipients,
            $newsletter,
            $siteId,
            '<p>Content</p>{{UNSUBSCRIBE_LINK}}',
            false
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(150, $result['sent']);
    }

    public function testDispatchWithArrayRecipients()
    {
        $sendRecord = $this->createMockSendRecord();
        $newsletter = $this->createMockNewsletter(false);
        $siteId = 1;

        $recipient = $this->createMockRecipient(1, 'user@example.com');
        $recipientArray = ['id' => 1, 'email' => 'user@example.com'];

        $this->mockPreferenceRepository->shouldReceive('findByEmails')
            ->andReturn(collect([]));

        $this->mockSubscriberRepository->shouldReceive('findByEmails')
            ->andReturn(collect([]));

        $this->mockRecipientRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($recipient);

        $this->mockEmailService->shouldReceive('send')->once();
        $recipient->shouldReceive('markAsSent')->once();

        $this->mockRecipientRepository->shouldReceive('updateSendCounts')
            ->once();

        $result = $this->dispatcher->dispatch(
            $sendRecord,
            [$recipientArray],
            $newsletter,
            $siteId,
            '<p>Content</p>{{UNSUBSCRIBE_LINK}}',
            false
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['sent']);
    }

    public function testDispatchReplacesViewInBrowserPlaceholderPerRecipient(): void
    {
        $sendRecord = $this->createMockSendRecord();
        $newsletter = $this->createMockNewsletter(false);
        $siteId = 1;
        $snapshotToken = 'abc123def456';

        $recipient = $this->createMockRecipient(1, 'user@example.com');
        $recipient->view_token = 'recipienttoken99';

        $this->mockPreferenceRepository->shouldReceive('findByEmails')->andReturn(collect([]));
        $this->mockSubscriberRepository->shouldReceive('findByEmails')->andReturn(collect([]));
        $this->mockRecipientRepository->shouldReceive('find')->with(1)->andReturn($recipient);

        $this->mockEmailService->shouldReceive('send')
            ->once()
            ->with(
                'user@example.com',
                'Test Newsletter',
                Mockery::on(function (string $html) {
                    return str_contains($html, '/newsletter/view/abc123def456?r=recipienttoken99');
                })
            );

        $recipient->shouldReceive('markAsSent')->once();
        $this->mockRecipientRepository->shouldReceive('updateSendCounts')->once();

        $this->dispatcher->dispatch(
            $sendRecord,
            [$recipient],
            $newsletter,
            $siteId,
            '<a href="{{VIEW_IN_BROWSER_URL:' . $snapshotToken . '}}">View online</a>{{UNSUBSCRIBE_LINK}}',
            false
        );
        $this->assertTrue(true);
    }

    private function createMockSendRecord(): NewsletterSend
    {
        $send = Mockery::mock(NewsletterSend::class)->makePartial();
        $send->id = 1;
        return $send;
    }

    private function createMockNewsletter(bool $isAutomated = false): Newsletter
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->title = 'Test Newsletter';
        $newsletter->shouldReceive('isAutomated')->andReturn($isAutomated);
        return $newsletter;
    }

    private function createMockRecipient(int $id, string $email)
    {
        // Ensure we are mocking the specific Class, not a generic mock
        $recipient = Mockery::mock(NewsletterSendRecipient::class)->makePartial();
        $recipient->id = $id;
        $recipient->email = $email;
        $recipient->unsubscribe_token = null;

        // Add this to prevent the "call to update on null" or "Unexpected method" errors
        $recipient->shouldReceive('update')->andReturn(true)->byDefault();

        return $recipient;
    }
}