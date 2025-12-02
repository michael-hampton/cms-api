<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Site;

class MenuReorganizationSeeder extends Seeder
{
    public function run(): void
    {
//        $this->reorganizeTechWeeklyMenu();
//        $this->reorganizeHavenHearthMenu();
//        $this->reorganizeMusicWeekMenu();
//        $this->reorganizeGamesRadarMenu();
//        $this->reorganizeSoundwaveMenu();
//        $this->reorganizeVogueNoirMenu();
//        $this->reorganizeGoCompareMenu();
        $this->reorganizeWineChronicleMenu();
    }

    private function reorganizeVogueNoirMenu(): void
    {
        $site = Site::find(6);
        if (!$site) return;

        $menu = Menu::where('site_id', $site->id)->where('slug', 'main-menu')->first();
        if (!$menu) return;

        MenuItem::where('menu_id', $menu->id)->delete();

        // Home
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Home',
            'target_type' => 'custom',
            'custom_url' => '/',
            'sort_order' => 1,
            'is_active' => true
        ]);

        // Fashion dropdown
        $fashionParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Fashion',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 2,
            'is_active' => true
        ]);

        $fashionTopics = [
            ['label' => 'Runway Shows', 'url' => '/category/runway-shows'],
            ['label' => 'Street Style', 'url' => '/category/street-style'],
            ['label' => 'Trends', 'url' => '/category/trends'],
            ['label' => 'Sustainable Fashion', 'url' => '/category/sustainable-fashion']
        ];

        foreach ($fashionTopics as $index => $topic) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $fashionParent->id,
                'label' => $topic['label'],
                'target_type' => 'custom',
                'custom_url' => $topic['url'],
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Shopping dropdown
        $shoppingParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Shopping',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 3,
            'is_active' => true
        ]);

        $shoppingTopics = [
            ['label' => 'Buying Guides', 'url' => '/category/buying-guides'],
            ['label' => 'Luxury', 'url' => '/category/luxury'],
            ['label' => 'Accessories', 'url' => '/category/accessories']
        ];

        foreach ($shoppingTopics as $index => $topic) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $shoppingParent->id,
                'label' => $topic['label'],
                'target_type' => 'custom',
                'custom_url' => $topic['url'],
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Beauty dropdown
        $beautyParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Beauty',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 4,
            'is_active' => true
        ]);

        $beautyTopics = [
            ['label' => 'Makeup', 'url' => '/category/makeup'],
            ['label' => 'Skincare', 'url' => '/category/skincare']
        ];

        foreach ($beautyTopics as $index => $topic) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $beautyParent->id,
                'label' => $topic['label'],
                'target_type' => 'custom',
                'custom_url' => $topic['url'],
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Designers dropdown
        $designersParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Designers',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 5,
            'is_active' => true
        ]);

        $designerTopics = [
            ['label' => 'Designer Profiles', 'url' => '/category/designer-profiles'],
            ['label' => 'Emerging Designers', 'url' => '/category/emerging-designers']
        ];

        foreach ($designerTopics as $index => $topic) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $designersParent->id,
                'label' => $topic['label'],
                'target_type' => 'custom',
                'custom_url' => $topic['url'],
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Features dropdown
        $featuresParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Features',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 6,
            'is_active' => true
        ]);

        $featureTopics = [
            ['label' => 'Interviews', 'url' => '/category/interviews'],
            ['label' => 'Style Guides', 'url' => '/category/style-guides']
        ];

        foreach ($featureTopics as $index => $topic) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $featuresParent->id,
                'label' => $topic['label'],
                'target_type' => 'custom',
                'custom_url' => $topic['url'],
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // About
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'About',
            'target_type' => 'page',
            'target_id' => 28, // About page
            'sort_order' => 7,
            'is_active' => true
        ]);

        // Contact
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Contact',
            'target_type' => 'page',
            'target_id' => 29, // Contact page
            'sort_order' => 8,
            'is_active' => true
        ]);
    }

    private function reorganizeGoCompareMenu(): void
    {
        $site = Site::find(28);
        if (!$site) return;

        $menu = Menu::where('site_id', $site->id)->where('slug', 'main-menu')->first();
        if (!$menu) return;

        MenuItem::where('menu_id', $menu->id)->delete();

        // Home
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Home',
            'target_type' => 'page',
            'target_id' => 268, // Homepage
            'sort_order' => 1,
            'is_active' => true
        ]);

        // Insurance dropdown
        $insuranceParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Insurance',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 2,
            'is_active' => true
        ]);

        $insuranceProducts = [
            ['label' => 'Car Insurance', 'page_id' => 269],
            ['label' => 'Home Insurance', 'page_id' => 270],
            ['label' => 'Travel Insurance', 'page_id' => 271],
            ['label' => 'Pet Insurance', 'page_id' => 275],
            ['label' => 'Life Insurance', 'page_id' => 276],
            ['label' => 'Health Insurance', 'url' => '/category/health-insurance']
        ];

        foreach ($insuranceProducts as $index => $product) {
            $item = [
                'menu_id' => $menu->id,
                'parent_id' => $insuranceParent->id,
                'label' => $product['label'],
                'sort_order' => $index + 1,
                'is_active' => true
            ];

            if (isset($product['page_id'])) {
                $item['target_type'] = 'page';
                $item['target_id'] = $product['page_id'];
            } else {
                $item['target_type'] = 'custom';
                $item['custom_url'] = $product['url'];
            }

            MenuItem::create($item);
        }

        // Money dropdown
        $moneyParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Money',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 3,
            'is_active' => true
        ]);

        $moneyProducts = [
            ['label' => 'Credit Cards', 'page_id' => 273],
            ['label' => 'Loans', 'url' => '/category/loans'],
            ['label' => 'Mortgages', 'url' => '/category/mortgages'],
            ['label' => 'Bank Accounts', 'url' => '/category/bank-accounts'],
            ['label' => 'Savings', 'url' => '/category/savings']
        ];

        foreach ($moneyProducts as $index => $product) {
            $item = [
                'menu_id' => $menu->id,
                'parent_id' => $moneyParent->id,
                'label' => $product['label'],
                'sort_order' => $index + 1,
                'is_active' => true
            ];

            if (isset($product['page_id'])) {
                $item['target_type'] = 'page';
                $item['target_id'] = $product['page_id'];
            } else {
                $item['target_type'] = 'custom';
                $item['custom_url'] = $product['url'];
            }

            MenuItem::create($item);
        }

        // Utilities dropdown
        $utilitiesParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Utilities',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 4,
            'is_active' => true
        ]);

        $utilitiesProducts = [
            ['label' => 'Energy', 'page_id' => 272],
            ['label' => 'Broadband', 'page_id' => 274],
            ['label' => 'Mobile Phones', 'url' => '/category/mobile-phones'],
            ['label' => 'TV & Streaming', 'url' => '/category/tv-streaming']
        ];

        foreach ($utilitiesProducts as $index => $product) {
            $item = [
                'menu_id' => $menu->id,
                'parent_id' => $utilitiesParent->id,
                'label' => $product['label'],
                'sort_order' => $index + 1,
                'is_active' => true
            ];

            if (isset($product['page_id'])) {
                $item['target_type'] = 'page';
                $item['target_id'] = $product['page_id'];
            } else {
                $item['target_type'] = 'custom';
                $item['custom_url'] = $product['url'];
            }

            MenuItem::create($item);
        }

        // Guides & Advice dropdown
        $guidesParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Guides & Advice',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 5,
            'is_active' => true
        ]);

        $guideTopics = [
            ['label' => 'How-To Guides', 'url' => '/category/how-to-guides'],
            ['label' => 'Money Saving Tips', 'url' => '/category/money-saving-tips'],
            ['label' => 'Comparison Guides', 'url' => '/category/comparison-guides']
        ];

        foreach ($guideTopics as $index => $topic) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $guidesParent->id,
                'label' => $topic['label'],
                'target_type' => 'custom',
                'custom_url' => $topic['url'],
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // About
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'About',
            'target_type' => 'page',
            'target_id' => 277,
            'sort_order' => 6,
            'is_active' => true
        ]);

        // Contact
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Contact',
            'target_type' => 'page',
            'target_id' => 278,
            'sort_order' => 7,
            'is_active' => true
        ]);
    }

    private function reorganizeTechWeeklyMenu(): void
    {
        $site = Site::where('slug', 'tech-weekly')->first();
        if (!$site) return;

        $menu = Menu::where('site_id', $site->id)->where('slug', 'main-menu')->first();
        if (!$menu) return;

        // Delete all existing menu items
        MenuItem::where('menu_id', $menu->id)->delete();

        // Home
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Home',
            'target_type' => 'custom',
            'custom_url' => '/',
            'sort_order' => 1,
            'is_active' => true
        ]);

        // Reviews dropdown
        $reviewsParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Reviews',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 2,
            'is_active' => true
        ]);

        MenuItem::create([
            'menu_id' => $menu->id,
            'parent_id' => $reviewsParent->id,
            'label' => 'TVs',
            'target_type' => 'custom',
            'custom_url' => '/category/tvs',
            'sort_order' => 1,
            'is_active' => true
        ]);

        MenuItem::create([
            'menu_id' => $menu->id,
            'parent_id' => $reviewsParent->id,
            'label' => 'Smartphones',
            'target_type' => 'custom',
            'custom_url' => '/category/smartphones',
            'sort_order' => 2,
            'is_active' => true
        ]);

        MenuItem::create([
            'menu_id' => $menu->id,
            'parent_id' => $reviewsParent->id,
            'label' => 'Laptops',
            'target_type' => 'custom',
            'custom_url' => '/category/laptops',
            'sort_order' => 3,
            'is_active' => true
        ]);

        MenuItem::create([
            'menu_id' => $menu->id,
            'parent_id' => $reviewsParent->id,
            'label' => 'Audio',
            'target_type' => 'custom',
            'custom_url' => '/category/audio',
            'sort_order' => 4,
            'is_active' => true
        ]);

        MenuItem::create([
            'menu_id' => $menu->id,
            'parent_id' => $reviewsParent->id,
            'label' => 'Gaming',
            'target_type' => 'custom',
            'custom_url' => '/category/gaming',
            'sort_order' => 5,
            'is_active' => true
        ]);

        // Brands dropdown
        $brandsParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Brands',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 3,
            'is_active' => true
        ]);

        $brands = ['Sony', 'Apple', 'Samsung', 'Google', 'Microsoft', 'LG', 'Nokia'];
        foreach ($brands as $index => $brand) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $brandsParent->id,
                'label' => $brand,
                'target_type' => 'custom',
                'custom_url' => '/brand/' . strtolower($brand),
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Guides
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Buying Guides',
            'target_type' => 'custom',
            'custom_url' => '/guides',
            'sort_order' => 4,
            'is_active' => true
        ]);

        // About & Contact
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'About',
            'target_type' => 'custom',
            'custom_url' => '/about',
            'sort_order' => 5,
            'is_active' => true
        ]);
    }

    private function reorganizeHavenHearthMenu(): void
    {
        $site = Site::where('slug', 'haven-hearth')->first();
        if (!$site) return;

        $menu = Menu::where('site_id', $site->id)->where('slug', 'main-menu')->first();
        if (!$menu) return;

        MenuItem::where('menu_id', $menu->id)->delete();

        // Home
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Home',
            'target_type' => 'custom',
            'custom_url' => '/',
            'sort_order' => 1,
            'is_active' => true
        ]);

        // Interior Design dropdown
        $interiorParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Interior Design',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 2,
            'is_active' => true
        ]);

        $interiorTopics = [
            'Living Room Ideas',
            'Bedroom Design',
            'Kitchen Inspiration',
            'Bathroom Makeovers',
            'Color & Paint',
            'Lighting Design'
        ];

        foreach ($interiorTopics as $index => $topic) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $interiorParent->id,
                'label' => $topic,
                'target_type' => 'custom',
                'custom_url' => '/interior-design/' . strtolower(str_replace(' ', '-', $topic)),
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Garden & Outdoor dropdown
        $gardenParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Garden & Outdoor',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 3,
            'is_active' => true
        ]);

        $gardenTopics = [
            'Gardening Tips',
            'Plant Care',
            'Outdoor Living',
            'Landscaping Ideas',
            'Seasonal Guides'
        ];

        foreach ($gardenTopics as $index => $topic) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $gardenParent->id,
                'label' => $topic,
                'target_type' => 'custom',
                'custom_url' => '/garden/' . strtolower(str_replace(' ', '-', $topic)),
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // DIY & Projects dropdown
        $diyParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'DIY & Projects',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 4,
            'is_active' => true
        ]);

        $diyTopics = [
            'Home Improvements',
            'Storage Solutions',
            'Furniture Projects',
            'Crafts & Decor'
        ];

        foreach ($diyTopics as $index => $topic) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $diyParent->id,
                'label' => $topic,
                'target_type' => 'custom',
                'custom_url' => '/diy/' . strtolower(str_replace(' ', '-', $topic)),
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Product Reviews
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Product Reviews',
            'target_type' => 'custom',
            'custom_url' => '/reviews',
            'sort_order' => 5,
            'is_active' => true
        ]);

        // About
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'About',
            'target_type' => 'custom',
            'custom_url' => '/about',
            'sort_order' => 6,
            'is_active' => true
        ]);
    }

    private function reorganizeMusicWeekMenu(): void
    {
        $site = Site::where('slug', 'music-week')->first();
        if (!$site) return;

        $menu = Menu::where('site_id', $site->id)->where('slug', 'main-menu')->first();
        if (!$menu) return;

        MenuItem::where('menu_id', $menu->id)->delete();

        // Home
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Home',
            'target_type' => 'custom',
            'custom_url' => '/',
            'sort_order' => 1,
            'is_active' => true
        ]);

        // News dropdown
        $newsParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'News',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 2,
            'is_active' => true
        ]);

        $newsTopics = [
            'Breaking News',
            'Industry News',
            'Chart News',
            'UK Music',
            'Global Music'
        ];

        foreach ($newsTopics as $index => $topic) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $newsParent->id,
                'label' => $topic,
                'target_type' => 'custom',
                'custom_url' => '/news/' . strtolower(str_replace(' ', '-', $topic)),
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Sectors dropdown
        $sectorsParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Sectors',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 3,
            'is_active' => true
        ]);

        $sectors = [
            'Recorded Music',
            'Live Music',
            'Publishing',
            'Music Tech',
            'Radio'
        ];

        foreach ($sectors as $index => $sector) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $sectorsParent->id,
                'label' => $sector,
                'target_type' => 'custom',
                'custom_url' => '/sectors/' . strtolower(str_replace(' ', '-', $sector)),
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Features dropdown
        $featuresParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Features',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 4,
            'is_active' => true
        ]);

        $features = [
            'Interviews',
            'Analysis',
            'Opinion',
            'Reports'
        ];

        foreach ($features as $index => $feature) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $featuresParent->id,
                'label' => $feature,
                'target_type' => 'custom',
                'custom_url' => '/features/' . strtolower($feature),
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Charts & Data
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Charts & Data',
            'target_type' => 'custom',
            'custom_url' => '/charts',
            'sort_order' => 5,
            'is_active' => true
        ]);

        // About
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'About',
            'target_type' => 'custom',
            'custom_url' => '/about',
            'sort_order' => 6,
            'is_active' => true
        ]);
    }

    private function reorganizeGamesRadarMenu(): void
    {
        $site = Site::where('slug', 'gamesradar')->first();
        if (!$site) return;

        $menu = Menu::where('site_id', $site->id)->where('slug', 'main-menu')->first();
        if (!$menu) return;

        MenuItem::where('menu_id', $menu->id)->delete();

        // Home
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Home',
            'target_type' => 'custom',
            'custom_url' => '/',
            'sort_order' => 1,
            'is_active' => true
        ]);

        // Reviews dropdown
        $reviewsParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Reviews',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 2,
            'is_active' => true
        ]);

        $reviewTypes = [
            'Game Reviews',
            'Hardware Reviews',
            'PS5 Reviews',
            'Xbox Reviews',
            'PC Reviews',
            'Switch Reviews'
        ];

        foreach ($reviewTypes as $index => $type) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $reviewsParent->id,
                'label' => $type,
                'target_type' => 'custom',
                'custom_url' => '/reviews/' . strtolower(str_replace(' ', '-', $type)),
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Platforms dropdown
        $platformsParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Platforms',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 3,
            'is_active' => true
        ]);

        $platforms = [
            'PlayStation',
            'Xbox',
            'Nintendo Switch',
            'PC Gaming',
            'Mobile'
        ];

        foreach ($platforms as $index => $platform) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $platformsParent->id,
                'label' => $platform,
                'target_type' => 'custom',
                'custom_url' => '/platform/' . strtolower(str_replace(' ', '-', $platform)),
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Guides dropdown
        $guidesParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Guides',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 4,
            'is_active' => true
        ]);

        $guideTypes = [
            'Walkthroughs',
            'Tips & Tricks',
            'How To Guides',
            'Best Lists'
        ];

        foreach ($guideTypes as $index => $type) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $guidesParent->id,
                'label' => $type,
                'target_type' => 'custom',
                'custom_url' => '/guides/' . strtolower(str_replace(' ', '-', $type)),
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // News
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'News',
            'target_type' => 'custom',
            'custom_url' => '/news',
            'sort_order' => 5,
            'is_active' => true
        ]);

        // Features
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Features',
            'target_type' => 'custom',
            'custom_url' => '/features',
            'sort_order' => 6,
            'is_active' => true
        ]);

        // About
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'About',
            'target_type' => 'custom',
            'custom_url' => '/about',
            'sort_order' => 7,
            'is_active' => true
        ]);
    }

    private function reorganizeSoundwaveMenu(): void
    {
        $site = Site::where('slug', 'soundwave')->first();
        if (!$site) return;

        $menu = Menu::where('site_id', $site->id)->where('slug', 'main-menu')->first();
        if (!$menu) return;

        MenuItem::where('menu_id', $menu->id)->delete();

        // Home
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Home',
            'target_type' => 'custom',
            'custom_url' => '/',
            'sort_order' => 1,
            'is_active' => true
        ]);

        // Genres dropdown
        $genresParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Genres',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 2,
            'is_active' => true
        ]);

        $genres = [
            'Rock',
            'Pop',
            'Hip-Hop',
            'Electronic',
            'Indie',
            'Jazz',
            'R&B',
            'Metal'
        ];

        foreach ($genres as $index => $genre) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $genresParent->id,
                'label' => $genre,
                'target_type' => 'custom',
                'custom_url' => '/genre/' . strtolower(str_replace('-', '', $genre)),
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Reviews dropdown
        $reviewsParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Reviews',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 3,
            'is_active' => true
        ]);

        $reviewTypes = [
            'Album Reviews',
            'Track Reviews',
            'Live Reviews',
            'Festival Coverage'
        ];

        foreach ($reviewTypes as $index => $type) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $reviewsParent->id,
                'label' => $type,
                'target_type' => 'custom',
                'custom_url' => '/reviews/' . strtolower(str_replace(' ', '-', $type)),
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Features dropdown
        $featuresParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Features',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 4,
            'is_active' => true
        ]);

        $features = [
            'Interviews',
            'News',
            'Opinion',
            'Charts'
        ];

        foreach ($features as $index => $feature) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $featuresParent->id,
                'label' => $feature,
                'target_type' => 'custom',
                'custom_url' => '/' . strtolower($feature),
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Playlists
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Playlists',
            'target_type' => 'custom',
            'custom_url' => '/playlists',
            'sort_order' => 5,
            'is_active' => true
        ]);

        // About
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'About',
            'target_type' => 'custom',
            'custom_url' => '/about',
            'sort_order' => 6,
            'is_active' => true
        ]);
    }

    private function reorganizeWineChronicleMenu(): void
    {
        $site = Site::where('slug', 'wine-chronicle')->first();
        if (!$site) return;

        $menu = Menu::where('site_id', $site->id)->where('slug', 'main-menu')->first();
        if (!$menu) return;

        MenuItem::where('menu_id', $menu->id)->delete();

        // Home
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Home',
            'target_type' => 'custom',
            'custom_url' => '/',
            'sort_order' => 1,
            'is_active' => true
        ]);

        // Wine Regions dropdown
        $regionsParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Wine Regions',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 2,
            'is_active' => true
        ]);

        $regions = [
            ['label' => 'Bordeaux', 'url' => '/bordeaux-wine-guide-left-bank-right-bank'], // or '/region/bordeaux'
            ['label' => 'Burgundy', 'url' => '/burgundy-beginners-guide-appellations'],
            ['label' => 'Champagne', 'url' => '/champagne-region-houses-growers-guide'],
            ['label' => 'Tuscany', 'url' => '/tuscany-wine-guide-chianti-brunello'],
            ['label' => 'Rhône Valley', 'url' => '/rhone-valley-wine-guide-north-south'],
            ['label' => 'Napa Valley', 'url' => '/napa-valley-wine-guide-cabernet-sauvignon']
        ];

        foreach ($regions as $index => $region) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $regionsParent->id,
                'label' => $region['label'],
                'target_type' => 'custom',
                'custom_url' => $region['url'],
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Wine Travel dropdown
        $travelParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Wine Travel',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 3,
            'is_active' => true
        ]);

        $travelTopics = [
            ['label' => 'Vineyard Tours', 'url' => '/category/vineyard-tours'],
            ['label' => 'Wine Routes', 'url' => '/category/wine-routes'],
            ['label' => 'Destinations', 'url' => '/category/destinations']
        ];

        foreach ($travelTopics as $index => $topic) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $travelParent->id,
                'label' => $topic['label'],
                'target_type' => 'custom',
                'custom_url' => $topic['url'],
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // Wine Knowledge dropdown
        $knowledgeParent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Wine Knowledge',
            'target_type' => 'custom',
            'custom_url' => '#',
            'sort_order' => 4,
            'is_active' => true
        ]);

        $knowledgeTopics = [
            ['label' => 'Tasting Guides', 'url' => '/category/tasting-guides'],
            ['label' => 'Grape Varieties', 'url' => '/category/grape-varieties'],
            ['label' => 'Food Pairing', 'url' => '/category/food-pairing']
        ];

        foreach ($knowledgeTopics as $index => $topic) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $knowledgeParent->id,
                'label' => $topic['label'],
                'target_type' => 'custom',
                'custom_url' => $topic['url'],
                'sort_order' => $index + 1,
                'is_active' => true
            ]);
        }

        // About - find the page ID
        $aboutPage = \App\Models\Page::where('slug', 'about')
            ->where('site_id', $site->id)
            ->first();

        if ($aboutPage) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'label' => 'About',
                'target_type' => 'page',
                'target_id' => $aboutPage->id,
                'sort_order' => 5,
                'is_active' => true
            ]);
        }

        // Contact - find the page ID
        $contactPage = \App\Models\Page::where('slug', 'contact')
            ->where('site_id', $site->id)
            ->first();

        if ($contactPage) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'label' => 'Contact',
                'target_type' => 'page',
                'target_id' => $contactPage->id,
                'sort_order' => 6,
                'is_active' => true
            ]);
        }
    }
}