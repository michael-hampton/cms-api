<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Framework\Support\Config\ConfigModel;

class SeedDesignTokensConfigData extends Seeder
{
    /**
     * Run the data ingestion migration.
     */
    public function run(): void
    {
        $rawData = config('public-content-design-tokens');

        $globalDefaults = $rawData['defaults'] ?? [];
        $sitesData = $rawData['sites'] ?? [];

        // Fetch all operational site IDs from your sites lookup table
        $siteRecords = \App\Models\Site::all();

        foreach ($siteRecords as $site) {
            // Find specific overrides for this tenant handle, or default to an empty configuration array
            $siteOverrides = $sitesData[$site->slug] ?? [];

            // Package both definitions into an isolated multi-tenant package
            $tenantPayload = [
                'defaults' => $globalDefaults,
                'overrides' => $siteOverrides
            ];

            // Hydrate the configuration model to assign tracking UUIDs to top-level design tokens
            $configModel = ConfigModel::fromArray($tenantPayload);
            $serializedEntries = json_encode($configModel->toSerializableArray());
            $fingerprint = md5($serializedEntries . time());

            // Check if record already exists to maintain script idempotency
            $exists = \App\Models\ConfigDocument::where('site_id', $site->id)
                ->where('type', 'design_tokens')
                ->exists();

            if (!$exists) {
                \App\Models\ConfigDocument::insert([
                    'site_id' => $site->id,
                    'type' => 'design_tokens',
                    'payload' => $serializedEntries,
                    'fingerprint' => $fingerprint,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}