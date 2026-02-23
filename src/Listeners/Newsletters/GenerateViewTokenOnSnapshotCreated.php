<?php

namespace App\Listeners\Newsletters;

use App\Events\Newsletters\NewsletterSnapshotCreated;
use App\Framework\Support\Logger;
use App\Services\Newsletter\NewsletterViewTokenService;

/**
 * Automatically generates a view-in-browser token when a snapshot is created.
 * This ensures every published newsletter has a shareable preview URL.
 */
class GenerateViewTokenOnSnapshotCreated
{
    public function __construct(
        private readonly NewsletterViewTokenService $tokenService,
        private readonly Logger                     $logger,
    )
    {
    }

    public function handle(NewsletterSnapshotCreated $event): void
    {
        try {
            $token = $this->tokenService->generateForSnapshot($event->snapshot->id);

            $this->logger->info('View token generated for newsletter snapshot', [
                'snapshot_id' => $event->snapshot->id,
                'newsletter_id' => $event->snapshot->newsletter_id,
                'view_url' => $this->tokenService->buildViewUrl($token),
            ]);
        } catch (\Exception $e) {
            // Non-critical — do not let token generation failure block publishing
            $this->logger->error('Failed to generate view token for newsletter snapshot', [
                'snapshot_id' => $event->snapshot->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}