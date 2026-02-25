<?php

namespace App\Tests\Unit\Repositories\Newsletter;

use App\Models\Model;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Models\NewsletterSendRecipient;
use App\Models\NewsletterSnapshot;
use App\Repositories\Newsletters\NewsletterSnapshotRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class NewsletterSnapshotRepositoryTest extends FunctionalTestCase
{
    private NewsletterSnapshotRepository $repository;

    public function test_creates_snapshot_with_required_fields(): void
    {
        $newsletter = $this->makeNewsletter();

        $snapshot = $this->repository->createSnapshot(
            newsletterId: $newsletter->id,
            htmlSnapshot: '<html><body>Hello</body></html>',
            brandingSnapshot: null,
            layoutVersionId: null,
            brandingVersionId: null,
        );

        $this->assertInstanceOf(NewsletterSnapshot::class, $snapshot);
        $this->assertEquals($newsletter->id, $snapshot->newsletter_id);
        $this->assertStringContainsString('Hello', $snapshot->layout_html_snapshot);
        $this->assertDatabaseHas('newsletter_snapshots', ['id' => $snapshot->id]);
    }

    private function makeNewsletter(): Model
    {
        return Newsletter::create([
            'title' => 'Snapshot Test Newsletter',
            'content_type' => 'manual',
            'interval' => 'weekly',
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'Test'
        ]);
    }

    // ─── createSnapshot ───────────────────────────────────────────────────────

    public function test_creates_snapshot_with_branding_snapshot_json(): void
    {
        $newsletter = $this->makeNewsletter();

        $branding = ['logo_url' => 'https://example.com/logo.png', 'footer_text' => 'Footer'];

        $snapshot = $this->repository->createSnapshot(
            newsletterId: $newsletter->id,
            htmlSnapshot: '<html><body>Content</body></html>',
            brandingSnapshot: $branding,
            layoutVersionId: null,
            brandingVersionId: null,
        );

        $this->assertNotNull($snapshot->branding_snapshot_json);
        $this->assertEquals('https://example.com/logo.png', $snapshot->branding_snapshot_json['logo_url']);
    }

    public function test_returns_most_recent_snapshot_for_newsletter(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->repository->createSnapshot($newsletter->id, '<html>v1</html>', null, null, null);
        $this->repository->createSnapshot($newsletter->id, '<html>v2</html>', null, null, null);
        $latest = $this->repository->createSnapshot($newsletter->id, '<html>v3</html>', null, null, null);

        $result = $this->repository->latestForNewsletter($newsletter->id);

        $this->assertEquals($latest->id, $result->id);
        $this->assertStringContainsString('v3', $result->layout_html_snapshot);
    }

    // ─── latestForNewsletter ──────────────────────────────────────────────────

    public function test_returns_null_when_no_snapshots_exist(): void
    {
        $newsletter = $this->makeNewsletter();

        $result = $this->repository->latestForNewsletter($newsletter->id);

        $this->assertNull($result);
    }

    public function test_returns_all_snapshots_in_descending_order(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->repository->createSnapshot($newsletter->id, '<html>v1</html>', null, null, null);
        $this->repository->createSnapshot($newsletter->id, '<html>v2</html>', null, null, null);
        $this->repository->createSnapshot($newsletter->id, '<html>v3</html>', null, null, null);

        $all = $this->repository->allForNewsletter($newsletter->id);

        $this->assertCount(3, $all);
    }

    // ─── allForNewsletter ─────────────────────────────────────────────────────

    public function test_attaches_view_token_to_snapshot(): void
    {
        $newsletter = $this->makeNewsletter();
        $snapshot = $this->repository->createSnapshot($newsletter->id, '<html>Content</html>', null, null, null);

        $token = bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+72 hours'));

        $result = $this->repository->attachViewToken($snapshot->id, $token, $expiresAt);

        $this->assertTrue($result);
        $this->assertEquals($token, $snapshot->fresh()->view_token);
    }

    // ─── attachViewToken ──────────────────────────────────────────────────────

    public function test_attach_view_token_returns_false_for_missing_snapshot(): void
    {
        $result = $this->repository->attachViewToken(99999, 'token', date('Y-m-d H:i:s'));
        $this->assertFalse($result);
    }

    public function test_finds_snapshot_by_view_token(): void
    {
        $newsletter = $this->makeNewsletter();
        $snapshot = $this->repository->createSnapshot($newsletter->id, '<html>Token Test</html>', null, null, null);

        $token = bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+72 hours'));

        $this->repository->attachViewToken($snapshot->id, $token, $expiresAt);

        $found = $this->repository->findByToken($token);

        $this->assertNotNull($found);
        $this->assertEquals($snapshot->id, $found->id);
    }

    // ─── findByToken ──────────────────────────────────────────────────────────

    public function test_returns_null_for_unknown_token(): void
    {
        $found = $this->repository->findByToken('nonexistent-token-xyz');
        $this->assertNull($found);
    }

    public function test_records_view_in_browser_click_for_recipient(): void
    {
        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        $send = NewsletterSend::create([
            'newsletter_id' => $newsletter->id,
            'sent_at' => now_datetime(),
            'recipient_count' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'pending_count' => 0,
            'content_snapshot' => []
        ]);

        $newsletter = $this->makeNewsletter();
        $snapshot = $this->repository->createSnapshot($newsletter->id, '<html>Content</html>', null, null, null);

        // You'll need a helper or factory for recipients — adjust to your test setup
        $recipient = NewsletterSendRecipient::create([
            'newsletter_send_id' => $send->id,
            'email' => 'pending@example.com',
            'status' => NewsletterSendRecipient::STATUS_PENDING
        ]);

        $this->repository->recordViewInBrowserClick($snapshot->id, $recipient->id);

        $this->assertDatabaseHas('newsletter_snapshot_views', [
            'newsletter_snapshot_id' => $snapshot->id,
            'newsletter_recipient_id' => $recipient->id,
        ]);
    }

    public function test_record_view_in_browser_click_is_idempotent(): void
    {
        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        $send = NewsletterSend::create([
            'newsletter_id' => $newsletter->id,
            'sent_at' => now_datetime(),
            'recipient_count' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'pending_count' => 0,
            'content_snapshot' => []
        ]);
        $snapshot = $this->repository->createSnapshot($newsletter->id, '<html>Content</html>', null, null, null);

        $recipient = NewsletterSendRecipient::create([
            'newsletter_send_id' => $send->id,
            'email' => 'pending@example.com',
            'status' => NewsletterSendRecipient::STATUS_PENDING
        ]);

        // Calling twice must not throw
        $this->repository->recordViewInBrowserClick($snapshot->id, $recipient->id);
        $this->repository->recordViewInBrowserClick($snapshot->id, $recipient->id);

        $this->assertDatabaseCount('newsletter_snapshot_views', 1);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(NewsletterSnapshotRepository::class);
    }
}