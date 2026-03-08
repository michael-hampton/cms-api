<?php

namespace App\Tests\Functional\Controllers\Members\Newsletters;

use App\Framework\Mail\ArrayMailer;
use App\Models\Newsletter;
use App\Models\NewsletterIssue;
use App\Models\Site;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class NewsletterIssueControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_index_returns_200_with_issues_array(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $this->createNewsletterIssue($newsletter, ['subject' => 'Issue One']);
        $this->createNewsletterIssue($newsletter, ['subject' => 'Issue Two']);

        $response = $this->getForSite("/api/newsletters/{$newsletter->id}/issues");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['issues']);
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

    public function test_index_returns_empty_array_when_no_issues_exist(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);

        $response = $this->getForSite("/api/newsletters/{$newsletter->id}/issues");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data['issues']);
        $this->assertCount(0, $data['issues']);
    }

    public function test_index_returns_404_for_nonexistent_newsletter(): void
    {
        $response = $this->getForSite('/api/newsletters/99999/issues');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_index_returns_404_for_newsletter_from_different_site(): void
    {
        $otherSite = Site::create(['name' => 'Other', 'slug' => 'other-index']);
        $newsletter = Newsletter::create([
            'title' => 'Foreign Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $otherSite->id,
        ]);

        $response = $this->getForSite("/api/newsletters/{$newsletter->id}/issues");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_index_does_not_return_issues_from_other_newsletters(): void
    {
        $newsletterA = $this->createNewsletter(['site_id' => $this->siteId]);
        $newsletterB = $this->createNewsletter(['site_id' => $this->siteId]);

        $this->createNewsletterIssue($newsletterA, ['subject' => 'A Issue']);
        $this->createNewsletterIssue($newsletterB, ['subject' => 'B Issue']);

        $response = $this->getForSite("/api/newsletters/{$newsletterA->id}/issues");

        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['issues']);
        $this->assertEquals('A Issue', $data['issues'][0]['subject']);
    }

    public function test_create_issue_returns_201_with_draft_issue(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/issues", [
            'subject' => 'April Edition',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('April Edition', $data['issue']['subject']);
        $this->assertEquals('draft', $data['issue']['status']);
        $this->assertEquals($newsletter->id, $data['issue']['newsletter_id']);
    }

    public function test_create_issue_defaults_subject_to_newsletter_title(): void
    {
        $newsletter = $this->createNewsletter([
            'title' => 'Weekly Digest',
            'site_id' => $this->siteId,
        ]);

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/issues", []);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Weekly Digest', $data['issue']['subject']);
    }

    public function test_create_issue_accepts_valid_content_blocks(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $blocks = [
            ['type' => 'heading', 'data' => ['text' => 'Hello World', 'level' => 1]],
            ['type' => 'text', 'data' => ['content' => 'Body copy here.']],
        ];

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/issues", [
            'content_blocks' => $blocks,
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['issue']['content_blocks']);
    }

    public function test_create_issue_returns_422_for_unknown_block_type(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/issues", [
            'content_blocks' => [
                ['type' => 'not-a-real-block', 'data' => []],
            ],
        ]);

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_create_issue_accepts_scheduled_at(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/issues", [
            'scheduled_at' => '2026-06-01T09:00:00',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data['issue']['scheduled_at']);
    }

    public function test_create_issue_auto_increments_issue_number(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);

        $this->postForSite("/api/newsletters/{$newsletter->id}/issues", ['subject' => 'First']);
        $this->postForSite("/api/newsletters/{$newsletter->id}/issues", ['subject' => 'Second']);

        $first = NewsletterIssue::where('subject', 'First')->first();
        $second = NewsletterIssue::where('subject', 'Second')->first();

        $this->assertEquals(1, $first->issue_number);
        $this->assertEquals(2, $second->issue_number);
    }

