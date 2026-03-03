<?php

namespace App\Tests\Functional\Controllers\Members\Newsletters;

use App\Framework\Mail\ArrayMailer;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Config;
use App\Models\Newsletter;
use App\Models\NewsletterIssue;
use App\Models\Site;
use App\Models\Subscriber;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class NewsletterControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private $manager;

    public function test_index_returns_all_active_newsletters(): void
    {
        // Arrange
        Newsletter::create([
            'title' => 'Active Newsletter 1',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        Newsletter::create([
            'title' => 'Active Newsletter 2',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_MONTHLY,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        Newsletter::create([
            'title' => 'Inactive Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_DAILY,
            'active' => false,
            'site_id' => $this->siteId
        ]);

        // Act
        $response = $this->getForSite('/api/newsletters');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['newsletters']);
    }

    public function test_create_newsletter_successfully(): void
    {
        // Arrange
        $newsletterData = [
            'title' => 'New Test Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Test content']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'content_type' => 'manual',
            'template' => 'default'
        ];

        // Act
        $response = $this->postForSite('/api/newsletters', $newsletterData);

        // Assert
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('New Test Newsletter', $data['data']['newsletter']['title']);
        $this->assertEquals(Newsletter::INTERVAL_WEEKLY, $data['data']['newsletter']['interval']);

        // Verify in database
        $newsletter = Newsletter::where('title', 'New Test Newsletter')
            ->where('site_id', $this->siteId)
            ->first();
        $this->assertNotNull($newsletter);
    }

    public function test_create_newsletter_with_automated_content(): void
    {
        // Arrange
        $newsletterData = [
            'title' => 'Automated Newsletter',
            'interval' => Newsletter::INTERVAL_DAILY,
            'active' => true,
            'content_type' => 'auto_pages',
            'max_pages' => 5,
            'sort_by' => 'published_at',
            'sort_order' => 'desc',
            'template' => 'digest',
            'content' => 'test'
        ];

        // Act
        $response = $this->postForSite('/api/newsletters', $newsletterData);

        // Assert
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('auto_pages', $data['data']['newsletter']['content_type']);
        $this->assertEquals(5, $data['data']['newsletter']['max_pages']);
    }

    public function test_show_returns_newsletter_by_id(): void
    {
        // Arrange
        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Act
        $response = $this->getForSite('/api/newsletters/' . $newsletter->id);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals($newsletter->id, $data['newsletter']['id']);
        $this->assertEquals('Test Newsletter', $data['newsletter']['title']);
    }

    public function test_show_returns_404_for_nonexistent_newsletter(): void
    {
        // Act
        $response = $this->getForSite('/api/newsletters/99999');

        // Assert
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_show_returns_404_for_newsletter_from_different_site(): void
    {
        // Arrange
        $otherSite = Site::create([
            'name' => 'Other Site',
            'slug' => 'other-site'
        ]);
        $newsletter = Newsletter::create([
            'title' => 'Other Site Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $otherSite->id
        ]);

        // Act
        $response = $this->getForSite('/api/newsletters/' . $newsletter->id);

        // Assert
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_update_newsletter_successfully(): void
    {
        // Arrange
        $newsletter = Newsletter::create([
            'title' => 'Original Title',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'interval' => Newsletter::INTERVAL_MONTHLY,
            'active' => false
        ];

        // Act
        $response = $this->putForSite('/api/newsletters/' . $newsletter->id, $updateData);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Updated Title', $data['data']['newsletter']['title']);
        $this->assertEquals(Newsletter::INTERVAL_MONTHLY, $data['data']['newsletter']['interval']);
        $this->assertFalse($data['data']['newsletter']['active']);

        // Verify in database
        $updated = Newsletter::find($newsletter->id);
        $this->assertEquals('Updated Title', $updated->title);
    }

    public function test_update_returns_404_for_nonexistent_newsletter(): void
    {
        // Act
        $response = $this->putForSite('/api/newsletters/99999', ['title' => 'New Title']);

        // Assert
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_update_returns_404_for_newsletter_from_different_site(): void
    {
        // Arrange
        $otherSite = Site::create([
            'name' => 'Other Site',
            'slug' => 'other-site'
        ]);
        $newsletter = Newsletter::create([
            'title' => 'Other Site Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $otherSite->id
        ]);

        // Act
        $response = $this->putForSite('/api/newsletters/' . $newsletter->id, ['title' => 'Hacked']);

        // Assert
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_delete_newsletter_successfully(): void
    {
        // Arrange
        $newsletter = Newsletter::create([
            'title' => 'To Delete',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Act
        $response = $this->deleteForSite('/api/newsletters/' . $newsletter->id);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        // Verify deleted from database
        $deleted = Newsletter::find($newsletter->id);
        $this->assertNull($deleted);
    }

    public function test_delete_returns_404_for_nonexistent_newsletter(): void
    {
        // Act
        $response = $this->deleteForSite('/api/newsletters/99999');

        // Assert
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_delete_returns_404_for_newsletter_from_different_site(): void
    {
        // Arrange
        $otherSite = Site::create([
            'name' => 'Other Site',
            'slug' => 'other-site'
        ]);
        $newsletter = Newsletter::create([
            'title' => 'Other Site Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $otherSite->id
        ]);

        // Act
        $response = $this->deleteForSite('/api/newsletters/' . $newsletter->id);

        // Assert
        $this->assertEquals(404, $response->getStatusCode());

        // Verify not deleted
        $stillExists = Newsletter::find($newsletter->id);
        $this->assertNotNull($stillExists);
    }

    public function test_send_newsletter_successfully(): void
    {
        // Arrange
        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Create confirmed subscribers
        Subscriber::create([
            'email' => 'subscriber1@example.com',
            'confirmed' => true,
            'site_id' => $this->siteId,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'unsubscribe_token' => 'test-token-123'
        ]);

        Subscriber::create([
            'email' => 'subscriber2@example.com',
            'confirmed' => true,
            'site_id' => $this->siteId,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'unsubscribe_token' => 'test-token-123'
        ]);

        // Act
        $response = $this->postForSite('/api/newsletters/' . $newsletter->id . '/send');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['data']['sent_to']);

        // Verify last_sent was updated
        $updated = Newsletter::find($newsletter->id);
        $this->assertNotNull($updated->last_sent);
    }

    public function test_send_returns_error_when_no_confirmed_subscribers(): void
    {
        // Arrange
        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Create unconfirmed subscriber
        Subscriber::create([
            'email' => 'unconfirmed@example.com',
            'confirmed' => false,
            'site_id' => $this->siteId,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'unsubscribe_token' => 'test-token-123'
        ]);

        // Act
        $response = $this->postForSite('/api/newsletters/' . $newsletter->id . '/send');

        // Assert
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('No confirmed subscribers', $data['error']);
    }

    public function test_get_newsletter_subscribers_returns_all_subscribers(): void
    {
        // Arrange
        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
        ]);

        Subscriber::create([
            'email' => 'subscriber1@example.com',
            'confirmed' => true,
            'site_id' => $this->siteId,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'unsubscribe_token' => 'test-token-123'
        ]);

        Subscriber::create([
            'email' => 'subscriber2@example.com',
            'confirmed' => false,
            'site_id' => $this->siteId,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'unsubscribe_token' => 'test-token-123'
        ]);

        // Act
        $response = $this->getForSite('/api/newsletters/' . $newsletter->id . '/subscribers');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['subscribers']);
    }

    public function test_signup_sends_confirmation_email(): void
    {
        // Arrange
        ArrayMailer::clear();

        $newsletter = $this->createNewsletter(['is_default' => true]);

        $signupData = [
            'email' => 'newsubscriber@example.com',
            'first_name' => 'Test'
        ];

        // Act
        $response = $this->postForSite('/api/newsletter/signup', $signupData);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        // Verify email was sent
        $emails = ArrayMailer::getEmails();
        $this->assertCount(1, $emails);

        $email = $emails[0];
        $this->assertEquals('newsubscriber@example.com', $email['to']);
        $this->assertStringContainsString('Confirm Your Newsletter Subscription', $email['subject']);
        $this->assertStringContainsString('newsletter/confirm', $email['body']);
    }

    public function test_signup_email_includes_confirmation_token(): void
    {
        // Arrange
        ArrayMailer::clear();

        $this->createNewsletter(['is_default' => true]);

        $signupData = [
            'email' => 'tokentest@example.com'
        ];

        // Act
        $response = $this->postForSite('/api/newsletter/signup', $signupData);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $token = $data['data']['confirmation_token'];

        // Verify email contains the token
        $emails = ArrayMailer::getEmails();
        $this->assertCount(1, $emails);
        $this->assertStringContainsString($token, $emails[0]['body']);
    }

    public function test_signup_email_includes_first_name_when_provided(): void
    {
        // Arrange
        ArrayMailer::clear();

        $this->createNewsletter(['is_default' => true]);

        $signupData = [
            'email' => 'john@example.com',
            'first_name' => 'John'
        ];

        // Act
        $response = $this->postForSite('/api/newsletter/signup', $signupData);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $emails = ArrayMailer::getEmails();
        $this->assertCount(1, $emails);
        $this->assertStringContainsString('John', $emails[0]['body']);
    }

    public function test_signup_email_uses_default_greeting_when_no_name(): void
    {
        // Arrange
        ArrayMailer::clear();

        $this->createNewsletter(['is_default' => true]);

        $signupData = [
            'email' => 'anonymous@example.com'
        ];

        // Act
        $response = $this->postForSite('/api/newsletter/signup', $signupData);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $emails = ArrayMailer::getEmails();
        $this->assertCount(1, $emails);
        // Should use default greeting like "there" or similar
        $this->assertStringContainsString('there', $emails[0]['body']);
    }

    public function test_signup_creates_subscriber_successfully(): void
    {
        $this->createNewsletter(['is_default' => true]);

        // Arrange
        $signupData = [
            'email' => 'newsubscriber@example.com',
            'first_name' => 'John'
        ];

        // Act
        $response = $this->postForSite('/api/newsletter/signup', $signupData);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['subscribed']);
        $this->assertArrayHasKey('confirmation_token', $data['data']);

        // Verify in database
        $subscriber = Subscriber::where('email', 'newsubscriber@example.com')
            ->where('site_id', $this->siteId)
            ->first();
        $this->assertNotNull($subscriber);
        $this->assertTrue($subscriber->confirmed);
    }

    public function test_signup_prevents_duplicate_emails(): void
    {
        // Arrange
        Subscriber::create([
            'email' => 'existing@example.com',
            'confirmed' => true,
            'site_id' => $this->siteId,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'unsubscribe_token' => 'test-token-123'
        ]);

        // Act
        $response = $this->postForSite('/api/newsletter/signup', [
            'email' => 'existing@example.com'
        ]);

        // Assert
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_confirm_subscription_successfully(): void
    {
        // Arrange
        $subscriber = Subscriber::create([
            'email' => 'confirm@example.com',
            'confirmed' => false,
            'confirmation_token' => 'test-token-123',
            'site_id' => $this->siteId,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'unsubscribe_token' => 'test-token-123'
        ]);

        // Act
        $response = $this->postForSite('/api/newsletter/confirm', [
            'token' => 'test-token-123'
        ]);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        // Verify confirmed in database
        $confirmed = Subscriber::find($subscriber->id);
        $this->assertTrue($confirmed->confirmed);
    }

    public function test_confirm_returns_error_for_invalid_token(): void
    {
        // Act
        $response = $this->postForSite('/api/newsletter/confirm', [
            'token' => 'invalid-token'
        ]);

        // Assert
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_unsubscribe_successfully(): void
    {
        // Arrange
        $subscriber = Subscriber::create([
            'email' => 'unsubscribe@example.com',
            'confirmed' => true,
            'unsubscribe_token' => 'unsubscribe-token-123',
            'site_id' => $this->siteId,
            'subscribed_at' => date('Y-m-d H:i:s')
        ]);

        // Act
        $response = $this->postForSite('/api/newsletter/unsubscribe', [
            'token' => 'unsubscribe-token-123'
        ]);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        // Verify deleted from database
        $deleted = Subscriber::find($subscriber->id);
        $this->assertNotEmpty($deleted->unsubscribed_at);
    }

    public function test_unsubscribe_by_subscriber_id(): void
    {
        // Arrange
        $subscriber = Subscriber::create([
            'email' => 'unsubscribe2@example.com',
            'confirmed' => true,
            'site_id' => $this->siteId,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'unsubscribe_token' => 'test-token-123'
        ]);

        // Act
        $response = $this->postForSite('/api/newsletter/unsubscribe', [
            'subscriber_id' => $subscriber->id
        ]);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        // Verify deleted from database
        $deleted = Subscriber::find($subscriber->id);
        $this->assertNotEmpty($deleted->unsubscribed_at);
    }

    public function test_unsubscribe_returns_error_when_missing_token_and_id(): void
    {
        // Act
        $response = $this->postForSite('/api/newsletter/unsubscribe', []);

        // Assert
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Missing token or subscriber ID', $data['error']);
    }

    public function test_get_subscribers_returns_confirmed_subscribers(): void
    {
        // Arrange
        Subscriber::create([
            'email' => 'confirmed1@example.com',
            'confirmed' => true,
            'site_id' => $this->siteId,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'unsubscribe_token' => 'test-token-123'
        ]);

        Subscriber::create([
            'email' => 'confirmed2@example.com',
            'confirmed' => true,
            'site_id' => $this->siteId,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'unsubscribe_token' => 'test-token-123'
        ]);

        Subscriber::create([
            'email' => 'unconfirmed@example.com',
            'confirmed' => false,
            'site_id' => $this->siteId,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'unsubscribe_token' => 'test-token-123'
        ]);

        // Act
        $response = $this->getForSite('/api/newsletter/subscribers');

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(2, $data['data']['count']);
        $this->assertCount(2, $data['data']['subscribers']);
    }

    public function test_manual_send_to_all_subscribers_returns_200(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $issue = $this->createNewsletterIssue($newsletter, ['status' => 'draft']);

        $this->createConfirmedSubscriber('manual-reader@example.com');

        $response = $this->postForSite("/api/newsletter-issues/{$issue->id}/send", [
            'send_type' => 'all',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['queued']);
    }

    public function test_manual_send_to_custom_emails_returns_200(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $issue = $this->createNewsletterIssue($newsletter, ['status' => 'draft']);

        $response = $this->postForSite("/api/newsletter-issues/{$issue->id}/send", [
            'send_type' => 'custom',
            'custom_emails' => ['preview@example.com', 'editor@example.com'],
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['data']['recipients']);
    }

    public function test_manual_send_does_not_transition_issue_to_sent(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $issue = $this->createNewsletterIssue($newsletter, ['status' => 'draft']);

        $this->postForSite("/api/newsletter-issues/{$issue->id}/send", [
            'send_type' => 'custom',
            'custom_emails' => ['preview@example.com'],
        ]);

        $refreshed = NewsletterIssue::find($issue->id);
        $this->assertEquals('draft', $refreshed->status);
    }

    public function test_manual_send_returns_422_for_missing_send_type(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $issue = $this->createNewsletterIssue($newsletter);

        $response = $this->postForSite("/api/newsletter-issues/{$issue->id}/send", []);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_manual_send_returns_422_for_custom_type_without_emails(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $issue = $this->createNewsletterIssue($newsletter);

        $response = $this->postForSite("/api/newsletter-issues/{$issue->id}/send", [
            'send_type' => 'custom',
            // custom_emails intentionally omitted
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }


    protected function setUp(): void
    {
        $config = include __DIR__ . '/../../../../../config/mail.php';
        $config['driver'] = 'array';

        Config::set('mail', $config);

        $this->manager = MailManager::getInstance();
        ArrayMailer::clear();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        ArrayMailer::clear();
        parent::tearDown();
    }

    private function createNewsletterIssue(Newsletter $newsletter, array $attributes = []): NewsletterIssue
    {
        return NewsletterIssue::create(array_merge([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Test Issue',
            'status' => 'draft',
        ], $attributes));
    }

    private function createConfirmedSubscriber(string $email): void
    {
        \App\Models\Subscriber::create([
            'email' => $email,
            'confirmed' => true,
            'site_id' => $this->siteId,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'unsubscribe_token' => bin2hex(random_bytes(16)),
        ]);
    }

    public function test_pause_newsletter_successfully(): void
    {
        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'paused' => false,
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite('/api/newsletters/' . $newsletter->id . '/pause');

        // dd(strlen($response->getContent()));

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['paused']);

        $updated = Newsletter::find($newsletter->id);
        $this->assertTrue($updated->paused);
    }

    public function test_resume_newsletter_successfully(): void
    {
        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'paused' => true,
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite('/api/newsletters/' . $newsletter->id . '/pause');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertFalse($data['data']['paused']);
    }

    public function test_send_returns_error_when_newsletter_is_paused(): void
    {
        $newsletter = Newsletter::create([
            'title' => 'Paused Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'paused' => true,
            'site_id' => $this->siteId
        ]);

        Subscriber::create([
            'email' => 'subscriber@example.com',
            'confirmed' => true,
            'site_id' => $this->siteId,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'unsubscribe_token' => 'test-token-paused'
        ]);

        $response = $this->postForSite('/api/newsletters/' . $newsletter->id . '/send');

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('paused', $data['error']);
    }

    public function test_pause_returns_404_for_nonexistent_newsletter(): void
    {
        $response = $this->postForSite('/api/newsletters/99999/pause');
        $this->assertEquals(404, $response->getStatusCode());
    }
}