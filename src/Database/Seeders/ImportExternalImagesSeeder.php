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

        foreach ($migration->run() as $type => $count) {
            echo sprintf("Updated %d %s records.\n", $count, $type);
        }
    }
}
