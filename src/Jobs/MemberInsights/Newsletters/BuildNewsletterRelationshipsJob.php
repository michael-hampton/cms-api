<?php

declare(strict_types=1);

namespace App\Jobs\MemberInsights\Newsletters;

use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Services\MemberInsights\Newsletters\Relationships\NewsletterRelationshipBuilder;

/**
 * Builds or refreshes the newsletter relationship graph for a single site.
 *
 * Dispatched by:
 *   - BuildNewsletterRelationshipsCommand (manual / scheduled)
 *   - Any listener that detects a new newsletter was created or updated
 *
 * Two-pass strategy (owned by NewsletterRelationshipBuilder):
 *   Pass 1 — Category/tag overlap: deterministic, works with zero user data.
 *   Pass 2 — Co-subscription enrichment: requires subscriber data; adds
 *             weighted edges where subscriber audiences overlap.
 *
 * All writes are upserts — running this job repeatedly is safe.
 * Manual admin-entered edges (UpsellPremium) are never overwritten.
 *
 * This job is intentionally thin — all logic lives in the builder service.
 */
class BuildNewsletterRelationshipsJob extends BaseJob
{
    public int $tries = 3;
    public int $backoff = 60;

    private NewsletterRelationshipBuilder $builder;
    private Logger $logger;

    public function __construct(
        public readonly int $siteId,
    )
    {
    }

    public function handle(): void
    {
        $this->logger->info('BuildNewsletterRelationshipsJob: started', [
            'site_id' => $this->siteId,
        ]);

        $summary = $this->builder->build($this->siteId);

        $this->logger->info('BuildNewsletterRelationshipsJob: completed', array_merge(
            ['site_id' => $this->siteId],
            $summary,
        ));
    }
}