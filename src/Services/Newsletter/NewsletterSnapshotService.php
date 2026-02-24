<?php

namespace App\Services\Newsletter;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Newsletter;
use App\Models\NewsletterSnapshot;
use App\Repositories\Newsletters\NewsletterBrandingRepository;
use App\Repositories\Newsletters\NewsletterSnapshotRepository;

/**
 * Creates and retrieves newsletter HTML snapshots.
 * Snapshots freeze both layout and branding at the time of publication —
 * they are the source of truth for "what was sent".
 */
class NewsletterSnapshotService
{
    public function __construct(
        private readonly NewsletterSnapshotRepository $snapshotRepository,
        private readonly NewsletterBrandingRepository $brandingRepository,
        private readonly BrandingRendererService      $brandingRenderer,
        private readonly LayoutRendererService        $layoutRenderer,
        private readonly Database                     $database
    )
    {
    }

    /**
     * Create a snapshot for a newsletter after rendering.
     * Captures branding and layout version at this exact point in time.
     */
    public function createSnapshot(
        Newsletter $newsletter,
        string     $renderedHtml
    ): NewsletterSnapshot
    {
        return $this->database->transaction(function () use ($newsletter, $renderedHtml) {
            $branding = $this->brandingRepository->findByNewsletterId($newsletter->id);
            $layoutVersion = $this->layoutRenderer->resolveLayoutVersion($newsletter);

            $brandingSnapshot = $branding?->toSnapshot();

            if ($branding) {
                $brandingVersion = $this->brandingRepository->createVersion(
                    $branding->id,
                    $brandingSnapshot
                );
            }

            // Store the already-branded HTML as-is.
            // branding_snapshot_json is kept as an audit trail and for
            // re-rendering via renderFromSnapshot() on the view-in-browser path.
            return $this->snapshotRepository->createSnapshot(
                newsletterId: $newsletter->id,
                htmlSnapshot: $renderedHtml,
                brandingSnapshot: $brandingSnapshot,
                layoutVersionId: $layoutVersion?->id,
                brandingVersionId: isset($brandingVersion) ? $brandingVersion->id : null,
            );
        });
    }

    /**
     * Retrieve the rendered HTML from the latest snapshot.
     * Always renders from snapshot — never from live layout/branding.
     */
    public function renderFromLatestSnapshot(int $newsletterId): ?string
    {
        $snapshot = $this->snapshotRepository->latestForNewsletter($newsletterId);

        if (!$snapshot) {
            return null;
        }

        return $snapshot->layout_html_snapshot;
    }

    /**
     * Retrieve HTML from a specific snapshot by ID.
     */
    public function renderFromSnapshot(int $snapshotId): ?string
    {
        $snapshot = NewsletterSnapshot::find($snapshotId);

        if (!$snapshot) {
            return null;
        }

        // Use the frozen branding snapshot, not live branding
        if ($snapshot->branding_snapshot_json) {
            return $this->brandingRenderer->applyBrandingFromSnapshot(
                $snapshot->layout_html_snapshot,
                $snapshot->newsletter_id,
                $snapshot->branding_snapshot_json
            );
        }

        return $snapshot->layout_html_snapshot;
    }

    public function getLatestSnapshot(int $newsletterId): ?NewsletterSnapshot
    {
        return $this->snapshotRepository->latestForNewsletter($newsletterId);
    }

    public function getAllSnapshotsForNewsletter(int $newsletterId): Collection
    {
        return $this->snapshotRepository->allForNewsletter($newsletterId);
    }
}