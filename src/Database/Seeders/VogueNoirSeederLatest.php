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

class VogueNoirSeederLatest extends Seeder
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
        $this->site = Site::where('slug', 'vogue-noir')->first();
        $this->createArticles();
    }


    // ... within the createArticles() method of DecanterSeeder.php
    private function createArticles(): void
    {
        $articles = [
            [
                'title' => 'The Iconoclast: A Candid Conversation with Designer Elara Vance on Her New Collection',
                'slug' => 'elara-vance-designer-interview',
                'tags' => ['interview', 'designer', 'haute-couture', 'fashion-week'],
                'categories' => ['Interviews', 'Designers', 'Couture'],
                'custom_fields' => ['author_name' => 'Vivienne Reed', 'read_time' => 12, 'excerpt' => 'Elara Vance discusses her revolutionary approach to sustainable luxury and the surprising inspirations behind her latest collection.'],
                'content' => [
                    ['type' => 'hero', 'data' => ['title' => 'The Art of the Unconventional', 'subtitle' => 'Elara Vance on her latest collection', 'backgroundImage' => 'https://images.unsplash.com/photo-1543163522-8758801d9f0f?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80']],
                    ['type' => 'quote', 'data' => ['text' => 'True luxury isn’t about excess; it’s about enduring quality and the story of the hands that made it.', 'attribution' => 'Elara Vance']],
                    ['type' => 'text', 'data' => ['paragraphs' => ['Vance, known for her sharp tailoring and unexpected material choices, revealed that the collection was inspired by Brutalist architecture and the erosion of concrete. It’s a bold, uncompromising vision that challenges the ephemeral nature of fashion trends.', 'We sat down with her in her Parisian studio to discuss the delicate balance between creative freedom and market demands.']]],
                    ['type' => 'gallery', 'data' => ['layout' => 'carousel', 'slides' => [['title' => 'Look 1', 'image' => ['src' => 'https://images.unsplash.com/photo-1574737233267-33230a6c6a28?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80']], ['title' => 'Look 2', 'image' => ['src' => 'https://images.unsplash.com/photo-1546271923-d3a90823c914?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80']]]]]
                ]
            ],
            // 2. Interviews: Photographer
            [
                'title' => 'Behind the Lens: Exclusive Interview with Photographer Alistair Kwon on Capturing the Avant-Garde',
                'slug' => 'alistair-kwon-photographer-interview',
                'tags' => ['interview', 'photography', 'art', 'editorial'],
                'categories' => ['Interviews', 'Photography', 'Art'],
                'custom_fields' => ['author_name' => 'Julian Hayes', 'read_time' => 8, 'excerpt' => 'Alistair Kwon, the master of stark contrast and narrative portraiture, shares his process for crafting emotionally complex editorial imagery.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The Power of Negative Space', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['Kwon’s work is instantly recognizable: dramatic shadows, minimalist staging, and a deep emotional intensity in his subjects. He rejects the notion of "pretty pictures," aiming instead for enduring artistic statements. In this exclusive, he details his transition from street photography to high fashion.', 'He emphasizes the importance of collaboration, viewing the model not as a canvas, but as a co-creator of the image.']]],
                    ['type' => 'table', 'data' => ['hasHeader' => true, 'rows' => [['Key Influence', 'Style Impact'], ['Diane Arbus', 'Raw, Unflinching Subjectivity'], ['Helmut Newton', 'High-Contrast Eroticism'], ['Hiroshi Sugimoto', 'Long Exposure & Minimalism']]]],
                    ['type' => 'note', 'data' => ['title' => 'Kwon’s Advice', 'paragraphs' => ['"The best photo is the one that tells you a secret about the person in it. Don’t just look at the clothes; look at the soul."']]],
                ]
            ],
            // 3. Interviews: Perfumer
            [
                'title' => 'Inside the Atelier: Master Perfumer Celeste Dubois on the Art of Scent and Memory',
                'slug' => 'celeste-dubois-perfumer-interview',
                'tags' => ['interview', 'beauty', 'fragrance', 'luxury', 'atelier'],
                'categories' => ['Interviews', 'Beauty', 'Fragrance'],
                'custom_fields' => ['author_name' => 'Eliza Viera', 'read_time' => 9, 'excerpt' => 'Celeste Dubois, creator of the most sought-after niche fragrances, opens her laboratory to discuss the science and poetry of perfume making.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'Composing a Scent', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['A master perfumer, or "Nose," can distinguish between thousands of individual notes. Dubois describes her work as composing a symphony, where the top notes are the bright opening, the heart notes are the enduring melody, and the base notes are the lingering harmony.', 'Her latest fragrance, *Nuit Foncée*, captures the scent of rain on concrete and aged leather, a deliberate move away from traditional florals.']]],
                    ['type' => 'list', 'data' => ['listType' => 'ul', 'items' => ['**Top Notes (The Introduction):** Bergamot, Pink Pepper', '**Heart Notes (The Core):** Orris Root, Aged Leather', '**Base Notes (The Lingering Dry Down):** Vetiver, Cedarwood, Patchouli']]],
                    ['type' => 'product', 'data' => ['name' => 'Nuit Foncée Eau de Parfum', 'brand' => 'Celeste Dubois', 'productName' => 'Luxury Fragrance', 'price' => 295.00, 'currency' => '£', 'description' => 'A unisex fragrance of rain, leather, and dark woods. Exclusive to Vogue Noir readers.', 'linkText' => 'Discover the Scent', 'image' => ['src' => 'https://images.unsplash.com/photo-1543163522-8758801d9f0f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80']]],
                ]
            ],
            [
                'title' => 'The Architectural Heel: Sculptural Footwear Dominating Paris Catwalks',
                'slug' => 'architectural-heel-paris-runway-shoes',
                'tags' => ['runway', 'shoes', 'luxury', 'design', 'catwalk'],
                'categories' => ['Runway Shoes', 'Luxury', 'Trends'],
                'custom_fields' => ['author_name' => 'Eliza Moreau', 'read_time' => 8, 'excerpt' => 'Heels are no longer just a support—they are the centerpiece. We examine the innovative, gravity-defying designs from the new season.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'Form Meets Function', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['Designers are commissioning sculptors to create bespoke heel structures from materials like recycled carbon fiber and bio-resin. This blurs the line between high fashion and fine art, justifying the luxury price tag.', 'The most notable trend features cantilevered shapes and negative space, challenging traditional stability norms.']]],
                    ['type' => 'list', 'data' => ['listType' => 'ul', 'items' => ['Cantilevered shapes and abstract forms.', 'The use of unconventional materials like chrome and bio-resin.', 'A focus on vibrant, monochromatic colorways.']]]
                ]
            ],
            [
                'title' => 'Deconstructed Denim: The Unexpected Fabric Trend Taking Over City Streets',
                'slug' => 'deconstructed-denim-street-style-trend',
                'tags' => ['street-style', 'trends', 'denim', 'diy', 'fashion-week'],
                'categories' => ['Street Style', 'Trends', 'Casual'],
                'custom_fields' => ['author_name' => 'Marcus Bell', 'read_time' => 7, 'excerpt' => 'Forget pristine jeans; the current zeitgeist favors patchwork, exposed seams, and exaggerated distressing. It’s a rebellion against fast fashion’s perfection.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The Art of Imperfection', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['This movement, popularized by emerging designers, celebrates the history and texture of the fabric. Raw hems and asymmetrical paneling turn standard denim jackets into statement pieces.', 'We show you how to thrift and DIY your own deconstructed look.']]],
                    ['type' => 'quote', 'data' => ['text' => 'The beauty of deconstructed denim is that no two pieces are exactly alike—it’s the ultimate form of personalized fashion.', 'attribution' => 'Street Style Photographer']]
                ]
            ],
            [
                'title' => 'Investment Pieces: Why Second-Hand Luxury is the Smartest Sustainable Choice',
                'slug' => 'second-hand-luxury-sustainable-choice',
                'tags' => ['sustainable-fashion', 'luxury', 'vintage', 'investing', 'resale'],
                'categories' => ['Sustainable Fashion', 'Luxury', 'Opinion'],
                'custom_fields' => ['author_name' => 'Chloe Davies', 'read_time' => 9, 'excerpt' => 'Beyond the environmental benefit, acquiring pre-owned designer goods offers unparalleled value retention and often, a better carbon footprint.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The Resale Economy', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The market for authenticated pre-owned luxury has exploded, driven by Gen Z’s focus on circular fashion and the desire for discontinued, archival pieces. Buying luxury second-hand is a financial investment, not just a purchase.', 'We provide a guide to the most trustworthy authentication platforms and the brands that retain the highest resale value.']]],
                    ['type' => 'product', 'data' => ['name' => 'Vintage Calfskin Flap Bag', 'brand' => 'Chanel (Pre-Owned)', 'productName' => 'Investment Piece', 'price' => 7500.00, 'currency' => '$', 'description' => 'A prime example of a luxury item with excellent value retention.', 'linkText' => 'Shop Vintage', 'image' => ['src' => 'https://images.unsplash.com/photo-1596765792070-52e698188151?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80']]]
                ]
            ],
            [
                'title' => 'Matte vs. Dewy: The New Era of Skin-First Foundation Reviews',
                'slug' => 'matte-vs-dewy-foundation-guide',
                'tags' => ['makeup', 'skincare', 'beauty-review', 'foundation'],
                'categories' => ['Makeup', 'Reviews'],
                'custom_fields' => ['author_name' => 'Sara Khan', 'read_time' => 6, 'excerpt' => 'Foundations are now skincare hybrids. We tested the top formulas to find out which finish truly enhances your natural complexion and longevity.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'Ingredients as Key', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The latest formulas are infused with hyaluronic acid, ceramides, and SPF. The choice between matte and dewy now hinges more on skin type and climate than on current trends.', 'Matte finishes are ideal for humid environments and oily skin, while dewy formulas shine in dry conditions and on mature skin.']]],
                    ['type' => 'stats', 'data' => ['title' => 'Top Rated', 'stats' => [['number' => '9.2/10', 'label' => 'Dewy Formula', 'icon' => '✨'], ['number' => '9.5/10', 'label' => 'Matte Formula', 'icon' => '💎']]]]
                ]
            ],
            [
                'title' => 'Dermatologist Interview: Debunking the Biggest TikTok Skincare Myths',
                'slug' => 'dermatologist-interview-tiktok-myths',
                'tags' => ['skincare', 'interview', 'trends', 'health', 'dermatology'],
                'categories' => ['Skincare', 'Interviews', 'Trends'],
                'custom_fields' => ['author_name' => 'Dr. Anya Sharma', 'read_time' => 10, 'excerpt' => 'From skin cycling to slugging, we ask a board-certified dermatologist to separate viral fads from proven, effective skincare practices.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'Fact vs. Fiction', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['Dr. Sharma warns that DIY remedies and over-exfoliation are causing an epidemic of damaged skin barriers. Consistency with basic steps (cleanse, treat, moisturize, SPF) remains the foundation of healthy skin.', 'She explains that while some trends like "slugging" (sealing moisture with an occlusive) can be beneficial for very dry skin, they are not suitable for acne-prone individuals.']]],
                    ['type' => 'note', 'data' => ['title' => 'The Golden Rule', 'paragraphs' => ['Always patch test new products and introduce active ingredients slowly. Consult a professional before starting any intensive routine.']]]
                ]
            ],
            [
                'title' => 'The 5 Micro-Trends from TikTok That Actually Made It to Fashion Week',
                'slug' => 'tiktok-micro-trends-fashion-week',
                'tags' => ['trends', 'street-style', 'social-media', 'youth-culture'],
                'categories' => ['Trends', 'Street Style', 'Culture'],
                'custom_fields' => ['author_name' => 'Julian Hayes', 'read_time' => 5, 'excerpt' => 'From the ' . '“clean girl”' . ' aesthetic to ' . '“dopamine dressing”' . ', we track the journey of viral online trends into the collections of major fashion houses.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'Algorithm to Atelier', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['Social media has fundamentally changed the trend cycle, making it faster and more democratic. Designers are now actively looking to youth platforms for inspiration, blurring the line between high and low fashion.', 'The key takeaway is speed: a trend’s lifecycle is measured in months, not seasons.']]]
                ]
            ],
            [
                'title' => 'From Farm to Closet: The Rise of Regenerative Textiles in High Fashion',
                'slug' => 'regenerative-textiles-high-fashion',
                'tags' => ['sustainable-fashion', 'textiles', 'eco-friendly', 'innovation', 'luxury'],
                'categories' => ['Sustainable', 'Innovation', 'Luxury'],
                'custom_fields' => ['author_name' => 'Anna Lee', 'read_time' => 11, 'excerpt' => 'Beyond just being "less bad," regenerative farming practices are creating materials that actively improve soil health and biodiversity. This is the future of fabric.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'A Positive Footprint', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['Regenerative cotton and wool are becoming the gold standard for conscious luxury brands. This approach treats the raw material supply chain as an ecosystem, not just a commodity source.', 'The process yields a superior quality fiber with unique textural properties.']]]
                ]
            ],
            [
                'title' => 'The Minimalist Routine: 3 Steps to Achieving the ' . '“No Makeup”' . ' Look',
                'slug' => 'minimalist-skincare-makeup-routine',
                'tags' => ['skincare', 'makeup', 'routine', 'minimalism', 'tutorial'],
                'categories' => ['Skincare', 'Makeup', 'Tutorial'],
                'custom_fields' => ['author_name' => 'Maya J.', 'read_time' => 4, 'excerpt' => 'It’s about prioritizing skin health so makeup can take a backseat. We distill the perfect three-product approach for luminous, effortless radiance.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The Core Three', 'level' => 2]],
                    ['type' => 'list', 'data' => ['listType' => 'ol', 'items' => ['**Vitamin C Serum:** For antioxidant protection and brightening.', '**Tinted Moisturizer/SPF Hybrid:** To unify tone and provide sun protection.', '**Cream Blush/Lip Stain:** To add a natural flush of color.']]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The key is blending and building. Using multi-purpose products cuts down on clutter and simplifies your morning.']]]
                ]
            ],
            [
                'title' => 'Boot Report: The Enduring Return of the Over-The-Knee Silhouette',
                'slug' => 'over-the-knee-boot-report',
                'tags' => ['runway', 'shoes', 'trends', 'style-guide', 'fall-winter'],
                'categories' => ['Runway Shoes', 'Trends', 'Style Guide'],
                'custom_fields' => ['author_name' => 'Daniel Cho', 'read_time' => 6, 'excerpt' => 'Taller, tighter, and unapologetically dramatic—the over-the-knee boot is back and being styled in surprising new ways this season.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'Styling the Statement Boot', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['This season’s version is less about suede and more about slick, patent leather or second-skin stretch materials. They are being paired with oversized knitwear and micro-mini dresses for a high-contrast look.', 'Finding the right fit in the thigh area is crucial for comfort and wearability.']]]
                ]
            ],
            [
                'title' => 'Beyond the Bag: Why Designer Headwear is the Ultimate Status Symbol',
                'slug' => 'designer-headwear-status-symbol',
                'tags' => ['street-style', 'luxury', 'accessories', 'trends', 'hats'],
                'categories' => ['Street Style', 'Luxury', 'Accessories'],
                'custom_fields' => ['author_name' => 'Tom Viera', 'read_time' => 5, 'excerpt' => 'Bucket hats, logo baseball caps, and intricate headbands—the most visible and immediate signifier of luxury is now worn on the head.'],
                'content' => [
                    ['type' => 'heading', 'data' => ['text' => 'The Top-Down Approach to Branding', 'level' => 2]],
                    ['type' => 'text', 'data' => ['paragraphs' => ['A logo accessory placed prominently on the head immediately elevates a simple outfit. This trend is driven by the desire for recognizable, shareable style moments on social media.']]],
                    ['type' => 'note', 'data' => ['title' => 'Top Picks', 'paragraphs' => ['The knitted balaclava (for winter), the embroidered baseball cap, and the silk headscarf (for transitional weather).']]]
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