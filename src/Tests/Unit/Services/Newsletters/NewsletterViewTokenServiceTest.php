<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Models\Model;
use App\Models\Newsletter;
use App\Models\NewsletterSnapshot;
use App\Repositories\Newsletters\NewsletterSnapshotRepository;
use App\Services\Newsletter\NewsletterViewTokenService;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class NewsletterViewTokenServiceTest extends RepositoryTestCase
{
    private NewsletterViewTokenService $service;
    private NewsletterSnapshotRepository $snapshotRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->snapshotRepository = app(NewsletterSnapshotRepository::class);
        $this->service = new NewsletterViewTokenService($this->snapshotRepository, $this->database);
    }

    private function makeSnapshot(int $newsletterId): NewsletterSnapshot
    {
        return $this->snapshotRepository->createSnapshot(
            newsletterId: $newsletterId,
            htmlSnapshot: '<html><body>Test</body></html>',
            brandingSnapshot: null,
            layoutVersionId: null,
            brandingVersionId: null,
        );
    }

    private function makeNewsletter(): Model
    {
        return Newsletter::create([
            'title' => 'Token Test Newsletter',
            'content_type' => 'manual',
            'interval' => 'weekly',
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'Test'
        ]);
    }

    public function test_generates_token_for_latest_snapshot(): void
    {
        $newsletter = $this->makeNewsletter();
        $this->makeSnapshot($newsletter->id);

        $token = $this->service->generateForNewsletter($newsletter->id);

        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function test_token_resolves_to_correct_snapshot(): void
    {
        $newsletter = $this->makeNewsletter();
        $snapshot = $this->makeSnapshot($newsletter->id);

        $token = $this->service->generateForSnapshot($snapshot->id);

        $resolved = $this->service->resolveSnapshot($token);

        $this->assertNotNull($resolved);
        $this->assertEquals($snapshot->id, $resolved->id);
    }

    public function test_returns_null_for_invalid_token(): void
    {
        $resolved = $this->service->resolveSnapshot('not-a-valid-token');

        $this->assertNull($resolved);
    }

    public function test_returns_null_for_expired_token(): void
    {
        $newsletter = $this->makeNewsletter();
        $snapshot = $this->makeSnapshot($newsletter->id);

        // Manually attach an already-expired token
        $this->snapshotRepository->attachViewToken(
            $snapshot->id,
            'expired-token-abc',
            date('Y-m-d H:i:s', strtotime('-1 hour'))
        );

        $resolved = $this->service->resolveSnapshot('expired-token-abc');

        $this->assertNull($resolved);
    }

    public function test_throws_when_no_snapshot_exists(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->expectException(\RuntimeException::class);

        $this->service->generateForNewsletter($newsletter->id);
    }

    public function test_throws_when_snapshot_id_is_invalid(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service->generateForSnapshot(99999);
    }

    public function test_builds_correct_view_url(): void
    {
        $url = $this->service->buildViewUrl('abc123');

        $this->assertStringContainsString('/newsletter/view/abc123', $url);
    }

    public function test_token_is_unique_per_generation(): void
    {
        $newsletter = $this->makeNewsletter();
        $snapshot = $this->makeSnapshot($newsletter->id);

        $token1 = $this->service->generateForSnapshot($snapshot->id);
        $token2 = $this->service->generateForSnapshot($snapshot->id);

        $this->assertNotEquals($token1, $token2);
    }

    public function test_token_generation_uses_transaction(): void
    {
        $newsletter = $this->makeNewsletter();
        $snapshot = $this->makeSnapshot($newsletter->id);

        $token = $this->service->generateForSnapshot($snapshot->id);

        // Verify token was persisted atomically
        $found = $this->snapshotRepository->findByToken($token);
        $this->assertNotNull($found);
        $this->assertEquals($snapshot->id, $found->id);
    }
}