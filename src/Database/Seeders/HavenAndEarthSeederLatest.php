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
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\BlockParserService;

class HavenAndEarthSeederLatest extends Seeder
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
        $this->site = Site::where('slug', 'haven-hearth')->first();
        $this->createArticles();
    }


    // ... within the createArticles() method of DecanterSeeder.php
    private function createArticles(): void
    {
        $articles = [
// 1. Gardening Tips
//            ['title' => 'The No-Dig Method: How to Build Your Soil Health for a Bumper Harvest This Year', 'slug' => 'no-dig-gardening-soil-health', 'tags' => ['gardening-tips', 'soil', 'organic', 'vegetables'], 'categories' => ['Gardening Tips', 'Vegetable Gardening', 'Soil Health'], 'custom_fields' => ['author_name' => 'Evelyn Reed', 'read_time' => 7, 'excerpt' => 'Learn the simple, organic technique that minimizes soil disturbance and maximizes beneficial fungi and microbes for healthier plants.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['No-dig gardening is primarily about layering organic materials directly on top of the soil rather than turning it over.']]], ['type' => 'note', 'data' => ['title' => 'Key Principle', 'paragraphs' => ['Always keep the soil covered with a layer of mulch or compost.']]]]],
//            ['title' => 'Maximising Small Spaces: 7 Vertical Gardening Techniques for Urban Balconies', 'slug' => 'vertical-gardening-urban-balconies', 'tags' => ['gardening-tips', 'small-spaces', 'urban', 'diy'], 'categories' => ['Gardening Tips', 'Outdoor-Living', 'DIY'], 'custom_fields' => ['author_name' => 'Samir Patel', 'read_time' => 5, 'excerpt' => 'Creative and practical ways to grow herbs, vegetables, and flowers up your walls using repurposed materials and custom planters.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['From pallet gardens to pocket planters, vertical setups triple your growing area.']]], ['type' => 'list', 'data' => ['listType' => 'ul', 'items' => ['Pallet Planters', 'Stacking Crates', 'Gutter Gardens', 'Tiered Shelves']]]]],
//            ['title' => 'Pest Control: Identifying and Naturally Treating the 5 Most Common Garden Invaders', 'slug' => 'natural-garden-pest-control', 'tags' => ['gardening-tips', 'pest-control', 'organic', 'plant-care'], 'categories' => ['Gardening Tips', 'Plant-Care', 'Organic Gardening'], 'custom_fields' => ['author_name' => 'Chloe Davies', 'read_time' => 8, 'excerpt' => 'A guide to non-toxic, eco-friendly methods for dealing with aphids, slugs, powdery mildew, and more.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Neem oil and companion planting are your best allies against unwanted guests.']]]]],
//
//            // 2. Plant Care
//            ['title' => 'The Complete Guide to Overwintering Tender Plants: Saving Your Favourites', 'slug' => 'overwintering-tender-plants-guide', 'tags' => ['plant-care', 'seasonal', 'winter', 'shrubs'], 'categories' => ['Plant Care', 'Seasonal Guides'], 'custom_fields' => ['author_name' => 'Evelyn Reed', 'read_time' => 9, 'excerpt' => 'Detailed instructions on how to lift, prune, and store dahlias, geraniums, and other tender perennials to ensure they survive the cold months.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Proper ventilation and monitoring for pests are crucial during the storage period.']]]]],
//            ['title' => 'Why Are My Houseplants Dying? A Troubleshooting Guide to Common Humidity and Light Issues', 'slug' => 'houseplant-troubleshooting-guide', 'tags' => ['plant-care', 'houseplants', 'indoor-gardening', 'guide'], 'categories' => ['Plant Care', 'Indoor Gardening'], 'custom_fields' => ['author_name' => 'Mike Chen', 'read_time' => 6, 'excerpt' => 'Diagnose yellowing leaves, crispy edges, and leggy growth by understanding your plant\'s specific needs for light and moisture.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The biggest killer of houseplants is almost always overwatering or inconsistent light.']]], ['type' => 'table', 'data' => ['hasHeader' => true, 'rows' => [['Symptom', 'Likely Cause'], ['Yellowing Bottom Leaves', 'Overwatering'], ['Crispy Leaf Edges', 'Low Humidity'], ['Long, Stretched Stems', 'Insufficient Light']]]]],
//                ['title' => 'Watering Wisdom: The Essential Guide to Deep Watering vs. Frequent Sprinkling', 'slug' => 'watering-wisdom-deep-vs-sprinkling', 'tags' => ['plant-care', 'gardening-tips', 'guide', 'drought'], 'categories' => ['Plant Care', 'Gardening Tips'], 'custom_fields' => ['author_name' => 'Sarah Jones', 'read_time' => 5, 'excerpt' => 'Why deep, infrequent watering encourages strong, drought-resistant root systems over shallow, dependent ones.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Deep watering ensures the entire root zone is hydrated, reducing stress during dry spells.']]]]],

//                // 3. Outdoor Living
//                ['title' => 'Creating a Tranquil Garden Retreat: Design Ideas for a Meditation Space', 'slug' => 'tranquil-garden-retreat-design', 'tags' => ['outdoor-living', 'landscaping', 'design', 'wellbeing'], 'categories' => ['Outdoor Living', 'Landscaping Ideas'], 'custom_fields' => ['author_name' => 'James Davies', 'read_time' => 8, 'excerpt' => 'Using sensory plants, soothing water features, and careful lighting to transform a corner of your garden into a place of rest.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['The sound of running water is the most effective element for promoting calmness.']]]]],
//                ['title' => 'The Art of Al Fresco Dining: Upgrading Your Patio for Year-Round Entertainment', 'slug' => 'al-fresco-dining-patio-upgrade', 'tags' => ['outdoor-living', 'furniture', 'entertainment', 'diy'], 'categories' => ['Outdoor Living', 'Home Improvements'], 'custom_fields' => ['author_name' => 'Anna Lee', 'read_time' => 7, 'excerpt' => 'From fire pits and heaters to comfortable weatherproof furniture, turn your patio into a functional extension of your home.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['A covered pergola or retractable awning is essential for four-season use.']]]]],
//                ['title' => 'DIY Outdoor Kitchen: Building a Modular Setup on a Budget', 'slug' => 'diy-modular-outdoor-kitchen', 'tags' => ['outdoor-living', 'diy', 'home-improvements', 'cooking'], 'categories' => ['Outdoor Living', 'DIY', 'Home Improvements'], 'custom_fields' => ['author_name' => 'Tom Viera', 'read_time' => 10, 'excerpt' => 'A step-by-step guide for constructing a functional, stylish, and durable outdoor kitchen using ready-made cabinets and counter slabs.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Focus on utility and weather resistance for the longest lifespan.']]]]],

            // 4. Landscaping Ideas
//                ['title' => 'Beyond the Lawn: Beautiful, Low-Maintenance Alternatives to Traditional Grass', 'slug' => 'low-maintenance-lawn-alternatives', 'tags' => ['landscaping-ideas', 'low-maintenance', 'drought-tolerant', 'design'], 'categories' => ['Landscaping Ideas', 'Gardening Tips'], 'custom_fields' => ['author_name' => 'Samir Patel', 'read_time' => 6, 'excerpt' => 'Explore options like microclover, thyme lawns, and rock gardens that require less water and far less mowing.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Replacing turf with groundcover dramatically cuts down on resource usage.']]]]],
//                ['title' => 'Designing with Water: How to Incorporate Ponds and Fountains into a Small Garden', 'slug' => 'designing-with-water-small-garden', 'tags' => ['landscaping-ideas', 'design', 'water-feature', 'wildlife'], 'categories' => ['Landscaping Ideas', 'Outdoor Living'], 'custom_fields' => ['author_name' => 'Evelyn Reed', 'read_time' => 8, 'excerpt' => 'Tips for adding the calming element of water, even in confined spaces, to attract beneficial wildlife and add visual interest.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['A simple bubbling fountain can provide the same sensory benefits as a large pond.']]]]],
//                ['title' => 'Hardscaping Hacks: Using Gravel and Stone to Define Garden Zones', 'slug' => 'hardscaping-gravel-stone-zones', 'tags' => ['landscaping-ideas', 'hardscaping', 'diy', 'design'], 'categories' => ['Landscaping Ideas', 'DIY'], 'custom_fields' => ['author_name' => 'Mike Chen', 'read_time' => 5, 'excerpt' => 'A budget-friendly guide to using different textures and colors of stone and gravel to delineate pathways, seating areas, and planting beds.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Proper ground preparation with weed fabric is essential for gravel longevity.']]]]],

            // 5. Seasonal Guides
//                ['title' => 'The Autumn Garden Checklist: Planting Bulbs and Preparing Beds for Winter', 'slug' => 'autumn-garden-checklist', 'tags' => ['seasonal-guides', 'autumn', 'bulbs', 'preparation'], 'categories' => ['Seasonal Guides', 'Gardening Tips'], 'custom_fields' => ['author_name' => 'Sarah Jones', 'read_time' => 7, 'excerpt' => 'The essential tasks to tackle in the fall to ensure a vibrant bloom next spring and protect your soil through the winter.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Focus on soil enrichment and planting alliums and daffodils before the first hard frost.']]]]],
//                ['title' => 'Spring Awakening: Your 8-Week Guide to Starting Seeds Indoors for Summer Blooms', 'slug' => 'spring-seed-starting-guide', 'tags' => ['seasonal-guides', 'spring', 'seeds', 'guide'], 'categories' => ['Seasonal Guides', 'Gardening Tips'], 'custom_fields' => ['author_name' => 'James Davies', 'read_time' => 9, 'excerpt' => 'A week-by-week timeline for success, from choosing the right grow lights to hardening off seedlings before transplanting.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Starting seeds indoors gives you a head start, especially for plants with long maturity periods like tomatoes and peppers.']]]]],
//                ['title' => 'The Summer Drought: Essential Plant Survival Tips for Heatwaves and Water Restrictions', 'slug' => 'summer-drought-survival-tips', 'tags' => ['seasonal-guides', 'summer', 'drought', 'plant-care'], 'categories' => ['Seasonal Guides', 'Plant Care'], 'custom_fields' => ['author_name' => 'Anna Lee', 'read_time' => 6, 'excerpt' => 'Watering deeply, mulching heavily, and understanding which plants handle heat best are key to saving your garden in the hottest months.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Apply at least a 3-inch layer of organic mulch to retain soil moisture.']]]]],

            // 6. Home Improvements
            ['title' => '5 Weekend Upgrades That Will Instantly Boost Your Home\'s Curb Appeal', 'slug' => 'weekend-curb-appeal-upgrades', 'tags' => ['home-improvements', 'diy', 'exterior', 'budget'], 'categories' => ['Home Improvements', 'DIY'], 'custom_fields' => ['author_name' => 'Tom Viera', 'read_time' => 5, 'excerpt' => 'Simple, high-impact projects like painting your front door, upgrading house numbers, and refreshing your porch lighting.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['A new coat of paint on the trim can make the whole house look professionally cleaned.']]]]],
            ['title' => 'The Essential Tools and Safety Gear for Every DIY Home Renovation Project', 'slug' => 'essential-diy-tools-safety', 'tags' => ['home-improvements', 'diy', 'tools', 'safety'], 'categories' => ['Home Improvements', 'DIY'], 'custom_fields' => ['author_name' => 'Samir Patel', 'read_time' => 7, 'excerpt' => 'A checklist of power tools, hand tools, and necessary safety equipment (gloves, goggles, respirators) before you start any major project.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Never skimp on the quality of your safety glasses or respirator.']]]]],
            ['title' => 'Energy Efficiency: Simple Home Improvements to Slash Your Heating Bill', 'slug' => 'energy-efficiency-heating-bill', 'tags' => ['home-improvements', 'energy-saving', 'insulation', 'budget'], 'categories' => ['Home Improvements', 'Tips'], 'custom_fields' => ['author_name' => 'Evelyn Reed', 'read_time' => 8, 'excerpt' => 'Easy fixes like weatherstripping doors, sealing air leaks around windows, and upgrading to a smart thermostat for maximum savings.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Air leaks can account for up to 30% of a home\'s heating and cooling losses.']]]]],

            // 7. Furniture Projects
            ['title' => 'Upcycling Challenge: Turning Old Wooden Pallets into a Stylish Outdoor Sofa', 'slug' => 'upcycle-pallets-outdoor-sofa', 'tags' => ['furniture-projects', 'diy', 'upcycling', 'outdoor-living'], 'categories' => ['Furniture Projects', 'DIY', 'Outdoor Living'], 'custom_fields' => ['author_name' => 'Mike Chen', 'read_time' => 10, 'excerpt' => 'A full tutorial on breaking down and rebuilding discarded wooden pallets into a durable, comfortable, and modern garden seating area.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Ensure your pallets are \'HT\' (Heat Treated) and not \'MB\' (Methyl Bromide) stamped.']]]]],
            ['title' => 'Beginner\'s Guide to Refinishing Antiques: Reviving a Tired Dresser', 'slug' => 'refinishing-antiques-dresser-guide', 'tags' => ['furniture-projects', 'diy', 'antiques', 'restoration'], 'categories' => ['Furniture Projects', 'DIY', 'Home Improvements'], 'custom_fields' => ['author_name' => 'Sarah Jones', 'read_time' => 12, 'excerpt' => 'Step-by-step guidance on sanding, staining, and sealing old furniture to restore its natural beauty without damaging its character.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['Proper preparation is 90% of a successful refinishing job.']]]]],
            ['title' => 'Build Your Own: A Step-by-Step Plan for a Modern, Mid-Century Planter Box Stand', 'slug' => 'diy-mid-century-planter-stand', 'tags' => ['furniture-projects', 'diy', 'houseplants', 'design'], 'categories' => ['Furniture Projects', 'DIY', 'Indoor Gardening'], 'custom_fields' => ['author_name' => 'James Davies', 'read_time' => 6, 'excerpt' => 'A simple woodworking project to create a stylish, elevating stand for your indoor plants that matches classic mid-century modern aesthetics.'], 'content' => [['type' => 'text', 'data' => ['paragraphs' => ['This project requires only basic tools and materials from your local hardware store.']]]]],
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