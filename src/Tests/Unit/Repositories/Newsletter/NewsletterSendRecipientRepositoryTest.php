<?php

namespace App\Tests\Unit\Repositories\Newsletter;

use App\Models\Model;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Models\NewsletterSendRecipient;
use App\Repositories\Newsletters\NewsletterSendRecipientRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class NewsletterSendRecipientRepositoryTest extends FunctionalTestCase
{
    private NewsletterSendRecipientRepository $repository;
    private NewsletterSendRepository $newsletterSendRepository;
    private Model $send;

    public function testCreateRecipients(): void
    {
        $emails = ['test1@example.com', 'test2@example.com', 'test3@example.com'];

        $recipients = $this->repository->createRecipients($this->send->id, $emails);

        $this->assertCount(3, $recipients);
        $this->assertEquals('test1@example.com', $recipients[0]->email);
        $this->assertEquals(NewsletterSendRecipient::STATUS_PENDING, $recipients[0]->status);
    }

    public function testGetPendingRecipients(): void
    {
        NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'pending@example.com',
            'status' => NewsletterSendRecipient::STATUS_PENDING
        ]);

        NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'sent@example.com',
            'status' => NewsletterSendRecipient::STATUS_SENT
        ]);

        $pending = $this->repository->getPendingRecipients($this->send->id);

        $this->assertCount(1, $pending);
        $this->assertEquals('pending@example.com', $pending[0]['email']);
    }

    public function testGetFailedRecipients(): void
    {
        NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'failed@example.com',
            'status' => NewsletterSendRecipient::STATUS_FAILED,
            'error_message' => 'SMTP error'
        ]);

        $failed = $this->repository->getFailedRecipients($this->send->id);

        $this->assertCount(1, $failed);
        $this->assertEquals('failed@example.com', $failed[0]['email']);
        $this->assertEquals('SMTP error', $failed[0]['error_message']);
    }

    public function testGetRetryableRecipients(): void
    {
        // Failed with 1 attempt - retryable
        NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'retryable@example.com',
            'status' => NewsletterSendRecipient::STATUS_FAILED,
            'attempts' => 1
        ]);

        // Failed with 3 attempts - not retryable
        NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'exhausted@example.com',
            'status' => NewsletterSendRecipient::STATUS_FAILED,
            'attempts' => 3
        ]);

        $retryable = $this->repository->getRetryableRecipients($this->send->id, 3);

        $this->assertCount(1, $retryable);
        $this->assertEquals('retryable@example.com', $retryable[0]['email']);
    }

    public function testGetStatistics(): void
    {
        // Create various recipients
        NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'sent1@example.com',
            'status' => NewsletterSendRecipient::STATUS_SENT,
            'attempts' => 1
        ]);

        NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'sent2@example.com',
            'status' => NewsletterSendRecipient::STATUS_SENT,
            'attempts' => 2
        ]);

        NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'failed@example.com',
            'status' => NewsletterSendRecipient::STATUS_FAILED,
            'attempts' => 1
        ]);

        NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'pending@example.com',
            'status' => NewsletterSendRecipient::STATUS_PENDING,
            'attempts' => 0
        ]);

        $stats = $this->repository->getStatistics($this->send->id);

        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(2, $stats['sent']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertEquals(1, $stats['pending']);
        $this->assertEquals(1.5, $stats['avg_attempts']);
    }

    public function testUpdateSendCounts(): void
    {
        // Create recipients
        NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'sent@example.com',
            'status' => NewsletterSendRecipient::STATUS_SENT
        ]);

        NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'failed@example.com',
            'status' => NewsletterSendRecipient::STATUS_FAILED
        ]);

        $this->repository->updateSendCounts($this->send->id);

        $send = NewsletterSend::find($this->send->id);

        $this->assertEquals(1, $send->sent_count);
        $this->assertEquals(1, $send->failed_count);
        $this->assertEquals(0, $send->pending_count);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->newsletterSendRepository = new NewsletterSendRepository();
        $this->repository = new NewsletterSendRecipientRepository($this->newsletterSendRepository);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        $this->send = NewsletterSend::create([
            'newsletter_id' => $newsletter->id,
            'sent_at' => now_datetime(),
            'recipient_count' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'pending_count' => 0,
            'content_snapshot' => []
        ]);
    }
}