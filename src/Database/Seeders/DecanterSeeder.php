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
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class DecanterSeeder extends Seeder
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
        $this->createHomepage();
        $this->createArticles();
        $this->createAboutPage();
        $this->createContactPage();
    }

    private function createSite(): void
    {
        $this->site = Site::find(3);
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
            'featured', 'trending', 'editors-choice', 'wine-of-the-week',
            'bordeaux', 'burgundy', 'champagne', 'napa-valley', 'tuscany',
            'red-wine', 'white-wine', 'sparkling', 'rosé', 'dessert-wine',
            'cabernet-sauvignon', 'pinot-noir', 'chardonnay', 'sauvignon-blanc',
            'wine-tasting', 'wine-pairing', 'vintage', 'organic', 'biodynamic',
            'wine-investment', 'wine-collecting', 'wine-storage',
            'beginner-guide', 'expert-advice', 'wine-travel', 'vineyard-visit',
            'wine-awards', 'best-value', 'premium', 'luxury',
            'new-release', 'rare-wine', 'auction', 'cellar-selection'
        ];

        foreach ($tags as $tagName) {
            $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
        }
    }

    private function createCategories(): void
    {
        $categories = [
            'Wine Reviews' => [
                'By Type' => ['Red Wine', 'White Wine', 'Sparkling', 'Rosé', 'Dessert Wine'],
                'By Region' => ['Bordeaux', 'Burgundy', 'Napa Valley', 'Tuscany', 'Rioja', 'Champagne'],
                'By Vintage' => ['2023', '2022', '2021', '2020', 'Older Vintages']
            ],
            'Wine Knowledge' => [
                'Tasting Guides' => ['Beginner', 'Intermediate', 'Advanced'],
                'Wine Regions' => ['France', 'Italy', 'Spain', 'USA', 'Australia', 'Chile'],
                'Grape Varieties' => ['Red Grapes', 'White Grapes'],
                'Food Pairing' => ['Meat', 'Seafood', 'Cheese', 'Desserts']
            ],
            'Wine Lifestyle' => [
                'Wine Travel' => ['Vineyard Tours', 'Wine Routes', 'Destinations'],
                'Collecting' => ['Investment', 'Cellaring', 'Auction'],
                'Events' => ['Tastings', 'Festivals', 'Masterclasses']
            ],
            'Buying Guides' => ['Best Value', 'Premium Selection', 'Gift Ideas', 'Wine Accessories'],
            'News & Features' => ['Industry News', 'Interviews', 'Opinion', 'Awards']
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
            ['key' => 'author_image', 'name' => 'Author Image', 'type' => 'text'],
            ['key' => 'read_time', 'name' => 'Read Time (minutes)', 'type' => 'number'],
            ['key' => 'wine_rating', 'name' => 'Wine Rating (out of 100)', 'type' => 'number'],
            ['key' => 'wine_producer', 'name' => 'Wine Producer', 'type' => 'text'],
            ['key' => 'wine_vintage', 'name' => 'Vintage Year', 'type' => 'text'],
            ['key' => 'wine_region', 'name' => 'Wine Region', 'type' => 'text'],
            ['key' => 'wine_country', 'name' => 'Country', 'type' => 'text'],
            ['key' => 'grape_variety', 'name' => 'Grape Variety', 'type' => 'text'],
            ['key' => 'wine_type', 'name' => 'Wine Type', 'type' => 'select', 'options' => '{"red":"Red Wine","white":"White Wine","sparkling":"Sparkling","rosé":"Rosé","dessert":"Dessert Wine"}'],
            ['key' => 'alcohol_content', 'name' => 'Alcohol %', 'type' => 'text'],
            ['key' => 'price_range', 'name' => 'Price Range', 'type' => 'select', 'options' => '{"budget":"Under £20","mid":"£20-50","premium":"£50-100","luxury":"£100+"}'],
            ['key' => 'drink_window', 'name' => 'Drinking Window', 'type' => 'text'],
            ['key' => 'excerpt', 'name' => 'Article Excerpt', 'type' => 'textarea'],
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

    private function createHomepage(): void
    {
        $page = Page::create([
            'title' => 'The Wine Chronicle - Fine Wine Reviews & Expert Guides',
            'page_type' => 'content',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'The Wine Chronicle - Expert Wine Reviews, Ratings & Buying Guides',
            'meta_description' => 'Discover expert wine reviews, tasting notes, vintage guides, and wine knowledge from our team of Master Sommeliers and wine critics.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Home',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        $featuredTag = $this->tagRepository->findOrCreateByName('featured', $this->site->id);
        $page->tags(true)->attach($featuredTag->id);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Discover Exceptional Wines',
                    'subtitle' => 'Expert reviews, tasting guides, and wine knowledge from Master Sommeliers',
                    'ctaText' => 'Latest Reviews',
                    'ctaUrl' => '#featured',
                    'secondaryCtaText' => 'Wine Education',
                    'secondaryCtaUrl' => '/guides',
                    'showSearch' => true,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'info',
                'data' => [
                    'infoType' => 'info',
                    'description' => '🏆 Wine of the Week: Château Margaux 2015 - A masterpiece of Bordeaux winemaking →'
                ],
                'order' => 2
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Featured Wine Reviews',
                    'subtitle' => 'Our highest-rated wines this month',
                    'level' => 2
                ],
                'order' => 3
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'layout' => 'masonry',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'showMeta' => true,
                    'pages' => [
                        [
                            'title' => 'Bordeaux 2020 Vintage: The Complete Guide',
                            'slug' => 'bordeaux-2020-vintage-guide',
                            'excerpt' => 'A comprehensive review of the exceptional 2020 Bordeaux vintage - tasting notes, scores, and investment potential.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1568213816046-0ee1c42bd559?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Bordeaux vineyard'
                            ],
                            'badge' => [
                                'text' => 'Editors Choice',
                                'color' => 'primary'
                            ],
                            'meta' => [
                                'author' => 'James Thornton MW',
                                'date' => 'March 15, 2025',
                                'readTime' => '12 min read'
                            ]
                        ],
                        [
                            'title' => 'Best Burgundy Under £50: Hidden Gems from Emerging Producers',
                            'slug' => 'burgundy-value-wines-guide',
                            'excerpt' => 'Discover exceptional Burgundy wines that won\'t break the bank - our top picks from small producers.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1547595628-c61a29f496f0?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Burgundy wine'
                            ],
                            'badge' => [
                                'text' => 'Best Value',
                                'color' => 'success'
                            ],
                            'meta' => [
                                'author' => 'Sophie Beaumont',
                                'date' => 'March 14, 2025',
                                'readTime' => '8 min read'
                            ]
                        ],
                        [
                            'title' => 'Champagne Tasting Guide: From Brut to Vintage',
                            'slug' => 'champagne-tasting-masterclass',
                            'excerpt' => 'Learn to taste Champagne like a professional with our comprehensive guide to styles, producers, and serving.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1558346648-9757f2fa4474?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Champagne glasses'
                            ],
                            'badge' => [
                                'text' => 'Masterclass',
                                'color' => 'warning'
                            ],
                            'meta' => [
                                'author' => 'Pierre Dubois',
                                'date' => 'March 13, 2025',
                                'readTime' => '15 min read'
                            ]
                        ]
                    ]
                ],
                'order' => 4
            ],
            [
                'type' => 'divider',
                'data' => [
                    'style' => 'decorative'
                ],
                'order' => 5
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Our Expertise',
                    'stats' => [
                        ['number' => '50+', 'label' => 'Years Combined Experience', 'icon' => '🍷'],
                        ['number' => '10,000+', 'label' => 'Wines Reviewed Annually', 'icon' => '📝'],
                        ['number' => '5', 'label' => 'Master Sommeliers', 'icon' => '🏆'],
                        ['number' => '30+', 'label' => 'Wine Regions Covered', 'icon' => '🌍']
                    ]
                ],
                'order' => 6
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Wine Knowledge',
                    'subtitle' => 'Essential guides for wine lovers',
                    'level' => 2
                ],
                'order' => 7
            ],
            [
                'type' => 'gallery',
                'data' => [
                    'layout' => 'grid',
                    'slides' => [
                        [
                            'title' => 'Beginner\'s Guide',
                            'description' => 'Start your wine journey with confidence',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1506377247377-2a5b3b417ebb?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'alt' => 'Wine tasting basics',
                            'link' => '/guides/beginners'
                        ],
                        [
                            'title' => 'Food Pairing',
                            'description' => 'Master the art of wine and food matching',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'alt' => 'Wine and food pairing',
                            'link' => '/guides/food-pairing'
                        ],
                        [
                            'title' => 'Wine Regions',
                            'description' => 'Explore the world\'s finest wine regions',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1474722883778-792e7990302f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'alt' => 'Vineyard landscape',
                            'link' => '/regions'
                        ]
                    ]
                ],
                'order' => 8
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'Wine is bottled poetry.',
                    'attribution' => 'Robert Louis Stevenson'
                ],
                'order' => 9
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Subscribe to Our Newsletter',
                    'subtitle' => 'Weekly wine reviews, exclusive offers, and expert insights delivered to your inbox',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => false,
                    'showSubject' => false,
                    'showMessage' => false,
                    'submitButtonText' => 'Subscribe',
                    'requireName' => true,
                    'requireEmail' => true
                ],
                'order' => 10
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createArticles(): void
    {
        $articles = [
            [
                'title' => 'Bordeaux 2020 Vintage: The Complete Guide with Ratings & Tasting Notes',
                'slug' => 'bordeaux-2020-vintage-guide',
                'tags' => ['featured', 'bordeaux', 'red-wine', 'vintage', 'wine-investment', 'editors-choice'],
                'categories' => ['Wine Reviews', 'By Region', 'Bordeaux'],
                'custom_fields' => [
                    'author_name' => 'James Thornton MW',
                    'author_bio' => 'Master of Wine and Bordeaux specialist with 25 years of experience reviewing fine wines.',
                    'read_time' => 12,
                    'wine_region' => 'Bordeaux',
                    'wine_country' => 'France',
                    'wine_vintage' => '2020',
                    'wine_type' => 'red',
                    'excerpt' => 'A comprehensive review of the exceptional 2020 Bordeaux vintage - tasting notes, scores, and investment potential across all appellations.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1568213816046-0ee1c42bd559?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Bordeaux vineyard at sunset',
                            'caption' => 'The 2020 vintage benefited from near-perfect growing conditions across Bordeaux',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The 2020 Bordeaux vintage will be remembered as one of the finest of the 21st century. After a challenging growing season that kept vignerons on their toes, the wines have emerged with remarkable concentration, balance, and aging potential.',
                                'We spent three weeks in Bordeaux tasting over 500 wines from the 2020 vintage, visiting châteaux across all major appellations. What we discovered was a vintage of stunning quality, though not without its nuances and regional variations.',
                                'This comprehensive guide breaks down the vintage by region, highlights the standout wines, and provides investment insights for collectors.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Vintage Conditions',
                            'subtitle' => 'The weather story behind the wines',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The 2020 growing season began with a mild winter and early budbreak, followed by a cool, wet spring that caused some flowering irregularities. However, the summer brought ideal conditions: warm days, cool nights, and just enough rainfall to maintain vine health without diluting the fruit.',
                                'Harvest began in mid-September under perfect conditions. The key to 2020\'s success was the extended hang time, allowing phenolic ripeness to match sugar levels - a balance that eluded many recent vintages.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => '2020 Vintage By The Numbers',
                            'stats' => [
                                ['number' => '2,850', 'label' => 'Growing Degree Days', 'icon' => '🌡️'],
                                ['number' => '450mm', 'label' => 'Total Rainfall', 'icon' => '🌧️'],
                                ['number' => 'Sept 15', 'label' => 'Harvest Start Date', 'icon' => '📅'],
                                ['number' => '97/100', 'label' => 'Vintage Quality Score', 'icon' => '⭐']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Left Bank Highlights',
                            'subtitle' => 'Pauillac, Saint-Julien, Margaux & Saint-Estèphe',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'award',
                        'data' => [
                            'subcategory' => 'Wine of the Vintage',
                            'productName' => 'Château Margaux 2020',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1547595628-c61a29f496f0?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'alt' => 'Château Margaux wine bottle',
                            'winner' => true,
                            'rating' => 99,
                            'strapline' => 'A transcendent wine combining power with ethereal elegance',
                            'caption' => 'The finest Margaux we have tasted in decades'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Château Margaux has produced a wine of extraordinary beauty in 2020. The nose offers layers of cassis, violets, and graphite, while the palate reveals remarkable depth without heaviness. The tannins are perfectly integrated, and the finish extends for minutes.',
                                'This is a wine that will improve for 30-40 years and drink beautifully for decades beyond that. At around £650 per bottle on release, it represents exceptional value for a wine of this calibre.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Château Margaux 2020',
                            'brand' => 'Château Margaux',
                            'productName' => 'Premier Grand Cru Classé',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1547595628-c61a29f496f0?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'price' => 650.00,
                            'currency' => '£',
                            'description' => '87% Cabernet Sauvignon, 10% Merlot, 2% Cabernet Franc, 1% Petit Verdot. 13.5% alcohol. Drinking window: 2028-2070.',
                            'link' => 'https://example.com/chateau-margaux-2020',
                            'linkText' => 'Find Stockists',
                            'displayAs' => 'button',
                            'layout' => 'standard',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 4.95,
                                'pros' => [
                                    'Exceptional balance and harmony',
                                    'Phenomenal aging potential',
                                    'Classic Margaux elegance',
                                    'Perfect integration of new oak',
                                    'Incredibly long finish'
                                ],
                                'cons' => [
                                    'Very limited availability',
                                    'Requires decades of cellaring'
                                ]
                            ],
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Château', 'Appellation', 'Score', 'Release Price', 'Investment Grade'],
                                ['Château Margaux', 'Margaux', '99', '£650', 'Exceptional'],
                                ['Château Latour', 'Pauillac', '98', '£720', 'Exceptional'],
                                ['Château Lafite Rothschild', 'Pauillac', '98', '£680', 'Exceptional'],
                                ['Château Mouton Rothschild', 'Pauillac', '97', '£650', 'Excellent'],
                                ['Château Léoville Las Cases', 'Saint-Julien', '97', '£280', 'Excellent'],
                                ['Château Pichon Longueville', 'Pauillac', '96', '£180', 'Very Good'],
                                ['Château Palmer', 'Margaux', '96', '£380', 'Excellent']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Right Bank Excellence',
                            'subtitle' => 'Pomerol & Saint-Émilion',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Right Bank enjoyed particularly favorable conditions in 2020, with the clay soils of Pomerol and Saint-Émilion benefiting from the balanced rainfall. Merlot ripened to perfection, producing wines of exceptional depth and sensuality.',
                                'Pétrus has crafted what may be their finest wine since 2010, while Cheval Blanc and Ausone both produced stunning wines that will rank among the vintage\'s very best.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Château Pétrus 2020',
                            'subtitle' => 'The pinnacle of Pomerol winemaking',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1553361371-9b22f78e8b1d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'url' => 'https://example.com/petrus-2020',
                            'linkText' => 'Enquire About Availability',
                            'displayAs' => 'button',
                            'specs' => [
                                ['text' => 'Grape', 'value' => '100% Merlot'],
                                ['text' => 'Alcohol', 'value' => '14.5%'],
                                ['text' => 'Production', 'value' => '2,500 cases'],
                                ['text' => 'Drinking Window', 'value' => '2030-2080'],
                                ['text' => 'Score', 'value' => '100/100']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Perfect expression of terroir',
                                'Unmatched concentration and purity',
                                'Seamless integration of elements',
                                'Monumental aging potential',
                                'Benchmark for the vintage'
                            ],
                            'cons' => [
                                'Extremely limited availability',
                                'Significant investment required (£3,500+)',
                                'Decades needed before approaching peak'
                            ],
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Left Bank vs Right Bank 2020',
                            'productA' => 'Château Latour (Pauillac)',
                            'productB' => 'Château Pétrus (Pomerol)',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Blend',
                                    'items' => [
                                        ['value' => '91% Cab Sauv, 8% Merlot'],
                                        ['value' => '100% Merlot']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Style',
                                    'items' => [
                                        ['value' => 'Powerful, structured, tannic'],
                                        ['value' => 'Rich, sensual, voluptuous']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Aging Potential',
                                    'items' => [
                                        ['value' => '50+ years'],
                                        ['value' => '50+ years']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Price',
                                    'items' => [
                                        ['value' => '£720'],
                                        ['value' => '£3,500']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Long-term cellaring'],
                                        ['value' => 'Ultimate luxury']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Best Value Bordeaux 2020',
                            'subtitle' => 'Outstanding wines under £100',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'While the First Growths command headlines and premium prices, the 2020 vintage offers exceptional value further down the hierarchy. Many Crus Bourgeois and lesser-known appellations produced wines that punch well above their weight.',
                                'These wines won\'t appreciate as dramatically as the top châteaux, but they offer tremendous drinking pleasure at accessible prices.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Château de Pez (Saint-Estèphe) - £35 - Classic Left Bank style with excellent aging potential',
                                'Château Phélan Ségur (Saint-Estèphe) - £45 - Consistently overdelivers, 2020 is no exception',
                                'Château Haut-Bailly (Pessac-Léognan) - £85 - Elegant and refined with superb balance',
                                'Vieux Château Certan (Pomerol) - £95 - Outstanding quality at a fraction of Pétrus prices',
                                'Château Figeac (Saint-Émilion) - £90 - Cabernet Franc dominance adds unique character'
                            ]
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => 'En Primeur Offer',
                            'productName' => 'Bordeaux 2020 Mixed Case',
                            'brand' => 'The Wine Chronicle Selection',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'price' => 899.00,
                            'salePrice' => 749.00,
                            'currency' => '£',
                            'description' => 'Exclusive mixed case featuring 12 bottles from top châteaux. Includes wines from Pauillac, Saint-Julien, Margaux & Pomerol. En primeur pricing - wines delivered 2023.',
                            'link' => 'https://example.com/bordeaux-2020-case',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Investment Outlook',
                            'paragraphs' => [
                                'The 2020 vintage represents a strong investment opportunity, particularly for the First Growths and "Super Seconds." Historical data shows that exceptional Bordeaux vintages appreciate 8-12% annually over the first decade.',
                                'However, en primeur prices for 2020 were relatively high compared to recent vintages. We recommend focusing on châteaux that showed restraint in pricing, particularly in Saint-Julien and Pauillac.',
                                'For drinking rather than investment, the value wines listed above offer better near-term returns in terms of enjoyment per pound spent.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Tasting Notes: Top 20 Wines',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'schema',
                        'data' => [
                            'schemaType' => 'question',
                            'question' => 'When should I drink 2020 Bordeaux?',
                            'answer' => 'The 2020 vintage is built for long-term aging. Most classified growth wines will benefit from 10-15 years of cellaring before entering their optimal drinking window.',
                            'expansion' => 'First Growths and top Pomerols should be cellared for at least 15 years and will continue improving for 50+ years. Lesser châteaux and Crus Bourgeois can be enjoyed sooner - from 5-10 years after vintage. The key is patience; these wines have the structure and balance to reward long cellaring.'
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'carousel',
                            'slides' => [
                                [
                                    'title' => 'Château Lafite Rothschild 2020',
                                    'description' => '98/100 - Aristocratic elegance with phenomenal aging potential',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1566754534506-d94ff9d39c38?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80'],
                                    'alt' => 'Lafite wine cellar'
                                ],
                                [
                                    'title' => 'Château Haut-Brion 2020',
                                    'description' => '97/100 - Smoky complexity with silky tannins',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1514362545857-3bc16c4c7d1b?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80'],
                                    'alt' => 'Haut-Brion vineyard'
                                ],
                                [
                                    'title' => 'Château Cheval Blanc 2020',
                                    'description' => '98/100 - Remarkable purity and precision',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1547595628-c61a29f496f0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80'],
                                    'alt' => 'Cheval Blanc château'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => '2020 is a vintage that reminds us why Bordeaux remains the benchmark for age-worthy red wines. These wines have the structure for decades, yet never lose sight of elegance.',
                            'attribution' => 'James Thornton MW'
                        ]
                    ],
                    [
                        'type' => 'testimonial',
                        'data' => [
                            'layout' => 'grid',
                            'testimonials' => [
                                [
                                    'text' => 'James\'s Bordeaux coverage is unmatched. His tasting notes helped me secure allocation of several top wines at en primeur.',
                                    'author' => 'Sir David Matthews',
                                    'role' => 'Private Collector',
                                    'rating' => 5
                                ],
                                [
                                    'text' => 'The value recommendations were spot-on. I built an excellent cellar of 2020s without breaking the bank.',
                                    'author' => 'Emma Richardson',
                                    'role' => 'Wine Investor',
                                    'rating' => 5
                                ],
                                [
                                    'text' => 'Clear, honest assessments that cut through the hype. This is the Bordeaux guide I trust most.',
                                    'author' => 'Michel Dubois',
                                    'role' => 'Sommelier',
                                    'rating' => 5
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The 2020 Bordeaux vintage stands as a triumph of winemaking skill and favorable conditions. While the First Growths rightfully capture attention with their perfection, the vintage offers quality at every price point.',
                                'For collectors, this is a vintage to buy with confidence. For drinkers, patience will be rewarded with wines of extraordinary beauty and complexity. Whether you\'re investing thousands or simply enjoying a few special bottles, 2020 Bordeaux will not disappoint.'
                            ]
                        ]
                    ]
                ],
            ],
            [
                'title' => 'Best Burgundy Under £50: Hidden Gems from Emerging Producers',
                'slug' => 'burgundy-value-wines-guide',
                'tags' => ['featured', 'burgundy', 'red-wine', 'white-wine', 'best-value', 'pinot-noir', 'chardonnay'],
                'categories' => ['Wine Reviews', 'By Region', 'Burgundy'],
                'custom_fields' => [
                    'author_name' => 'Sophie Beaumont',
                    'author_bio' => 'Burgundy specialist and wine writer focusing on value and emerging producers.',
                    'read_time' => 8,
                    'wine_region' => 'Burgundy',
                    'wine_country' => 'France',
                    'price_range' => 'mid',
                    'excerpt' => 'Discover exceptional Burgundy wines that won\'t break the bank - our top picks from small producers making waves.'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Affordable Burgundy Exists',
                            'subtitle' => 'Exceptional wines from emerging producers under £50',
                            'ctaText' => 'See Our Picks',
                            'ctaUrl' => '#wines',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1547595628-c61a29f496f0?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Burgundy has earned a reputation for stratospheric prices, with Grand Cru bottles routinely fetching thousands of pounds. But beyond the famous names and prestigious vineyards lies a thriving scene of talented young winemakers producing exceptional wines at accessible prices.',
                                'We spent six months searching Burgundy\'s lesser-known villages and working with new-generation vignerons to find wines that deliver authentic Burgundian character without the premium price tag.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Look for villages just outside famous appellations: Marsannay near Gevrey-Chambertin, Savigny-lès-Beaune near Beaune, and Monthelie near Volnay offer similar terroir at fraction of the cost.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Top Red Burgundy Under £50',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Domaine Fourrier Gevrey-Chambertin Vieilles Vignes 2021',
                            'brand' => 'Domaine Fourrier',
                            'productName' => 'Gevrey-Chambertin Vieilles Vignes',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1553361371-9b22f78e8b1d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'price' => 48.00,
                            'currency' => '£',
                            'description' => 'From 50+ year old vines, this offers classic Gevrey structure with remarkable purity. Notes of red cherry, forest floor, and subtle spice.',
                            'link' => 'https://example.com/fourrier-gevrey',
                            'linkText' => 'Find Stockists',
                            'displayAs' => 'button',
                            'layout' => 'standard',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 4.5,
                                'pros' => [
                                    'Exceptional value for Gevrey-Chambertin',
                                    'Pure expression of terroir',
                                    'Minimal intervention winemaking',
                                    'Age-worthy structure'
                                ],
                                'cons' => [
                                    'Limited availability',
                                    'Needs 2-3 years cellaring'
                                ]
                            ],
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Domaine Hudelot-Noëllat Bourgogne Rouge 2022 - £28 - Gorgeous fruit from declassified village wines',
                                'Domaine Sylvain Pataille Marsannay 2021 - £35 - Brilliant value from this rising star',
                                'Domaine Anne Gros Hautes-Côtes de Nuits 2021 - £38 - From one of Burgundy\'s top producers',
                                'Benjamin Leroux Savigny-lès-Beaune 2021 - £42 - Négociant magic with impeccable fruit selection'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'White Burgundy Bargains',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Domaine William Fèvre Chablis 2022',
                            'subtitle' => 'Classic Chablis minerality at fair price',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1568213816046-0ee1c42bd559?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'url' => 'https://example.com/fevre-chablis',
                            'linkText' => 'Buy Now',
                            'displayAs' => 'button',
                            'specs' => [
                                ['text' => 'Region', 'value' => 'Chablis'],
                                ['text' => 'Grape', 'value' => '100% Chardonnay'],
                                ['text' => 'Alcohol', 'value' => '12.5%'],
                                ['text' => 'Aging', 'value' => 'Stainless steel'],
                                ['text' => 'Score', 'value' => '91/100']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Textbook Chablis character',
                                'Steely minerality with citrus notes',
                                'Perfect with seafood',
                                'Excellent consistency across vintages'
                            ],
                            'cons' => [
                                'Very dry - not for sweet wine lovers',
                                'Best consumed within 3-4 years'
                            ],
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Wine', 'Region', 'Price', 'Style', 'Score'],
                                ['William Fèvre Chablis', 'Chablis', '£26', 'Crisp, mineral', '91'],
                                ['Jean-Marc Brocard Chablis 1er Cru', 'Chablis', '£42', 'Complex, layered', '93'],
                                ['Domaine Leflaive Mâcon-Verzé', 'Mâconnais', '£35', 'Rich, textured', '92'],
                                ['Louis Jadot St-Aubin 1er Cru', 'Côte de Beaune', '£45', 'Elegant, refined', '91']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Great Burgundy doesn\'t have to cost a fortune. These emerging producers are crafting wines that honor tradition while offering incredible value.',
                            'attribution' => 'Sophie Beaumont'
                        ]
                    ]
                ],
            ],
            [
                'title' => 'Champagne Tasting Guide: From Brut to Vintage - Master the Art of Sparkling Wine',
                'slug' => 'champagne-tasting-masterclass',
                'tags' => ['featured', 'champagne', 'sparkling', 'wine-tasting', 'expert-advice', 'beginner-guide'],
                'categories' => ['Wine Knowledge', 'Tasting Guides', 'Advanced'],
                'custom_fields' => [
                    'author_name' => 'Pierre Dubois',
                    'author_bio' => 'Champagne specialist and certified sommelier with 20 years experience in the Champagne region.',
                    'read_time' => 15,
                    'wine_region' => 'Champagne',
                    'wine_country' => 'France',
                    'wine_type' => 'sparkling',
                    'excerpt' => 'Learn to taste Champagne like a professional - understanding styles, producers, serving, and the nuances that separate great from extraordinary.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1558346648-9757f2fa4474?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Champagne glasses with bubbles',
                            'caption' => 'Understanding Champagne elevates every celebration',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Champagne is more than just bubbles - it\'s one of the world\'s most complex and rewarding wine categories. From the chalky soils of the Côte des Blancs to the labor-intensive méthode champenoise, everything about Champagne production is designed to create wines of extraordinary finesse.',
                                'This masterclass will teach you to taste Champagne like a professional, understand the different styles, and appreciate the craftsmanship behind every bottle.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Understanding Champagne Styles',
                            'subtitle' => 'From Non-Vintage to Prestige Cuvées',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Non-Vintage (NV): Blend of multiple years, represents house style. Most versatile and food-friendly.',
                                'Vintage: Made only in exceptional years from single harvest. More complex and age-worthy.',
                                'Blanc de Blancs: 100% Chardonnay. Elegant, precise, citrus-driven with high acidity.',
                                'Blanc de Noirs: Made from Pinot Noir and/or Pinot Meunier. Fuller-bodied with red fruit notes.',
                                'Rosé: Pink color from skin contact or blending. Ranges from delicate to powerful.',
                                'Prestige Cuvée: Top-tier wine from best parcels. Examples: Dom Pérignon, Cristal, Krug.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'schema',
                        'data' => [
                            'schemaType' => 'how-to',
                            'title' => 'How to Taste Champagne Properly',
                            'description' => 'A step-by-step guide to professional Champagne tasting technique.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1558346648-9757f2fa4474?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80']
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Chill to 8-10°C - too cold and you\'ll mute the flavors',
                                'Use a tulip-shaped glass, never a coupe or flute',
                                'Observe the bubbles - smaller, persistent bubbles indicate quality',
                                'Assess the aroma - first impression, then after bubbles settle',
                                'Take a small sip, let it coat your entire palate',
                                'Note the texture, acidity, and length of finish',
                                'Consider the evolution - how does it change in the glass?'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'The Importance of Glassware',
                            'paragraphs' => [
                                'Forget everything you know about wide-bowled coupes or narrow flutes. Professional tasters use tulip-shaped glasses that allow the wine to breathe while concentrating aromatics.',
                                'The ideal Champagne glass is narrower than a white wine glass but with a slight taper at the top. Brands like Zalto and Riedel make excellent Champagne-specific stemware.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Top Champagne Producers to Know',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Grande Marques',
                                    'description' => 'Historic houses: Moët, Veuve Clicquot, Pol Roger',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1549073710-c5f282238ed7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Champagne cellar'
                                ],
                                [
                                    'title' => 'Grower Champagne',
                                    'description' => 'Small producers: Egly-Ouriet, Jacques Selosse',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Champagne vineyard'
                                ],
                                [
                                    'title' => 'Prestige Cuvées',
                                    'description' => 'Dom Pérignon, Cristal, Krug Clos du Mesnil',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1566754534506-d94ff9d39c38?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Prestige Champagne'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Grande Marque vs Grower Champagne',
                            'productA' => 'Moët & Chandon (Grande Marque)',
                            'productB' => 'Jacques Selosse (Grower)',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Production',
                                    'items' => [
                                        ['value' => 'Millions of bottles'],
                                        ['value' => 'Thousands of bottles']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Style',
                                    'items' => [
                                        ['value' => 'Consistent house style'],
                                        ['value' => 'Terroir-driven, vintage variation']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Price (NV)',
                                    'items' => [
                                        ['value' => '£40-50'],
                                        ['value' => '£80-120']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Availability',
                                    'items' => [
                                        ['value' => 'Widely available'],
                                        ['value' => 'Limited, specialist retailers']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Celebrations, consistency'],
                                        ['value' => 'Exploration, unique character']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'event',
                        'data' => [
                            'title' => 'Champagne Masterclass: Tasting Tour',
                            'description' => 'Join Pierre Dubois for an intimate tasting of 10 exceptional Champagnes, from entry-level to prestige cuvées. Learn professional tasting techniques, understand vintage variations, and discover food pairing secrets. Includes extensive tasting notes booklet and cheese pairing.',
                            'startDate' => '2025-06-12',
                            'startTime' => '7:00 PM',
                            'endTime' => '10:00 PM',
                            'location' => 'The Wine Chronicle Tasting Room',
                            'address' => '45 Sommelier Street, London W1K 4AB',
                            'ticketPrice' => 125.00,
                            'currency' => '£',
                            'ticketUrl' => 'https://example.com/champagne-masterclass',
                            'capacity' => 20,
                            'organizerName' => 'The Wine Chronicle',
                            'organizerEmail' => 'events@winechronicle.com',
                            'category' => 'Masterclass',
                            'image' => 'https://images.unsplash.com/photo-1558346648-9757f2fa4474?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'showSignupForm' => true
                        ]
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'style' => 'solid'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Food Pairing with Champagne',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Champagne is one of the most versatile wines for food pairing. Its high acidity cuts through rich foods, while its effervescence cleanses the palate between bites.',
                                'Contrary to popular belief, Champagne isn\'t just for celebrations - it\'s one of the best wines to serve throughout an entire meal.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Champagne Style', 'Best Food Pairing', 'Why It Works'],
                                ['Brut NV', 'Oysters, sushi, fried foods', 'Acidity cuts richness'],
                                ['Blanc de Blancs', 'Seafood, light fish, goat cheese', 'Delicate flavors complement'],
                                ['Blanc de Noirs', 'Roast chicken, salmon, mushrooms', 'Body matches richer dishes'],
                                ['Rosé', 'Duck, tuna, berry desserts', 'Red fruit notes bridge flavors'],
                                ['Vintage', 'Lobster, aged cheese, white truffles', 'Complexity matches luxury ingredients']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'I drink Champagne when I\'m happy and when I\'m sad. Sometimes I drink it when I\'m alone. When I have company I consider it obligatory. I trifle with it if I\'m not hungry and drink it when I am. Otherwise, I never touch it - unless I\'m thirsty.',
                            'attribution' => 'Lily Bollinger'
                        ]
                    ]
                ],
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

    private function createAboutPage(): void
    {
        $page = Page::create([
            'title' => 'About The Wine Chronicle',
            'page_type' => 'content',
            'slug' => 'about',
            'status' => 'published',
            'meta_title' => 'About Us - The Wine Chronicle',
            'meta_description' => 'Learn about The Wine Chronicle - your trusted source for expert wine reviews, ratings, and education.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'About',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'About The Wine Chronicle',
                    'subtitle' => 'Expert wine criticism and education since 1975',
                    'ctaText' => 'Our Team',
                    'ctaUrl' => '#team',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The Wine Chronicle was founded in 1975 with a simple mission: to provide honest, expert wine criticism free from commercial influence. For nearly five decades, we have been the trusted voice for wine lovers, collectors, and professionals seeking authoritative reviews and ratings.',
                        'Our team of Master Sommeliers, Masters of Wine, and expert critics taste over 10,000 wines annually, visiting vineyards across the globe to bring you comprehensive coverage of both established producers and exciting newcomers.',
                        'We believe great wine should be accessible to everyone. Whether you\'re just beginning your wine journey or you\'re a seasoned collector, The Wine Chronicle provides the knowledge and guidance you need to discover exceptional bottles.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Our Reach & Influence',
                    'stats' => [
                        ['number' => '50', 'label' => 'Years of Excellence', 'icon' => '🏆'],
                        ['number' => '10,000+', 'label' => 'Wines Reviewed Annually', 'icon' => '🍷'],
                        ['number' => '2M+', 'label' => 'Monthly Readers', 'icon' => '📖'],
                        ['number' => '30+', 'label' => 'Countries Covered', 'icon' => '🌍']
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Expert Team',
                    'subtitle' => 'Masters of Wine & Sommeliers',
                    'level' => 2
                ],
                'order' => 4
            ],
            [
                'type' => 'team',
                'data' => [
                    'title' => '',
                    'layout' => 'grid',
                    'members' => [
                        [
                            'name' => 'James Thornton MW',
                            'role' => 'Chief Wine Critic - Bordeaux Specialist',
                            'bio' => 'Master of Wine with 25 years experience reviewing fine wines. James is recognized as one of the world\'s leading authorities on Bordeaux.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'james.thornton@winechronicle.com'
                        ],
                        [
                            'name' => 'Sophie Beaumont',
                            'role' => 'Senior Editor - Burgundy & Value Wines',
                            'bio' => 'Burgundy specialist and champion of emerging producers. Sophie focuses on finding exceptional quality at accessible prices.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'sophie.beaumont@winechronicle.com'
                        ],
                        [
                            'name' => 'Pierre Dubois',
                            'role' => 'Master Sommelier - Champagne Expert',
                            'bio' => 'Based in Épernay, Pierre brings unparalleled expertise in Champagne and sparkling wines from around the world.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'pierre.dubois@winechronicle.com'
                        ],
                        [
                            'name' => 'Isabella Romano MW',
                            'role' => 'Italian Wine Correspondent',
                            'bio' => 'Master of Wine specializing in Italian wine regions from Piedmont to Sicily. Based in Florence.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'isabella.romano@winechronicle.com'
                        ],
                        [
                            'name' => 'David Chen',
                            'role' => 'New World Wine Editor',
                            'bio' => 'Expert in wines from California, Australia, New Zealand, and South America. Previously worked as a winemaker in Napa Valley.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'david.chen@winechronicle.com'
                        ],
                        [
                            'name' => 'Emma Thompson',
                            'role' => 'Education Director',
                            'bio' => 'WSET educator and wine writer focused on making wine knowledge accessible to enthusiasts at all levels.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'emma.thompson@winechronicle.com'
                        ]
                    ]
                ],
                'order' => 5
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Rating System',
                    'level' => 2
                ],
                'order' => 6
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The Wine Chronicle uses a 100-point rating scale, which has become the industry standard worldwide. Our ratings are based on blind tastings conducted by our expert panel, ensuring objectivity and consistency.'
                    ]
                ],
                'order' => 7
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        '95-100: Extraordinary - The finest wines, exceptional in every way',
                        '90-94: Outstanding - Excellent wines with distinct personality and complexity',
                        '85-89: Very Good - Solid wines with notable character and quality',
                        '80-84: Good - Well-made wines suitable for everyday drinking',
                        '75-79: Acceptable - Simple wines with minor flaws',
                        'Below 75: Not Recommended'
                    ]
                ],
                'order' => 8
            ],
            [
                'type' => 'note',
                'data' => [
                    'title' => 'Our Independence',
                    'paragraphs' => [
                        'The Wine Chronicle maintains strict editorial independence. We purchase all wines reviewed at retail prices, and we never accept payment for reviews or ratings.',
                        'Our reputation is built on trust, objectivity, and expertise. When you see a Wine Chronicle rating, you can be confident it reflects our honest, expert assessment.'
                    ],
                    'alignment' => 'fullscreen'
                ],
                'order' => 9
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'Wine is one of the most civilized things in the world and one of the most natural things of the world that has been brought to the greatest perfection, and it offers a greater range for enjoyment and appreciation than, possibly, any other purely sensory thing.',
                    'attribution' => 'Ernest Hemingway'
                ],
                'order' => 10
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createContactPage(): void
    {
        $page = Page::create([
            'title' => 'Contact The Wine Chronicle',
            'page_type' => 'content',
            'slug' => 'contact',
            'status' => 'published',
            'meta_title' => 'Contact Us - The Wine Chronicle',
            'meta_description' => 'Get in touch with The Wine Chronicle editorial team, submit wines for review, or inquire about events and masterclasses.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Contact',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Get In Touch',
                    'subtitle' => 'Questions about wine? Want to submit samples for review? We\'re here to help',
                    'showSearch' => false
                ],
                'order' => 1
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'The Wine Chronicle Editorial',
                    'role' => 'Contact Information',
                    'email' => 'editorial@winechronicle.com',
                    'phone' => '+44 20 7946 0123',
                    'address' => 'The Wine Chronicle
Wine House, 25 Vintner Street
London, EC4V 3BG

Editorial Office Hours:
Monday-Friday: 9:00 AM - 6:00 PM
Saturday: 10:00 AM - 2:00 PM
Sunday: Closed',
                    'displayType' => 'contact'
                ],
                'order' => 2
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'For wine sample submissions, please contact our reviews team at reviews@winechronicle.com. We review wines from producers of all sizes, from prestigious estates to emerging winemakers.',
                        'For event inquiries, masterclass bookings, or private tastings, contact events@winechronicle.com.',
                        'Press and media inquiries should be directed to press@winechronicle.com.',
                        'For subscription and account support, visit our Help Centre or email subscriptions@winechronicle.com.'
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Send Us a Message',
                    'subtitle' => 'Our editorial team typically responds within 2 business days',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => true,
                    'showSubject' => true,
                    'showMessage' => true,
                    'submitButtonText' => 'Send Message',
                    'requireName' => true,
                    'requireEmail' => true,
                    'requireMessage' => true
                ],
                'order' => 4
            ],
            [
                'type' => 'info',
                'data' => [
                    'infoType' => 'note',
                    'description' => 'Follow us on social media @WineChronicle for daily wine tips, exclusive content, and live tasting updates from around the world!'
                ],
                'order' => 5
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
}