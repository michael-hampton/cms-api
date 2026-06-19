<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Services\Cms\ContentImageRewriter;
use App\Services\Cms\StoredContentImageMigration;
use App\Services\Cms\UnsplashImageImporter;

final class ImportExternalImagesSeeder extends Seeder
{
    public function run(): void
    {
        $migration = new StoredContentImageMigration(
            new ContentImageRewriter(
                new UnsplashImageImporter()
            )
        );

        $result = $migration->run();

        foreach ($result['updated'] as $type => $count) {
            echo sprintf("Updated %d %s records.\n", $count, $type);
        }

        $failures = $result['failures'];
        echo sprintf("Failed image imports: %d.\n", count($failures));

        foreach ($failures as $failure) {
            echo sprintf(
                "[FAILED] site=%d url=%s error=%s\n",
                $failure['site_id'],
                $failure['url'],
                $failure['message']
            );
        }
    }
}
