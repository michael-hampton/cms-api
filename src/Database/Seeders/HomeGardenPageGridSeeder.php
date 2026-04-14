<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Page;
use App\Models\PageGrid;
use App\Models\Site;

class HomeGardenPageGridSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::find(9);

        if (!$site) {
            echo "Site with ID 8 not found. Please check the site ID.\n";
            return;
        }

        // Get all published content pages for this site
        $pages = Page::where('site_id', $site->id)
            ->where('status', 'published')
            ->where('page_type', 'content')
            ->whereNotIn('slug', ['home', 'about', 'contact']) // Exclude non-article pages
            ->get();

        if ($pages->isEmpty()) {
            echo "No articles found for site. Please create articles first.\n";
            return;
        }

        $this->createArticleGrid($site, $pages);
    }

    private function createArticleGrid(Site $site, $pages): void
    {
        $items = [];

        foreach ($pages as $page) {
            // Get the first image from page blocks if available
            $blocks = $page->blocks;
            $imageUrl = 'https://images.unsplash.com/photo-1556912173-3bb406ef7e77?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'; // Default

            foreach ($blocks as $block) {
                $blockData = $block->data;
                if ($block->type === 'image' && !empty($blockData['src'])) {
                    $imageUrl = $blockData['src'];
                    break;
                }
            }

            // Get custom fields
            $customFields = [];
            foreach ($page->customFields as $cf) {
                $customFields[$cf->customFieldDefinition->key] = $cf->field_value;
            }

            // Get first tag for badge
            $badge = null;
            $firstTag = $page->tags->first();
            if ($firstTag) {
                $badgeColors = [
                    'featured' => 'primary',
                    'trending' => 'warning',
                    'seasonal' => 'success',
                    'product-review' => 'info',
                    'diy' => 'secondary'
                ];
                $badge = [
                    'text' => ucfirst($firstTag->name),
                    'color' => $badgeColors[$firstTag->name] ?? 'primary'
                ];
            }

            $items[] = [
                'title' => $page->title,
                'slug' => $page->slug,
                'excerpt' => $customFields['excerpt'] ?? substr(strip_tags($page->title), 0, 150) . '...',
                'image' => [
                    'src' => $imageUrl,
                    'alt' => $page->title
                ],
                'badge' => $badge,
                'meta' => [
                    'author' => $customFields['author_name'] ?? 'Haven & Hearth',
                    'date' => $page->created_at ? $page->created_at->format('F j, Y') : date('F j, Y'),
                    'readTime' => isset($customFields['read_time']) ? $customFields['read_time'] . ' min read' : '5 min read'
                ],
                'features' => $this->getArticleFeatures($page, $customFields),
                'actions' => [
                    [
                        'text' => 'Read Article',
                        'url' => "/{$site->slug}/{$page->slug}",
                        'style' => 'primary'
                    ]
                ]
            ];
        }

        $pageGrid = PageGrid::create([
            'title' => 'All Articles',
            'subtitle' => 'Explore our complete collection of home design inspiration, gardening tips, and product reviews',
            'slug' => 'all-articles',
            'layout' => 'masonry',
            'columns' => 3,
            'show_excerpt' => true,
            'show_image' => true,
            'show_features' => true,
            'show_actions' => true,
            'is_active' => true,
            'use_hero' => true,
            'items' => $items,
            'site_id' => $site->id
        ]);

        echo "Created page grid 'All Articles' for {$site->name} with " . count($items) . " articles\n";
    }

    private function getArticleFeatures(Page $page, array $customFields): array
    {
        $features = [];

        // Add difficulty level if it's a DIY article
        if (isset($customFields['difficulty_level'])) {
            $features[] = 'Difficulty: ' . ucfirst($customFields['difficulty_level']);
        }

        // Add project time if available
        if (isset($customFields['project_time'])) {
            $features[] = 'Time: ' . $customFields['project_time'];
        }

        // Add cost if available
        if (isset($customFields['project_cost'])) {
            $features[] = 'Cost: ' . $customFields['project_cost'];
        }

        // Add room type if available
        if (isset($customFields['room_type'])) {
            $roomTypes = [
                'living' => 'Living Room',
                'bedroom' => 'Bedroom',
                'kitchen' => 'Kitchen',
                'bathroom' => 'Bathroom',
                'outdoor' => 'Outdoor'
            ];
            $features[] = $roomTypes[$customFields['room_type']] ?? ucfirst($customFields['room_type']);
        }

        // Add style if available
        if (isset($customFields['style'])) {
            $features[] = 'Style: ' . $customFields['style'];
        }

        // Add primary category
        $primaryCategory = $page->categories->first();
        if ($primaryCategory) {
            $features[] = $primaryCategory->name;
        }

        // Return max 3 features
        return array_slice($features, 0, 3);
    }
}