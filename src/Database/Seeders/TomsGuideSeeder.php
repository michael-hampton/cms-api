<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\CustomFieldDefinition;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\Site;
use App\Repositories\BlockRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;
use App\Services\BlockParserService;

class TomsGuideSeeder extends Seeder
{
    private $pageRepository;
    private $blockRepository;
    private $tagRepository;
    private $categoryRepository;
    private $blockParserService;
    private \App\Models\Model $site;
    private \App\Models\Model $menu;

    public function __construct()
    {
        $this->pageRepository = new PageRepository();
        $this->blockRepository = new BlockRepository();
        $this->tagRepository = new TagRepository();
        $this->categoryRepository = new CategoryRepository();
        $this->blockParserService = (new Container())->resolve(BlockParserService::class);

        parent::__construct();
    }

    public function run(): void
    {
        $this->createSite();
        $this->createMenu();
        $this->createTags();
        $this->createCategories();
        $this->createCustomFields();
        $this->createBestPicksPages(); // Create these FIRST so homepage can reference them
        $this->createHomepage();
        $this->createVsPages();
        $this->createNewsPages();
        $this->createHowToPages();
        $this->createDealsPages();
        $this->createAboutPage();
        $this->createContactPage();
        $this->createCategoryPages();
        $this->createCategoryMenuItems();
    }

    private function createSite(): void
    {
        $this->site = Site::create([
            'name' => 'Tom\'s Guide UK',
            'slug' => 'toms-guide',
            'is_active' => true,
        ]);
    }

    private function createMenu(): void
    {
        $this->menu = Menu::create([
            'name' => 'Main Menu',
            'slug' => 'main-menu',
            'site_id' => $this->site->id,
            'is_active' => true,
        ]);
    }

    private function createTags(): void
    {
        $tags = [
            'featured', 'trending', 'editors-choice', 'best-buy',
            'smartphones', 'laptops', 'tablets', 'wearables',
            'home-tech', 'entertainment', 'gaming', 'computing',
            'audio', 'cameras', 'smart-home', 'appliances',
            'tv', 'streaming', 'security', 'networking',
            'reviews', 'buying-guides', 'how-to', 'news',
            'deals', 'comparison', 'versus', 'explainer'
        ];

        foreach ($tags as $tagName) {
            $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
        }
    }

    private function createCategories(): void
    {
        $categories = [
            'Best Picks' => [
                'Phones' => ['Best Phones', 'Best iPhone', 'Best Android', 'Best Budget Phones'],
                'Laptops' => ['Best Laptops', 'Best Gaming Laptops', 'Best Chromebooks', 'Best MacBooks'],
                'Tablets' => ['Best Tablets', 'Best iPad', 'Best Android Tablets'],
                'Wearables' => ['Best Smartwatches', 'Best Fitness Trackers', 'Best Headphones'],
                'Home' => ['Best TVs', 'Best Soundbars', 'Best Speakers', 'Best Streaming Devices']
            ],
            'VS' => ['Phone Comparisons', 'Laptop Comparisons', 'Tech Face-Offs'],
            'News' => ['Mobile', 'Computing', 'Entertainment', 'Smart Home', 'Science'],
            'How To' => ['Phone Guides', 'Computing Tips', 'Streaming Help', 'Smart Home Setup'],
            'Deals' => ['Phone Deals', 'Laptop Deals', 'TV Deals', 'Tech Sales']
        ];

        $this->createCategoriesRecursively($categories);
    }

    private function createCategoriesRecursively(array $categories, ?int $parentId = null): void
    {
        foreach ($categories as $name => $children) {
            $category = $this->categoryRepository->findOrCreateByName($name, $this->site->id);
            if ($parentId) {
                $category->parent_id = $parentId;
                $category->save();
            }

            if (is_array($children)) {
                $this->createCategoriesRecursively($children, $category->id);
            }
        }
    }

    private function createCustomFields(): void
    {
        $fields = [
            ['key' => 'author_name', 'name' => 'Author Name', 'type' => 'text'],
            ['key' => 'author_bio', 'name' => 'Author Bio', 'type' => 'textarea'],
            ['key' => 'read_time', 'name' => 'Read Time (minutes)', 'type' => 'number'],
            ['key' => 'excerpt', 'name' => 'Article Excerpt', 'type' => 'textarea'],
            ['key' => 'review_score', 'name' => 'Review Score', 'type' => 'number'],
            ['key' => 'pros', 'name' => 'Pros', 'type' => 'textarea'],
            ['key' => 'cons', 'name' => 'Cons', 'type' => 'textarea'],
            ['key' => 'price', 'name' => 'Price', 'type' => 'text'],
            ['key' => 'buy_link', 'name' => 'Buy Link', 'type' => 'text'],
        ];

        foreach ($fields as $field) {
            CustomFieldDefinition::create([
                'key' => $field['key'],
                'name' => $field['name'],
                'type' => $field['type'],
                'is_active' => true,
                'sort_order' => 10,
                'options' => $field['options'] ?? null,
                'site_id' => $this->site->id
            ]);
        }
    }

    private function createBestPicksPages(): void
    {
        // PHONES - All 4 phones from homepage grid
        $this->createFullReview([
            'title' => 'iPhone 15 Pro Max Review: Apple\'s Best Gets Better',
            'slug' => 'iphone-15-pro-max-review',
            'tags' => ['featured', 'smartphones', 'reviews', 'editors-choice'],
            'categories' => ['Best Picks', 'Phones', 'Best iPhone'],
            'author' => 'John Smith',
            'read_time' => 10,
            'excerpt' => 'Apple\'s flagship delivers exceptional performance and cameras, but is it worth the premium price?',
            'score' => 4.5,
            'pros' => 'Titanium design, A17 Pro performance, Excellent cameras, Long battery life',
            'cons' => 'Very expensive, No major design changes, Heavy',
            'price' => '£1,199',
            'image' => 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?auto=format&fit=crop&w=2340&q=80',
            'award_type' => 'Editor\'s Choice'
        ]);

        $this->createFullReview([
            'title' => 'Samsung Galaxy S24 Ultra Review: Android Excellence',
            'slug' => 'samsung-galaxy-s24-ultra-review',
            'tags' => ['featured', 'smartphones', 'reviews', 'best-buy'],
            'categories' => ['Best Picks', 'Phones', 'Best Android'],
            'author' => 'Sarah Johnson',
            'read_time' => 12,
            'excerpt' => 'Samsung\'s S24 Ultra sets new standards for Android flagships with incredible cameras and performance.',
            'score' => 4.5,
            'pros' => '200MP camera, S Pen included, Excellent display, 7 years of updates',
            'cons' => 'Expensive, Large and heavy, Slow charging',
            'price' => '£1,249',
            'image' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?auto=format&fit=crop&w=2340&q=80',
            'award_type' => 'Best Android Phone'
        ]);

        $this->createFullReview([
            'title' => 'MacBook Pro M3 Review: Creative Powerhouse Perfected',
            'slug' => 'macbook-pro-m3-review',
            'tags' => ['featured', 'laptops', 'reviews', 'editors-choice'],
            'categories' => ['Best Picks', 'Laptops', 'Best MacBooks'],
            'author' => 'Mike Davis',
            'read_time' => 15,
            'excerpt' => 'Apple\'s M3 chip delivers incredible performance for creative professionals.',
            'score' => 5.0,
            'pros' => 'Incredible M3 performance, Stunning display, All-day battery, Silent operation',
            'cons' => 'Very expensive, Limited ports, RAM not upgradeable',
            'price' => '£1,699',
            'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=2340&q=80',
            'award_type' => 'Editor\'s Choice'
        ]);

        $this->createFullReview([
            'title' => 'Sony WH-1000XM5 Review: Noise-Cancelling Perfection',
            'slug' => 'sony-wh-1000xm5-review',
            'tags' => ['featured', 'wearables', 'reviews', 'best-buy'],
            'categories' => ['Best Picks', 'Wearables', 'Best Headphones'],
            'author' => 'Emma Wilson',
            'read_time' => 8,
            'excerpt' => 'Sony refines its flagship noise-cancelling headphones with improved sound and comfort.',
            'score' => 5.0,
            'pros' => 'Best-in-class ANC, Exceptional sound, Comfortable fit, 30-hour battery',
            'cons' => 'Expensive, No aptX support, Case is bulky',
            'price' => '£379',
            'image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=2340&q=80',
            'award_type' => 'Best Headphones'
        ]);

        // Additional phones for "Best Phones" section
        $this->createFullReview([
            'title' => 'Google Pixel 8 Pro Review: AI Camera Magic',
            'slug' => 'best-phones-pixel-8-pro',
            'tags' => ['smartphones', 'reviews', 'best-buy'],
            'categories' => ['Best Picks', 'Phones', 'Best Phones'],
            'author' => 'John Smith',
            'read_time' => 11,
            'excerpt' => 'Google\'s AI-powered phone with exceptional cameras and 7 years of updates.',
            'score' => 4.5,
            'pros' => 'Best camera system, Clean Android, 7 years updates, AI features',
            'cons' => 'Tensor G3 slower than rivals, Gets warm, Expensive',
            'price' => '£999',
            'image' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?auto=format&fit=crop&w=2340&q=80'
        ]);

        $this->createFullReview([
            'title' => 'OnePlus 12 Review: Flagship Killer Returns',
            'slug' => 'best-phones-oneplus-12',
            'tags' => ['smartphones', 'reviews'],
            'categories' => ['Best Picks', 'Phones', 'Best Phones'],
            'author' => 'Sarah Johnson',
            'read_time' => 9,
            'excerpt' => 'Flagship specs at a more affordable price point with 100W charging.',
            'score' => 4.0,
            'pros' => 'Great value, 100W fast charging, Excellent display, Good cameras',
            'cons' => 'No wireless charging, Average battery life, OxygenOS has bloat',
            'price' => '£799',
            'image' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=2340&q=80'
        ]);

        $this->createFullReview([
            'title' => 'iPhone 15 Review: The Best iPhone for Most People',
            'slug' => 'best-phones-iphone-15',
            'tags' => ['smartphones', 'reviews', 'best-buy'],
            'categories' => ['Best Picks', 'Phones', 'Best iPhone'],
            'author' => 'John Smith',
            'read_time' => 9,
            'excerpt' => 'The standard iPhone 15 offers most Pro features at a lower price.',
            'score' => 4.5,
            'pros' => 'Dynamic Island, Great cameras, USB-C finally, Good battery life',
            'cons' => '60Hz display, No telephoto lens, Still expensive',
            'price' => '£799',
            'image' => 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?auto=format&fit=crop&w=2340&q=80'
        ]);

        $this->createFullReview([
            'title' => 'Samsung Galaxy A54 Review: Best Budget Phone',
            'slug' => 'best-phones-galaxy-a54',
            'tags' => ['smartphones', 'reviews', 'best-buy'],
            'categories' => ['Best Picks', 'Phones', 'Best Budget Phones'],
            'author' => 'Sarah Johnson',
            'read_time' => 8,
            'excerpt' => 'Premium features at a budget price make this the phone to beat under £400.',
            'score' => 4.0,
            'pros' => 'Great display, Excellent cameras, Water resistant, 5 years updates',
            'cons' => 'Plastic build, Slow charging, Mediocre processor',
            'price' => '£399',
            'image' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?auto=format&fit=crop&w=2340&q=80'
        ]);

        // LAPTOPS - All 4 from homepage
        $this->createFullReview([
            'title' => 'Dell XPS 15 Review: Premium Windows Excellence',
            'slug' => 'best-laptops-dell-xps-15',
            'tags' => ['laptops', 'reviews'],
            'categories' => ['Best Picks', 'Laptops', 'Best Laptops'],
            'author' => 'Mike Davis',
            'read_time' => 10,
            'excerpt' => 'Premium Windows laptop with stunning OLED display and excellent build quality.',
            'score' => 4.5,
            'pros' => 'Gorgeous OLED display, Premium build, Strong performance, Great keyboard',
            'cons' => 'Expensive, Limited ports, Average battery',
            'price' => '£1,499',
            'image' => 'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?auto=format&fit=crop&w=2340&q=80'
        ]);

        $this->createFullReview([
            'title' => 'Asus ROG Zephyrus G14 Review: Gaming Laptop Excellence',
            'slug' => 'best-laptops-asus-rog-g14',
            'tags' => ['laptops', 'gaming', 'reviews'],
            'categories' => ['Best Picks', 'Laptops', 'Best Gaming Laptops'],
            'author' => 'Mike Davis',
            'read_time' => 12,
            'excerpt' => 'Compact gaming powerhouse with RTX 4090 performance.',
            'score' => 4.5,
            'pros' => 'RTX 4090 power, Compact design, Excellent display, Good battery',
            'cons' => 'Expensive, Gets hot, Loud fans',
            'price' => '£1,899',
            'image' => 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?auto=format&fit=crop&w=2340&q=80'
        ]);

        $this->createFullReview([
            'title' => 'Lenovo ThinkPad X1 Carbon Review: Business Laptop Perfection',
            'slug' => 'best-laptops-thinkpad-x1',
            'tags' => ['laptops', 'reviews'],
            'categories' => ['Best Picks', 'Laptops', 'Best Laptops'],
            'author' => 'Mike Davis',
            'read_time' => 9,
            'excerpt' => 'Business laptop perfection with legendary keyboard and durability.',
            'score' => 4.5,
            'pros' => 'Best keyboard, Ultra-light, MIL-STD tested, Excellent ports',
            'cons' => 'Expensive, Average display, Webcam quality',
            'price' => '£1,399',
            'image' => 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?auto=format&fit=crop&w=2340&q=80'
        ]);

        $this->createFullReview([
            'title' => 'MacBook Air M3 Review: The Perfect Everyday Laptop',
            'slug' => 'best-laptops-macbook-air-m3',
            'tags' => ['laptops', 'reviews', 'best-buy'],
            'categories' => ['Best Picks', 'Laptops', 'Best MacBooks'],
            'author' => 'Mike Davis',
            'read_time' => 10,
            'excerpt' => 'The MacBook Air gets even better with the M3 chip and remains the laptop to beat.',
            'score' => 5.0,
            'pros' => 'Fanless design, All-day battery, Beautiful display, Excellent performance',
            'cons' => 'Only two ports, No Face ID, Expensive upgrades',
            'price' => '£1,099',
            'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=2340&q=80'
        ]);

        $this->createFullReview([
            'title' => 'HP Spectre x360 14 Review: Versatile 2-in-1 Excellence',
            'slug' => 'best-laptops-hp-spectre-x360',
            'tags' => ['laptops', 'reviews'],
            'categories' => ['Best Picks', 'Laptops', 'Best Laptops'],
            'author' => 'Mike Davis',
            'read_time' => 10,
            'excerpt' => 'The best Windows 2-in-1 laptop with stunning design and versatility.',
            'score' => 4.5,
            'pros' => 'Beautiful OLED display, Versatile design, Great pen support, Solid battery',
            'cons' => 'Expensive, Heavy for tablet mode, Fan can be loud',
            'price' => '£1,599',
            'image' => 'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?auto=format&fit=crop&w=2340&q=80'
        ]);

        // Additional products for variety
        $this->createFullReview([
            'title' => 'iPad Pro M2 Review: Tablet or Laptop Replacement?',
            'slug' => 'best-tablets-ipad-pro-m2',
            'tags' => ['tablets', 'reviews', 'editors-choice'],
            'categories' => ['Best Picks', 'Tablets', 'Best iPad'],
            'author' => 'Emma Wilson',
            'read_time' => 11,
            'excerpt' => 'The most powerful tablet ever made, but can it replace your laptop?',
            'score' => 4.5,
            'pros' => 'M2 chip power, Stunning display, Apple Pencil hover, Excellent speakers',
            'cons' => 'Very expensive, iPadOS limitations, No calculator app',
            'price' => '£1,099',
            'image' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?auto=format&fit=crop&w=2340&q=80'
        ]);

        $this->createFullReview([
            'title' => 'Samsung Galaxy Tab S9 Ultra Review: Android Tablet King',
            'slug' => 'best-tablets-galaxy-tab-s9-ultra',
            'tags' => ['tablets', 'reviews'],
            'categories' => ['Best Picks', 'Tablets', 'Best Android Tablets'],
            'author' => 'Sarah Johnson',
            'read_time' => 10,
            'excerpt' => 'Massive 14.6-inch display makes this the ultimate Android tablet.',
            'score' => 4.5,
            'pros' => 'Huge stunning display, S Pen included, Great multitasking, Water resistant',
            'cons' => 'Extremely expensive, Heavy, Android tablet apps',
            'price' => '£1,199',
            'image' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?auto=format&fit=crop&w=2340&q=80'
        ]);

        $this->createFullReview([
            'title' => 'Apple Watch Series 9 Review: Still the Best Smartwatch',
            'slug' => 'best-wearables-apple-watch-series-9',
            'tags' => ['wearables', 'reviews', 'best-buy'],
            'categories' => ['Best Picks', 'Wearables', 'Best Smartwatches'],
            'author' => 'Emma Wilson',
            'read_time' => 9,
            'excerpt' => 'Iterative updates keep Apple Watch on top of the smartwatch world.',
            'score' => 4.5,
            'pros' => 'Bright display, Double tap gesture, Accurate sensors, Strong ecosystem',
            'cons' => 'Battery still one day, Expensive, iPhone only',
            'price' => '£399',
            'image' => 'https://images.unsplash.com/photo-1434494878577-86c23bcb06b9?auto=format&fit=crop&w=2340&q=80'
        ]);

        $this->createFullReview([
            'title' => 'AirPods Pro 2 Review: Worth the Premium Price',
            'slug' => 'best-wearables-airpods-pro-2',
            'tags' => ['wearables', 'audio', 'reviews'],
            'categories' => ['Best Picks', 'Wearables', 'Best Headphones'],
            'author' => 'Emma Wilson',
            'read_time' => 8,
            'excerpt' => 'Apple\'s premium earbuds get even better with improved ANC and sound.',
            'score' => 4.5,
            'pros' => 'Excellent ANC, Great sound, Seamless Apple integration, Good battery',
            'cons' => 'Expensive, Stems still divide opinion, No volume controls',
            'price' => '£229',
            'image' => 'https://images.unsplash.com/photo-1606841837239-c5a1a4a07af7?auto=format&fit=crop&w=2340&q=80'
        ]);

        $this->createFullReview([
            'title' => 'LG C3 OLED TV Review: Best TV for Most People',
            'slug' => 'best-home-lg-c3-oled',
            'tags' => ['tv', 'reviews', 'best-buy'],
            'categories' => ['Best Picks', 'Home', 'Best TVs'],
            'author' => 'John Smith',
            'read_time' => 12,
            'excerpt' => 'LG\'s mid-range OLED delivers stunning picture quality at a reasonable price.',
            'score' => 4.5,
            'pros' => 'Stunning OLED picture, Great for gaming, webOS is excellent, Good value',
            'cons' => 'Can\'t match QD-OLED brightness, Sound is average, Ads in UI',
            'price' => '£1,299',
            'image' => 'https://images.unsplash.com/photo-1593784991095-a205069470b6?auto=format&fit=crop&w=2340&q=80'
        ]);

        $this->createFullReview([
            'title' => 'Sonos Arc Review: Premium Soundbar Excellence',
            'slug' => 'best-home-sonos-arc',
            'tags' => ['audio', 'reviews'],
            'categories' => ['Best Picks', 'Home', 'Best Soundbars'],
            'author' => 'Emma Wilson',
            'read_time' => 9,
            'excerpt' => 'Sonos Arc delivers cinematic sound with easy setup and great music playback.',
            'score' => 4.5,
            'pros' => 'Excellent sound quality, Dolby Atmos support, Easy setup, Great music',
            'cons' => 'Very expensive, No DTS support, Requires Sonos app',
            'price' => '£899',
            'image' => 'https://images.unsplash.com/photo-1545127398-14699f92334b?auto=format&fit=crop&w=2340&q=80'
        ]);
    }

