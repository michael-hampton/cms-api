<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\Site;
use App\Services\Offers\DealsService;

/**
 * Snapshots today's featured deals for the public content deals carousel.
 *
 * Public content prefers any active featured deal, then live sale products, so
 * this seeder is optional rather than required for the carousel to appear.
 *
 * Usage:
 *   php artisan db:seed --class=PublicFeaturedDealsCarouselSeeder
 */
class PublicFeaturedDealsCarouselSeeder extends Seeder
{
    public function run(): void
    {
        $sites = Site::where('is_active', true)->get();

        if ($sites->isEmpty()) {
            echo "No active sites found. Featured deals were not seeded.\n";
            return;
        }

        $deals = (new Container())->resolve(DealsService::class);

        foreach ($sites as $site) {
            $this->seedSite($deals, (int) $site->id, (string) ($site->slug ?? $site->id));
        }

        echo "Public featured deals carousel seeded successfully.\n";
    }

    private function seedSite(DealsService $deals, int $siteId, string $siteLabel): void
    {
        $items = $deals->refreshTodaysDeals($siteId);
        $count = count($items);

        echo "Site {$siteLabel} ({$siteId}): {$count} featured deal(s) for today.\n";
    }
}
