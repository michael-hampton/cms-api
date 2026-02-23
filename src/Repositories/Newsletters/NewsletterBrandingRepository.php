<?php

namespace App\Repositories\Newsletters;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\NewsletterBrandingConfiguration;
use App\Models\NewsletterBrandingVersion;
use App\Repositories\Repository;

class NewsletterBrandingRepository extends Repository
{
    protected function getModelClass(): string
    {
        return NewsletterBrandingConfiguration::class;
    }

    public function findByNewsletterId(int $newsletterId): ?NewsletterBrandingConfiguration
    {
        return NewsletterBrandingConfiguration::where('newsletter_id', $newsletterId)->first();
    }

    public function upsertForNewsletter(int $newsletterId, array $brandingData): Model
    {
        $existing = $this->findByNewsletterId($newsletterId);

        if ($existing) {
            foreach ($brandingData as $key => $value) {
                $existing->$key = $value;
            }
            $existing->save();
            return $existing->fresh();
        }

        return NewsletterBrandingConfiguration::create(array_merge(
            ['newsletter_id' => $newsletterId],
            $brandingData
        ));
    }

    public function createVersion(int $brandingConfigId, array $snapshot): Model
    {
        $nextNumber = $this->nextVersionNumber($brandingConfigId);

        return NewsletterBrandingVersion::create([
            'branding_config_id' => $brandingConfigId,
            'version_number' => $nextNumber,
            'branding_json_snapshot' => $snapshot,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function nextVersionNumber(int $brandingConfigId): int
    {
        $latest = NewsletterBrandingVersion::where('branding_config_id', $brandingConfigId)
            ->orderBy('version_number', 'desc')
            ->first();

        return $latest ? ($latest->version_number + 1) : 1;
    }

    public function findVersion(int $brandingConfigId, int $versionNumber): ?NewsletterBrandingVersion
    {
        return NewsletterBrandingVersion::where('branding_config_id', $brandingConfigId)
            ->where('version_number', $versionNumber)
            ->first();
    }

    public function findVersionById(int $versionId): ?Model
    {
        return NewsletterBrandingVersion::find($versionId);
    }

    public function versionHistory(int $brandingConfigId): Collection
    {
        return NewsletterBrandingVersion::where('branding_config_id', $brandingConfigId)
            ->orderBy('version_number', 'desc')
            ->get();
    }
}