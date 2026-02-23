<?php

namespace App\Services\Newsletter;

use App\Framework\Database\Database;
use App\Models\NewsletterSnapshot;
use App\Repositories\Newsletters\NewsletterSnapshotRepository;

/**
 * Generates time-limited, shareable tokens for viewing newsletters in browser.
 *
 * Token flow:
 *   Generate token → attach to snapshot → serve HTML on valid token request
 */
class NewsletterViewTokenService
{
    private const TOKEN_TTL_HOURS = 72;

    public function __construct(
        private readonly NewsletterSnapshotRepository $snapshotRepository,
        private readonly Database                     $database
    )
    {
    }

    /**
     * Generate a view token for the latest snapshot of a newsletter.
     * Returns the token string.
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
     * Generate a token for a specific snapshot.
     */
    public function generateForSnapshot(int $snapshotId): string
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
     * Build the public view-in-browser URL for a token.
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