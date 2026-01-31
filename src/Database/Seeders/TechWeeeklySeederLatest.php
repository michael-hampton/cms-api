<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\Site;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class TechWeeeklySeederLatest extends Seeder
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
        $this->site = Site::where('slug', 'tech-weekly')->first();
        $this->createArticles();
    }


    // ... within the createArticles() method of DecanterSeeder.php
    private function createArticles(): void
    {
        $articles = [
            [
                'title' => 'Nokia X-Series: The Return of the Unbeatable Battery Life? (Review)',
                'slug' => 'nokia-x-series-battery-review',
                'tags' => ['nokia', 'review', 'smartphone', 'battery', 'mid-range'],
                'categories' => ['Nokia', 'Smartphones', 'Reviews'],
                'custom_fields' => ['author_name' => 'Sara Khan', 'read_time' => 8, 'excerpt' => 'A hands-on review of the new Nokia X-Series focusing on its durable design and industry-leading battery performance.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'Durability Meets Stamina', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['HMD Global’s latest push with the Nokia X-Series is a clear pivot back to the brand’s roots: reliability and longevity. We spent two weeks with the phone to test if the 3-day battery claim holds up in the real world. Spoiler: it mostly does.', 'The phone’s industrial design is robust, passing our drop tests with minor cosmetic damage. In a market saturated with fragile glass devices, this is a welcome change.']]],
                    ['type' => 'product', 'data' => ['name' => 'Nokia X21', 'brand' => 'HMD Global', 'productName' => 'Nokia X-Series Phone', 'price' => 399.99, 'currency' => '$', 'description' => 'The definitive mid-range phone focused on long battery life and durability.', 'linkText' => 'Buy Now', 'image' => ['src' => 'https://images.unsplash.com/photo-1610476059102-18751532f78e?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80']]],
                    ['type' => 'stats', 'data' => ['title' => 'X-Series Test Results', 'stats' => [['number' => '72h', 'label' => 'Battery Life (Tested)', 'icon' => '🔋'], ['number' => 'IP67', 'label' => 'Water/Dust Rating', 'icon' => '💧'], ['number' => '6.5"', 'label' => 'Display Size', 'icon' => '📱']]]]
                ]
            ],
            // 2. Nokia: Strategy
            [
                'title' => 'HMD Global\'s Strategy: Can a Revived Nokia Reclaim the Mid-Range Market?',
                'slug' => 'hmd-nokia-strategy-midrange',
                'tags' => ['nokia', 'analysis', 'business', 'market-share', 'smartphones'],
                'categories' => ['Nokia', 'Business', 'Analysis'],
                'custom_fields' => ['author_name' => 'Marcus Bell', 'read_time' => 10, 'excerpt' => 'An analysis of HMD Global’s market strategy, focusing on their plan to challenge Samsung and Xiaomi in the competitive mid-tier smartphone segment.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The Mid-Tier Battleground', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['Nokia\'s revival under HMD Global has been strategically cautious, avoiding the high-stakes flagship race. Their focus is on delivering consistent updates and superior build quality to consumers often overlooked by the high-end marketing campaigns of Apple and Samsung.', 'The key to their success lies in leveraging the nostalgic brand trust while offering cutting-edge features like guaranteed Android updates and strong security patches.']]],
                    ['type' => 'quote', 'data' => ['text' => 'Nokia’s focus on clean Android and three years of guaranteed security updates is a powerful differentiator in the crowded mid-range market.', 'attribution' => 'TechWeekly Analyst']],
                ]
            ],
            // 3. Nokia: Nostalgia
            [
                'title' => 'Three Classic Nokia Phones We Want to See Reissued Next',
                'slug' => 'classic-nokia-reissue-wishlist',
                'tags' => ['nokia', 'feature', 'nostalgia', 'retro-tech'],
                'categories' => ['Nokia', 'Features', 'Retro Tech'],
                'custom_fields' => ['author_name' => 'Chloe Davies', 'read_time' => 5, 'excerpt' => 'A look back at the iconic Nokia handsets that HMD Global should bring back next, complete with modern 4G and smartphone capabilities.'],
                'content' => [
                    ['type' => 'list', 'data' => ['listType' => 'ol', 'items' => ['The 3310 was a success, but the iconic **Nokia 8210** with its sleek, small form factor is perfect for a modern digital detox phone.', 'The **Nokia N95** slider phone—a multimedia powerhouse of its era—could be reinvented as a niche enthusiast device.', 'The **Nokia 5110**, simple, rugged, and customizable, would make a perfect ultra-budget emergency phone.']]],
                    ['type' => 'image', 'data' => ['src' => 'https://images.unsplash.com/photo-1511707171634-5f897ff498d9?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80', 'alt' => 'Nokia 3310 re-release example', 'caption' => 'The 3310 re-release proved the demand for classic Nokia designs.', 'alignment' => 'center']]
                ]
            ],
            // 4. Google: Camera
            [
                'title' => 'Google Pixel 10 Pro Camera Deep Dive: Can Computational Photography Still Lead?',
                'slug' => 'pixel-10-pro-camera-deep-dive',
                'tags' => ['google', 'pixel', 'review', 'camera', 'ai', 'computational-photography'],
                'categories' => ['Google', 'Smartphones', 'Reviews'],
                'custom_fields' => ['author_name' => 'Daniel Cho', 'read_time' => 9, 'excerpt' => 'We test the new Tensor chip\'s photographic capabilities in the Pixel 10 Pro to see if software can still beat raw hardware in the smartphone camera war.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'Software vs. Silicon', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The Pixel series has consistently relied on Google\'s groundbreaking computational photography to deliver stunning images. The Pixel 10 Pro introduces the third generation of the Tensor chip, promising even smarter image stacking and video processing.', 'Our tests show massive leaps in low-light video stabilization and object segmentation in portrait mode, proving Google is still the king of post-capture AI enhancement.']]],
                    ['type' => 'product-comparison', 'data' => ['title' => 'Flagship Camera Comparison', 'productA' => 'Pixel 10 Pro', 'productB' => 'iPhone 17 Pro', 'comparisons' => [['subtitle' => 'Low Light Video', 'items' => ['Excellent AI Noise Reduction', 'Good Low Light Detail']], ['subtitle' => 'Portrait Edge Detection', 'items' => ['Near Perfect', 'Very Good']], ['subtitle' => 'Zoom Clarity (10x)', 'items' => ['Solid Detail', 'Slightly Sharper']]]]]
                ]
            ],
            // 5. Google: AI Roadmap
            [
                'title' => 'The Future of Android: A Look at Google\'s AI Integration Roadmap for 2026',
                'slug' => 'google-android-ai-roadmap',
                'tags' => ['google', 'android', 'ai', 'future', 'software'],
                'categories' => ['Google', 'Software', 'Analysis'],
                'custom_fields' => ['author_name' => 'Eliza Viera', 'read_time' => 12, 'excerpt' => 'A leak detailing Google\'s aggressive integration of Gemini-powered AI features into the core Android OS, revolutionizing the user experience.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'Contextual Computing', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['Leaked documents suggest that the next major Android release will deeply integrate generative AI across system functions. This goes beyond search and assistant, impacting notification management, predictive scheduling, and even live language translation during calls.', 'The goal is to create a hyper-personalized, context-aware operating system that anticipates user needs before they are explicitly expressed.']]],
                    ['type' => 'list', 'data' => ['listType' => 'ul', 'items' => ['**Predictive Notifications:** AI sorts and summarizes complex group chats and emails, only bubbling up critical information.', '**Dynamic Battery Management:** The OS learns daily power consumption patterns and pre-emptively adjusts performance profiles.', '**Live Interpretation:** System-wide, real-time audio translation for video and voice calls in multiple languages.']]],
                ]
            ],
            // 6. Google: Wearable
            [
                'title' => 'Pixel Watch 3 vs. Samsung Galaxy Watch: Battle for the Best Android Wearable',
                'slug' => 'pixel-watch-3-vs-galaxy-watch',
                'tags' => ['google', 'wearable', 'smartwatch', 'review', 'samsung'],
                'categories' => ['Google', 'Wearables', 'Reviews'],
                'custom_fields' => ['author_name' => 'Jason Lee', 'read_time' => 7, 'excerpt' => 'Comparing the latest smartwatches from Google and Samsung: a head-to-head on design, software, fitness tracking, and battery life.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'Wear OS Showdown', 'level' => 2]],
                    ['type' => 'product-comparison', 'data' => ['title' => 'Smartwatch Specs', 'productA' => 'Pixel Watch 3', 'productB' => 'Galaxy Watch 7', 'comparisons' => [['subtitle' => 'Design', 'items' => ['Circular, Minimalist', 'Circular, Bezel Control']], ['subtitle' => 'Battery Life', 'items' => ['28 Hours', '50 Hours']], ['subtitle' => 'Fitness Focus', 'items' => ['Fitbit Integration', 'Samsung Health Ecosystem']]]]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The Pixel Watch 3 has finally closed the gap on battery life, making it a serious contender. However, Samsung still holds the edge in multi-day stamina and advanced health sensors. The choice comes down to ecosystem preference: Fitbit for Google users, or the full Samsung Health suite.']]],
                ]
            ],
            // 1. Gaming (Esports Analysis)
            [
                'title' => 'The Business of Esports: Why Viewership is Soaring and How Brands Are Cashing In',
                'slug' => 'esports-viewership-business-analysis',
                'tags' => ['gaming', 'esports', 'industry', 'streaming', 'finance'],
                'categories' => ['Gaming', 'Analysis'],
                'custom_fields' => ['author_name' => 'Sara Khan', 'read_time' => 9, 'excerpt' => 'Esports has moved from niche hobby to mainstream spectator sport. We look at the multi-million dollar media rights and sponsorship deals driving its exponential growth.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The New Stadium Experience', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['Professional gaming tournaments now command stadium-filling crowds and massive online audiences, rivaling traditional sports finals. The key difference is accessibility, with streaming platforms offering free, global access.', 'Brand partnerships are shifting from peripheral ads to deep, in-game integrations and custom merchandise lines.']]],
                    ['type' => 'stats', 'data' => ['title' => 'Esports Growth Metrics', 'stats' => [['number' => '400M+', 'label' => 'Global Audience', 'icon' => '👥'], ['number' => '$1.8B', 'label' => 'Total Revenue', 'icon' => '💰']]]]
                ]
            ],
            // 2. Gaming (Hardware Review)
            [
                'title' => 'Review: The New Generation of Gaming Monitors Making 4K @ 144Hz Affordable',
                'slug' => '4k-144hz-gaming-monitor-review',
                'tags' => ['gaming', 'hardware', 'review', 'pc-gaming', 'monitors'],
                'categories' => ['Gaming', 'Reviews', 'Hardware'],
                'custom_fields' => ['author_name' => 'Mike Chen', 'read_time' => 7, 'excerpt' => 'High-refresh-rate 4K displays were once exclusively luxury items. We test three new models that deliver top-tier performance at mid-range prices.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'Balancing Resolution and Refresh Rate', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The latest display panels feature improved response times and better HDR implementation. Crucially, the cost of the necessary G-Sync and FreeSync hardware has dropped, making smooth, tear-free 4K gaming a reality for more users.', 'We recommend prioritizing IPS panels for color accuracy over TN for pure speed.']]],
                    ['type' => 'product', 'data' => ['name' => 'Aorus FI32U', 'brand' => 'AORUS', 'productName' => '32-inch 4K Gaming Monitor', 'price' => 799.00, 'currency' => '$', 'description' => 'Our top pick for performance-to-price ratio.', 'linkText' => 'Check Price', 'image' => ['src' => 'https://images.unsplash.com/photo-1594956322238-d9f7e53f0f4a?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80']]]
                ]
            ],
            // 3. Gaming (Software/Trend)
            [
                'title' => 'The Open World Fatigue: Are Massive Games Trading Scale for Soul?',
                'slug' => 'open-world-gaming-fatigue-analysis',
                'tags' => ['gaming', 'opinion', 'design', 'rpg', 'indie-games'],
                'categories' => ['Gaming', 'Opinion'],
                'custom_fields' => ['author_name' => 'Daniel Cho', 'read_time' => 6, 'excerpt' => 'In a world saturated with sprawling maps and thousands of waypoints, we ask if game design is suffering from an obsession with size over meaningful content and narrative focus.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The Case for Constraints', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['Many players are turning towards smaller, more tightly designed linear or segmented games for a focused, high-impact narrative experience. The pressure to deliver hundreds of hours of content often results in repetitive filler activities.', 'A trend is emerging for ' . '“medium-sized”' . ' worlds that emphasize player choice and environmental density.']]]
                ]
            ],

            // 4. LG (TVs)
            [
                'title' => 'LG Signature OLED G-Series Review: The Ultimate TV for Wall-Mount Enthusiasts',
                'slug' => 'lg-oled-g-series-tv-review',
                'tags' => ['tvs', 'lg', 'oled', 'review', 'premium-tech'],
                'categories' => ['TVs', 'Reviews'],
                'custom_fields' => ['author_name' => 'Julian Hayes', 'read_time' => 8, 'excerpt' => 'LG’s ' . '“Gallery”' . ' design philosophy reaches new heights with a near-seamless wall-mount system and the brightest OLED panel yet.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The Zero-Gap Difference', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The G-series panel is breathtakingly thin and utilizes a specialized mount that eliminates the gap between the TV and the wall. Paired with the new Alpha 9 Gen 6 processor, motion handling and upscaling are significantly improved.', 'For serious home cinema and console gaming, the inclusion of four HDMI 2.1 ports is a major win.']]],
                    ['type' => 'stats', 'data' => ['title' => 'Performance Metrics', 'stats' => [['number' => '4x', 'label' => 'HDMI 2.1 Ports', 'icon' => '🔗'], ['number' => '10/10', 'label' => 'Design Score', 'icon' => '✨']]]]
                ]
            ],
            // 5. LG (Smartphones/Innovation)
            [
                'title' => 'Remembering LG’s Mobile Legacy: A Look Back at the Most Innovative Phone Designs',
                'slug' => 'lg-mobile-legacy-innovative-phones',
                'tags' => ['smartphones', 'lg', 'history', 'innovation', 'design'],
                'categories' => ['Smartphones', 'History', 'Innovation'],
                'custom_fields' => ['author_name' => 'Chloe Davies', 'read_time' => 7, 'excerpt' => 'Though LG exited the mobile market, their pioneering efforts in dual screens, modularity, and swiveling displays set a high bar for experimental smartphone design.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The Era of Experimentation', 'level' => 2]],
                    ['type' => 'list', 'data' => ['listType' => 'ul', 'items' => ['**LG Wing:** A unique swiveling dual-screen phone that provided new multitasking possibilities.', '**LG G5:** An early attempt at modular design, allowing users to swap camera and battery components.', '**LG Flex:** The first curved, self-healing plastic display phone.']]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['LG was not afraid to take risks. Many of their concepts, though perhaps ahead of their time, directly influenced the form factors we see today.']]]
                ]
            ],
            // 6. LG (Company/AI)
            [
                'title' => 'How LG’s AI-Powered Thinq Ecosystem is Redefining the Smart Home',
                'slug' => 'lg-thinq-ai-smart-home',
                'tags' => ['lg', 'smart-home', 'ai', 'appliances', 'iot'],
                'categories' => ['Home Tech', 'Innovation'],
                'custom_fields' => ['author_name' => 'Anna Lee', 'read_time' => 10, 'excerpt' => 'LG is moving beyond individual smart appliances, connecting them into a seamless AI-driven network that learns and anticipates household needs.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The Holistic House', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The ThinQ platform uses deep learning to optimize energy consumption and automate routines. For example, the washing machine can communicate with the dryer to set the perfect cycle, and the refrigerator can track inventory.', 'This is a shift from simple remote control to true, proactive intelligence in the home environment.']]],
                    ['type' => 'note', 'data' => ['title' => 'Key Features', 'paragraphs' => ['Proactive customer care (diagnosis and remote fixes) and adaptive energy management.']]]
                ]
            ],
        ];

        foreach ($articles as $articleData) {
            $this->createArticle($articleData);
        }
    }

    private function createArticle(array $data): void
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => $data['title'] . ' - The Wine Chronicle',
            'meta_description' => $data['custom_fields']['excerpt'] ?? '',
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

        foreach ($data['custom_fields'] as $key => $value) {
            $fieldDef = CustomFieldDefinition::where('key', $key)->first();
            if ($fieldDef) {
                PageCustomField::create([
                    'custom_field_definition_id' => $fieldDef->id,
                    'field_value' => (string)$value,
                    'page_id' => $page->id
                ]);
            }
        }

        foreach ($data['content'] as $index => $blockData) {
            $this->blockRepository->create([
                'page_id' => $page->id,
                'type' => $blockData['type'],
                'data' => json_encode($blockData['data']),
                'order' => $index + 1
            ]);
        }
    }
}