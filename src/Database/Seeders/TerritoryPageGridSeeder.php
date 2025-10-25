<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Page;
use App\Models\PageGrid;
use App\Models\Site;
use App\Models\Territory;

class TerritoryPageGridSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::where('slug', 'global-wanderlust')->first();

        if (!$site) {
            echo "Site 'global-wanderlust' not found. Please create it first.\n";
            return;
        }

        // Get territories
        $territories = Territory::where('site_id', $site->id)
            ->where('is_active', true)
            ->get();

        if ($territories->isEmpty()) {
            echo "No territories found for site. Please create territories first.\n";
            return;
        }

        $pages = Page::with(['territories'])->where('site_id', $site->id)->get();

        $allPages = [];

        foreach ($pages as $page) {
            $territory = $page->territories->first()->slug;
            $allPages[$territory][] = $page;
        }

        foreach ($territories as $territory) {
            $this->createRegionPageGrid($site, $territory, $allPages[$territory->slug]);
        }
    }

    private function createRegionPageGrid(Site $site, Territory $territory, array $pages): void
    {
        $items = [];

        foreach ($pages as $page) {
                $items[] = [
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'excerpt' => 'Discover the secret spots that locals love and tourists miss.',
                    'image' => [
                        'src' => 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?w=800',
                        'alt' => "Hidden Gems in {$territory->name}"
                    ],
                    'badge' => [
                        'text' => 'Featured',
                        'color' => 'primary'
                    ],
                    'meta' => [
                        'author' => 'Travel Explorer',
                        'date' => date('F j, Y'),
                        'readTime' => '8 min read'
                    ],
                    'features' => [
                        'Off the beaten path destinations',
                        'Local insider tips',
                        'Budget-friendly options'
                    ],
                    'actions' => [
                        [
                            'text' => 'Read More',
                            'url' => $site->slug . "/" . "/{$territory->slug}/{$page->slug}",
                            'style' => 'primary'
                        ]
                    ]
                ];
        }

        $pageGrid = PageGrid::create([
            'title' => "Explore {$territory->name}",
            'subtitle' => "Discover the best of {$territory->name}",
            'slug' => "{$territory->slug}-explorer",
            'layout' => 'masonry',
            'columns' => 3,
            'show_excerpt' => true,
            'show_image' => true,
            'show_features' => true,
            'show_actions' => true,
            'is_active' => true,
            'use_hero' => true,
            'items' => $items
        ]);

        // Link to territory
        $pageGrid->territories(true)->attach($territory->id);

        echo "Created page grid for {$territory->name}\n";
    }
}