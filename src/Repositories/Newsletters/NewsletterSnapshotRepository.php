<?php

namespace App\Repositories\Newsletters;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\NewsletterSnapshot;
use App\Repositories\Repository;

class NewsletterSnapshotRepository extends Repository
{
    protected function getModelClass(): string
    {
        return NewsletterSnapshot::class;
    }

    public function createSnapshot(
        int     $newsletterId,
        string  $htmlSnapshot,
        ?array  $brandingSnapshot,
        ?int    $layoutVersionId,
        ?int    $brandingVersionId,
        ?string $viewToken = null,
        ?string $viewTokenExpiresAt = null
    ): Model
    {
        return NewsletterSnapshot::create([
            'newsletter_id' => $newsletterId,
            'layout_version_id' => $layoutVersionId,
            'branding_version_id' => $brandingVersionId,
            'layout_html_snapshot' => $htmlSnapshot,
            'branding_snapshot_json' => $brandingSnapshot,
            'view_token' => $viewToken,
            'view_token_expires_at' => $viewTokenExpiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function findByToken(string $token): ?NewsletterSnapshot
    {
        return NewsletterSnapshot::findByToken($token);
    }

    public function latestForNewsletter(int $newsletterId): ?NewsletterSnapshot
    {
        return NewsletterSnapshot::where('newsletter_id', $newsletterId)
            ->orderByDesc('id')
            ->first();
    }

    public function allForNewsletter(int $newsletterId): Collection
    {
        return NewsletterSnapshot::where('newsletter_id', $newsletterId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function attachViewToken(int $snapshotId, string $token, string $expiresAt): bool
    {
        $snapshot = NewsletterSnapshot::find($snapshotId);

        if (!$snapshot) {
            return false;
        }

        $snapshot->view_token = $token;
        $snapshot->view_token_expires_at = $expiresAt;
        return $snapshot->save();
    }

    public function recordViewInBrowserClick(int $snapshotId, int $recipientId): void
    {
        // Use an insert-or-ignore pattern so duplicate clicks don't throw.
        // If you have a dedicated tracking table, insert there instead.
        Database::table('newsletter_snapshot_views')->insertOrIgnore([
            'newsletter_snapshot_id' => $snapshotId,
            'newsletter_recipient_id' => $recipientId,
            'viewed_at' => now_datetime()->toDateTimeString(),
        ]);
    }
}