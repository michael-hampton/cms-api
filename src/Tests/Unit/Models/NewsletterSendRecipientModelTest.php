<?php

namespace App\Tests\Unit\Models;

use App\Models\Model;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Models\NewsletterSendRecipient;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class NewsletterSendRecipientModelTest extends FunctionalTestCase
{
    private Model $send;

    public function testMarkAsSent(): void
    {
        $recipient = NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'test@example.com',
            'status' => NewsletterSendRecipient::STATUS_PENDING,
            'attempts' => 0
        ]);

        $recipient->markAsSent();

        $this->assertEquals(NewsletterSendRecipient::STATUS_SENT, $recipient->status);
        $this->assertEquals(1, $recipient->attempts);
        $this->assertNotNull($recipient->sent_at);
        $this->assertNotNull($recipient->last_attempt_at);
    }

    public function testMarkAsFailed(): void
    {
        $recipient = NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'test@example.com',
            'status' => NewsletterSendRecipient::STATUS_PENDING,
            'attempts' => 0
        ]);

        $recipient->markAsFailed('SMTP connection failed');

        $this->assertEquals(NewsletterSendRecipient::STATUS_FAILED, $recipient->status);
        $this->assertEquals('SMTP connection failed', $recipient->error_message);
        $this->assertEquals(1, $recipient->attempts);
        $this->assertNotNull($recipient->last_attempt_at);
    }

    public function testCanRetry(): void
    {
        $recipient = NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'test@example.com',
            'status' => NewsletterSendRecipient::STATUS_FAILED,
            'attempts' => 1
        ]);

        $this->assertTrue($recipient->canRetry(3));

        $recipient->attempts = 3;
        $recipient->save();

        $this->assertFalse($recipient->canRetry(3));
    }

    public function testSentRecipientCannotRetry(): void
    {
        $recipient = NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'test@example.com',
            'status' => NewsletterSendRecipient::STATUS_SENT,
            'attempts' => 1
        ]);

        $this->assertFalse($recipient->canRetry());
    }

    public function testScopePending(): void
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

        $pending = NewsletterSendRecipient::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('pending@example.com', $pending->first()->email);
    }

    public function testScopeFailed(): void
    {
        NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'failed@example.com',
            'status' => NewsletterSendRecipient::STATUS_FAILED
        ]);

        NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'sent@example.com',
            'status' => NewsletterSendRecipient::STATUS_SENT
        ]);

        $failed = NewsletterSendRecipient::failed()->get();

        $this->assertCount(1, $failed);
        $this->assertEquals('failed@example.com', $failed->first()->email);
    }

    public function testScopeSent(): void
    {
        NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'sent@example.com',
            'status' => NewsletterSendRecipient::STATUS_SENT
        ]);

        NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'pending@example.com',
            'status' => NewsletterSendRecipient::STATUS_PENDING
        ]);

        $sent = NewsletterSendRecipient::sent()->get();

        $this->assertCount(1, $sent);
        $this->assertEquals('sent@example.com', $sent->first()->email);
    }

    public function testSendRelationship(): void
    {
        $recipient = NewsletterSendRecipient::create([
            'newsletter_send_id' => $this->send->id,
            'email' => 'test@example.com',
            'status' => NewsletterSendRecipient::STATUS_PENDING
        ]);

        $this->assertNotNull($recipient->send());
        $this->assertEquals($this->send->id, $recipient->send->id);
    }

    protected function setUp(): void
    {
        parent::setUp();

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