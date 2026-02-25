<?php

namespace App\Services\Newsletter;

use App\Framework\Database\Database;
use App\Models\NewsletterSnapshot;
use App\Repositories\Newsletters\NewsletterSnapshotRepository;

/**
 * Generates time-limited, shareable tokens for viewing newsletters in a browser.
 *
 * Token flow:
 *   Send time  → NewsletterSendService calls generateTokenForSnapshot()
 *                The token is embedded in the HTML as {{VIEW_IN_BROWSER_URL:{token}}}
 *   Dispatch   → NewsletterDispatcher resolves the token and appends ?r={recipientToken}
 *   Click      → Controller calls resolveSnapshot() then records the attribution via
 *                NewsletterViewInBrowserService::recordView()
 */
class NewsletterViewTokenService
{
    private const TOKEN_TTL_HOURS = 72;

    public function __construct(
        private readonly NewsletterSnapshotRepository $snapshotRepository,
        private readonly Database $database,
    )
    {
    }

    /**
     * Generate a view token for a specific snapshot (called at send time).
     *
     * This is the primary entry-point used by NewsletterSendService.  It does NOT
     * require a pre-existing token on the snapshot — it creates one and persists it.
     *
     * Returns the raw token string so the send service can embed it in the HTML.
     */
    public function generateTokenForSnapshot(int $snapshotId): string
    {
        return $this->database->transaction(function () use ($snapshotId) {
            $snapshot = NewsletterSnapshot::find($snapshotId);

            if (!$snapshot) {
                throw new \RuntimeException("Snapshot ID {$snapshotId} not found.");
            }

            $token = $this->generateToken();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::TOKEN_TTL_HOURS . ' hours'));

            $this->snapshotRepository->attachViewToken($snapshotId, $token, $expiresAt);

            return $token;
        });
    }

    /**
     * Generate a view token for the latest snapshot of a newsletter.
     *
     * Kept for backwards-compatibility with manual publish/preview flows.
     * Prefer generateTokenForSnapshot() when the snapshot ID is already known.
     */
    public function generateForNewsletter(int $newsletterId): string
    {
        return $this->database->transaction(function () use ($newsletterId) {
            $snapshot = $this->snapshotRepository->latestForNewsletter($newsletterId);

            if (!$snapshot) {
                throw new \RuntimeException(
                    "No snapshot found for newsletter ID {$newsletterId}. Publish the newsletter first."
                );
            }

            $token = $this->generateToken();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::TOKEN_TTL_HOURS . ' hours'));

            $this->snapshotRepository->attachViewToken($snapshot->id, $token, $expiresAt);

            return $token;
        });
    }

    /**
     * Generate a view token for a specific snapshot (public alias kept for existing callers).
     */
    public function generateForSnapshot(int $snapshotId): string
    {
        return $this->generateTokenForSnapshot($snapshotId);
    }

    /**
     * Resolve a snapshot from a view token. Returns null if token is invalid or expired.
     */
    public function resolveSnapshot(string $token): ?NewsletterSnapshot
    {
        $snapshot = $this->snapshotRepository->findByToken($token);

        if (!$snapshot || !$snapshot->isViewTokenValid()) {
            return null;
        }

        return $snapshot;
    }

    /**
     * Build the public view-in-browser URL for a token (without recipient attribution).
     * The recipient `r=` parameter is appended by NewsletterDispatcher at dispatch time.
     */
    public function buildViewUrl(string $token): string
    {
        return url("/newsletter/view/{$token}");
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}