<?php

namespace App\Services\Newsletter;

use App\Events\Newsletters\NewsletterBrandingUpdated;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\NewsletterBrandingConfiguration;
use App\Repositories\Newsletters\NewsletterBrandingRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Services\Newsletter\Branding\CssSanitizer;

/**
 * Orchestrates branding configuration CRUD and creates versioned snapshots
 * whenever branding changes.
 */
class NewsletterBrandingService
{
    public function __construct(
        private readonly NewsletterBrandingRepository $brandingRepository,
        private readonly NewsletterRepository         $newsletterRepository,
        private readonly CssSanitizer                 $cssSanitizer,
        private readonly Logger                       $logger,
        private Database                              $database
    )
    {
    }

    /**
     * Save branding for a newsletter.
     * Creates a version snapshot automatically on each save.
     */
    public function saveBranding(int $newsletterId, array $brandingData): NewsletterBrandingConfiguration
    {
        return $this->database->transaction(function () use ($newsletterId, $brandingData) {
            $newsletter = $this->newsletterRepository->find($newsletterId);

            if (!$newsletter) {
                throw new \InvalidArgumentException("Newsletter ID {$newsletterId} not found.");
            }

            // Sanitize CSS before storing
            if (isset($brandingData['custom_css']) && !empty($brandingData['custom_css'])) {
                $brandingData['custom_css'] = $this->cssSanitizer->sanitize($brandingData['custom_css']);
            }

            $branding = $this->brandingRepository->upsertForNewsletter($newsletterId, $brandingData);

            // Version the branding snapshot
            $this->brandingRepository->createVersion($branding->id, $branding->toSnapshot());

            event(new NewsletterBrandingUpdated($newsletter, $branding));

            return $branding;
        });
    }

    /**
     * Retrieve current branding for a newsletter.
     */
    public function getBranding(int $newsletterId): ?NewsletterBrandingConfiguration
    {
        return $this->brandingRepository->findByNewsletterId($newsletterId);
    }

    /**
     * Retrieve full version history for a newsletter's branding.
     */
    public function getBrandingVersionHistory(int $newsletterId): array
    {
        $branding = $this->brandingRepository->findByNewsletterId($newsletterId);

        if (!$branding) {
            return [];
        }

        return $this->brandingRepository->versionHistory($branding->id)->toArray();
    }

    /**
     * Restore branding to a specific version number.
     * Creates a new version record from the restored snapshot.
     */
    public function restoreBrandingVersion(int $newsletterId, int $versionNumber): NewsletterBrandingConfiguration
    {
        return $this->database->transaction(function () use ($newsletterId, $versionNumber) {
            $branding = $this->brandingRepository->findByNewsletterId($newsletterId);

            if (!$branding) {
                throw new \RuntimeException("No branding configuration found for newsletter {$newsletterId}.");
            }

            $version = $this->brandingRepository->findVersion($branding->id, $versionNumber);

            if (!$version) {
                throw new \RuntimeException("Branding version {$versionNumber} not found.");
            }

            $snapshot = $version->branding_json_snapshot;

            // Sanitize restored CSS
            if (isset($snapshot['custom_css']) && !empty($snapshot['custom_css'])) {
                $snapshot['custom_css'] = $this->cssSanitizer->sanitize($snapshot['custom_css']);
            }

            $restored = $this->brandingRepository->upsertForNewsletter($newsletterId, $snapshot);
            $this->brandingRepository->createVersion($restored->id, $restored->toSnapshot());

            $this->logger->info('Branding version restored', [
                'newsletter_id' => $newsletterId,
                'restored_from' => $versionNumber,
            ]);

            return $restored;
        });
    }
}