    private function createFullReview(array $data): void
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => $data['title'] . ' - Tom\'s Guide UK',
            'meta_description' => $data['excerpt'],
            'site_id' => $this->site->id,
        ]);

        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
            $page->tags(true)->attach($tag->id);
        }

        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, $this->site->id);
            $page->categories(true)->attach($category->id);
        }

        $customFields = [
            'author_name' => $data['author'],
            'read_time' => $data['read_time'],
            'excerpt' => $data['excerpt'],
            'review_score' => $data['score'],
            'pros' => $data['pros'],
            'cons' => $data['cons'],
            'price' => $data['price'],
        ];

        foreach ($customFields as $key => $value) {
            $fieldDef = CustomFieldDefinition::where('key', $key)->first();
            if ($fieldDef) {
                PageCustomField::create([
                    'custom_field_definition_id' => $fieldDef->id,
                    'field_value' => (string)$value,
                    'page_id' => $page->id
                ]);
            }
        }

        $blocks = [
            [
                'type' => 'image',
                'data' => [
                    'src' => $data['image'],
                    'alt' => $data['title'],
                    'caption' => $data['title'],
                    'layout' => 'full',
                    'alignment' => 'fullscreen'
                ],
                'order' => 1
            ]
        ];

        // Generate unique intro based on product type
        if (str_contains($data['slug'], 'iphone-15-pro-max')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The iPhone 15 Pro Max represents Apple\'s most ambitious smartphone to date. The switch to titanium, USB-C, and the A17 Pro chip mark significant upgrades.',
                        'After two weeks of intensive testing, including photo shoots, gaming sessions, and daily use, we\'ve found this to be the best iPhone ever made.',
                        'With a starting price of £1,199, it\'s expensive, but the combination of features, performance, and build quality justify the premium for those who want the absolute best.'
                    ]
                ],
                'order' => 2
            ];
        } elseif (str_contains($data['slug'], 'galaxy-s24-ultra')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The Samsung Galaxy S24 Ultra is a powerhouse that pushes smartphone capabilities to new heights. With its 200MP camera, integrated S Pen, and stunning 6.8-inch display, it\'s the ultimate Android phone.',
                        'We\'ve spent three weeks testing every aspect of the S24 Ultra, from its AI-powered camera features to its all-day battery life.',
                        'The addition of seven years of OS updates is a game-changer, ensuring your £1,249 investment remains relevant and secure for years to come.'
                    ]
                ],
                'order' => 2
            ];
        } elseif (str_contains($data['slug'], 'macbook-pro-m3')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The MacBook Pro with M3 chip represents the pinnacle of laptop design and performance. Apple has created a machine that handles professional creative workloads with ease while maintaining incredible battery life.',
                        'Whether editing 8K video in Final Cut Pro, rendering 3D models in Blender, or compiling complex code, the M3 MacBook Pro handles it effortlessly.',
                        'The combination of raw performance, stunning Liquid Retina XDR display, and macOS Sonoma creates an unparalleled creative workflow that justifies the £1,699 starting price.'
                    ]
                ],
                'order' => 2
            ];
        } elseif (str_contains($data['slug'], 'sony-wh-1000xm5')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The Sony WH-1000XM5 represents the peak of noise-cancelling headphone technology. Sony has refined every aspect from the previous generation, delivering the best ANC experience available.',
                        'After hundreds of hours of testing across flights, commutes, and office work, these headphones have become our top recommendation for anyone seeking audio isolation and exceptional sound quality.',
                        'At £379, they\'re expensive, but the combination of comfort, sound quality, and industry-leading noise cancellation make them worth every penny for frequent travelers.'
                    ]
                ],
                'order' => 2
            ];
        } else {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        $data['excerpt'],
                        'After extensive hands-on testing, we\'ve evaluated every aspect of this product to bring you our comprehensive verdict.',
                        'Our testing process involves real-world usage scenarios, benchmark comparisons, and detailed feature analysis to ensure our recommendations are reliable.'
                    ]
                ],
                'order' => 2
            ];
        }

        if (isset($data['award_type'])) {
            $blocks[] = [
                'type' => 'award',
                'data' => [
                    'subcategory' => $data['award_type'],
                    'productName' => $data['title'],
                    'image' => $data['image'],
                    'winner' => true,
                    'rating' => $data['score'],
                    'strapline' => $data['excerpt']
                ],
                'order' => 3
            ];
        }

        $blocks[] = [
            'type' => 'heading',
            'data' => ['text' => 'Design and Build Quality', 'level' => 2],
            'order' => 4
        ];

        // Unique design content based on product
        if (str_contains($data['slug'], 'iphone')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The switch to titanium is immediately noticeable. At 221g, the iPhone 15 Pro Max is 19g lighter than its stainless steel predecessor, making it easier to handle despite the large 6.7-inch display.',
                        'Apple has refined the design with slimmer bezels and contoured edges that feel more comfortable. The brushed titanium finish resists fingerprints better than the glossy stainless steel of previous models.',
                        'The new Action button replaces the mute switch, offering customizable shortcuts. While it takes getting used to, we appreciate the added functionality for launching the camera, voice memos, or shortcuts.'
                    ]
                ],
                'order' => 5
            ];
        } elseif (str_contains($data['slug'], 'galaxy')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Samsung\'s design language remains refined and professional. The titanium frame feels premium and provides excellent structural rigidity. At 232g, it\'s hefty but manageable for a 6.8-inch device.',
                        'The flat display is a welcome change from curved screens of previous generations. It\'s more practical for everyday use, works better with screen protectors, and eliminates accidental edge touches.',
                        'Build quality is exceptional throughout. The phone has zero flex, buttons are tactile and responsive, and the integrated S Pen slots perfectly into the chassis without adding bulk.'
                    ]
                ],
                'order' => 5
            ];
        } elseif (str_contains($data['slug'], 'macbook')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Apple\'s aluminum unibody construction remains the gold standard for laptop build quality. The MacBook Pro feels incredibly solid with zero flex in the chassis or keyboard deck.',
                        'At 1.61kg for the 14-inch model, it\'s portable enough for daily commutes while feeling substantial and premium. The space gray finish resists scratches and looks professional.',
                        'Every detail is meticulously crafted, from the smooth hinge mechanism to the satisfying click of the large trackpad. This is industrial design at its finest.'
                    ]
                ],
                'order' => 5
            ];
        } elseif (str_contains($data['slug'], 'dell-xps')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The Dell XPS 15 maintains its reputation for premium build quality. The all-aluminum chassis feels solid and sophisticated, rivaling the MacBook Pro in materials and construction.',
                        'Dell has slimmed the InfinityEdge bezels further, giving you a massive 15.6-inch screen in a footprint closer to traditional 14-inch laptops. At 1.96kg, it\'s portable for a 15-inch machine.',
                        'The carbon fiber palm rest stays cool during use and resists fingerprints better than aluminum. The keyboard deck has zero flex, even when typing vigorously.'
                    ]
                ],
                'order' => 5
            ];
        } elseif (str_contains($data['slug'], 'sony')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Sony has refined the design significantly from the XM4. The headband is slimmer and more elegant, while the ear cups are slightly larger for better comfort during extended wear.',
                        'Materials feel premium throughout with soft synthetic leather on the ear cushions and a matte finish on the plastic that resists fingerprints. Build quality inspires confidence for daily use.',
                        'At 250g, they\'re lightweight for over-ear headphones. The folding mechanism feels robust and they collapse down into a compact (if bulky) carrying case.'
                    ]
                ],
                'order' => 5
            ];
        } else {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The design is immediately impressive with premium materials and excellent build quality throughout.',
                        'Attention to detail is evident in every aspect, from the button placement to the overall ergonomics.',
                        'The finish resists fingerprints reasonably well and feels durable for long-term use.'
                    ]
                ],
                'order' => 5
            ];
        }

        $blocks[] = [
            'type' => 'heading',
            'data' => ['text' => 'Performance', 'level' => 2],
            'order' => 6
        ];

        // Unique performance content
        if (str_contains($data['slug'], 'iphone-15-pro-max')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The A17 Pro chip is a generational leap forward. Built on a 3nm process, it delivers 10% faster CPU performance and 20% faster GPU performance while being more power efficient.',
                        'In real-world use, apps launch instantly, multitasking is seamless, and demanding games like Resident Evil Village run at console-quality settings with ray tracing enabled at a smooth 60fps.',
                        'The 8GB of RAM (up from 6GB) ensures apps stay in memory longer. We experienced zero slowdowns or crashes during our testing period, even with dozens of apps open.'
                    ]
                ],
                'order' => 7
            ];
        } elseif (str_contains($data['slug'], 'galaxy-s24-ultra')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The Snapdragon 8 Gen 3 delivers desktop-class performance. In Geekbench 6, it scored 2,300 single-core and 7,200 multi-core, matching or exceeding the iPhone 15 Pro Max in many tests.',
                        'Gaming performance is exceptional with sustained high frame rates in demanding titles like Genshin Impact and Call of Duty Mobile. The large vapor chamber cooling system prevents thermal throttling.',
                        'The 12GB of RAM enables true multitasking. We regularly ran 20+ apps simultaneously with Samsung DeX connected to an external monitor without any performance degradation.'
                    ]
                ],
                'order' => 7
            ];
        } elseif (str_contains($data['slug'], 'macbook-pro-m3')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The M3 chip is a revelation. In our 4K video export test using Final Cut Pro, it completed a 10-minute timeline with effects in just 3 minutes 42 seconds—45% faster than the M2.',
                        'Blender rendering saw similar improvements. Complex scenes that took minutes on Intel-based MacBooks now render in seconds. The hardware-accelerated ray tracing brings console-quality visuals to macOS.',
                        'What\'s remarkable is the efficiency. The MacBook Pro remains cool and silent even under sustained load. The fan only activated during our most extreme stress tests, and even then was barely audible.'
                    ]
                ],
                'order' => 7
            ];
        } else {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Performance is exceptional across the board. Daily tasks are handled with ease and never feel sluggish.',
                        'In our benchmark testing, results were consistently impressive and competitive with rivals in this category.',
                        'Real-world performance matches the specifications, with smooth operation throughout our extensive testing period.'
                    ]
                ],
                'order' => 7
            ];
        }

        $blocks[] = [
            'type' => 'heading',
            'data' => ['text' => 'Key Features', 'level' => 2],
            'order' => 8
        ];

        $blocks[] = [
            'type' => 'list',
            'data' => [
                'listType' => 'ul',
                'items' => explode(', ', $data['pros'])
            ],
            'order' => 9
        ];

        $blocks[] = [
            'type' => 'heading',
            'data' => ['text' => 'Battery Life', 'level' => 2],
            'order' => 10
        ];

        // Unique battery content
        if (str_contains($data['slug'], 'iphone')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Battery life is excellent. In our web browsing test over 5G, the iPhone 15 Pro Max lasted 14 hours and 2 minutes—well above average and enough for a full day plus evening use.',
                        'Video playback yielded even better results at 16 hours and 45 minutes. In real-world mixed use, we consistently ended the day with 20-30% remaining after heavy photography and social media use.',
                        'Charging speeds are adequate but not class-leading. The phone supports 27W wired charging, taking about 50 minutes to reach 50% and nearly 2 hours for a full charge. MagSafe wireless charging tops out at 15W.'
                    ]
                ],
                'order' => 11
            ];
        } elseif (str_contains($data['slug'], 'galaxy')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The 5,000mAh battery easily lasts a full day. In our testing, we achieved 13 hours and 35 minutes of web browsing over 5G—excellent for a phone with this large, bright display.',
                        'Real-world usage typically left us with 25-35% remaining at bedtime after heavy use including photography, gaming, and streaming. Power users can comfortably get through a full day.',
                        'The 45W wired charging is faster than the iPhone, reaching 50% in about 30 minutes and full charge in 65 minutes. Wireless charging maxes out at 15W, which is adequate but not exceptional.'
                    ]
                ],
                'order' => 11
            ];
        } elseif (str_contains($data['slug'], 'macbook-pro-m3')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Battery life is simply exceptional. In our web browsing test, the 14-inch MacBook Pro lasted 18 hours and 32 minutes—easily a full workday plus evening use on a single charge.',
                        'Even under heavy creative workloads, we routinely achieved 10-12 hours. Video editing in Final Cut Pro, the battery lasted 8 hours of continuous work before needing a charge.',
                        'The 96W USB-C fast charging provides 50% charge in just 30 minutes. MagSafe 3 charging is convenient and secure, though you can still charge via USB-C if needed.'
                    ]
                ],
                'order' => 11
            ];
        } else {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Battery performance is solid, easily lasting through a full day of typical use without issue.',
                        'In our standardized battery tests, results were competitive with category leaders and met manufacturer claims.',
                        'Charging speeds are respectable, though not class-leading in this price range. A full charge takes approximately 1-2 hours depending on usage.'
                    ]
                ],
                'order' => 11
            ];
        }

        $blocks[] = [
            'type' => 'product',
            'data' => [
                'name' => $data['title'],
                'brand' => explode(' ', $data['title'])[0],
                'productName' => $data['title'],
                'image' => $data['image'],
                'price' => (float)str_replace(['£', ','], '', $data['price']),
                'currency' => '£',
                'description' => $data['excerpt'],
                'link' => 'https://example.com/buy',
                'linkText' => 'Check Price',
                'displayAs' => 'button',
                'showReviewPanel' => true,
                'review' => [
                    'rating' => $data['score'],
                    'pros' => explode(', ', $data['pros']),
                    'cons' => explode(', ', $data['cons'])
                ]
            ],
            'order' => 12
        ];

        $blocks[] = [
            'type' => 'heading',
            'data' => ['text' => 'Verdict', 'level' => 2],
            'order' => 13
        ];

        // Unique verdict based on score
        if ($data['score'] >= 4.5) {
            $verdict = 'This is an outstanding product that excels in virtually every category. While not perfect, the combination of features, performance, and quality make it a top recommendation.';
        } else {
            $verdict = 'This is a very good product with some notable strengths and a few weaknesses. It offers solid value for the asking price in a competitive market.';
        }

        $blocks[] = [
            'type' => 'text',
            'data' => [
                'paragraphs' => [
                    $verdict,
                    'The ' . $data['price'] . ' asking price positions it as a premium offering, which is justified by the quality and feature set on offer.',
                    'We recommend this product for users who prioritize the strengths outlined in our testing and can live with the minor drawbacks mentioned.'
                ]
            ],
            'order' => 14
        ];

        $blocks[] = [
            'type' => 'quote',
            'data' => [
                'text' => $data['excerpt'],
                'attribution' => $data['author'] . ', Tom\'s Guide'
            ],
            'order' => 15
        ];

        foreach ($blocks as $block) {
            $this->blockRepository->create([
                'page_id' => $page->id,
                'type' => $block['type'],
                'data' => json_encode($block['data']),
                'order' => $block['order']
            ]);
        }
    }

    private function createHomepage(): void
    {
        $page = Page::create([
            'title' => 'Tom\'s Guide UK - Tech Reviews, Buying Guides & News',
            'page_type' => 'content',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'Tom\'s Guide UK | Expert Tech Reviews & Buying Guides',
            'meta_description' => 'Get expert tech reviews, buying guides, news and deals. We test and review the latest phones, laptops, TVs and more.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Home',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
            'sort_order' => 1
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Expert Tech Reviews & Buying Guides',
                    'subtitle' => 'Independent testing and advice to help you buy smarter',
                    'ctaText' => 'Browse Reviews',
                    'ctaUrl' => '#reviews',
                    'secondaryCtaText' => 'Latest Deals',
                    'secondaryCtaUrl' => '#deals',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1526948531399-320e7e40f0ca?auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Latest Reviews',
                    'subtitle' => 'Expert verdicts on the newest tech',
                    'level' => 2
                ],
                'order' => 2
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'layout' => 'grid',
                    'columns' => 4,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'pages' => [
                        [
                            'title' => 'iPhone 15 Pro Max Review',
                            'slug' => 'iphone-15-pro-max-review',
                            'excerpt' => 'Apple\'s flagship delivers exceptional performance and cameras, but is it worth the premium price?',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'iPhone 15 Pro Max'
                            ],
                            'badge' => [
                                'text' => 'Editor\'s Choice',
                                'color' => 'primary'
                            ],
                            'meta' => [
                                'author' => 'John Smith',
                                'date' => 'Dec 10, 2024',
                                'readTime' => '10 min read'
                            ]
                        ],
                        [
                            'title' => 'Samsung Galaxy S24 Ultra Review',
                            'slug' => 'samsung-galaxy-s24-ultra-review',
                            'excerpt' => 'Samsung\'s S24 Ultra sets new standards for Android flagships with incredible cameras and performance.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Samsung Galaxy S24 Ultra'
                            ],
                            'badge' => [
                                'text' => '4.5/5',
                                'color' => 'success'
                            ],
                            'meta' => [
                                'author' => 'Sarah Johnson',
                                'date' => 'Dec 9, 2024',
                                'readTime' => '12 min read'
                            ]
                        ],
                        [
                            'title' => 'MacBook Pro M3 Review',
                            'slug' => 'macbook-pro-m3-review',
                            'excerpt' => 'Apple\'s M3 chip delivers incredible performance for creative professionals.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'MacBook Pro M3'
                            ],
                            'badge' => [
                                'text' => 'Best Buy',
                                'color' => 'warning'
                            ],
                            'meta' => [
                                'author' => 'Mike Davis',
                                'date' => 'Dec 8, 2024',
                                'readTime' => '15 min read'
                            ]
                        ],
                        [
                            'title' => 'Sony WH-1000XM5 Review',
                            'slug' => 'sony-wh-1000xm5-review',
                            'excerpt' => 'Sony refines its flagship noise-cancelling headphones with improved sound and comfort.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Sony WH-1000XM5'
                            ],
                            'badge' => [
                                'text' => '5/5',
                                'color' => 'primary'
                            ],
                            'meta' => [
                                'author' => 'Emma Wilson',
                                'date' => 'Dec 7, 2024',
                                'readTime' => '8 min read'
                            ]
                        ]
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Latest News',
                    'subtitle' => 'Stay updated with the tech world',
                    'level' => 2
                ],
                'order' => 4
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'layout' => 'grid',
                    'columns' => 4,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'pages' => [
                        [
                            'title' => 'Apple Announces New AI Features',
                            'slug' => 'apple-ai-features-announcement',
                            'excerpt' => 'Apple reveals major AI upgrades coming to iOS 18 and macOS.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1611532736570-b9bf59d4aaea?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Apple AI'
                            ],
                            'meta' => [
                                'date' => 'Dec 11, 2024',
                                'readTime' => '5 min read'
                            ]
                        ],
                        [
                            'title' => 'Samsung Teases Galaxy S25',
                            'slug' => 'samsung-galaxy-s25-teaser',
                            'excerpt' => 'First look at Samsung\'s next flagship smartphone.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Galaxy S25'
                            ],
                            'meta' => [
                                'date' => 'Dec 11, 2024',
                                'readTime' => '4 min read'
                            ]
                        ],
                        [
                            'title' => 'Google Pixel 9 Leaks',
                            'slug' => 'google-pixel-9-leaks',
                            'excerpt' => 'New renders show redesigned Pixel 9 with improved cameras.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Google Pixel 9'
                            ],
                            'meta' => [
                                'date' => 'Dec 10, 2024',
                                'readTime' => '6 min read'
                            ]
                        ],
                        [
                            'title' => 'Netflix Price Increase',
                            'slug' => 'netflix-price-increase-2024',
                            'excerpt' => 'Streaming giant raises subscription prices across all tiers.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1611162617474-5b21e879e113?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Netflix'
                            ],
                            'meta' => [
                                'date' => 'Dec 10, 2024',
                                'readTime' => '3 min read'
                            ]
                        ]
                    ]
                ],
                'order' => 5
            ],
            [
                'type' => 'team',
                'data' => [
                    'title' => 'Meet Our Expert Team',
                    'subtitle' => 'Award-winning journalists and tech experts',
                    'layout' => 'grid',
                    'members' => [
                        [
                            'name' => 'John Smith',
                            'role' => 'Editor-in-Chief',
                            'bio' => '15+ years testing and reviewing consumer technology',
                            'image' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=400&q=80',
                            'email' => 'john@tomsguide.com'
                        ],
                        [
                            'name' => 'Sarah Johnson',
                            'role' => 'Senior Mobile Editor',
                            'bio' => 'Smartphone expert who\'s tested hundreds of devices',
                            'image' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=400&q=80',
                            'email' => 'sarah@tomsguide.com'
                        ],
                        [
                            'name' => 'Mike Davis',
                            'role' => 'Computing Editor',
                            'bio' => 'Laptop and desktop PC specialist',
                            'image' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=400&q=80',
                            'email' => 'mike@tomsguide.com'
                        ],
                        [
                            'name' => 'Emma Wilson',
                            'role' => 'Audio & Wearables Editor',
                            'bio' => 'Expert in headphones, speakers and smartwatches',
                            'image' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=400&q=80',
                            'email' => 'emma@tomsguide.com'
                        ]
                    ]
                ],
                'order' => 6
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Best Phones',
                    'subtitle' => 'Our top-rated smartphones',
                    'level' => 2
                ],
                'order' => 7
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'layout' => 'grid',
                    'columns' => 4,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'pages' => [
                        [
                            'title' => 'iPhone 15 Pro Max',
                            'slug' => 'best-phones-iphone-15-pro-max',
                            'excerpt' => 'The ultimate iPhone with titanium design and best-in-class cameras.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'iPhone 15 Pro Max'
                            ],
                            'badge' => [
                                'text' => 'Best Overall',
                                'color' => 'primary'
                            ]
                        ],
                        [
                            'title' => 'Samsung Galaxy S24 Ultra',
                            'slug' => 'best-phones-galaxy-s24-ultra',
                            'excerpt' => 'Samsung\'s powerhouse with S Pen and 200MP camera.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Galaxy S24 Ultra'
                            ],
                            'badge' => [
                                'text' => 'Best Android',
                                'color' => 'success'
                            ]
                        ],
                        [
                            'title' => 'Google Pixel 8 Pro',
                            'slug' => 'best-phones-pixel-8-pro',
                            'excerpt' => 'Google\'s AI-powered phone with exceptional cameras.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Pixel 8 Pro'
                            ],
                            'badge' => [
                                'text' => 'Best Camera',
                                'color' => 'warning'
                            ]
                        ],
                        [
                            'title' => 'OnePlus 12',
                            'slug' => 'best-phones-oneplus-12',
                            'excerpt' => 'Flagship specs at a more affordable price point.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'OnePlus 12'
                            ],
                            'badge' => [
                                'text' => 'Best Value',
                                'color' => 'primary'
                            ]
                        ]
                    ]
                ],
                'order' => 8
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Best Laptops',
                    'subtitle' => 'Top-rated notebooks for every need',
                    'level' => 2
                ],
                'order' => 9
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'layout' => 'grid',
                    'columns' => 4,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'pages' => [
                        [
                            'title' => 'MacBook Pro 16-inch M3 Max',
                            'slug' => 'best-laptops-macbook-pro-m3',
                            'excerpt' => 'The ultimate laptop for creative professionals.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'MacBook Pro M3'
                            ],
                            'badge' => [
                                'text' => 'Editor\'s Choice',
                                'color' => 'primary'
                            ]
                        ],
                        [
                            'title' => 'Dell XPS 15',
                            'slug' => 'best-laptops-dell-xps-15',
                            'excerpt' => 'Premium Windows laptop with stunning OLED display.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Dell XPS 15'
                            ],
                            'badge' => [
                                'text' => 'Best Windows',
                                'color' => 'success'
                            ]
                        ],
                        [
                            'title' => 'Asus ROG Zephyrus G14',
                            'slug' => 'best-laptops-asus-rog-g14',
                            'excerpt' => 'Compact gaming powerhouse with RTX 4090.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Asus ROG G14'
                            ],
                            'badge' => [
                                'text' => 'Best Gaming',
                                'color' => 'warning'
                            ]
                        ],
                        [
                            'title' => 'Lenovo ThinkPad X1 Carbon',
                            'slug' => 'best-laptops-thinkpad-x1',
                            'excerpt' => 'Business laptop perfection with legendary keyboard.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'ThinkPad X1'
                            ],
                            'badge' => [
                                'text' => 'Best Business',
                                'color' => 'primary'
                            ]
                        ]
                    ]
                ],
                'order' => 10
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createBlocksForPage(int $pageId, array $blocks): void
    {
        foreach ($blocks as $blockData) {
            $this->blockRepository->create([
                'page_id' => $pageId,
                'type' => $blockData['type'],
                'data' => json_encode($blockData['data']),
                'order' => $blockData['order']
            ]);
        }
    }

    private function createVsPages(): void
    {
        $this->createComparisonArticle([
            'title' => 'iPhone 15 Pro Max vs Samsung Galaxy S24 Ultra: Which Flagship Wins?',
            'slug' => 'iphone-15-pro-max-vs-galaxy-s24-ultra',
            'tags' => ['versus', 'smartphones', 'comparison'],
            'categories' => ['VS', 'Phone Comparisons'],
            'author' => 'Sarah Johnson',
            'read_time' => 12,
            'excerpt' => 'We compare Apple and Samsung\'s best phones to help you decide which flagship is right for you.',
            'productA' => 'iPhone 15 Pro Max',
            'productB' => 'Galaxy S24 Ultra',
            'image' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?auto=format&fit=crop&w=2340&q=80'
        ]);

        $this->createComparisonArticle([
            'title' => 'MacBook Pro M3 vs Dell XPS 15: Which Premium Laptop Wins?',
            'slug' => 'macbook-pro-m3-vs-dell-xps-15',
            'tags' => ['versus', 'laptops', 'comparison'],
            'categories' => ['VS', 'Laptop Comparisons'],
            'author' => 'Mike Davis',
            'read_time' => 10,
            'excerpt' => 'The ultimate battle between macOS and Windows premium laptops.',
            'productA' => 'MacBook Pro 14" M3',
            'productB' => 'Dell XPS 15',
            'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=2340&q=80'
        ]);

        $this->createComparisonArticle([
            'title' => 'AirPods Pro vs Sony WF-1000XM5: Best Wireless Earbuds?',
            'slug' => 'airpods-pro-vs-sony-wf-1000xm5',
            'tags' => ['versus', 'wearables', 'comparison'],
            'categories' => ['VS', 'Tech Face-Offs'],
            'author' => 'Emma Wilson',
            'read_time' => 8,
            'excerpt' => 'Two premium wireless earbuds battle for supremacy.',
            'productA' => 'AirPods Pro 2',
            'productB' => 'Sony WF-1000XM5',
            'image' => 'https://images.unsplash.com/photo-1606841837239-c5a1a4a07af7?auto=format&fit=crop&w=2340&q=80'
        ]);

        $this->createComparisonArticle([
            'title' => 'PS5 vs Xbox Series X: Which Console Should You Buy?',
            'slug' => 'ps5-vs-xbox-series-x',
            'tags' => ['versus', 'gaming', 'comparison'],
            'categories' => ['VS', 'Tech Face-Offs'],
            'author' => 'John Smith',
            'read_time' => 11,
            'excerpt' => 'The console war continues – we help you pick the right system.',
            'productA' => 'PlayStation 5',
            'productB' => 'Xbox Series X',
            'image' => 'https://images.unsplash.com/photo-1486401899868-0e435ed85128?auto=format&fit=crop&w=2340&q=80'
        ]);

        $this->createComparisonArticle([
            'title' => 'iPad Pro vs Samsung Galaxy Tab S9 Ultra: Best Premium Tablet?',
            'slug' => 'ipad-pro-vs-galaxy-tab-s9-ultra',
            'tags' => ['versus', 'tablets', 'comparison'],
            'categories' => ['VS', 'Tech Face-Offs'],
            'author' => 'Sarah Johnson',
            'read_time' => 10,
            'excerpt' => 'The two best tablets face off in this detailed comparison.',
            'productA' => 'iPad Pro 12.9"',
            'productB' => 'Galaxy Tab S9 Ultra',
            'image' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?auto=format&fit=crop&w=2340&q=80'
        ]);
    }

    private function createComparisonArticle(array $data): void
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => $data['title'] . ' - Tom\'s Guide UK',
            'meta_description' => $data['excerpt'],
            'site_id' => $this->site->id,
        ]);

        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
            $page->tags(true)->attach($tag->id);
        }

        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, $this->site->id);
            $page->categories(true)->attach($category->id);
        }

        $customFields = [
            'author_name' => $data['author'],
            'read_time' => $data['read_time'],
            'excerpt' => $data['excerpt'],
        ];

        foreach ($customFields as $key => $value) {
            $fieldDef = CustomFieldDefinition::where('key', $key)->first();
            if ($fieldDef) {
                PageCustomField::create([
                    'custom_field_definition_id' => $fieldDef->id,
                    'field_value' => (string)$value,
                    'page_id' => $page->id
                ]);
            }
        }

        $blocks = [
            [
                'type' => 'image',
                'data' => [
                    'src' => $data['image'],
                    'alt' => $data['title'],
                    'caption' => 'The ultimate face-off',
                    'layout' => 'full',
                    'alignment' => 'fullscreen'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Choosing between these two excellent products is difficult. Both offer premium features and excellent performance.',
                        'We\'ve tested both extensively to help you make an informed decision based on your needs and preferences.',
                        'This comprehensive comparison covers design, performance, features, and value to help you choose the right one.'
                    ]
                ],
                'order' => 2
            ]
        ];

        // Generate unique comparison tables and content based on slug
        if (str_contains($data['slug'], 'iphone') && str_contains($data['slug'], 'galaxy')) {
            $blocks[] = [
                'type' => 'product-comparison',
                'data' => [
                    'title' => 'Specifications Comparison',
                    'productA' => 'iPhone 15 Pro Max',
                    'productB' => 'Galaxy S24 Ultra',
                    'comparisons' => [
                        ['subtitle' => 'Display', 'items' => [['value' => '6.7" OLED, 120Hz'], ['value' => '6.8" AMOLED, 120Hz']]],
                        ['subtitle' => 'Processor', 'items' => [['value' => 'A17 Pro (3nm)'], ['value' => 'Snapdragon 8 Gen 3']]],
                        ['subtitle' => 'RAM', 'items' => [['value' => '8GB'], ['value' => '12GB']]],
                        ['subtitle' => 'Main Camera', 'items' => [['value' => '48MP'], ['value' => '200MP']]],
                        ['subtitle' => 'Telephoto', 'items' => [['value' => '5x optical'], ['value' => '3x + 5x optical']]],
                        ['subtitle' => 'Battery', 'items' => [['value' => '4,422mAh'], ['value' => '5,000mAh']]],
                        ['subtitle' => 'Charging', 'items' => [['value' => '27W wired'], ['value' => '45W wired']]],
                        ['subtitle' => 'S Pen', 'items' => [['value' => 'No'], ['value' => 'Yes, included']]],
                        ['subtitle' => 'Weight', 'items' => [['value' => '221g'], ['value' => '232g']]],
                        ['subtitle' => 'Price', 'items' => [['value' => 'From £1,199'], ['value' => 'From £1,249']]]
                    ]
                ],
                'order' => 3
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Design and Build', 'level' => 2], 'order' => 4];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Both phones feature titanium frames, but they feel distinctly different. The iPhone\'s brushed finish resists fingerprints better, while the S24 Ultra\'s matte glass back feels more premium.',
                        'Samsung\'s boxier design offers more screen in a similar footprint. The flat display is more practical for everyday use and works better with screen protectors.',
                        'The S24 Ultra includes the S Pen stylus, adding functionality the iPhone can\'t match. For note-taking and precise editing, it\'s invaluable.'
                    ]
                ],
                'order' => 5
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Camera Showdown', 'level' => 2], 'order' => 6];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Both phones deliver exceptional camera performance with different approaches. The iPhone prioritizes natural colors and video quality, while the S24 Ultra offers more creative control and zoom capability.',
                        'For video, the iPhone maintains its lead with superior stabilization and color science. The S24 Ultra shoots great video, but the iPhone is the choice for serious content creators.',
                        'Samsung wins for zoom with its dual telephoto setup (3x and 10x) providing more flexibility than the iPhone\'s single 5x lens.'
                    ]
                ],
                'order' => 7
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'The Verdict', 'level' => 2], 'order' => 8];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Choose the iPhone 15 Pro Max if you: value video quality, prefer iOS simplicity, are invested in Apple\'s ecosystem, or want the best gaming performance.',
                        'Choose the Galaxy S24 Ultra if you: need the S Pen, want more customization, prefer Android flexibility, need better zoom capability, or value longer battery life.',
                        'You can\'t go wrong with either phone. Both are exceptional flagships that will serve you well for years.'
                    ]
                ],
                'order' => 9
            ];
        } elseif (str_contains($data['slug'], 'macbook') && str_contains($data['slug'], 'dell')) {
            $blocks[] = [
                'type' => 'product-comparison',
                'data' => [
                    'title' => 'Key Specifications',
                    'productA' => 'MacBook Pro 14" M3',
                    'productB' => 'Dell XPS 15',
                    'comparisons' => [
                        ['subtitle' => 'Display', 'items' => [['value' => '14.2" Mini-LED, 120Hz'], ['value' => '15.6" OLED, 60Hz']]],
                        ['subtitle' => 'Processor', 'items' => [['value' => 'Apple M3 Pro'], ['value' => 'Intel Core Ultra 7']]],
                        ['subtitle' => 'RAM', 'items' => [['value' => '18GB unified'], ['value' => '16GB DDR5']]],
                        ['subtitle' => 'Storage', 'items' => [['value' => '512GB SSD'], ['value' => '512GB SSD']]],
                        ['subtitle' => 'Battery Life', 'items' => [['value' => '18+ hours'], ['value' => '10-12 hours']]],
                        ['subtitle' => 'Weight', 'items' => [['value' => '1.61kg'], ['value' => '1.96kg']]],
                        ['subtitle' => 'Ports', 'items' => [['value' => '3x USB-C, MagSafe'], ['value' => '2x USB-C, SD card']]],
                        ['subtitle' => 'Price', 'items' => [['value' => 'From £1,699'], ['value' => 'From £1,499']]]
                    ]
                ],
                'order' => 3
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Performance Battle', 'level' => 2], 'order' => 4];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The MacBook Pro wins on battery life, performance per watt, and overall efficiency. The M3 chip is simply more advanced than Intel\'s latest offerings.',
                        'The Dell offers more configurability and can be specced with more storage and RAM, though at a higher price premium.',
                        'For creative workflows optimized for macOS (Final Cut, Logic Pro), the MacBook Pro is unbeatable. For Windows-specific applications, the XPS 15 is your only choice.'
                    ]
                ],
                'order' => 5
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Display Quality', 'level' => 2], 'order' => 6];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The Dell\'s OLED display offers perfect blacks and infinite contrast, making it stunning for movie watching. However, it\'s limited to 60Hz refresh rate.',
                        'The MacBook\'s Mini-LED display can\'t match OLED\'s perfect blacks, but it gets brighter (up to 1,600 nits peak) and offers 120Hz ProMotion for smoother scrolling.',
                        'For color-critical work, both are excellent with wide color gamut coverage and factory calibration.'
                    ]
                ],
                'order' => 7
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Which Should You Buy?', 'level' => 2], 'order' => 8];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Your choice depends on your software needs. If you require Windows-specific applications, the XPS 15 is excellent. For creative workflows optimized for macOS, the MacBook Pro is unbeatable.',
                        'The MacBook Pro offers better value when you factor in longevity and resale value, despite the higher upfront cost.',
                        'Both are exceptional premium laptops that will serve you well for 5+ years.'
                    ]
                ],
                'order' => 9
            ];
        } elseif (str_contains($data['slug'], 'airpods') && str_contains($data['slug'], 'sony')) {
            $blocks[] = [
                'type' => 'product-comparison',
                'data' => [
                    'title' => 'Specifications Comparison',
                    'productA' => 'AirPods Pro 2',
                    'productB' => 'Sony WF-1000XM5',
                    'comparisons' => [
                        ['subtitle' => 'Battery (Buds)', 'items' => [['value' => '6 hours'], ['value' => '8 hours']]],
                        ['subtitle' => 'Battery (Case)', 'items' => [['value' => '30 hours'], ['value' => '24 hours']]],
                        ['subtitle' => 'ANC Quality', 'items' => [['value' => 'Excellent'], ['value' => 'Best-in-class']]],
                        ['subtitle' => 'Sound Quality', 'items' => [['value' => 'Very good'], ['value' => 'Excellent']]],
                        ['subtitle' => 'Codec Support', 'items' => [['value' => 'AAC'], ['value' => 'LDAC, AAC']]],
                        ['subtitle' => 'Spatial Audio', 'items' => [['value' => 'Yes'], ['value' => 'Yes']]],
                        ['subtitle' => 'Multipoint', 'items' => [['value' => 'No'], ['value' => 'Yes']]],
                        ['subtitle' => 'Price', 'items' => [['value' => '£229'], ['value' => '£259']]]
                    ]
                ],
                'order' => 3
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Sound Quality', 'level' => 2], 'order' => 4];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The Sony WF-1000XM5 offers superior sound quality with better detail retrieval and more nuanced bass response. Audiophiles will appreciate the LDAC codec support for near-lossless wireless audio.',
                        'AirPods Pro 2 sound very good with a balanced, natural presentation that works well across all genres. Apple\'s Adaptive EQ automatically tunes sound to your ear shape.',
                        'Both support spatial audio, but AirPods Pro\'s implementation is more refined with better head tracking and Apple ecosystem integration.'
                    ]
                ],
                'order' => 5
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Noise Cancellation', 'level' => 2], 'order' => 6];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Sony takes the crown for noise cancellation. The WF-1000XM5 blocks out more ambient noise across all frequencies, especially in noisy environments like airplanes.',
                        'AirPods Pro 2\'s ANC is still excellent and improved over the original. The transparency mode is the best in class, sounding completely natural.',
                        'For commuting and travel, the Sony\'s superior ANC makes a noticeable difference.'
                    ]
                ],
                'order' => 7
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'The Verdict', 'level' => 2], 'order' => 8];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Choose AirPods Pro 2 if you: use Apple devices exclusively, value seamless integration, prefer smaller earbuds, or want the best transparency mode.',
                        'Choose Sony WF-1000XM5 if you: prioritize sound quality above all, need multipoint connectivity, use Android devices, or want the best noise cancellation.',
                        'Both are excellent premium earbuds. Your ecosystem will likely determine the best choice.'
                    ]
                ],
                'order' => 9
            ];
        } elseif (str_contains($data['slug'], 'ps5') && str_contains($data['slug'], 'xbox')) {
            $blocks[] = [
                'type' => 'product-comparison',
                'data' => [
                    'title' => 'Console Specifications',
                    'productA' => 'PlayStation 5',
                    'productB' => 'Xbox Series X',
                    'comparisons' => [
                        ['subtitle' => 'GPU Power', 'items' => [['value' => '10.28 TFLOPS'], ['value' => '12 TFLOPS']]],
                        ['subtitle' => 'RAM', 'items' => [['value' => '16GB GDDR6'], ['value' => '16GB GDDR6']]],
                        ['subtitle' => 'Storage', 'items' => [['value' => '825GB SSD'], ['value' => '1TB SSD']]],
                        ['subtitle' => 'Max Resolution', 'items' => [['value' => '8K'], ['value' => '8K']]],
                        ['subtitle' => 'Frame Rate', 'items' => [['value' => 'Up to 120fps'], ['value' => 'Up to 120fps']]],
                        ['subtitle' => 'Ray Tracing', 'items' => [['value' => 'Yes'], ['value' => 'Yes']]],
                        ['subtitle' => 'Disc Drive', 'items' => [['value' => 'Optional'], ['value' => 'Yes']]],
                        ['subtitle' => 'Game Pass', 'items' => [['value' => 'No'], ['value' => 'Yes']]],
                        ['subtitle' => 'Price', 'items' => [['value' => '£479'], ['value' => '£479']]]
                    ]
                ],
                'order' => 3
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Exclusive Games', 'level' => 2], 'order' => 4];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'PlayStation 5 has a slight edge in exclusive titles with games like Spider-Man 2, God of War Ragnarök, Horizon Forbidden West, and the upcoming Wolverine.',
                        'Xbox Series X counters with Starfield, Forza Motorsport, and access to hundreds of games via Game Pass, including day-one releases of all Microsoft first-party titles.',
                        'If you care about exclusive single-player adventures, PS5 is the clear winner. For value and variety, Xbox Game Pass is unbeatable.'
                    ]
                ],
                'order' => 5
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Performance and Features', 'level' => 2], 'order' => 6];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'On paper, Xbox Series X is slightly more powerful, but in practice, performance is nearly identical. Most games run at the same resolution and frame rate on both consoles.',
                        'PS5\'s DualSense controller offers unique haptic feedback and adaptive triggers that add immersion in supported games. Xbox\'s controller is more traditional but battle-tested.',
                        'Both support 4K gaming at 60fps (or 120fps in performance modes), ray tracing, and ultra-fast SSD loading times that essentially eliminate load screens.'
                    ]
                ],
                'order' => 7
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Which Console to Buy', 'level' => 2], 'order' => 8];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Choose PS5 if you: want the best exclusive games, prefer single-player adventures, already have PS4 games, or want innovative controller features.',
                        'Choose Xbox Series X if you: want the best value with Game Pass, prefer multiplayer games, need backward compatibility with original Xbox games, or want more storage.',
                        'Both are excellent choices. Let your game preferences and friend\'s platforms guide your decision.'
                    ]
                ],
                'order' => 9
            ];
        } elseif (str_contains($data['slug'], 'ipad') && str_contains($data['slug'], 'galaxy-tab')) {
            $blocks[] = [
                'type' => 'product-comparison',
                'data' => [
                    'title' => 'Tablet Comparison',
                    'productA' => 'iPad Pro 12.9"',
                    'productB' => 'Galaxy Tab S9 Ultra',
                    'comparisons' => [
                        ['subtitle' => 'Display Size', 'items' => [['value' => '12.9"'], ['value' => '14.6"']]],
                        ['subtitle' => 'Display Tech', 'items' => [['value' => 'Mini-LED'], ['value' => 'AMOLED']]],
                        ['subtitle' => 'Processor', 'items' => [['value' => 'M2'], ['value' => 'Snapdragon 8 Gen 2']]],
                        ['subtitle' => 'RAM', 'items' => [['value' => '8GB/16GB'], ['value' => '12GB/16GB']]],
                        ['subtitle' => 'Stylus', 'items' => [['value' => 'Apple Pencil (extra)'], ['value' => 'S Pen (included)']]],
                        ['subtitle' => 'Battery', 'items' => [['value' => '10 hours'], ['value' => '14 hours']]],
                        ['subtitle' => 'Water Resistant', 'items' => [['value' => 'No'], ['value' => 'IP68']]],
                        ['subtitle' => 'Weight', 'items' => [['value' => '682g'], ['value' => '732g']]],
                        ['subtitle' => 'Price', 'items' => [['value' => 'From £1,099'], ['value' => 'From £1,199']]]
                    ]
                ],
                'order' => 3
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Display and Design', 'level' => 2], 'order' => 4];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The Galaxy Tab S9 Ultra\'s massive 14.6-inch AMOLED display is stunning for media consumption. The perfect blacks and vibrant colors make movies and photos pop.',
                        'iPad Pro\'s 12.9-inch Mini-LED display can\'t match OLED blacks, but it gets much brighter (up to 1,600 nits) making it better for outdoor use.',
                        'Samsung\'s tablet is larger and better for multitasking with multiple apps side-by-side. The iPad Pro is more portable while still offering a generous screen.'
                    ]
                ],
                'order' => 5
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Software and Apps', 'level' => 2], 'order' => 6];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'iPadOS offers better tablet-optimized apps, especially for creative work. Apps like Procreate, LumaFusion, and Affinity Photo have no Android equivalents.',
                        'Samsung\'s DeX mode turns the tablet into a desktop-like experience with windowed apps and taskbar. It\'s more flexible than iPadOS for multitasking.',
                        'The Galaxy Tab S9 Ultra includes the S Pen in the box, while Apple Pencil costs an additional £129-£139.'
                    ]
                ],
                'order' => 7
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'The Verdict', 'level' => 2], 'order' => 8];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Choose iPad Pro if you: need the best creative apps, prefer iOS/iPadOS, want better app optimization, or need a more portable size.',
                        'Choose Galaxy Tab S9 Ultra if you: want the biggest screen, need better multitasking, prefer Android, or want a stylus included.',
                        'Both are exceptional premium tablets. Your ecosystem and app needs should guide your choice.'
                    ]
                ],
                'order' => 9
            ];
        }

        $blocks[] = [
            'type' => 'quote',
            'data' => [
                'text' => 'Both products are outstanding in their own right. Choose based on your ecosystem and specific needs, not because one is objectively better.',
                'attribution' => $data['author'] . ', Tom\'s Guide'
            ],
            'order' => 10
        ];

        foreach ($blocks as $block) {
            $this->blockRepository->create([
                'page_id' => $page->id,
                'type' => $block['type'],
                'data' => json_encode($block['data']),
                'order' => $block['order']
            ]);
        }
    }

    private function createNewsPages(): void
    {
        $newsArticles = [
            [
                'title' => 'Apple Announces Revolutionary AI Features Coming to iOS 18',
                'slug' => 'apple-ai-features-announcement',
                'tags' => ['news', 'smartphones', 'trending'],
                'categories' => ['News', 'Mobile'],
                'author' => 'John Smith',
                'read_time' => 5,
                'excerpt' => 'Apple reveals major AI upgrades coming to iOS 18 and macOS.',
                'image' => 'https://images.unsplash.com/photo-1611162617474-5b21e879e113?auto=format&fit=crop&w=2340&q=80'
            ],
            [
                'title' => 'Samsung Teases Galaxy S25: First Look at Next Flagship',
                'slug' => 'samsung-galaxy-s25-teaser',
                'tags' => ['news', 'smartphones', 'trending'],
                'categories' => ['News', 'Mobile'],
                'author' => 'Sarah Johnson',
                'read_time' => 4,
                'excerpt' => 'First look at Samsung\'s next flagship smartphone series.',
                'image' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?auto=format&fit=crop&w=2340&q=80'
            ],
            [
                'title' => 'Google Pixel 9 Leaks: Major Camera Upgrade Coming',
                'slug' => 'google-pixel-9-leaks',
                'tags' => ['news', 'smartphones'],
                'categories' => ['News', 'Mobile'],
                'author' => 'John Smith',
                'read_time' => 6,
                'excerpt' => 'New renders show redesigned Pixel 9 with improved cameras.',
                'image' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?auto=format&fit=crop&w=2340&q=80'
            ],
            [
                'title' => 'Netflix Raises Prices Across All Subscription Tiers',
                'slug' => 'netflix-price-increase-2024',
                'tags' => ['news', 'streaming', 'entertainment'],
                'categories' => ['News', 'Entertainment'],
                'author' => 'Emma Wilson',
                'read_time' => 3,
                'excerpt' => 'Streaming giant raises subscription prices across all tiers.',
                'image' => 'https://images.unsplash.com/photo-1611162617474-5b21e879e113?auto=format&fit=crop&w=2340&q=80'
            ],
            [
                'title' => 'Microsoft Announces Windows 12: What to Expect',
                'slug' => 'microsoft-windows-12-announcement',
                'tags' => ['news', 'computing'],
                'categories' => ['News', 'Computing'],
                'author' => 'Mike Davis',
                'read_time' => 7,
                'excerpt' => 'Microsoft reveals details about the next version of Windows.',
                'image' => 'https://images.unsplash.com/photo-1633356122544-f134324a6cee?auto=format&fit=crop&w=2340&q=80'
            ],
            [
                'title' => 'Meta Quest 3S Leaked: Budget VR Headset Coming Soon',
                'slug' => 'meta-quest-3s-leak',
                'tags' => ['news', 'gaming'],
                'categories' => ['News', 'Entertainment'],
                'author' => 'John Smith',
                'read_time' => 5,
                'excerpt' => 'Affordable VR headset could bring virtual reality to more users.',
                'image' => 'https://images.unsplash.com/photo-1617802690658-1173a812650d?auto=format&fit=crop&w=2340&q=80'
            ]
        ];

        foreach ($newsArticles as $article) {
            $this->createNewsArticle($article);
        }
    }

    private function createNewsArticle(array $data): void
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => $data['title'] . ' - Tom\'s Guide UK',
            'meta_description' => $data['excerpt'],
            'site_id' => $this->site->id,
        ]);

        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
            $page->tags(true)->attach($tag->id);
        }

        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, $this->site->id);
            $page->categories(true)->attach($category->id);
        }

        $customFields = [
            'author_name' => $data['author'],
            'read_time' => $data['read_time'],
            'excerpt' => $data['excerpt'],
        ];

        foreach ($customFields as $key => $value) {
            $fieldDef = CustomFieldDefinition::where('key', $key)->first();
            if ($fieldDef) {
                PageCustomField::create([
                    'custom_field_definition_id' => $fieldDef->id,
                    'field_value' => (string)$value,
                    'page_id' => $page->id
                ]);
            }
        }

        // Generate unique content based on the article slug
        $blocks = [];
        $blocks[] = [
            'type' => 'image',
            'data' => [
                'src' => $data['image'],
                'alt' => $data['title'],
                'caption' => $data['title'],
                'layout' => 'full'
            ],
            'order' => 1
        ];

        // Unique content based on article type
        if (str_contains($data['slug'], 'apple-ai')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Apple has unveiled Apple Intelligence, a groundbreaking AI system coming to iOS 18, iPadOS 18, and macOS Sequoia later this year.',
                        'The new features focus on privacy-first AI that processes data on-device, setting Apple apart from cloud-based competitors.',
                        'Key features include system-wide Writing Tools, intelligent photo editing, and Siri enhancements powered by large language models.'
                    ]
                ],
                'order' => 2
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Key AI Features', 'level' => 2], 'order' => 3];
            $blocks[] = [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Writing Tools: AI-powered proofreading, rewriting, and summarization across all apps',
                        'Image Playground: Generate images in moments, animation, illustration, or sketch styles',
                        'Genmoji: Create custom emoji using text descriptions',
                        'Smart Reply: Contextual quick replies in Mail and Messages',
                        'Priority Notifications: AI surfaces most important alerts',
                        'Audio transcription: Real-time transcription in Notes and Phone apps'
                    ]
                ],
                'order' => 4
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Privacy-First Approach', 'level' => 2], 'order' => 5];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Unlike competitors, Apple processes most AI requests on-device using the company\'s custom silicon.',
                        'For complex queries requiring server processing, Apple uses Private Cloud Compute, ensuring data isn\'t stored or accessible.',
                        'This approach maintains Apple\'s privacy stance while delivering powerful AI capabilities.'
                    ]
                ],
                'order' => 6
            ];
        } elseif (str_contains($data['slug'], 'samsung-s25')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Samsung has officially teased the Galaxy S25 series ahead of its expected January 2025 launch event.',
                        'The new flagships will feature Qualcomm\'s upcoming Snapdragon 8 Gen 4 processor globally, moving away from Exynos variants.',
                        'Design refinements include slimmer bezels and a slightly lighter titanium frame on the Ultra model.'
                    ]
                ],
                'order' => 2
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Expected Upgrades', 'level' => 2], 'order' => 3];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The S25 Ultra is rumored to feature an improved 200MP main sensor with better low-light performance.',
                        'Battery capacity increases to 5,500mAh on the Ultra, with 65W fast charging finally arriving.',
                        'Samsung is doubling down on AI features, including real-time translation and enhanced photo editing.'
                    ]
                ],
                'order' => 4
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Launch Timeline', 'level' => 2], 'order' => 5];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Samsung typically launches its S-series in late January or early February.',
                        'Pre-orders are expected to open immediately after the announcement, with retail availability in mid-February.',
                        'Pricing is likely to remain similar to the S24 series, starting around £1,249 for the Ultra.'
                    ]
                ],
                'order' => 6
            ];
        } elseif (str_contains($data['slug'], 'pixel-9')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Leaked renders show Google\'s Pixel 9 series will feature a significant design overhaul, departing from the camera bar design.',
                        'The new camera module features individual lens bumps in a refined aluminum housing.',
                        'Three models are expected: Pixel 9, Pixel 9 Pro, and Pixel 9 Pro XL with larger display.'
                    ]
                ],
                'order' => 2
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Camera Improvements', 'level' => 2], 'order' => 3];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The Pixel 9 Pro models will reportedly feature an upgraded 50MP main sensor with larger pixels.',
                        'Google is improving the ultrawide camera to 50MP, up from 12MP on previous models.',
                        'Video recording gets a boost with 8K/30fps capability and improved stabilization.'
                    ]
                ],
                'order' => 4
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Tensor G4 Inside', 'level' => 2], 'order' => 5];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The new Tensor G4 chip promises significant performance improvements over G3.',
                        'Google claims 20% better CPU performance and 30% better GPU performance.',
                        'AI features remain the focus, with on-device processing for most computational photography.'
                    ]
                ],
                'order' => 6
            ];
        } elseif (str_contains($data['slug'], 'netflix-price')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Netflix has announced price increases across all subscription tiers in the UK and several other markets.',
                        'The Standard plan now costs £12.99, up from £10.99, while Premium rises to £17.99 from £15.99.',
                        'The ad-supported tier remains unchanged at £4.99, encouraging users to switch to the cheaper option.'
                    ]
                ],
                'order' => 2
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'New Pricing Structure', 'level' => 2], 'order' => 3];
            $blocks[] = [
                'type' => 'table',
                'data' => [
                    'hasHeader' => true,
                    'rows' => [
                        ['Tier', 'Old Price', 'New Price', 'Change'],
                        ['Standard with ads', '£4.99', '£4.99', 'No change'],
                        ['Standard', '£10.99', '£12.99', '+£2.00'],
                        ['Premium', '£15.99', '£17.99', '+£2.00']
                    ]
                ],
                'order' => 4
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Why the Increase?', 'level' => 2], 'order' => 5];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Netflix cites rising content production costs and investment in new original programming.',
                        'The company has spent billions on shows like Stranger Things, The Crown, and Wednesday.',
                        'This marks the third UK price increase in five years as streaming competition intensifies.'
                    ]
                ],
                'order' => 6
            ];
        } elseif (str_contains($data['slug'], 'windows-12')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Microsoft has officially announced Windows 12, set to launch in fall 2025.',
                        'The new version features a redesigned interface with floating taskbar and enhanced AI integration.',
                        'Windows 12 requires newer hardware, specifically CPUs from 2021 or later with TPM 2.0.'
                    ]
                ],
                'order' => 2
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Major New Features', 'level' => 2], 'order' => 3];
            $blocks[] = [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'AI Copilot deeply integrated throughout the OS',
                        'Floating taskbar that adapts to your workflow',
                        'Enhanced virtual desktops with smart organization',
                        'Improved gaming performance with DirectStorage 2.0',
                        'Native support for Android apps without emulation',
                        'Enhanced security with kernel-level protection'
                    ]
                ],
                'order' => 4
            ];
        } elseif (str_contains($data['slug'], 'meta-quest')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Leaked marketing materials reveal Meta Quest 3S, an affordable VR headset launching this holiday season.',
                        'The headset will reportedly cost $299, making it the most affordable entry into Meta\'s VR ecosystem.',
                        'It uses older Fresnel lenses instead of pancake lenses to reduce costs while maintaining decent image quality.'
                    ]
                ],
                'order' => 2
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Specifications', 'level' => 2], 'order' => 3];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The Quest 3S features the same Snapdragon XR2 Gen 2 processor as the Quest 3.',
                        'Resolution is slightly lower at 1832 x 1920 per eye compared to Quest 3\'s 2064 x 2208.',
                        'It retains full color passthrough and supports all Quest 3 games and apps.'
                    ]
                ],
                'order' => 4
            ];
        }

        // Add conclusion for all
        $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Our Take', 'level' => 2], 'order' => 7];
        $blocks[] = [
            'type' => 'text',
            'data' => [
                'paragraphs' => [
                    'This development will significantly impact the market and consumer choices.',
                    'We\'ll continue to monitor this story and provide updates as more information becomes available.',
                    'Stay tuned for our hands-on coverage when products become available.'
                ]
            ],
            'order' => 8
        ];

        foreach ($blocks as $block) {
            $this->blockRepository->create([
                'page_id' => $page->id,
                'type' => $block['type'],
                'data' => json_encode($block['data']),
                'order' => $block['order']
            ]);
        }
    }

    private function createHowToPages(): void
    {
        $howToArticles = [
            [
                'title' => 'How to Take Better Photos on Your iPhone: Expert Tips',
                'slug' => 'how-to-take-better-iphone-photos',
                'tags' => ['how-to', 'smartphones', 'tutorials'],
                'categories' => ['How To', 'Phone Guides'],
                'author' => 'Emma Wilson',
                'read_time' => 8,
                'excerpt' => 'Master your iPhone camera with these professional photography tips and tricks.',
                'steps' => [
                    'Clean your lens before shooting',
                    'Use gridlines to compose shots using the rule of thirds',
                    'Tap to focus and adjust exposure',
                    'Use Portrait mode for stunning depth effects',
                    'Enable Night mode for low-light shots',
                    'Experiment with different angles',
                    'Use burst mode for action shots',
                    'Edit photos using the built-in tools'
                ]
            ],
            [
                'title' => 'How to Set Up a New Windows 11 PC: Complete Guide',
                'slug' => 'how-to-setup-windows-11-pc',
                'tags' => ['how-to', 'computing'],
                'categories' => ['How To', 'Computing Tips'],
                'author' => 'Mike Davis',
                'read_time' => 12,
                'excerpt' => 'Step-by-step guide to setting up your new Windows 11 computer perfectly.',
                'steps' => [
                    'Complete initial Windows setup wizard',
                    'Install all Windows updates',
                    'Set up Windows Security and virus protection',
                    'Install essential applications',
                    'Configure privacy settings properly',
                    'Create a backup and recovery plan',
                    'Customize your desktop and taskbar',
                    'Set up OneDrive cloud storage'
                ]
            ],
            [
                'title' => 'How to Fix Netflix Not Working: Troubleshooting Guide',
                'slug' => 'how-to-fix-netflix-not-working',
                'tags' => ['how-to', 'streaming'],
                'categories' => ['How To', 'Streaming Help'],
                'author' => 'Sarah Johnson',
                'read_time' => 7,
                'excerpt' => 'Quick fixes for common Netflix streaming problems and error codes.',
                'steps' => [
                    'Check your internet connection speed',
                    'Restart the Netflix app completely',
                    'Clear app cache and data',
                    'Update the Netflix app to latest version',
                    'Restart your streaming device',
                    'Check Netflix server status',
                    'Sign out and sign back in',
                    'Reinstall the Netflix app'
                ]
            ],
            [
                'title' => 'How to Connect AirPods to Any Device: Complete Guide',
                'slug' => 'how-to-connect-airpods',
                'tags' => ['how-to', 'wearables', 'tutorials'],
                'categories' => ['How To', 'Phone Guides'],
                'author' => 'Emma Wilson',
                'read_time' => 6,
                'excerpt' => 'Connect your AirPods to iPhone, Android, Windows PC, and more.',
                'steps' => [
                    'Open AirPods case near your device',
                    'Press and hold the setup button',
                    'Select AirPods from Bluetooth menu',
                    'Complete pairing process',
                    'Test audio playback',
                    'Adjust settings if needed'
                ]
            ],
            [
                'title' => 'How to Speed Up Your Slow PC: 10 Easy Fixes',
                'slug' => 'how-to-speed-up-slow-pc',
                'tags' => ['how-to', 'computing', 'tutorials'],
                'categories' => ['How To', 'Computing Tips'],
                'author' => 'Mike Davis',
                'read_time' => 10,
                'excerpt' => 'Make your old computer feel new again with these simple optimization tips.',
                'steps' => [
                    'Uninstall unused programs and bloatware',
                    'Disable startup programs you don\'t need',
                    'Run disk cleanup to free up space',
                    'Defragment your hard drive (if using HDD)',
                    'Scan for malware and viruses',
                    'Update all drivers and software',
                    'Add more RAM if possible',
                    'Consider upgrading to an SSD',
                    'Adjust visual effects for performance',
                    'Reset Windows as last resort'
                ]
            ]
        ];

        foreach ($howToArticles as $article) {
            $this->createHowToArticle($article);
        }
    }

    private function createHowToArticle(array $data): void
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => $data['title'] . ' - Tom\'s Guide UK',
            'meta_description' => $data['excerpt'],
            'site_id' => $this->site->id,
        ]);

        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
            $page->tags(true)->attach($tag->id);
        }

        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, $this->site->id);
            $page->categories(true)->attach($category->id);
        }

        $customFields = [
            'author_name' => $data['author'],
            'read_time' => $data['read_time'],
            'excerpt' => $data['excerpt'],
        ];

        foreach ($customFields as $key => $value) {
            $fieldDef = CustomFieldDefinition::where('key', $key)->first();
            if ($fieldDef) {
                PageCustomField::create([
                    'custom_field_definition_id' => $fieldDef->id,
                    'field_value' => (string)$value,
                    'page_id' => $page->id
                ]);
            }
        }

        $blocks = [
            [
                'type' => 'heading',
                'data' => ['text' => $data['title'], 'level' => 1],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        $data['excerpt'],
                        'Follow this step-by-step guide to get the best results.',
                        'We\'ve tested these methods extensively to ensure they work reliably.'
                    ]
                ],
                'order' => 2
            ]
        ];

        // Add unique intro content based on article
        if (str_contains($data['slug'], 'iphone-photos')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The iPhone camera is incredibly powerful, but most users only scratch the surface of its capabilities.',
                        'Professional photographers rely on these techniques to capture stunning images with their iPhones.',
                        'Whether you\'re shooting landscapes, portraits, or street photography, these tips will transform your results.'
                    ]
                ],
                'order' => 3
            ];
        } elseif (str_contains($data['slug'], 'windows-11')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Setting up Windows 11 correctly from the start saves time and prevents issues down the road.',
                        'This guide covers essential setup steps many users skip, leading to problems later.',
                        'Expect this process to take 1-2 hours, but it\'s time well spent for optimal performance.'
                    ]
                ],
                'order' => 3
            ];
        } elseif (str_contains($data['slug'], 'netflix')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Netflix streaming issues are frustrating, but usually easy to fix with the right troubleshooting.',
                        'Most problems stem from connection issues, outdated apps, or device-specific glitches.',
                        'Follow these steps in order - most users find their solution in the first few steps.'
                    ]
                ],
                'order' => 3
            ];
        } elseif (str_contains($data['slug'], 'airpods')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'While AirPods are designed for Apple devices, they work perfectly with Android, Windows, and more.',
                        'The pairing process is straightforward once you know the trick.',
                        'You\'ll lose some Apple-exclusive features, but core functionality works great on any device.'
                    ]
                ],
                'order' => 3
            ];
        } elseif (str_contains($data['slug'], 'slow-pc')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'A slow PC doesn\'t necessarily mean you need a new computer - often simple fixes restore performance.',
                        'These optimization techniques can make even a 5-year-old PC feel responsive again.',
                        'Start with the easy fixes first, then progress to more advanced solutions if needed.'
                    ]
                ],
                'order' => 3
            ];
        }

        $blocks[] = [
            'type' => 'heading',
            'data' => ['text' => 'Step-by-Step Instructions', 'level' => 2],
            'order' => 4
        ];

        $blocks[] = [
            'type' => 'list',
            'data' => [
                'listType' => 'ol',
                'schemaType' => 'steps',
                'items' => $data['steps']
            ],
            'order' => 5
        ];

        // Add unique additional tips based on article
        $blocks[] = [
            'type' => 'heading',
            'data' => ['text' => 'Additional Tips', 'level' => 2],
            'order' => 6
        ];

        if (str_contains($data['slug'], 'iphone-photos')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The golden hour (hour after sunrise and before sunset) provides the best natural lighting for photos.',
                        'Avoid using digital zoom - instead, move closer to your subject or crop in post-processing.',
                        'Clean your lens daily - fingerprints and dust significantly degrade image quality.',
                        'Experiment with third-party camera apps like Halide or ProCamera for advanced controls.'
                    ]
                ],
                'order' => 7
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Common Mistakes to Avoid', 'level' => 2], 'order' => 8];
            $blocks[] = [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Relying on flash in low light - use Night mode instead',
                        'Centering every subject - rule of thirds creates more interesting compositions',
                        'Shooting in harsh midday sun - wait for better lighting',
                        'Using maximum zoom - image quality degrades significantly',
                        'Forgetting to tap to focus - crucial for sharp images'
                    ]
                ],
                'order' => 9
            ];
        } elseif (str_contains($data['slug'], 'windows-11')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Create a Microsoft account during setup for full feature access and cloud sync.',
                        'Use a local account if privacy is a priority, though some features won\'t work.',
                        'Windows Update will run multiple times - be patient and let each round complete.',
                        'Consider creating a system restore point after setup is complete.'
                    ]
                ],
                'order' => 7
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Essential Software to Install', 'level' => 2], 'order' => 8];
            $blocks[] = [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Browser: Chrome, Firefox, or Edge (already installed)',
                        'Security: Your preferred antivirus (Windows Defender is excellent)',
                        'Productivity: Microsoft Office or LibreOffice',
                        'Media: VLC Media Player for video playback',
                        'Utilities: 7-Zip for file compression',
                        'Communication: Slack, Zoom, or Teams as needed'
                    ]
                ],
                'order' => 9
            ];
        } elseif (str_contains($data['slug'], 'netflix')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Internet speed requirements: 3 Mbps for SD, 5 Mbps for HD, 25 Mbps for 4K.',
                        'Use ethernet instead of Wi-Fi when possible for more stable streaming.',
                        'Close other applications using bandwidth while streaming.',
                        'Try streaming during off-peak hours if you experience consistent buffering.'
                    ]
                ],
                'order' => 7
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Common Error Codes', 'level' => 2], 'order' => 8];
            $blocks[] = [
                'type' => 'table',
                'data' => [
                    'hasHeader' => true,
                    'rows' => [
                        ['Error Code', 'Meaning', 'Fix'],
                        ['NW-2-5', 'Network connectivity issue', 'Check internet connection'],
                        ['UI-800-3', 'App needs to refresh', 'Sign out and sign back in'],
                        ['NW-3-6', 'Network configuration issue', 'Restart device and router'],
                        ['M7111-1331', 'Browser issue', 'Clear cookies and cache']
                    ]
                ],
                'order' => 9
            ];
        } elseif (str_contains($data['slug'], 'airpods')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'On Android, you\'ll lose automatic ear detection and Siri functionality.',
                        'Battery life indicator may not be as accurate on non-Apple devices.',
                        'Spatial audio and adaptive EQ work best within the Apple ecosystem.',
                        'Consider third-party apps like MaterialPods for enhanced Android integration.'
                    ]
                ],
                'order' => 7
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Troubleshooting Connection Issues', 'level' => 2], 'order' => 8];
            $blocks[] = [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Make sure AirPods are charged (place in case for 15 minutes)',
                        'Forget the Bluetooth connection and re-pair from scratch',
                        'Reset AirPods by holding button for 15 seconds until amber flashes',
                        'Update firmware on your device to latest version',
                        'Try pairing with a different device to isolate the problem'
                    ]
                ],
                'order' => 9
            ];
        } elseif (str_contains($data['slug'], 'slow-pc')) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The single biggest upgrade for older PCs is replacing an HDD with an SSD.',
                        'Adding RAM is cost-effective if you have less than 8GB (16GB is ideal).',
                        'CPU upgrades are rarely worth it - better to save for a new PC.',
                        'Clean dust from your PC internals - overheating causes thermal throttling.'
                    ]
                ],
                'order' => 7
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'When to Consider a New PC', 'level' => 2], 'order' => 8];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'If your PC is more than 7-8 years old, upgrading may cost more than it\'s worth.',
                        'Systems with 4GB RAM or less struggle with modern Windows and applications.',
                        'Hard drive failures are a sign your PC is nearing end of life.',
                        'If optimization doesn\'t improve speed, it\'s likely time for new hardware.'
                    ]
                ],
                'order' => 9
            ];
        }

        foreach ($blocks as $block) {
            $this->blockRepository->create([
                'page_id' => $page->id,
                'type' => $block['type'],
                'data' => json_encode($block['data']),
                'order' => $block['order']
            ]);
        }
    }

    private function createDealsPages(): void
    {
        $dealsArticles = [
            [
                'title' => 'Best Black Friday Phone Deals 2024: Live Updates',
                'slug' => 'black-friday-phone-deals-2024',
                'tags' => ['deals', 'smartphones', 'trending'],
                'categories' => ['Deals', 'Phone Deals'],
                'author' => 'Mike Davis',
                'read_time' => 5,
                'excerpt' => 'The best smartphone deals available this Black Friday, updated live.',
            ],
            [
                'title' => 'Best Laptop Deals Today: Save on MacBooks, Dell & More',
                'slug' => 'best-laptop-deals-today',
                'tags' => ['deals', 'laptops'],
                'categories' => ['Deals', 'Laptop Deals'],
                'author' => 'Mike Davis',
                'read_time' => 6,
                'excerpt' => 'Today\'s best laptop deals across all major brands and retailers.',
            ],
            [
                'title' => 'Best TV Deals: Save Big on 4K and OLED TVs',
                'slug' => 'best-tv-deals',
                'tags' => ['deals', 'tv', 'entertainment'],
                'categories' => ['Deals', 'TV Deals'],
                'author' => 'John Smith',
                'read_time' => 7,
                'excerpt' => 'The best TV deals on 4K, OLED, and QLED models from top brands.',
            ],
            [
                'title' => 'Amazon Prime Day 2024: Best Tech Deals',
                'slug' => 'amazon-prime-day-tech-deals-2024',
                'tags' => ['deals', 'trending'],
                'categories' => ['Deals', 'Tech Sales'],
                'author' => 'Sarah Johnson',
                'read_time' => 8,
                'excerpt' => 'All the best Prime Day tech deals in one place, updated live.',
            ]
        ];

        foreach ($dealsArticles as $article) {
            $this->createDealArticle($article);
        }
    }

    private function createDealArticle(array $data): void
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => $data['title'] . ' - Tom\'s Guide UK',
            'meta_description' => $data['excerpt'],
            'site_id' => $this->site->id,
        ]);

        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
            $page->tags(true)->attach($tag->id);
        }

        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, $this->site->id);
            $page->categories(true)->attach($category->id);
        }

        $customFields = [
            'author_name' => $data['author'],
            'read_time' => $data['read_time'],
            'excerpt' => $data['excerpt'],
        ];

        foreach ($customFields as $key => $value) {
            $fieldDef = CustomFieldDefinition::where('key', $key)->first();
            if ($fieldDef) {
                PageCustomField::create([
                    'custom_field_definition_id' => $fieldDef->id,
                    'field_value' => (string)$value,
                    'page_id' => $page->id
                ]);
            }
        }

        $blocks = [
            [
                'type' => 'heading',
                'data' => ['text' => $data['title'], 'level' => 1],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        $data['excerpt'],
                        'Our deal hunters are constantly monitoring prices to bring you the best offers.',
                        'All deals are verified and updated regularly throughout the day.'
                    ]
                ],
                'order' => 2
            ]
        ];

        // Unique content based on deal type
        if (str_contains($data['slug'], 'black-friday-phone')) {
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Best Phone Deals Right Now', 'level' => 2], 'order' => 3];
            $blocks[] = [
                'type' => 'deal',
                'data' => [
                    'title' => 'iPhone 15 Pro - Massive Discount',
                    'productName' => 'iPhone 15 Pro 256GB',
                    'brand' => 'Apple',
                    'image' => 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?auto=format&fit=crop&w=800&q=80',
                    'price' => 999.00,
                    'salePrice' => 849.00,
                    'currency' => '£',
                    'description' => 'Save £150 on iPhone 15 Pro at Amazon - lowest price ever recorded!',
                    'link' => 'https://example.com/deal',
                    'showDealButton' => true,
                    'starBlock' => true
                ],
                'order' => 4
            ];
            $blocks[] = [
                'type' => 'deal',
                'data' => [
                    'title' => 'Galaxy S24 Ultra - Record Low',
                    'productName' => 'Samsung Galaxy S24 Ultra',
                    'brand' => 'Samsung',
                    'image' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?auto=format&fit=crop&w=800&q=80',
                    'price' => 1249.00,
                    'salePrice' => 999.00,
                    'currency' => '£',
                    'description' => 'Save £250 on the S24 Ultra with S Pen - incredible value!',
                    'link' => 'https://example.com/deal',
                    'showDealButton' => true,
                    'starBlock' => true
                ],
                'order' => 5
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'When Do Phone Deals Go Live?', 'level' => 2], 'order' => 6];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Black Friday phone deals typically start appearing in early November and peak on Black Friday itself.',
                        'The best deals often sell out within hours, so set up price alerts for models you want.',
                        'Cyber Monday often brings additional deals or restocks of popular Black Friday offers.',
                        'Carrier deals sometimes offer better value than unlocked phones during sales events.'
                    ]
                ],
                'order' => 7
            ];
        } elseif (str_contains($data['slug'], 'laptop-deals')) {
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Top Laptop Deals Today', 'level' => 2], 'order' => 3];
            $blocks[] = [
                'type' => 'deal',
                'data' => [
                    'title' => 'MacBook Air M3 - Best Price',
                    'productName' => 'MacBook Air 13" M3',
                    'brand' => 'Apple',
                    'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=80',
                    'price' => 1099.00,
                    'salePrice' => 949.00,
                    'currency' => '£',
                    'description' => 'Save £150 on the newest MacBook Air - perfect for students and professionals.',
                    'link' => 'https://example.com/deal',
                    'showDealButton' => true,
                    'starBlock' => true
                ],
                'order' => 4
            ];
            $blocks[] = [
                'type' => 'deal',
                'data' => [
                    'title' => 'Dell XPS 15 - Limited Stock',
                    'productName' => 'Dell XPS 15 OLED',
                    'brand' => 'Dell',
                    'image' => 'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?auto=format&fit=crop&w=800&q=80',
                    'price' => 1499.00,
                    'salePrice' => 1199.00,
                    'currency' => '£',
                    'description' => 'Save £300 on this premium Windows laptop with stunning OLED display.',
                    'link' => 'https://example.com/deal',
                    'showDealButton' => true,
                    'starBlock' => true
                ],
                'order' => 5
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'How to Spot Genuine Laptop Deals', 'level' => 2], 'order' => 6];
            $blocks[] = [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Compare prices across multiple retailers using price tracking tools',
                        'Check historical price data to verify "sale" prices are actually lower',
                        'Beware of older models being cleared out at seemingly good prices',
                        'Student discounts can beat sale prices - check education stores',
                        'Refurbished laptops from manufacturers offer excellent value',
                        'Black Friday and Back to School are the best times for laptop deals'
                    ]
                ],
                'order' => 7
            ];
        } elseif (str_contains($data['slug'], 'tv-deals')) {
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Best TV Deals Available Now', 'level' => 2], 'order' => 3];
            $blocks[] = [
                'type' => 'deal',
                'data' => [
                    'title' => 'LG C3 OLED - Huge Savings',
                    'productName' => 'LG C3 55" OLED 4K TV',
                    'brand' => 'LG',
                    'image' => 'https://images.unsplash.com/photo-1593784991095-a205069470b6?auto=format&fit=crop&w=800&q=80',
                    'price' => 1299.00,
                    'salePrice' => 999.00,
                    'currency' => '£',
                    'description' => 'Save £300 on our favorite OLED TV - perfect for movies and gaming.',
                    'link' => 'https://example.com/deal',
                    'showDealButton' => true,
                    'starBlock' => true
                ],
                'order' => 4
            ];
            $blocks[] = [
                'type' => 'deal',
                'data' => [
                    'title' => 'Samsung S90C QLED - Gaming TV',
                    'productName' => 'Samsung 65" S90C QD-OLED',
                    'brand' => 'Samsung',
                    'image' => 'https://images.unsplash.com/photo-1593784991095-a205069470b6?auto=format&fit=crop&w=800&q=80',
                    'price' => 1799.00,
                    'salePrice' => 1399.00,
                    'currency' => '£',
                    'description' => 'Save £400 on this bright QD-OLED with 144Hz for PS5 and Xbox gaming.',
                    'link' => 'https://example.com/deal',
                    'showDealButton' => true,
                    'starBlock' => true
                ],
                'order' => 5
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'TV Buying Guide: What to Look For', 'level' => 2], 'order' => 6];
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Screen size matters: 55" for rooms up to 8 feet, 65" for 8-12 feet, 75"+ for larger spaces.',
                        'OLED offers perfect blacks and infinite contrast, but QLED is brighter for well-lit rooms.',
                        '4K is standard now - don\'t buy 1080p unless it\'s a secondary TV or monitor.',
                        'For gaming, look for HDMI 2.1, 120Hz refresh rate, and VRR support.',
                        'Smart TV platform matters - Roku, Google TV, and webOS are the best.',
                        'November (Black Friday) sees the best TV deals of the year - often 30-50% off.'
                    ]
                ],
                'order' => 7
            ];
        } elseif (str_contains($data['slug'], 'prime-day')) {
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Live Prime Day Deals', 'level' => 2], 'order' => 3];
            $blocks[] = [
                'type' => 'deal',
                'data' => [
                    'title' => 'Echo Dot (5th Gen) - All-Time Low',
                    'productName' => 'Amazon Echo Dot 5th Gen',
                    'brand' => 'Amazon',
                    'image' => 'https://images.unsplash.com/photo-1543512214-318c7553f230?auto=format&fit=crop&w=800&q=80',
                    'price' => 54.99,
                    'salePrice' => 24.99,
                    'currency' => '£',
                    'description' => 'Save 55% on Echo Dot - perfect for every room in your home.',
                    'link' => 'https://example.com/deal',
                    'showDealButton' => true,
                    'starBlock' => true
                ],
                'order' => 4
            ];
            $blocks[] = [
                'type' => 'deal',
                'data' => [
                    'title' => 'Fire TV Stick 4K Max - Half Price',
                    'productName' => 'Fire TV Stick 4K Max',
                    'brand' => 'Amazon',
                    'image' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?auto=format&fit=crop&w=800&q=80',
                    'price' => 69.99,
                    'salePrice' => 34.99,
                    'currency' => '£',
                    'description' => 'Save 50% on the fastest Fire TV Stick with WiFi 6E support.',
                    'link' => 'https://example.com/deal',
                    'showDealButton' => true,
                    'starBlock' => true
                ],
                'order' => 5
            ];
            $blocks[] = ['type' => 'heading', 'data' => ['text' => 'Prime Day Pro Tips', 'level' => 2], 'order' => 6];
            $blocks[] = [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Lightning Deals sell out fast - set up notifications for items you want',
                        'Many deals start days before Prime Day officially begins',
                        'Other retailers like Walmart and Target run competing sales',
                        'Use CamelCamelCamel to verify deal prices are actually good',
                        'Bundle deals on Echo devices offer the best value',
                        'Amazon devices see the deepest discounts - often 50% off or more'
                    ]
                ],
                'order' => 7
            ];
        }

        // Common ending
        $blocks[] = ['type' => 'heading', 'data' => ['text' => 'How We Find the Best Deals', 'level' => 2], 'order' => 8];
        $blocks[] = [
            'type' => 'text',
            'data' => [
                'paragraphs' => [
                    'Our team uses advanced price tracking tools to monitor thousands of products across major retailers.',
                    'We only recommend deals on products we\'ve tested and can genuinely endorse.',
                    'All prices are manually verified before publication to ensure accuracy.',
                    'We update this page multiple times per day as new deals appear and old ones expire.'
                ]
            ],
            'order' => 9
        ];

        foreach ($blocks as $block) {
            $this->blockRepository->create([
                'page_id' => $page->id,
                'type' => $block['type'],
                'data' => json_encode($block['data']),
                'order' => $block['order']
            ]);
        }
    }

    private function createAboutPage(): void
    {
        $page = Page::create([
            'title' => 'About Tom\'s Guide UK',
            'page_type' => 'content',
            'slug' => 'about',
            'status' => 'published',
            'meta_title' => 'About Us - Tom\'s Guide UK',
            'meta_description' => 'Learn about Tom\'s Guide UK - your trusted source for expert tech reviews.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'About',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
            'sort_order' => 99
        ]);

        $blocks = [
            [
                'type' => 'heading',
                'data' => ['text' => 'About Tom\'s Guide', 'level' => 1],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Tom\'s Guide is a leading technology media brand dedicated to helping readers make smarter buying decisions through expert reviews, news, and how-to guides.',
                        'Founded in 1996, we\'ve been at the forefront of technology journalism for nearly three decades. Our team tests hundreds of products each year.',
                        'We believe in rigorous testing, transparent scoring, and putting readers first. Every product we review goes through extensive real-world testing.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Our Testing Process', 'level' => 2],
                'order' => 3
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'We use standardized testing procedures to ensure fair comparisons across products.',
                        'Each product is evaluated across multiple criteria relevant to its category.',
                        'Our recommendations are based solely on performance, never influenced by advertising.'
                    ]
                ],
                'order' => 4
            ],
            [
                'type' => 'team',
                'data' => [
                    'title' => 'Our Expert Team',
                    'layout' => 'grid',
                    'members' => [
                        [
                            'name' => 'John Smith',
                            'role' => 'Editor-in-Chief',
                            'bio' => '15+ years testing consumer technology',
                            'image' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=400&q=80'
                        ],
                        [
                            'name' => 'Sarah Johnson',
                            'role' => 'Senior Mobile Editor',
                            'bio' => 'Smartphone expert, tested 100+ devices',
                            'image' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=400&q=80'
                        ],
                        [
                            'name' => 'Mike Davis',
                            'role' => 'Computing Editor',
                            'bio' => 'Laptop and PC specialist',
                            'image' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=400&q=80'
                        ],
                        [
                            'name' => 'Emma Wilson',
                            'role' => 'Audio Editor',
                            'bio' => 'Headphones and audio expert',
                            'image' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=400&q=80'
                        ]
                    ]
                ],
                'order' => 5
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createContactPage(): void
    {
        $page = Page::create([
            'title' => 'Contact Tom\'s Guide UK',
            'page_type' => 'content',
            'slug' => 'contact',
            'status' => 'published',
            'meta_title' => 'Contact Us - Tom\'s Guide UK',
            'meta_description' => 'Get in touch with the Tom\'s Guide UK editorial team.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Contact',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
            'sort_order' => 100
        ]);

        $blocks = [
            [
                'type' => 'heading',
                'data' => ['text' => 'Contact Us', 'subtitle' => 'Get in touch with our team', 'level' => 1],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Have a question, story tip, or feedback? We\'d love to hear from you.',
                        'Our editorial team reads every message and responds to inquiries as quickly as possible.',
                        'For press releases and review samples, please use the email addresses below.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Send Us a Message',
                    'showName' => true,
                    'showEmail' => true,
                    'showSubject' => true,
                    'showMessage' => true,
                    'submitButtonText' => 'Send Message',
                    'requireName' => true,
                    'requireEmail' => true,
                    'requireMessage' => true
                ],
                'order' => 3
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Editorial Contacts', 'level' => 2],
                'order' => 4
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'General inquiries: editorial@tomsguide.com',
                        'Review samples: reviews@tomsguide.com',
                        'Press releases: press@tomsguide.com',
                        'Advertising: advertising@tomsguide.com'
                    ]
                ],
                'order' => 5
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createCategoryPages(): void
    {
        // Best Picks landing page
        $page = Page::create([
            'title' => 'Best Picks - Expert Tech Buying Guides',
            'slug' => 'best-picks',
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => 'Best Picks | Expert Tech Buying Guides - Tom\'s Guide',
            'meta_description' => 'Discover our expert-tested recommendations for the best tech products.',
            'site_id' => $this->site->id,
        ]);

        $blocks = [
            [
                'type' => 'heading',
                'data' => ['text' => 'Best Picks', 'subtitle' => 'Expert-tested recommendations', 'level' => 1],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Our expert reviewers spend hundreds of hours testing products to bring you definitive buying guides.',
                        'Every recommendation is based on rigorous real-world testing, not just specifications.',
                        'We test in controlled conditions and real-world scenarios to ensure our advice is reliable.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Best Phones', 'level' => 2],
                'order' => 3
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'layout' => 'grid',
                    'columns' => 4,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'pages' => [
                        ['slug' => 'iphone-15-pro-max-review'],
                        ['slug' => 'samsung-galaxy-s24-ultra-review'],
                        ['slug' => 'best-phones-pixel-8-pro'],
                        ['slug' => 'best-phones-oneplus-12']
                    ]
                ],
                'order' => 4
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Best Laptops', 'level' => 2],
                'order' => 5
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'layout' => 'grid',
                    'columns' => 4,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'pages' => [
                        ['slug' => 'macbook-pro-m3-review'],
                        ['slug' => 'best-laptops-dell-xps-15'],
                        ['slug' => 'best-laptops-asus-rog-g14'],
                        ['slug' => 'best-laptops-thinkpad-x1']
                    ]
                ],
                'order' => 6
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Best Wearables', 'level' => 2],
                'order' => 7
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'layout' => 'grid',
                    'columns' => 4,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'pages' => [
                        ['slug' => 'sony-wh-1000xm5-review'],
                        ['slug' => 'best-wearables-airpods-pro-2'],
                        ['slug' => 'best-wearables-apple-watch-series-9']
                    ]
                ],
                'order' => 8
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);

        // Individual category pages
        $this->createSimpleCategoryPage('Phones', 'phones', [
            'iphone-15-pro-max-review',
            'samsung-galaxy-s24-ultra-review',
            'best-phones-pixel-8-pro',
            'best-phones-oneplus-12',
            'best-phones-iphone-15',
            'best-phones-galaxy-a54'
        ]);

        $this->createSimpleCategoryPage('Laptops', 'laptops', [
            'macbook-pro-m3-review',
            'best-laptops-dell-xps-15',
            'best-laptops-asus-rog-g14',
            'best-laptops-thinkpad-x1',
            'best-laptops-macbook-air-m3',
            'best-laptops-hp-spectre-x360'
        ]);

        $this->createSimpleCategoryPage('Tablets', 'tablets', [
            'best-tablets-ipad-pro-m2',
            'best-tablets-galaxy-tab-s9-ultra'
        ]);

        $this->createSimpleCategoryPage('Wearables', 'wearables', [
            'sony-wh-1000xm5-review',
            'best-wearables-apple-watch-series-9',
            'best-wearables-airpods-pro-2'
        ]);

        $this->createSimpleCategoryPage('Home', 'home', [
            'best-home-lg-c3-oled',
            'best-home-sonos-arc'
        ]);

        // News page
        $page = Page::create([
            'title' => 'Tech News - Latest Technology News & Updates',
            'slug' => 'news',
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => 'Tech News | Latest Technology News & Updates',
            'meta_description' => 'Stay updated with the latest tech news, product launches, and industry updates.',
            'site_id' => $this->site->id,
        ]);

        $blocks = [
            [
                'type' => 'heading',
                'data' => ['text' => 'Latest Tech News', 'subtitle' => 'Breaking news and updates', 'level' => 1],
                'order' => 1
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'layout' => 'grid',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'pages' => [
                        ['slug' => 'apple-ai-features-announcement'],
                        ['slug' => 'samsung-galaxy-s25-teaser'],
                        ['slug' => 'google-pixel-9-leaks'],
                        ['slug' => 'netflix-price-increase-2024'],
                        ['slug' => 'microsoft-windows-12-announcement'],
                        ['slug' => 'meta-quest-3s-leak']
                    ]
                ],
                'order' => 2
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);

        // How To page
        $page = Page::create([
            'title' => 'How To Guides - Tech Tutorials & Tips',
            'slug' => 'how-to',
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => 'How To Guides | Tech Tutorials & Tips',
            'meta_description' => 'Learn how to get the most from your tech with our expert tutorials.',
            'site_id' => $this->site->id,
        ]);

        $blocks = [
            [
                'type' => 'heading',
                'data' => ['text' => 'How To Guides', 'subtitle' => 'Expert tutorials and tips', 'level' => 1],
                'order' => 1
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'layout' => 'grid',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'pages' => [
                        ['slug' => 'how-to-take-better-iphone-photos'],
                        ['slug' => 'how-to-setup-windows-11-pc'],
                        ['slug' => 'how-to-fix-netflix-not-working'],
                        ['slug' => 'how-to-connect-airpods'],
                        ['slug' => 'how-to-speed-up-slow-pc']
                    ]
                ],
                'order' => 2
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);

        // Deals page
        $page = Page::create([
            'title' => 'Best Tech Deals - Save on Phones, Laptops & More',
            'slug' => 'deals',
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => 'Best Tech Deals | Save on Phones, Laptops & More',
            'meta_description' => 'Find the best tech deals and discounts on top products.',
            'site_id' => $this->site->id,
        ]);

        $blocks = [
            [
                'type' => 'heading',
                'data' => ['text' => 'Best Tech Deals', 'subtitle' => 'Save big on top tech products', 'level' => 1],
                'order' => 1
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'layout' => 'grid',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'pages' => [
                        ['slug' => 'black-friday-phone-deals-2024'],
                        ['slug' => 'best-laptop-deals-today'],
                        ['slug' => 'best-tv-deals'],
                        ['slug' => 'amazon-prime-day-tech-deals-2024']
                    ]
                ],
                'order' => 2
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createSimpleCategoryPage(string $title, string $slug, array $pageSlugs): void
    {
        $page = Page::create([
            'title' => 'Best ' . $title . ' - Expert Buying Guide',
            'slug' => $slug,
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => 'Best ' . $title . ' 2024 | Expert Buying Guide',
            'meta_description' => 'Find the perfect ' . strtolower($title) . ' with our expert reviews and buying advice.',
            'site_id' => $this->site->id,
        ]);

        $blocks = [
            [
                'type' => 'heading',
                'data' => ['text' => 'Best ' . $title . ' 2024', 'subtitle' => 'Expert-tested recommendations', 'level' => 1],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'We\'ve tested dozens of ' . strtolower($title) . ' to bring you our top recommendations.',
                        'Our experts evaluate each product across multiple criteria to ensure you get the best advice.',
                        'All recommendations are based on extensive hands-on testing and real-world use.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'layout' => 'grid',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'pages' => array_map(fn($slug) => ['slug' => $slug], $pageSlugs)
                ],
                'order' => 3
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createCategoryMenuItems(): void
    {
        // Create Best Picks parent menu
        $bestPicksParent = MenuItem::create([
            'label' => 'Best Picks',
            'menu_id' => $this->menu->id,
            'is_active' => true,
            'sort_order' => 2
        ]);

        // Best Picks categories
        $bestPicksCategories = ['Phones', 'Laptops', 'Tablets', 'Wearables', 'Home'];
        foreach ($bestPicksCategories as $index => $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, $this->site->id);
            MenuItem::create([
                'label' => $categoryName,
                'menu_id' => $this->menu->id,
                'target_type' => 'category',
                'target_id' => $category->id,
                'parent_id' => $bestPicksParent->id,
                'is_active' => true,
                'sort_order' => $index
            ]);
        }

        // Create VS menu
        $vsCategory = $this->categoryRepository->findOrCreateByName('VS', $this->site->id);
        MenuItem::create([
            'label' => 'VS',
            'menu_id' => $this->menu->id,
            'target_type' => 'category',
            'target_id' => $vsCategory->id,
            'is_active' => true,
            'sort_order' => 3
        ]);

        // Create News parent menu
        $newsParent = MenuItem::create([
            'label' => 'News',
            'menu_id' => $this->menu->id,
            'is_active' => true,
            'sort_order' => 4
        ]);

        $newsCategories = ['Mobile', 'Computing', 'Entertainment', 'Smart Home'];
        foreach ($newsCategories as $index => $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, $this->site->id);
            MenuItem::create([
                'label' => $categoryName,
                'menu_id' => $this->menu->id,
                'target_type' => 'category',
                'target_id' => $category->id,
                'parent_id' => $newsParent->id,
                'is_active' => true,
                'sort_order' => $index
            ]);
        }

        // Create How To menu
        $howToCategory = $this->categoryRepository->findOrCreateByName('How To', $this->site->id);
        MenuItem::create([
            'label' => 'How To',
            'menu_id' => $this->menu->id,
            'target_type' => 'category',
            'target_id' => $howToCategory->id,
            'is_active' => true,
            'sort_order' => 5
        ]);

        // Create Deals menu
        $dealsCategory = $this->categoryRepository->findOrCreateByName('Deals', $this->site->id);
        MenuItem::create([
            'label' => 'Deals',
            'menu_id' => $this->menu->id,
            'target_type' => 'category',
            'target_id' => $dealsCategory->id,
            'is_active' => true,
            'sort_order' => 6
        ]);
    }
}