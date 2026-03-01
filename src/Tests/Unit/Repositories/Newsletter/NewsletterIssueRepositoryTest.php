<?php

namespace App\Tests\Unit\Repositories\Newsletter;

use App\Models\NewsletterIssue;
use App\Models\NewsletterSend;
use App\Repositories\Newsletters\NewsletterIssueRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class NewsletterIssueRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private NewsletterIssueRepository $repository;

    public function test_find_returns_issue_by_id(): void
    {
        $newsletter = $this->createNewsletter();
        $issue = NewsletterIssue::create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Weekly Digest',
            'status' => 'draft',
        ]);

        $found = $this->repository->find($issue->id);

        $this->assertNotNull($found);
        $this->assertEquals($issue->id, $found->id);
        $this->assertEquals('Weekly Digest', $found->subject);
    }

    // =========================================================================
    // find()
    // =========================================================================

    public function test_find_returns_null_for_nonexistent_id(): void
    {
        $found = $this->repository->find(99999);

        $this->assertNull($found);
    }

    public function test_create_persists_a_new_issue(): void
    {
        $newsletter = $this->createNewsletter();

        $issue = $this->repository->create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Issue Subject',
            'content_blocks' => [['type' => 'text', 'data' => ['content' => 'Hello']]],
            'status' => 'draft',
            'scheduled_at' => null,
        ]);

        $this->assertInstanceOf(NewsletterIssue::class, $issue);
        $this->assertNotNull($issue->id);
        $this->assertEquals('Issue Subject', $issue->subject);
        $this->assertEquals('draft', $issue->status);
        $this->assertEquals($newsletter->id, $issue->newsletter_id);
        $this->assertEquals($this->siteId, $issue->site_id);
    }

    // =========================================================================
    // create()
    // =========================================================================

    public function test_create_persists_content_blocks_as_array(): void
    {
        $newsletter = $this->createNewsletter();
        $blocks = [
            ['type' => 'heading', 'data' => ['text' => 'Hello']],
            ['type' => 'text', 'data' => ['content' => 'World']],
        ];

        $issue = $this->repository->create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Block Issue',
            'content_blocks' => $blocks,
            'status' => 'draft',
        ]);

        $fresh = $this->repository->find($issue->id);
        $this->assertIsArray($fresh->content_blocks);
        $this->assertCount(2, $fresh->content_blocks);
        $this->assertEquals('heading', $fresh->content_blocks[0]['type']);
    }

    public function test_create_allows_null_content_blocks(): void
    {
        $newsletter = $this->createNewsletter();

        $issue = $this->repository->create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Empty Draft',
            'content_blocks' => null,
            'status' => 'draft',
        ]);

        $this->assertNull($issue->content_blocks);
    }

    public function test_create_persists_scheduled_at(): void
    {
        $newsletter = $this->createNewsletter();
        $scheduledAt = '2026-04-01 09:00:00';

        $issue = $this->repository->create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Scheduled Issue',
            'status' => 'draft',
            'scheduled_at' => $scheduledAt,
        ]);

        $fresh = $this->repository->find($issue->id);
        $this->assertNotNull($fresh->scheduled_at);
        $this->assertEquals(
            '2026-04-01 09:00:00',
            $fresh->scheduled_at->format('Y-m-d H:i:s')
        );
    }

    public function test_update_changes_specified_fields(): void
    {
        $newsletter = $this->createNewsletter();
        $issue = NewsletterIssue::create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Original Subject',
            'status' => 'draft',
        ]);

        $updated = $this->repository->update($issue->id, [
            'subject' => 'Updated Subject',
            'status' => 'ready',
        ]);

        $this->assertEquals('Updated Subject', $updated->subject);
        $this->assertEquals('ready', $updated->status);

        // Confirm persisted
        $fresh = $this->repository->find($issue->id);
        $this->assertEquals('Updated Subject', $fresh->subject);
        $this->assertEquals('ready', $fresh->status);
    }

    // =========================================================================
    // update()
    // =========================================================================

    public function test_update_can_set_send_id_and_sent_at(): void
    {
        $newsletter = $this->createNewsletter();
        $issue = NewsletterIssue::create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'About to Send',
            'status' => 'ready',
        ]);

        $send = NewsletterSend::create([
            'newsletter_id' => $newsletter->id,
            'sent_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'recipient_count' => 100
        ]);

        $sentAt = date('Y-m-d H:i:s');

        $updated = $this->repository->update($issue->id, [
            'status' => 'sent',
            'send_id' => $send->id,
            'sent_at' => $sentAt,
        ]);

        $this->assertEquals('sent', $updated->status);
        $this->assertEquals($send->id, $updated->send_id);
        $this->assertNotNull($updated->sent_at);
    }

    public function test_update_returns_fresh_model_after_changes(): void
    {
        $newsletter = $this->createNewsletter();
        $issue = NewsletterIssue::create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Stale',
            'status' => 'draft',
        ]);

        $returned = $this->repository->update($issue->id, ['subject' => 'Fresh']);

        // The returned model must reflect the new value, not the pre-update state
        $this->assertEquals('Fresh', $returned->subject);
    }

    public function test_find_by_newsletter_returns_only_matching_issues(): void
    {
        $newsletter = $this->createNewsletter();
        $otherNewsletter = $this->createNewsletter();

        NewsletterIssue::create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Issue A',
            'status' => 'draft',
        ]);
        NewsletterIssue::create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Issue B',
            'status' => 'sent',
        ]);
        NewsletterIssue::create([
            'newsletter_id' => $otherNewsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Other Newsletter Issue',
            'status' => 'draft',
        ]);

        $results = $this->repository->findByNewsletter($newsletter->id, $this->siteId);

        $this->assertCount(2, $results);
        foreach ($results as $issue) {
            $this->assertEquals($newsletter->id, $issue->newsletter_id);
        }
    }

    // =========================================================================
    // findByNewsletter()
    // =========================================================================

    public function test_find_by_newsletter_scopes_by_site_id(): void
    {
        $newsletter = $this->createNewsletter();
        $otherSite = $this->createSite();

        NewsletterIssue::create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'This Site Issue',
            'status' => 'draft',
        ]);
        NewsletterIssue::create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $otherSite->id,
            'subject' => 'Other Site Issue',
            'status' => 'draft',
        ]);

        $results = $this->repository->findByNewsletter($newsletter->id, $this->siteId);

        $this->assertCount(1, $results);
        $this->assertEquals('This Site Issue', $results->first()->subject);
    }

    public function test_find_by_newsletter_returns_newest_first(): void
    {
        $newsletter = $this->createNewsletter();

        $first = NewsletterIssue::create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Oldest',
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        ]);
        $second = NewsletterIssue::create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Newest',
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $results = $this->repository->findByNewsletter($newsletter->id, $this->siteId);

        $this->assertEquals('Newest', $results->first()->subject);
    }

    public function test_find_by_newsletter_returns_empty_collection_when_none_exist(): void
    {
        $newsletter = $this->createNewsletter();

        $results = $this->repository->findByNewsletter($newsletter->id, $this->siteId);

        $this->assertCount(0, $results);
    }

    public function test_find_sendable_returns_draft_and_ready_only(): void
    {
        $newsletter = $this->createNewsletter();

        foreach (['draft', 'ready', 'sent'] as $status) {
            NewsletterIssue::create([
                'newsletter_id' => $newsletter->id,
                'site_id' => $this->siteId,
                'subject' => "Issue {$status}",
                'status' => $status,
            ]);
        }

        $results = $this->repository->findSendableByNewsletter($newsletter->id);

        $this->assertCount(2, $results);
        foreach ($results as $issue) {
            $this->assertContains($issue->status, ['draft', 'ready']);
        }
    }

    // =========================================================================
    // findSendableByNewsletter()
    // =========================================================================

    public function test_find_sendable_excludes_sent_issues(): void
    {
        $newsletter = $this->createNewsletter();

        NewsletterIssue::create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Already Sent',
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
        ]);

        $results = $this->repository->findSendableByNewsletter($newsletter->id);

        $this->assertCount(0, $results);
    }

    public function test_find_sendable_returns_empty_when_no_issues(): void
    {
        $newsletter = $this->createNewsletter();

        $results = $this->repository->findSendableByNewsletter($newsletter->id);

        $this->assertCount(0, $results);
    }

    public function test_is_draft_returns_true_for_draft_status(): void
    {
        $newsletter = $this->createNewsletter();
        $issue = $this->repository->create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Draft',
            'status' => 'draft',
        ]);

        $this->assertTrue($issue->isDraft());
        $this->assertFalse($issue->isReady());
        $this->assertFalse($issue->isSent());
        $this->assertTrue($issue->isSendable());
    }

    // =========================================================================
    // Model status helpers
    // =========================================================================

    public function test_is_ready_returns_true_for_ready_status(): void
    {
        $newsletter = $this->createNewsletter();
        $issue = $this->repository->create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Ready',
            'status' => 'ready',
        ]);

        $this->assertTrue($issue->isReady());
        $this->assertFalse($issue->isDraft());
        $this->assertFalse($issue->isSent());
        $this->assertTrue($issue->isSendable());
    }

    public function test_is_sent_returns_true_for_sent_status(): void
    {
        $newsletter = $this->createNewsletter();
        $issue = $this->repository->create([
            'newsletter_id' => $newsletter->id,
            'site_id' => $this->siteId,
            'subject' => 'Sent',
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
        ]);

        $this->assertTrue($issue->isSent());
        $this->assertFalse($issue->isDraft());
        $this->assertFalse($issue->isReady());
        $this->assertFalse($issue->isSendable());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new NewsletterIssueRepository();
    }
}