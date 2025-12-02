<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\Site;
use App\Repositories\BlockRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;
use App\Services\BlockParserService;

class GamesRadarSeederLatest extends Seeder
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
        $this->site = Site::where('slug', 'gamesradar')->first();
        $this->createArticles();
    }


    // ... within the createArticles() method of DecanterSeeder.php
    private function createArticles(): void
    {
        $articles = [
// 1. PS5 Reviews
            ['title' => 'Final Verdict: Is \'Cosmic Drift\' the PS5\'s First Essential Open-World RPG?', 'slug' => 'cosmic-drift-ps5-review', 'tags' => ['ps5-reviews', 'rpg', 'open-world', 'exclusive'], 'categories' => ['PS5 Reviews', 'PlayStation', 'Reviews'], 'custom_fields' => ['author_name' => 'Mike Chen', 'read_time' => 12, 'rating' => '9.5/10', 'excerpt' => 'A sprawling sci-fi epic that redefines scale, but stumbles slightly in the final act.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The game leverages the DualSense controller and PS5 SSD perfectly.']]], ['type' => 'stats', 'data' => ['title' => 'Scores', 'stats' => [['number' => '9.5/10', 'label' => 'Score', 'icon' => '⭐'], ['number' => '120h', 'label' => 'Playtime', 'icon' => '⏱']]]]]],
            ['title' => 'PS5 Review: \'Stellaris 2\' Proves Strategy Games Can Shine on Console', 'slug' => 'stellaris-2-ps5-review', 'tags' => ['ps5-reviews', 'strategy', 'simulation', 'port'], 'categories' => ['PS5 Reviews', 'PlayStation', 'Reviews'], 'custom_fields' => ['author_name' => 'Sarah Jones', 'read_time' => 8, 'rating' => '8/10', 'excerpt' => 'A surprisingly intuitive control scheme and a clean UI make this complex 4X game feel native on the console.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The new radial menu system is a triumph of console interface design.']]]]],
            ['title' => 'Review: \'The Last Bastion\' is a Visually Stunning but Flawed PS5 Exclusive', 'slug' => 'last-bastion-ps5-review', 'tags' => ['ps5-reviews', 'shooter', 'exclusive', 'single-player'], 'categories' => ['PS5 Reviews', 'PlayStation', 'Reviews'], 'custom_fields' => ['author_name' => 'James Davies', 'read_time' => 9, 'rating' => '6/10', 'excerpt' => 'Beautiful world design is let down by a repetitive mission structure and uninspired combat.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['It’s a technical showpiece that lacks the creative spark of its contemporaries.']]]]],

            // 2. Xbox Reviews
            ['title' => 'Xbox Series X Review: \'Shadowfall\' Delivers a Masterclass in Next-Gen Stealth', 'slug' => 'shadowfall-xbox-review', 'tags' => ['xbox-reviews', 'stealth', 'rpg', 'series-x'], 'categories' => ['Xbox Reviews', 'Xbox', 'Reviews'], 'custom_fields' => ['author_name' => 'Anna Lee', 'read_time' => 11, 'rating' => '9/10', 'excerpt' => 'A gorgeous and taut stealth experience that uses ray-tracing to its full, shadow-hiding advantage.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The sound design alone is worth the price of admission.']]]]],
            ['title' => 'The Definitive Verdict on \'Wasteland Echoes\': A New Benchmark for Xbox RPGs', 'slug' => 'wasteland-echoes-xbox-review', 'tags' => ['xbox-reviews', 'rpg', 'post-apocalypse', 'game-pass'], 'categories' => ['Xbox Reviews', 'Xbox', 'Reviews'], 'custom_fields' => ['author_name' => 'Tom Viera', 'read_time' => 13, 'rating' => '10/10', 'excerpt' => 'A flawless, narratively dense RPG that rivals the genre’s best, and it\'s available day one on Game Pass.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The dialogue trees and moral choices are the deepest we\'ve seen this generation.']]], ['type' => 'quote', 'data' => ['text' => 'The writing in this game is a masterpiece of world-building.', 'attribution' => 'Tom Viera']]]],
            ['title' => 'Review: \'Forza Apex\' Maintains its Crown as the Undisputed King of Racing Sims', 'slug' => 'forza-apex-xbox-review', 'tags' => ['xbox-reviews', 'racing', 'simulation', 'series-x'], 'categories' => ['Xbox Reviews', 'Xbox', 'Reviews'], 'custom_fields' => ['author_name' => 'Samir Patel', 'read_time' => 7, 'rating' => '9.2/10', 'excerpt' => 'Unparalleled visual fidelity and physics make this a must-own for racing enthusiasts, despite a familiar career mode.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The new dynamic weather system is a game-changer for track conditions.']]]]],

            // 3. PC Reviews
            ['title' => 'PC Review: \'Cybernetic Dawn\' Sets a New Standard for Hardware-Pushing Graphics', 'slug' => 'cybernetic-dawn-pc-review', 'tags' => ['pc-reviews', 'shooter', 'cyberpunk', 'ray-tracing'], 'categories' => ['PC Reviews', 'PC Gaming', 'Reviews'], 'custom_fields' => ['author_name' => 'Eliza Viera', 'read_time' => 10, 'rating' => '8.5/10', 'excerpt' => 'This game is the ultimate benchmark for high-end PCs, but casual players may need to wait for optimization patches.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The level of detail in the city environments is staggering at max settings.']]]]],
            ['title' => 'Why \'Dungeon Crawler VII\' is the Most Surprising Indie Hit of the Year on PC', 'slug' => 'dungeon-crawler-vii-pc-review', 'tags' => ['pc-reviews', 'indie', 'roguelike', 'rpg'], 'categories' => ['PC Reviews', 'PC Gaming', 'Reviews'], 'custom_fields' => ['author_name' => 'Mike Chen', 'read_time' => 6, 'rating' => '9/10', 'excerpt' => 'A deceptively simple-looking game with incredibly deep build customization and addictive loop.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['It feels like a love letter to 90s classic RPGs, streamlined for a modern audience.']]]]],
            ['title' => 'Early Access Review: Is \'Galactic Frontier\' Worth Your Time and Money Yet?', 'slug' => 'galactic-frontier-early-access', 'tags' => ['pc-reviews', 'early-access', 'space-sim', 'mmo'], 'categories' => ['PC Reviews', 'PC Gaming', 'Reviews'], 'custom_fields' => ['author_name' => 'Sarah Jones', 'read_time' => 8, 'rating' => '7/10 (EA)', 'excerpt' => 'Huge potential is locked behind buggy systems and placeholder content. Wait for the 1.0 release unless you love giving feedback.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The core flight mechanics are solid, but the resource gathering is currently a grind.']]]]],

            // 4. Switch Reviews
            ['title' => 'Review: \'Pocket Monsters: Azure\' Combines Nostalgia with Brilliant New Mechanics', 'slug' => 'pocket-monsters-azure-switch-review', 'tags' => ['switch-reviews', 'rpg', 'nintendo', 'exclusive'], 'categories' => ['Switch Reviews', 'Reviews'], 'custom_fields' => ['author_name' => 'James Davies', 'read_time' => 9, 'rating' => '9.5/10', 'excerpt' => 'The best entry in the series in years, delivering both open-world freedom and classic turn-based polish.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The new \'fusion\' mechanic fundamentally changes team strategy.']]]]],
            ['title' => 'Nintendo Switch Review: \'Mystic Isles\' Is the Perfect Co-Op Adventure', 'slug' => 'mystic-isles-switch-review', 'tags' => ['switch-reviews', 'co-op', 'puzzle', 'indie'], 'categories' => ['Switch Reviews', 'Reviews'], 'custom_fields' => ['author_name' => 'Anna Lee', 'read_time' => 7, 'rating' => '8.8/10', 'excerpt' => 'A charming, accessible puzzle-adventure that is best played with a friend on the couch.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The game’s aesthetic feels like a spiritual successor to classic N64 titles.']]]]],
            ['title' => '\'Paper Dungeons: The Lost Scroll\' - The Best Portable RPG Since the 3DS? (Review)', 'slug' => 'paper-dungeons-switch-review', 'tags' => ['switch-reviews', 'rpg', 'turn-based', 'portable'], 'categories' => ['Switch Reviews', 'Reviews'], 'custom_fields' => ['author_name' => 'Tom Viera', 'read_time' => 8, 'rating' => '9.1/10', 'excerpt' => 'A visually distinct and narratively rich title that utilizes the Switch\'s portability beautifully.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The paper-craft aesthetic hides a surprisingly deep, dark fantasy story.']]]]],

            // 5. PlayStation
            ['title' => 'The 10 Most Anticipated PlayStation 5 Games Coming in Early 2026', 'slug' => 'most-anticipated-ps5-games-2026', 'tags' => ['playstation', 'ps5', 'upcoming', 'preview'], 'categories' => ['PlayStation', 'Features'], 'custom_fields' => ['author_name' => 'Samir Patel', 'read_time' => 5, 'excerpt' => 'Our look ahead at the must-have titles launching exclusively or first on the PlayStation 5 in the first half of next year.'], 'content' => [['type' => 'list', 'data' => ['listType' => 'ol', 'items' => ['Horizon: Forbidden West 2', 'Gran Turismo 8', 'Demon\'s Souls 2']]]]],
            ['title' => 'How PS VR2 Is Changing the Way We Experience Console Gaming', 'slug' => 'ps-vr2-changing-console-gaming', 'tags' => ['playstation', 'ps-vr2', 'vr', 'analysis'], 'categories' => ['PlayStation', 'Analysis'], 'custom_fields' => ['author_name' => 'Mike Chen', 'read_time' => 9, 'excerpt' => 'From eye-tracking to advanced haptics, the PS VR2 is making virtual reality an essential component of the PS5 experience.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The ease of setup and the quality of the exclusive titles have been key to its adoption rate.']]]]],
            ['title' => 'The Legacy of the DualSense: Has Sony Perfected the Controller?', 'slug' => 'dualsense-controller-legacy', 'tags' => ['playstation', 'ps5', 'hardware', 'opinion'], 'categories' => ['PlayStation', 'Hardware', 'Opinion'], 'custom_fields' => ['author_name' => 'Sarah Jones', 'read_time' => 7, 'excerpt' => 'Adaptive triggers and haptic feedback have moved beyond gimmicks to become fundamental to PS5 game design.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['We argue that the DualSense represents the biggest controller innovation since the introduction of analog sticks.']]]]],

            // 6. Xbox
            ['title' => 'Everything Announced at the Xbox Summer Showcase: New IPs and Release Dates', 'slug' => 'xbox-summer-showcase-summary', 'tags' => ['xbox', 'event', 'reveal', 'news'], 'categories' => ['Xbox', 'News'], 'custom_fields' => ['author_name' => 'James Davies', 'read_time' => 6, 'excerpt' => 'A full roundup of all the major reveals from the annual Xbox event, including a brand new fantasy IP and several surprising Game Pass additions.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The focus was heavily on first-party titles and showing off in-engine gameplay.']]]]],
            ['title' => 'Xbox Game Pass: Our Top 5 Must-Play Games Leaving the Service Soon', 'slug' => 'xbox-game-pass-leaving-soon', 'tags' => ['xbox', 'game-pass', 'feature', 'must-play'], 'categories' => ['Xbox', 'Features'], 'custom_fields' => ['author_name' => 'Anna Lee', 'read_time' => 4, 'excerpt' => 'Don\'t miss these critically acclaimed titles before they cycle out of the Game Pass library at the end of the month.'], 'content' => [['type' => 'list', 'data' => ['listType' => 'ul', 'items' => ['Control', 'Mass Effect Legendary Edition', 'Hades', 'Outer Wilds', 'Hitman Trilogy']]]]],
            ['title' => 'Why Microsoft\'s Acquisition Strategy Is Crucial for the Future of Xbox', 'slug' => 'microsoft-xbox-acquisition-strategy', 'tags' => ['xbox', 'microsoft', 'business', 'analysis'], 'categories' => ['Xbox', 'Business', 'Analysis'], 'custom_fields' => ['author_name' => 'Tom Viera', 'read_time' => 10, 'excerpt' => 'Analyzing how the purchase of major studios is aimed at securing an unassailable content library for Game Pass dominance.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The strategy is less about console sales and more about subscription ecosystem growth.']]]]],

            // 7. PC Gaming
            ['title' => 'Building the Ultimate Budget PC: Max Performance for Under $800', 'slug' => 'ultimate-budget-pc-build', 'tags' => ['pc-gaming', 'hardware', 'diy', 'guide'], 'categories' => ['PC Gaming', 'Hardware', 'Guides'], 'custom_fields' => ['author_name' => 'Samir Patel', 'read_time' => 9, 'excerpt' => 'Our step-by-step guide to selecting components that deliver 1080p high-refresh gaming without breaking the bank.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['We show you how to balance GPU and CPU power for maximum gaming value.']]]]],
            ['title' => 'The Modding Community: How Fan Creations Keep Classic PC Games Alive', 'slug' => 'pc-modding-community-legacy', 'tags' => ['pc-gaming', 'modding', 'community', 'classic-games'], 'categories' => ['PC Gaming', 'Features'], 'custom_fields' => ['author_name' => 'Mike Chen', 'read_time' => 7, 'excerpt' => 'Celebrating the dedicated fans who create new content, fix bugs, and overhaul the graphics of beloved PC titles years after their release.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['From high-res texture packs to total conversion mods, the community is a vital force.']]]]],
            ['title' => '5 Essential Tools Every PC Gamer Needs for Optimization and Streaming', 'slug' => 'essential-pc-gaming-tools', 'tags' => ['pc-gaming', 'software', 'guide', 'optimization'], 'categories' => ['PC Gaming', 'Guides'], 'custom_fields' => ['author_name' => 'Sarah Jones', 'read_time' => 5, 'excerpt' => 'Must-have software, from frame rate overlays to fan curve managers, that will enhance your gaming experience.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['These utilities can make a huge difference in performance consistency and visual quality.']]]]],

            // 8. Mobile
            ['title' => 'The Rise of Cloud Gaming on Mobile: Is This the End of Dedicated Handhelds?', 'slug' => 'cloud-gaming-mobile-vs-handhelds', 'tags' => ['mobile', 'cloud-gaming', 'analysis', 'hardware'], 'categories' => ['Mobile', 'Analysis', 'Hardware'], 'custom_fields' => ['author_name' => 'James Davies', 'read_time' => 8, 'excerpt' => 'With platforms like Xbox Cloud Gaming and GeForce NOW delivering console-quality experiences to smartphones, the portable console market is under threat.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Latency improvements and 5G networks are closing the gap between cloud and native gaming.']]]]],
            ['title' => 'Top 10 Mobile Games That Feel Like Full Console Experiences', 'slug' => 'top-10-console-quality-mobile-games', 'tags' => ['mobile', 'feature', 'top-10', 'rpg'], 'categories' => ['Mobile', 'Features'], 'custom_fields' => ['author_name' => 'Anna Lee', 'read_time' => 6, 'excerpt' => 'These mobile titles offer deep gameplay, compelling narratives, and incredible graphics that rival their console counterparts.'], 'content' => [['type' => 'list', 'data' => ['listType' => 'ol', 'items' => ['Genshin Impact', 'Call of Duty Mobile', 'Grid Autosport']]]]],
            ['title' => 'Genshin Impact vs. Honkai: Which Mobile RPG Dominates the Gacha Market?', 'slug' => 'genshin-vs-honkai-gacha-market', 'tags' => ['mobile', 'gacha', 'rpg', 'comparison'], 'categories' => ['Mobile', 'Comparison', 'Analysis'], 'custom_fields' => ['author_name' => 'Tom Viera', 'read_time' => 9, 'excerpt' => 'A deep dive into the business models, character design, and world-building of the two biggest mobile RPG juggernauts.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['While Genshin boasts a massive open world, Honkai delivers a tighter, more focused narrative experience.']]]]],
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