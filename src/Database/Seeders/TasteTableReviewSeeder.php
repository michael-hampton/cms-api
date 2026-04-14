<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\Category;
use App\Models\Page;
use App\Models\PageCategory;
use App\Models\PageTag;
use App\Models\Site;
use App\Models\Tag;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class TasteTableReviewSeeder extends Seeder
{
    private $pageRepository;
    private $blockRepository;
    private $tagRepository;
    private $categoryRepository;
    private $blockParserService;
    private \App\Models\Model $site;

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
        $this->site = Site::find(5);

        if (!$this->site) {
            echo "Taste & Table site not found.\n";
            return;
        }

        $reviewPages = $this->createReviewPages();
        $this->addReviewSectionToHomepage($reviewPages);
    }

    private function createReviewPages(): array
    {
        $reviews = [
            [
                'title' => 'Le Bernardin NYC Review: Seafood Excellence Redefined',
                'slug' => 'le-bernardin-nyc-review',
                'image' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=800',
                'restaurant' => 'Le Bernardin',
                'rating' => 5.0,
                'cuisine' => 'French Seafood',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Le Bernardin continues to set the standard for fine dining seafood in New York City. Chef Eric Ripert\'s meticulous approach to seafood preparation results in dishes that are both technically perfect and deeply satisfying. The tasting menu showcases the finest ingredients sourced from around the world, each prepared with reverence and creativity.',
                            'The dining room exudes understated elegance, with impeccable service that feels attentive without being intrusive. Each course is presented beautifully, demonstrating the kitchen\'s commitment to both visual artistry and culinary excellence. Wine pairings are thoughtfully curated to complement the delicate flavors of the seafood without overwhelming them.',
                            'Standout dishes include the barely cooked hamachi with foie gras, the butter-poached lobster, and the signature barely cooked tuna. Each bite reveals layers of flavor and texture, showcasing ingredients at their peak. The progression of courses is masterfully paced, allowing diners to appreciate each dish fully before moving to the next.',
                            'Overall, Le Bernardin delivers an unforgettable fine dining experience that justifies its reputation as one of the world\'s premier seafood restaurants. It\'s a must-visit destination for serious food lovers seeking culinary perfection.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Osteria Francescana Review: A Journey Through Italian Innovation',
                'slug' => 'osteria-francescana-modena-review',
                'image' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=800',
                'restaurant' => 'Osteria Francescana',
                'rating' => 5.0,
                'cuisine' => 'Modern Italian',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Chef Massimo Bottura\'s Osteria Francescana is a temple of culinary innovation that honors Italian tradition while fearlessly pushing boundaries. The restaurant transforms classic Italian dishes into contemporary masterpieces, each plate telling a story about Italian culture, history, and identity. The tasting menu is a carefully orchestrated journey through Bottura\'s creative vision.',
                            'The dining experience begins the moment you enter the elegant townhouse setting in Modena. Service is warm, knowledgeable, and genuinely passionate about the food being served. Each dish is introduced with context and storytelling that enhances appreciation for the artistry on the plate. The wine selection features exceptional Italian bottles alongside international selections.',
                            'Signature dishes like "Oops! I Dropped the Lemon Tart" and "The Crunchy Part of the Lasagna" demonstrate Bottura\'s playful approach to deconstruction and reimagination. Technical execution is flawless, with flavors that are bold yet balanced, familiar yet surprising. The attention to detail in every element, from bread service to petit fours, is extraordinary.',
                            'In conclusion, Osteria Francescana offers one of the world\'s most memorable dining experiences. It\'s a pilgrimage destination for food enthusiasts seeking to understand how tradition and innovation can coexist beautifully on a single plate.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Noma Copenhagen Review: Redefining Nordic Cuisine',
                'slug' => 'noma-copenhagen-review',
                'image' => 'https://images.unsplash.com/photo-1551218808-94e220e084d2?w=800',
                'restaurant' => 'Noma',
                'rating' => 4.9,
                'cuisine' => 'New Nordic',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'René Redzepi\'s Noma has revolutionized our understanding of Nordic cuisine and fine dining altogether. The restaurant\'s commitment to hyper-local, seasonal ingredients results in menus that change completely throughout the year, offering vegetable, seafood, and game seasons. Each visit to Noma is a unique exploration of what the Nordic landscape has to offer at that precise moment.',
                            'The setting combines rustic charm with contemporary design, creating an atmosphere that feels both special and unpretentious. The service team exhibits deep knowledge about every ingredient, often foraging items themselves and explaining the provenance and preparation methods. The open kitchen allows diners to witness the precision and teamwork that goes into each dish.',
                            'Dishes showcase ingredients that many fine dining establishments would overlook: moss, bark, wild herbs, and ancient grains all find their way onto the menu. The innovation lies not in molecular techniques but in revealing the inherent beauty and flavor of these overlooked ingredients. Each course is a revelation, challenging preconceptions about what constitutes fine dining food.',
                            'Overall, Noma delivers an experience that transcends traditional restaurant dining. It\'s an education in terroir, seasonality, and the possibilities of ingredient-focused cooking. While challenging at times, it rewards adventurous diners with memories that last a lifetime.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Gaggan Bangkok Review: Progressive Indian Cuisine',
                'slug' => 'gaggan-bangkok-review',
                'image' => 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=800',
                'restaurant' => 'Gaggan',
                'rating' => 4.8,
                'cuisine' => 'Progressive Indian',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Chef Gaggan Anand has created something truly unique in Bangkok: a restaurant that takes Indian flavors and techniques into completely unexpected territory. The tasting menu is presented via emojis rather than descriptions, adding an element of playful surprise to each course. This whimsical approach belies the serious technical skill and creativity behind every dish.',
                            'The dining experience feels more like attending a dinner party than visiting a formal restaurant. Gaggan himself often interacts with guests, explaining his philosophy and the inspiration behind dishes. The atmosphere is energetic and unconventional, with loud music and a relaxed vibe that contrasts sharply with typical fine dining stuffiness.',
                            'Dishes deconstruct and reimagine Indian classics using modern techniques and unexpected presentations. Yogurt explosions, edible silverware, and deconstructed samosas showcase technical prowess while maintaining the soul and flavor profiles of traditional Indian cuisine. Each bite is intensely flavorful, balancing spice, acid, richness, and texture masterfully.',
                            'In summary, Gaggan offers a thrilling dining adventure that challenges conventions while celebrating Indian culinary heritage. It\'s perfect for diners seeking innovation, bold flavors, and an experience that doesn\'t take itself too seriously despite its world-class execution.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Eleven Madison Park Review: Plant-Based Fine Dining Excellence',
                'slug' => 'eleven-madison-park-review',
                'image' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=800',
                'restaurant' => 'Eleven Madison Park',
                'rating' => 4.7,
                'cuisine' => 'Plant-Based Fine Dining',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Chef Daniel Humm\'s bold decision to transition Eleven Madison Park to an entirely plant-based menu has resulted in one of the most sophisticated and satisfying vegetable-focused tasting menus in the world. The restaurant proves that fine dining can be completely plant-based without sacrificing complexity, satisfaction, or the sense of luxury that defines the genre.',
                            'The Art Deco dining room overlooking Madison Square Park provides a stunning backdrop for the meal. Service is polished and professional, with staff expertly explaining each dish\'s components and preparation methods. The beverage program includes creative non-alcoholic pairings alongside traditional wine options, all selected to complement the vegetable-forward menu.',
                            'Dishes showcase vegetables and grains in ways that highlight their natural flavors while adding layers of complexity through technique and complementary ingredients. Umami-rich preparations, creative textures, and beautiful presentations ensure that no one leaves the table feeling deprived. The kitchen demonstrates remarkable skill in coaxing maximum flavor from plant-based ingredients.',
                            'Overall, Eleven Madison Park has successfully reinvented itself for a new era while maintaining the excellence that earned its reputation. It\'s essential dining for anyone curious about the future of fine dining and the possibilities of plant-based cuisine.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Central Lima Review: Altitude-Based Peruvian Cuisine',
                'slug' => 'central-lima-review',
                'image' => 'https://images.unsplash.com/photo-1559339352-11d035aa65de?w=800',
                'restaurant' => 'Central',
                'rating' => 4.9,
                'cuisine' => 'Peruvian',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Virgilio Martínez\'s Central offers a unique conceptual framework: a tasting menu organized by altitude, showcasing ingredients from different ecosystems across Peru. This approach educates diners about the incredible biodiversity of Peru while delivering a meal that is both intellectually stimulating and deeply delicious. Each course represents a different elevation, from below sea level to high Andes peaks.',
                            'The restaurant\'s sleek, modern design complements the innovative cuisine without distracting from it. Service is informative and engaging, with staff sharing knowledge about ingredients, many of which will be completely unfamiliar to international visitors. The presentation of each dish is stunning, often incorporating natural elements that reference the ecosystem being represented.',
                            'Flavors are bold and distinctive, showcasing ingredients like Amazonian cacao, Andean tubers, and coastal seafood in preparations that honor their origins while applying modern technique. The progression through different altitudes creates a natural narrative arc to the meal. Each course reveals something new about Peru\'s culinary landscape and agricultural heritage.',
                            'In conclusion, Central delivers one of the most original and memorable fine dining experiences available anywhere. It successfully combines education, innovation, and exceptional flavor into a cohesive whole that celebrates Peru\'s remarkable biodiversity and culinary traditions.'
                        ]
                    ]
                ]
            ]
        ];

        $pages = [];
        foreach ($reviews as $reviewData) {
            $page = Page::create([
                'title' => $reviewData['title'],
                'slug' => $reviewData['slug'],
                'status' => 'published',
                'page_type' => 'content',
                'meta_title' => $reviewData['title'] . ' - Vogue Noir',
                'site_id' => 9,
            ]);

            $category = Category::where('slug', strtolower($reviewData['restaurant']))->where('site_id', 9)->first();
            if ($category) {
                PageCategory::create(['page_id' => $page->id, 'category_id' => $category->id]);
            }

            $tag = Tag::where('slug', $reviewData['cuisine'])->where('site_id', 9)->first();
            if ($tag) {
                PageTag::create(['page_id' => $page->id, 'tag_id' => $tag->id]);
            }

            $blocks = [
                [
                    'type' => 'image',
                    'data' => [
                        'src' => $reviewData['image'],
                        'alt' => $reviewData['cuisine'] ?? $reviewData['restaurant'] ?? '',
                        'layout' => 'full',
                        'alignment' => 'fullscreen'
                    ]
                ],
                [
                    'type' => 'heading',
                    'data' => ['text' => $reviewData['title'], 'level' => 1]
                ],
                [
                    'type' => 'award',
                    'data' => [
                        'subcategory' => 'Expert Review',
                        'productName' => ($reviewData['restaurant'] ?? '') . ' by ' . $reviewData['cuisine'],
                        'winner' => true,
                        'rating' => $reviewData['rating'],
                    ]
                ],
                [
                    'type' => 'text',
                    'data' => $reviewData['review']['data']
                ]
            ];

            $this->createBlocksForPage($page->id, $blocks);
            $pages[] = ['page' => $page, 'data' => $reviewData];
        }

        return $pages;
    }

    private function createBlocksForPage(int $pageId, array $blocks): void
    {
        foreach ($blocks as $blockData) {
            $this->blockRepository->create([
                'page_id' => $pageId,
                'type' => $blockData['type'],
                'data' => json_encode($blockData['data']),
                'order' => $blockData['order'] ?? 1
            ]);
        }
    }

    private function addReviewSectionToHomepage(array $reviewPages): void
    {
        $homepage = Page::where('slug', 'home')->where('site_id', 6)->first();
        if (!$homepage) return;

        echo "Adding reviews to homepage.\n";

        $reviewItems = [];
        foreach ($reviewPages as $item) {
            $page = $item['page'];
            $data = $item['data'];

            $reviewItems[] = [
                'title' => $page->title,
                'slug' => $page->slug,
                'image' => ['src' => $data['image'], 'alt' => $data['restaurant'] ?? $data['cuisine'] ?? ''],
                'badge' => ['text' => '⭐ ' . $data['rating'] . '/5', 'color' => 'success'],
                'meta' => [
                    'cuisine' => $data['cuisine'] ?? '',
                    'restaurant' => $data['restaurant'] ?? '',
                    'readTime' => '10 min read'
                ]
            ];
        }

        $reviewBlock = [
            'type' => 'page_grid',
            'data' => [
                'title' => 'Latest Reviews',
                'subtitle' => 'In-depth expert reviews of the latest tech products',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'pages' => $reviewItems
            ]
        ];

        // Get current max order
        $maxOrder = $homepage->blocks()->max('order') ?? 0;

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => $reviewBlock['type'],
            'data' => json_encode($reviewBlock['data']),
            'order' => $maxOrder + 1
        ]);
    }
}