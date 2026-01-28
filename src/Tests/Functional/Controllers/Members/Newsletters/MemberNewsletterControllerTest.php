<?php

namespace App\Tests\Functional\Controllers\Members\Newsletters;

use App\Framework\Authorization\MemberAuth;
use App\Models\Member;
use App\Models\Model;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Models\NewsletterSendRecipient;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class MemberNewsletterControllerTest extends FunctionalTestCase
{
    private Model $testMember;
    private Model $testNewsletter;

    public function testToggleSubscribeToNewsletter(): void
    {
        $response = $this->postForSiteUnauthenticated('/member/newsletters/toggle', [
            'newsletter_id' => $this->testNewsletter->id,
            'subscribe' => true
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Successfully subscribed to newsletter', $data['message']);
        $this->assertTrue($data['subscribed']);

        // Verify subscription was created
        $subscriber = Subscriber::where('email', $this->testMember->email)
            ->where('newsletter_id', $this->testNewsletter->id)
            ->first();

        $this->assertNotNull($subscriber);
        $this->assertTrue($subscriber->confirmed);
        $this->assertNull($subscriber->unsubscribed_at);
    }

    public function testToggleUnsubscribeFromNewsletter(): void
    {
        // First subscribe
        Subscriber::create([
            'email' => $this->testMember->email,
            'newsletter_id' => $this->testNewsletter->id,
            'site_id' => $this->siteId,
            'confirmed' => true,
            'confirmation_token' => bin2hex(random_bytes(32)),
            'unsubscribe_token' => bin2hex(random_bytes(32)),
            'subscribed_at' => now_datetime()->format('Y-m-d H:i:s')
        ]);

        // Now unsubscribe
        $response = $this->postForSiteUnauthenticated('/member/newsletters/toggle', [
            'newsletter_id' => $this->testNewsletter->id,
            'subscribe' => false
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Successfully unsubscribed from newsletter', $data['message']);
        $this->assertFalse($data['subscribed']);

        // Verify unsubscription
        $subscriber = Subscriber::where('email', $this->testMember->email)
            ->where('newsletter_id', $this->testNewsletter->id)
            ->first();

        $this->assertNotNull($subscriber);
        $this->assertNotNull($subscriber->unsubscribed_at);
    }

    public function testToggleResubscribeAfterUnsubscribe(): void
    {
        // Create unsubscribed subscriber
        Subscriber::create([
            'email' => $this->testMember->email,
            'newsletter_id' => $this->testNewsletter->id,
            'site_id' => $this->siteId,
            'confirmed' => true,
            'confirmation_token' => bin2hex(random_bytes(32)),
            'unsubscribe_token' => bin2hex(random_bytes(32)),
            'subscribed_at' => now_datetime()->format('Y-m-d H:i:s'),
            'unsubscribed_at' => now_datetime()->format('Y-m-d H:i:s')
        ]);

        // Resubscribe
        $response = $this->postForSiteUnauthenticated('/member/newsletters/toggle', [
            'newsletter_id' => $this->testNewsletter->id,
            'subscribe' => true
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Successfully resubscribed to newsletter', $data['message']);

        // Verify resubscription
        $subscriber = Subscriber::where('email', $this->testMember->email)
            ->where('newsletter_id', $this->testNewsletter->id)
            ->first();

        $this->assertNotNull($subscriber);
        $this->assertNull($subscriber->unsubscribed_at);
    }

    public function testToggleFailsWhenNotLoggedIn(): void
    {
        MemberAuth::logout();

        $response = $this->postForSiteUnauthenticated('/member/newsletters/toggle', [
            'newsletter_id' => $this->testNewsletter->id,
            'subscribe' => true
        ]);

        $this->assertResponseStatus(401, $response);
    }

    public function testToggleFailsForPremiumNewsletterWithoutSubscription(): void
    {
        // Update newsletter to be premium
        $this->testNewsletter->update(['is_premium' => true]);

        $response = $this->postForSiteUnauthenticated('/member/newsletters/toggle', [
            'newsletter_id' => $this->testNewsletter->id,
            'subscribe' => true
        ]);

        $this->assertResponseStatus(403, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertTrue($data['requires_upgrade']);
    }

    public function testToggleSucceedsForPremiumNewsletterWithActiveSubscription(): void
    {
        // Update newsletter to be premium
        $this->testNewsletter->update(['is_premium' => true, 'slug' => 'test-newsletter']);

        // Create subscription plan
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'premium_access' => [
                ['type' => 'newsletter', 'identifier' => 'test-newsletter']
            ]
        ]);

        // Create active subscription
        $subscription = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'start_date' => now_datetime()->format('Y-m-d H:i:s'),
            'price' => $plan->price,
            'currency' => $plan->currency
        ]);

        // Grant premium access
        $subscription->grantPremiumAccess('newsletter', 'test-newsletter');

        $response = $this->postForSiteUnauthenticated('/member/newsletters/toggle', [
            'newsletter_id' => $this->testNewsletter->id,
            'subscribe' => true
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testToggleFailsWithMissingParameters(): void
    {
        $response = $this->postForSiteUnauthenticated('/member/newsletters/toggle', [
            'newsletter_id' => $this->testNewsletter->id
            // Missing 'subscribe' parameter
        ]);

        $this->assertResponseStatus(400, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Missing required parameters', $data['message']);
    }

    public function testToggleFailsForNonexistentNewsletter(): void
    {
        $response = $this->postForSiteUnauthenticated('/member/newsletters/toggle', [
            'newsletter_id' => 99999,
            'subscribe' => true
        ]);

        $this->assertResponseStatus(404, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Newsletter not found', $data['message']);
    }

    public function testViewNewsletterWithSendIdUsesCachedContent(): void
    {
        $cachedHtml = '<html><body><h1>Cached Newsletter</h1></body></html>';

        $send = NewsletterSend::create([
            'newsletter_id' => $this->testNewsletter->id,
            'sent_at' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'recipient_count' => 100,
            'content_snapshot' => [
                ['id' => 1, 'title' => 'Page 1']
            ],
            'html_snapshot' => $cachedHtml
        ]);

        $response = $this->getForSiteUnauthenticated("/newsletters/{$this->testNewsletter->id}?send_id={$send->id}");

        $this->assertResponseStatus(200, $response);
        $this->assertStringContainsString('Test Newsletter', $response->getContent());
    }

    public function testViewNewsletterWithoutSendIdGeneratesCurrentContent(): void
    {
        $response = $this->getForSiteUnauthenticated("/newsletters/{$this->testNewsletter->id}");

        $this->assertResponseStatus(200, $response);
        // Should not contain cached content but current newsletter content
    }

    public function testEditionsPageGroupsByYearAndMonth(): void
    {
        // Create sends in different months
        NewsletterSend::create([
            'newsletter_id' => $this->testNewsletter->id,
            'sent_at' => date('Y-m-d H:i:s', strtotime('2024-01-05')),
            'recipient_count' => 50,
            'content_snapshot' => []
        ]);

        NewsletterSend::create([
            'newsletter_id' => $this->testNewsletter->id,
            'sent_at' => date('Y-m-d H:i:s', strtotime('2024-01-15')),
            'recipient_count' => 60,
            'content_snapshot' => []
        ]);

        NewsletterSend::create([
            'newsletter_id' => $this->testNewsletter->id,
            'sent_at' => date('Y-m-d H:i:s', strtotime('2024-02-10')),
            'recipient_count' => 70,
            'content_snapshot' => []
        ]);

        $response = $this->getForSiteUnauthenticated("/newsletters/{$this->testNewsletter->id}");

        $this->assertResponseStatus(200, $response);
        $this->assertStringContainsString('January', $response->getContent());
        $this->assertStringContainsString('February', $response->getContent());
    }

    public function testTrackPageViewRecordsClick(): void
    {
        $send = NewsletterSend::create([
            'newsletter_id' => $this->testNewsletter->id,
            'sent_at' => now_datetime()->format('Y-m-d H:i:s'),
            'recipient_count' => 100,
            'content_snapshot' => []
        ]);

        $page = \App\Models\Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'site_id' => $this->siteId,
            'status' => 'published',
            'published_at' => now_datetime()->format('Y-m-d H:i:s')
        ]);

        $response = $this->postForSiteUnauthenticated('/newsletters/track-view', [
            'send_id' => $send->id,
            'page_id' => $page->id,
            'email' => $this->testMember->email
        ]);

        $this->assertResponseStatus(302, $response);

        // Verify tracking record was created
        $pageViewRepo = app(\App\Repositories\Newsletters\NewsletterSendPageViewRepository::class);
        $views = $pageViewRepo->getViewsForSend($send->id);

        $this->assertCount(1, $views);
        $this->assertEquals($page->id, $views[0]['page_id']);
    }

    public function testSendAnalyticsReturnsStatistics(): void
    {
        $send = NewsletterSend::create([
            'newsletter_id' => $this->testNewsletter->id,
            'sent_at' => now_datetime()->format('Y-m-d H:i:s'),
            'recipient_count' => 100,
            'recipients' => ['user1@test.com', 'user2@test.com'],
            'content_snapshot' => []
        ]);

        $page = \App\Models\Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'site_id' => $this->siteId,
            'status' => 'published',
            'published_at' => now_datetime()->format('Y-m-d H:i:s')
        ]);

        // Create some page views
        $pageViewRepo = app(\App\Repositories\Newsletters\NewsletterSendPageViewRepository::class);
        $pageViewRepo->trackPageView($send->id, $page->id, 'user1@test.com', '127.0.0.1', 'UA1');
        $pageViewRepo->trackPageView($send->id, $page->id, 'user2@test.com', '127.0.0.2', 'UA2');

        $response = $this->getForSiteUnauthenticated("/newsletters/{$this->testNewsletter->id}/sends/{$send->id}/analytics");

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['statistics']['total_clicks']);
        $this->assertEquals(2, $data['statistics']['unique_recipients']);
        $this->assertEquals(2.0, $data['statistics']['click_through_rate']); // 2/100 * 100
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->testMember = Member::create([
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'site_id' => $this->siteId,
            'email_verified_at' => now_datetime()->format('Y-m-d H:i:s')
        ]);

        $this->testNewsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'slug' => 'test-newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'interval' => 'weekly',
            'content_type' => 'manual',
            'content' => 'Test Newsletter',
        ]);

        MemberAuth::login($this->testMember);
    }

    protected function tearDown(): void
    {
        MemberAuth::logout();
        parent::tearDown();
    }


    /*public function testIndexDisplaysNewsletterSubscriptions(): void
    {
        // Create some subscriptions
        $newsletter1 = Newsletter::create([
            'title' => 'Active Newsletter',
            'slug' => 'active-newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'interval' => 'daily',
            'content_type' => 'manual'
        ]);

        $newsletter2 = Newsletter::create([
            'title' => 'Inactive Newsletter',
            'slug' => 'inactive-newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'interval' => 'monthly',
            'content_type' => 'manual'
        ]);

        Subscriber::create([
            'email' => $this->testMember->email,
            'newsletter_id' => $newsletter1->id,
            'site_id' => $this->siteId,
            'confirmed' => true,
            'confirmation_token' => bin2hex(random_bytes(32)),
            'unsubscribe_token' => bin2hex(random_bytes(32)),
            'subscribed_at' => now_datetime()->format('Y-m-d H:i:s')
        ]);

        Subscriber::create([
            'email' => $this->testMember->email,
            'newsletter_id' => $newsletter2->id,
            'site_id' => $this->siteId,
            'confirmed' => true,
            'confirmation_token' => bin2hex(random_bytes(32)),
            'unsubscribe_token' => bin2hex(random_bytes(32)),
            'subscribed_at' => now_datetime()->format('Y-m-d H:i:s'),
            'unsubscribed_at' => now_datetime()->format('Y-m-d H:i:s')
        ]);

        $response = $this->get('/member/newsletters');

        $this->assertEquals(200, $response['status']);
        $this->assertStringContainsString('Active Newsletter', $response['body']);
        $this->assertStringContainsString('Inactive Newsletter', $response['body']);
    }*/

    public function testPreviewSendsToSpecifiedEmails(): void
    {
        $page = \App\Models\Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'site_id' => $this->siteId,
            'status' => 'published',
            'published_at' => now_datetime()->format('Y-m-d H:i:s')
        ]);

        $response = $this->postForSiteUnauthenticated("/newsletters/{$this->testNewsletter->id}/preview", [
            'preview_emails' => ['preview1@example.com', 'preview2@example.com']
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['preview']);
        $this->assertGreaterThan(0, $data['data']['send_id']);
    }

    public function testPreviewFailsWithNoEmails(): void
    {
        $response = $this->postForSite("/newsletters/{$this->testNewsletter->id}/preview", [
            'preview_emails' => []
        ]);

        $this->assertResponseStatus(400, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertEquals('No preview email addresses provided', $data['error']);
    }

    public function testPreviewFailsWithInvalidEmail(): void
    {
        $response = $this->postForSite("/newsletters/{$this->testNewsletter->id}/preview", [
            'preview_emails' => ['invalid-email']
        ]);

        $this->assertResponseStatus(400, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Invalid email address', $data['error']);
    }

    public function testPreviewFailsWithTooManyEmails(): void
    {
        $emails = array_map(fn($i) => "test{$i}@example.com", range(1, 11));

        $response = $this->postForSite("/newsletters/{$this->testNewsletter->id}/preview", [
            'preview_emails' => $emails
        ]);

        $this->assertResponseStatus(400, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Maximum 10 preview recipients allowed', $data['error']);
    }

    public function testGetSendStatisticsReturnsCorrectData(): void
    {
        // Create a send record
        $send = NewsletterSend::create([
            'newsletter_id' => $this->testNewsletter->id,
            'sent_at' => now_datetime()->format('Y-m-d H:i:s'),
            'recipient_count' => 10,
            'sent_count' => 8,
            'failed_count' => 2,
            'pending_count' => 0,
            'content_snapshot' => [],
            'html_snapshot' => '<html>Test</html>'
        ]);

        // Create some recipients
        NewsletterSendRecipient::create([
            'newsletter_send_id' => $send->id,
            'email' => 'user1@example.com',
            'status' => 'sent',
            'sent_at' => now_datetime()->format('Y-m-d H:i:s')
        ]);

        NewsletterSendRecipient::create([
            'newsletter_send_id' => $send->id,
            'email' => 'user2@example.com',
            'status' => 'failed',
            'error_message' => 'SMTP error'
        ]);

        $response = $this->getForSite("/newsletters/{$this->testNewsletter->id}/sends/{$send->id}/statistics");

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('statistics', $data);
        $this->assertGreaterThanOrEqual(0, $data['statistics']['sent']);
        $this->assertGreaterThanOrEqual(0, $data['statistics']['failed']);
    }

    public function testGetSendStatisticsFailsForInvalidNewsletter(): void
    {
        $response = $this->getForSite("/newsletters/99999/sends/1/statistics");

        $this->assertResponseStatus(404, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testRetrySendRetriesFailedRecipients(): void
    {
        // Create a send record with failed recipients
        $send = NewsletterSend::create([
            'newsletter_id' => $this->testNewsletter->id,
            'sent_at' => now_datetime()->format('Y-m-d H:i:s'),
            'recipient_count' => 3,
            'sent_count' => 1,
            'failed_count' => 2,
            'pending_count' => 0,
            'content_snapshot' => [],
            'html_snapshot' => '<html>Test</html>'
        ]);

        // Create failed recipients
        NewsletterSendRecipient::create([
            'newsletter_send_id' => $send->id,
            'email' => 'failed1@example.com',
            'status' => 'failed',
            'attempts' => 1,
            'error_message' => 'SMTP error'
        ]);

        NewsletterSendRecipient::create([
            'newsletter_send_id' => $send->id,
            'email' => 'failed2@example.com',
            'status' => 'failed',
            'attempts' => 2,
            'error_message' => 'Connection timeout'
        ]);

        $response = $this->postForSite("/newsletters/{$this->testNewsletter->id}/sends/{$send->id}/retry", [
            'max_attempts' => 3
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals($send->id, $data['data']['send_id']);
        $this->assertGreaterThan(0, $data['data']['retried']);
    }

    public function testRetrySendFailsWhenNoRetryableRecipients(): void
    {
        // Create a send record with all recipients successfully sent
        $send = NewsletterSend::create([
            'newsletter_id' => $this->testNewsletter->id,
            'sent_at' => now_datetime()->format('Y-m-d H:i:s'),
            'recipient_count' => 2,
            'sent_count' => 2,
            'failed_count' => 0,
            'pending_count' => 0,
            'content_snapshot' => [],
            'html_snapshot' => '<html>Test</html>'
        ]);

        NewsletterSendRecipient::create([
            'newsletter_send_id' => $send->id,
            'email' => 'sent@example.com',
            'status' => 'sent',
            'sent_at' => now_datetime()->format('Y-m-d H:i:s')
        ]);

        $response = $this->postForSite("/newsletters/{$this->testNewsletter->id}/sends/{$send->id}/retry", [
            'max_attempts' => 3
        ]);

        $this->assertResponseStatus(400, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('No recipients available for retry', $data['error']);
    }

    public function testRetrySendFailsForInvalidSendId(): void
    {
        $response = $this->postForSite("/newsletters/{$this->testNewsletter->id}/sends/99999/retry", [
            'max_attempts' => 3
        ]);

        $this->assertResponseStatus(400, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }
}