<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Framework\Support\Config\ConfigModel;
use App\Models\ConfigDocument;
use App\Models\Site;

class MigrateConfig extends Seeder
{

    public function run(): void
    {
        $rawData = config('public_content');

        // Build the ConfigModel directly from the raw configuration array file.
        // This assigns long-lived UUID tracking IDs to each top-level key.
        $configModel = ConfigModel::fromArray($rawData);
        $serializedEntries = json_encode($configModel->toSerializableArray());

        // Generate an initial baseline fingerprint required by the concurrency layer.
        $fingerprint = md5($serializedEntries . time());

        // Target your multi-tenant site scope tags
        $sites = Site::all();

        foreach ($sites as $site) {
            // Guard clause to prevent duplicate row insertion constraints
            $exists = ConfigDocument::where('site_id', $site->id)
                ->where('type', 'public_content')
                ->exists();

            if (!$exists) {
                ConfigDocument::insert([
                    'site_id' => $site->id,
                    'type' => 'public_content',
                    'payload' => $serializedEntries,
                    'fingerprint' => $fingerprint,
                    'updated_by' => 'Data Migration Seeder',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}