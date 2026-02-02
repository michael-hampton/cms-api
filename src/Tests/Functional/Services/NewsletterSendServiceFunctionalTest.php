<?php

namespace App\Tests\Functional\Services;

use App\Framework\Container;
use App\Models\Member;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Models\NewsletterSendRecipient;
use App\Models\Subscriber;
use App\Models\MemberSubscriptionPreference;
use App\Services\Newsletter\NewsletterSendService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class NewsletterSendServiceFunctionalTest extends FunctionalTestCase
{
    use CreatesTestData;

    private NewsletterSendService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = Container::getInstance()->resolve(NewsletterSendService::class);
    }

    public function testSendNewsletterCreatesRecordsInDatabase()
    {
        $siteId = 1;

        // Create newsletter
        $newsletter = Newsletter::create([
            'site_id' => $siteId,
            'title' => 'Test Newsletter',
            'interval' => 'weekly',
            'content' => json_encode([
                ['type' => 'paragraph', 'content' => 'Test content']
            ]),
            'status' => 'active'
        ]);

        // Create subscribers
        Subscriber::create([
            'site_id' => $siteId,
            'email' => 'subscriber1@example.com',
            'status' => 'confirmed',
            'unsubscribe_token' => 'token1',
            'subscribed_at' => now_datetime(),
            'confirmed' => true
        ]);

        Subscriber::create([
            'site_id' => $siteId,
            'email' => 'subscriber2@example.com',
            'status' => 'confirmed',
            'unsubscribe_token' => 'token2',
            'subscribed_at' => now_datetime(),
            'confirmed' => true
        ]);

        $this->createPage();

        $result = $this->service->sendNewsletter($newsletter, $siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['recipients']);

        // Verify send record created
        $send = NewsletterSend::where('newsletter_id', $newsletter->id)->first();
        $this->assertNotNull($send);
        $this->assertEquals(2, $send->recipient_count);
        $this->assertEquals(2, $send->sent_count);
        $this->assertEquals(0, $send->failed_count);

        // Verify recipients created
        $recipients = NewsletterSendRecipient::where('newsletter_send_id', $send->id)->get();
        $this->assertCount(2, $recipients);

        foreach ($recipients as $recipient) {
            $this->assertEquals('sent', $recipient->status);
            $this->assertNotNull($recipient->sent_at);
        }

        // Verify newsletter last_sent updated
        $newsletter = $newsletter->fresh();
        $this->assertNotNull($newsletter->last_sent);
    }

    public function testSendNewsletterFiltersOptedOutMembers()
    {
        $siteId = 1;

        $newsletter = Newsletter::create([
            'site_id' => $siteId,
            'title' => 'Test Newsletter',
            'interval' => 'weekly',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Test']]),
            'status' => 'active'
        ]);

        // Create member who wants newsletters
        $member1 = $this->createMember();

        Subscriber::create([
            'site_id' => $siteId,
            'email' => $member1->email,
            'status' => 'confirmed',
            'unsubscribe_token' => 'token2',
            'subscribed_at' => now_datetime(),
            'confirmed' => true
        ]);

        MemberSubscriptionPreference::create([
            'member_id' => $member1->id,
            'site_id' => $siteId,
            'newsletter_frequency' => 'weekly',
            'newsletter_opt_out' => false,
            'unsubscribe_token' => 'test'
        ]);

        // Create member who opted out
        $member2 = $this->createMember();

        Subscriber::create([
            'site_id' => $siteId,
            'email' => $member2->email,
            'status' => 'confirmed',
            'unsubscribe_token' => 'token2',
            'subscribed_at' => now_datetime(),
            'confirmed' => true
        ]);

        MemberSubscriptionPreference::create([
            'member_id' => $member2->id,
            'site_id' => $siteId,
            'newsletter_frequency' => 'weekly',
            'newsletter_opt_out' => true,
            'unsubscribe_token' => 'test',
        ]);

        $page = $this->createPage();

        $result = $this->service->sendNewsletter($newsletter, $siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);
        $this->assertCount(1, $result['skipped']);
        $this->assertEquals($member2->email, $result['skipped'][0]['email']);
    }

    public function testPreviewNewsletterDoesNotAffectRealRecords()
    {
        $siteId = 1;

        $newsletter = Newsletter::create([
            'site_id' => $siteId,
            'title' => 'Test Newsletter',
            'interval' => 'weekly',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Preview']]),
            'status' => 'active'
        ]);

        $this->createPage();

        $result = $this->service->previewNewsletter(
            $newsletter,
            ['preview@example.com'],
            $siteId
        );

        $this->assertTrue($result['success']);

        // Verify send record is marked as preview
        $send = NewsletterSend::find($result['send_id']);
        $this->assertTrue($send->is_preview);

        // Verify newsletter last_sent NOT updated
        $newsletter = $newsletter->fresh();
        $this->assertNull($newsletter->last_sent);
    }

    public function testRetrySendOnlyRetriesFailedRecipients()
    {
        $siteId = 1;

        $newsletter = Newsletter::create([
            'site_id' => $siteId,
            'title' => 'Test Newsletter',
            'interval' => 'weekly',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Test']]),
            'status' => 'active'
        ]);

        $send = NewsletterSend::create([
            'newsletter_id' => $newsletter->id,
            'sent_at' => now(),
            'recipient_count' => 2,
            'sent_count' => 1,
            'failed_count' => 1,
            'pending_count' => 0,
            'html_snapshot' => '<p>Test</p>'
        ]);

        // Create one sent and one failed recipient
        $sent = NewsletterSendRecipient::create([
            'newsletter_send_id' => $send->id,
            'email' => 'success@example.com',
            'status' => 'sent',
            'sent_at' => now(),
            'attempts' => 1
        ]);

        $failed = NewsletterSendRecipient::create([
            'newsletter_send_id' => $send->id,
            'email' => 'failed@example.com',
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => 'SMTP error',
            'attempts' => 1
        ]);

        $result = $this->service->retrySend($send->id, 3, $siteId);

        // Should only retry the failed one
        $sent = $sent->fresh();
        $failed = $failed->fresh();

        $this->assertEquals('sent', $sent->status);
        $this->assertEquals(1, $sent->attempts);

        // Failed recipient should have been retried
        $this->assertEquals(2, $failed->attempts);
    }

    public function testSendDueNewslettersProcessesMultiple()
    {
        $siteId = 1;

        // Create two due newsletters
        $newsletter1 = Newsletter::create([
            'site_id' => $siteId,
            'title' => 'Newsletter 1',
            'interval' => 'weekly',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content 1']]),
            'status' => 'active',
            'last_sent' => now_datetime()->subWeeks(1)
        ]);

        $newsletter2 = Newsletter::create([
            'site_id' => $siteId,
            'title' => 'Newsletter 2',
            'interval' => 'daily',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content 2']]),
            'status' => 'active',
            'last_sent' => now_datetime()->subDays(1)
        ]);

        // Create subscriber
        Subscriber::create([
            'site_id' => $siteId,
            'email' => 'subscriber@example.com',
            'status' => 'confirmed',
            'subscribed_at' => now_datetime(),
            'unsubscribe_token' => 'token1',
            'confirmed' => true
        ]);

        $this->createPage(['published_at' => now_datetime()]);

        $results = $this->service->sendDueNewsletters($siteId);

        $this->assertCount(2, $results);

        foreach ($results as $result) {
            $this->assertTrue($result['success']);
            $this->assertGreaterThan(0, $result['recipients']);
        }
    }

    public function testSendNewsletterPreventsDoubleSend()
    {
        $siteId = 1;

        $newsletter = Newsletter::create([
            'site_id' => $siteId,
            'title' => 'Recent Send',
            'interval' => 'weekly',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Test']]),
            'status' => 'active',
            'last_sent' => now_datetime()->subMinutes(30) // Sent 30 minutes ago
        ]);

        Subscriber::create([
            'site_id' => $siteId,
            'email' => 'test@example.com',
            'status' => 'confirmed',
            'subscribed_at' => now_datetime(),
            'unsubscribe_token' => 'token1',
            'confirmed' => true
        ]);

        $result = $this->service->sendNewsletter($newsletter, $siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Newsletter already sent recently', $result['error']);
    }
}