//    public function test_create_issue_persists_snapshot_json(): void
//    {
//        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
//        $snapshot   = ['layout' => ['layout_id' => 2], 'blocks' => [], 'metadata' => ['title' => 'Apr']];
//
//        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/issues", [
//            'snapshot_json' => $snapshot,
//        ]);
//
//        $this->assertEquals(201, $response->getStatusCode());
//        $issue = NewsletterIssue::where('newsletter_id', $newsletter->id)->first();
//        $this->assertNotNull($issue->snapshot_json);
//    }

    public function test_create_issue_returns_404_for_nonexistent_newsletter(): void
    {
        $response = $this->postForSite('/api/newsletters/99999/issues', [
            'subject' => 'Orphan Issue',
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_create_issue_returns_404_for_newsletter_from_different_site(): void
    {
        $otherSite = Site::create(['name' => 'Other', 'slug' => 'other']);
        $newsletter = Newsletter::create([
            'title' => 'Foreign Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $otherSite->id,
        ]);

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/issues", [
            'subject' => 'Sneaky Issue',
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_create_issue_persists_to_database(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);

        $this->postForSite("/api/newsletters/{$newsletter->id}/issues", [
            'subject' => 'DB Persist Test',
        ]);

        $issue = NewsletterIssue::where('newsletter_id', $newsletter->id)
            ->where('subject', 'DB Persist Test')
            ->first();

        $this->assertNotNull($issue);
        $this->assertEquals('draft', $issue->status);
        $this->assertEquals($this->siteId, $issue->site_id);
    }

    public function test_show_returns_200_with_issue(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $issue = $this->createNewsletterIssue($newsletter, ['subject' => 'Show Me']);

        $response = $this->getForSite("/api/newsletters/{$newsletter->id}/issues/{$issue->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals($issue->id, $data['issue']['id']);
        $this->assertEquals('Show Me', $data['issue']['subject']);
    }

    public function test_show_returns_404_for_nonexistent_issue(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);

        $response = $this->getForSite("/api/newsletters/{$newsletter->id}/issues/99999");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_show_returns_404_for_issue_on_different_site(): void
    {
        $otherSite = Site::create(['name' => 'Other', 'slug' => 'other-show']);
        $newsletter = Newsletter::create([
            'title' => 'Foreign',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $otherSite->id,
        ]);
        $issue = NewsletterIssue::create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $otherSite->id,
            'subject' => 'Foreign Issue',
            'status' => 'draft',
        ]);

        $response = $this->getForSite("/api/newsletters/{$newsletter->id}/issues/{$issue->id}");

        $this->assertEquals(404, $response->getStatusCode());
    }

    private function validStorePayload(array $overrides = []): array
    {
        return array_merge([
            'subject' => 'Weekly digest #1',
            'content_blocks' => [
                ['type' => 'text', 'data' => ['content' => 'Hello world']],
            ],
            'snapshot_json' => [],
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('+1 week')),
        ], $overrides);
    }

    public function testStoreRejectsSubjectExceeding255Characters(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues",
            $this->validStorePayload(['subject' => str_repeat('x', 256)])
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreRejectsContentBlocksThatIsNotAnArray(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues",
            $this->validStorePayload(['content_blocks' => 'not-an-array'])
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreRejectsSnapshotJsonThatIsNotAnArray(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues",
            $this->validStorePayload(['snapshot_json' => 'not-an-array'])
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreRejectsInvalidScheduledAtDateFormat(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues",
            $this->validStorePayload(['scheduled_at' => 'not-a-date'])
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreAcceptsNullSubject(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues",
            $this->validStorePayload(['subject' => null])
        );

        // subject is optional in CreateNewsletterIssueRequest — omitting is valid
        $this->assertResponseStatus(201, $response);
    }

    public function testStoreAcceptsEmptyContentBlocks(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues",
            $this->validStorePayload(['content_blocks' => []])
        );

        $this->assertResponseStatus(201, $response);
    }

    public function test_show_returns_404_when_issue_belongs_to_different_newsletter(): void
    {
        $newsletterA = $this->createNewsletter(['site_id' => $this->siteId]);
        $newsletterB = $this->createNewsletter(['site_id' => $this->siteId]);
        $issue = $this->createNewsletterIssue($newsletterB);

        // Issue exists and belongs to the same site, but to newsletterB not newsletterA
        $response = $this->getForSite("/api/newsletters/{$newsletterA->id}/issues/{$issue->id}");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_revert_returns_200_with_snapshot(): void
    {
        $snapshot = ['layout' => ['layout_id' => 1], 'blocks' => [], 'metadata' => ['title' => 'May']];
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $issue = $this->createNewsletterIssue($newsletter, ['snapshot_json' => json_encode($snapshot)]);

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues/{$issue->id}/revert"
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('issue', $data);
        $this->assertArrayHasKey('snapshot_json', $data);
        $this->assertEquals($issue->id, $data['issue']['id']);
    }

    public function test_revert_returns_null_snapshot_when_none_saved(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $issue = $this->createNewsletterIssue($newsletter);

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues/{$issue->id}/revert"
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertNull($data['snapshot_json']);
    }

    public function test_revert_does_not_mutate_the_newsletter(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $issue = $this->createNewsletterIssue($newsletter);

        $originalContent = $newsletter->content;

        $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues/{$issue->id}/revert"
        );

        $refreshed = Newsletter::find($newsletter->id);
        $this->assertEquals($originalContent, $refreshed->content);
    }

    public function test_revert_returns_404_for_nonexistent_issue(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues/99999/revert"
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_revert_returns_404_for_issue_on_different_site(): void
    {
        $otherSite = Site::create(['name' => 'Other', 'slug' => 'other-revert']);
        $newsletter = Newsletter::create([
            'title' => 'Foreign',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $otherSite->id,
        ]);
        $issue = NewsletterIssue::create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $otherSite->id,
            'subject' => 'Foreign Issue',
            'status' => 'draft',
        ]);

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues/{$issue->id}/revert"
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_send_issue_returns_200_with_send_summary(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $issue = $this->createNewsletterIssue($newsletter, ['status' => 'draft']);

        $this->createConfirmedSubscriber('reader@example.com');

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues/{$issue->id}/send"
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals($issue->id, $data['data']['issue_id']);
        $this->assertEquals($newsletter->id, $data['data']['newsletter_id']);
        $this->assertArrayHasKey('send_id', $data['data']);
        $this->assertArrayHasKey('sent_to', $data['data']);
    }

    public function testSendReturns409WhenIssueAlreadySent(): void
    {
        $newsletter = $this->createNewsletter();
        $issue = $this->createNewsletterIssue($newsletter, [
            'newsletter_id' => $newsletter->id,
            'status' => 'sent',
        ]);

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues/{$issue->id}/send"
        );

        $this->assertResponseStatus(409, $response);
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

    public function test_send_issue_transitions_issue_to_sent_in_database(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $issue = $this->createNewsletterIssue($newsletter, ['status' => 'draft']);

        $this->createConfirmedSubscriber('reader2@example.com');

        $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues/{$issue->id}/send"
        );

        $refreshed = NewsletterIssue::find($issue->id);
        $this->assertEquals('sent', $refreshed->status);
        $this->assertNotNull($refreshed->sent_at);
        $this->assertNotNull($refreshed->send_id);
    }

    public function test_send_issue_returns_409_when_issue_already_sent(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $issue = $this->createNewsletterIssue($newsletter, [
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues/{$issue->id}/send"
        );

        $this->assertEquals(409, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_send_issue_returns_404_for_nonexistent_newsletter(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $issue = $this->createNewsletterIssue($newsletter, ['status' => 'draft']);

        $response = $this->postForSite(
            "/api/newsletters/99999/issues/{$issue->id}/send"
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_send_issue_returns_404_for_issue_on_different_site(): void
    {
        $otherSite = Site::create(['name' => 'Other', 'slug' => 'other-send']);
        $newsletter = Newsletter::create([
            'title' => 'Foreign',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $otherSite->id,
        ]);
        $issue = NewsletterIssue::create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $otherSite->id,
            'subject' => 'Foreign Issue',
            'status' => 'draft',
        ]);

        // Request is scoped to $this->siteId, so neither the newsletter nor issue
        // should be accessible
        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues/{$issue->id}/send"
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_send_issue_returns_400_when_no_eligible_recipients(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $issue = $this->createNewsletterIssue($newsletter, ['status' => 'draft']);

        // No subscribers created — send should fail gracefully
        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues/{$issue->id}/send"
        );

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_send_ready_issue_succeeds_same_as_draft(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $issue = $this->createNewsletterIssue($newsletter, ['status' => 'ready']);

        $this->createConfirmedSubscriber('ready-reader@example.com');

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/issues/{$issue->id}/send"
        );

        $this->assertEquals(200, $response->getStatusCode());
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

    public function testManualSendRejectsInvalidSendType(): void
    {
        $newsletter = $this->createNewsletter();
        $issue = $this->createNewsletterIssue($newsletter, ['newsletter_id' => $newsletter->id]);

        $response = $this->postForSite("/api/newsletter-issues/{$issue->id}/send", [
            'send_type' => 'selected', // not a valid send_type
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function testManualSendRequiresCustomEmailsWhenSendTypeIsCustom(): void
    {
        $newsletter = $this->createNewsletter();
        $issue = $this->createNewsletterIssue($newsletter, ['newsletter_id' => $newsletter->id]);

        $response = $this->postForSite("/api/newsletter-issues/{$issue->id}/send", [
            'send_type' => 'custom',
            // custom_emails omitted
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function testManualSendReturns404ForNonExistentIssue(): void
    {
        $response = $this->postForSite("/api/newsletter-issues/99999/send", [
            'send_type' => 'all',
        ]);

        $this->assertResponseStatus(404, $response);
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

    public function test_manual_send_returns_404_for_nonexistent_issue(): void
    {
        $response = $this->postForSite('/api/newsletter-issues/99999/send', [
            'send_type' => 'all',
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_manual_send_returns_404_for_issue_on_different_site(): void
    {
        $otherSite = Site::create(['name' => 'Other', 'slug' => 'other-manual']);
        $newsletter = Newsletter::create([
            'title' => 'Foreign',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $otherSite->id,
        ]);
        $issue = NewsletterIssue::create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $otherSite->id,
            'subject' => 'Foreign Issue',
            'status' => 'draft',
        ]);

        $response = $this->postForSite("/api/newsletter-issues/{$issue->id}/send", [
            'send_type' => 'all',
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    protected function setUp(): void
    {
//        $config = include __DIR__ . '/../../../../../config/mail.php';
//        $config['driver'] = 'array';
//
//        Config::set('mail', $config);
//
//        $this->manager = MailManager::getInstance();
//        ArrayMailer::clear();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        ArrayMailer::clear();
        parent::tearDown();
    }
}