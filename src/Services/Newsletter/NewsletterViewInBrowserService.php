<?php

namespace App\Services\Newsletter;

use App\Framework\Database\Database;
use App\Models\NewsletterSendRecipient;
use App\Repositories\Newsletters\NewsletterSnapshotRepository;

/**
 * Handles "view in browser" for sent newsletters.
 *
 * Design:
 *   1. At send time, a snapshot is created and a single view token is generated
 *      from it (NewsletterViewTokenService).  The token is stored on the snapshot.
 *
 *   2. The HTML template contains the placeholder {{VIEW_IN_BROWSER_URL}}.
 *      At dispatch time, NewsletterDispatcher replaces this placeholder with a
 *      per-recipient tracked URL:
 *
 *        /newsletter/view/{snapshotToken}?r={recipientToken}
 *
 *      The `r` parameter identifies the recipient so click attribution is possible.
 *
 *   3. The web controller resolves the snapshot from the token, records the view
 *      against the recipient, and serves the HTML snapshot.
 *
 * This service is responsible for:
 *   - Building the per-recipient view URL
 *   - Recording view clicks (attribution)
 */
class NewsletterViewInBrowserService
{
    public function __construct(
        private readonly NewsletterViewTokenService   $viewTokenService,
        private readonly NewsletterSnapshotRepository $snapshotRepository,
        private readonly Database                     $database,
    )
    {
    }

    /**
     * Build the per-recipient view-in-browser URL.
     *
     * @param string $snapshotViewToken The token generated for the snapshot (same for all recipients).
     * @param string $recipientToken The per-recipient token (used for click attribution).
     */
    public function buildViewUrl(string $snapshotViewToken, string $recipientToken): string
    {
        $base = $this->viewTokenService->buildViewUrl($snapshotViewToken);

        return $base . '?r=' . urlencode($recipientToken);
    }

    /**
     * Record that a recipient opened the newsletter via the view-in-browser link.
     * Non-critical: failures are caught and logged rather than thrown.
     */
    public function recordView(string $snapshotToken, string $recipientToken): void
    {
        $snapshot = $this->viewTokenService->resolveSnapshot($snapshotToken);

        if (!$snapshot) {
            return;
        }

        $recipient = NewsletterSendRecipient::where('token', $recipientToken)->first();

        if (!$recipient) {
            return;
        }

        $this->snapshotRepository->recordViewInBrowserClick(
            $snapshot->id,
            $recipient->id,
        );
    }
}