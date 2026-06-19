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
        $logger = static function (string $message): void {
            echo sprintf("[%s] %s\n", date('H:i:s'), $message);

            if (function_exists('ob_flush')) {
                @ob_flush();
            }

            flush();
        };

        $migration = new StoredContentImageMigration(
            new ContentImageRewriter(
                new UnsplashImageImporter($logger)
            ),
            $logger
        );

        $result = $migration->run();

        foreach ($result['updated'] as $type => $count) {
            $logger(sprintf('Updated %d %s records', $count, $type));
        }

        $failures = $result['failures'];
        $logger(sprintf('Failed image imports: %d', count($failures)));

        foreach ($failures as $failure) {
            $logger(sprintf(
                '[FAILED] site=%d url=%s error=%s',
                $failure['site_id'],
                $failure['url'],
                $failure['message']
            ));
        }
    }
}
