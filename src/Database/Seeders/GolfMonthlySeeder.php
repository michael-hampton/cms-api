<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\Category;
use App\Models\CustomFieldDefinition;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\PageGrid;
use App\Models\Site;
use App\Models\Tag;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class GolfMonthlySeeder extends Seeder
{
    private $pageRepository;
    private $blockRepository;
    private $tagRepository;
    private $categoryRepository;
    private $blockParserService;
    private \App\Models\Model $site;
    private \App\Models\Model $menu;
    private array $categories = [];
    private array $articles = [];

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
        echo '============ tags================';
        $this->createTags();
        echo '============ categories================';
        $this->createCategories();
        echo '============ menu================';
        $this->createMenu();
        echo '============ articles================';
        $this->createArticles();
        echo '============ homepage================';
        $this->createHomepage();
        echo '============ about ================';
        $this->createAboutPage();
        echo '============ contact ================';
        $this->createContactPage();
        $this->createPageGrid();
        $this->createMenuNavItems();
    }

    private function createPageGrid(): void
    {
        $items = [];

        $articles = Page::where('page_type', 'content')->where('status', 'published')->where('site_id', 46)->get();

        foreach ($articles as $page) {
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
                'features' => ['a', 'b', 'c', 'd'],
                'actions' => [
                    [
                        'text' => 'Read Article',
                        'url' => "/{$this->site->slug}/{$page->slug}",
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
            'items' => json_encode($items),
            'site_id' => $this->site->id
        ]);
    }

    private function createSite(): void
    {
        $this->site = Site::create([
            'name' => 'Golf Monthly',
            'slug' => 'golf-monthly',
            'is_active' => true,
        ]);
    }

    private function createTags(): void
    {
        $tagsData = ['Driver', 'Putter', 'Iron', 'Ball', 'Rules', 'Fitness', 'PGA Tour'];
        foreach ($tagsData as $name) {
            Tag::create(['site_id' => $this->site->id, 'name' => $name, 'slug' => strtolower(str_replace(' ', '-', $name))]);
        }
    }

    private function createCategories(): void
    {
        $categoriesData = ['Reviews', 'Guides', 'Opinion', 'News'];
        foreach ($categoriesData as $name) {
            $this->categories[] = Category::create(['site_id' => $this->site->id, 'name' => $name, 'slug' => strtolower(str_replace(' ', '-', $name))]);
        }
    }

    private function createMenu(): void
    {
        $this->menu = Menu::create([
            'name' => 'Main Menu',
            'site_id' => $this->site->id,
            'slug' => 'main-menu',
        ]);

        $sortOrder = 10;
        foreach ($this->categories as $category) {
            MenuItem::create([
                'label' => $category->name,
                'menu_id' => $this->menu->id,
                'target_type' => 'category',
                'target_id' => $category->id,
                'is_active' => true,
                'sort_order' => $sortOrder += 10
            ]);
        }
    }

    private function createHomepage(): void
    {
        $page = Page::create([
            'title' => 'Golf Monthly - The Ultimate Golf Magazine',
            'page_type' => 'landing-page',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'Golf Monthly - The Ultimate Golf Magazine',
            'meta_description' => 'The best golf equipment reviews, instruction tips, and tour news.',
            'site_id' => $this->site->id,
        ]);
        $this->createBlocksForPage($page->id, $this->getHomepageBlocks([]));
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

    private function getHomepageBlocks(array $articles = []): array
    {
        return [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Golf Monthly: Play Better Golf',
                    'subtitle' => 'The best equipment reviews, instruction tips, and tour news.',
                    'backgroundImage' => 'golfmonthly-hero.jpg', //todo
                    'ctaText' => 'Improve Your Swing',
                    'ctaUrl' => '/instruction',
                    'showSearch' => false
                ]
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Featured Stories',
                    'subtitle' => 'Our Editor\'s Picks',
                    'level' => 2
                ],
                'order' => 2
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
                            'title' => 'Masters 2025: The Shots That Defined Augusta',
                            'slug' => 'ping-g430-driver-review',
                            'excerpt' => 'From impossible bunker escapes to near-mythical putting runs, here are the moments that shaped this year’s Masters.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1502877338535-766e1452684a?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Augusta Golf Course'
                            ],
                            'badge' => [
                                'text' => 'Featured',
                                'color' => 'primary'
                            ],
                            'meta' => [
                                'author' => 'James Holloway',
                                'date' => 'April 10, 2025',
                                'readTime' => '7 min read'
                            ]
                        ],
                        [
                            'title' => 'The Rise of Sustainable Golf Courses',
                            'slug' => 'short-game-clinic-drills',
                            'excerpt' => 'Architects and course designers are reshaping golf’s environmental footprint without sacrificing playability.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Eco-Friendly Golf Course'
                            ],
                            'badge' => [
                                'text' => 'Trending',
                                'color' => 'success'
                            ],
                            'meta' => [
                                'author' => 'Lydia Park',
                                'date' => 'April 8, 2025',
                                'readTime' => '6 min read'
                            ]
                        ],
                        [
                            'title' => 'Inside the World of Elite Golf Training',
                            'slug' => 'best-budget-golf-balls-2026',
                            'excerpt' => 'A look at the biomechanics, tech, and mental conditioning behind today’s best players.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Golf Training'
                            ],
                            'badge' => [
                                'text' => 'Exclusive',
                                'color' => 'warning'
                            ],
                            'meta' => [
                                'author' => 'Daniel Ko',
                                'date' => 'April 6, 2025',
                                'readTime' => '5 min read'
                            ]
                        ]
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'divider',
                'data' => [
                    'style' => 'decorative'
                ],
                'order' => 4
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Latest Trends',
                    'subtitle' => 'What\'s Hot Right Now',
                    'level' => 2
                ],
                'order' => 5
            ],
            [
                'type' => 'gallery',
                'data' => [
                    'layout' => 'grid',
                    'slides' => [
                        [
                            'title' => 'Perfect Swing Mechanics',
                            'description' => 'Master the fundamentals of a powerful, controlled swing',
                            'image' => 'https://images.unsplash.com/photo-1502877338535-766e1452684a?auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Golf Swing',
                            'link' => '/golf/perfect-swing'
                        ],
                        [
                            'title' => 'Elite Course Design',
                            'description' => 'What separates world-class golf courses from the rest',
                            'image' => 'https://images.unsplash.com/photo-1527489377706-5bf97e608852?auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Golf Course Architecture',
                            'link' => '/golf/course-design'
                        ],
                        [
                            'title' => 'Pro Gear Trends',
                            'description' => 'Clubs, tech, and wearables shaping the future of the sport',
                            'image' => 'https://images.unsplash.com/photo-1521412644187-c49fa049e84d?auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Golf Gear',
                            'link' => '/golf/gear-trends'
                        ],
                    ]
                ],
                'order' => 6
            ],
            ['type' => 'text', 'data' => [
                'paragraphs' => [
                    'Our expert testers put the newest drivers, irons, and balls through their paces so you can make an informed choice for your game.'
                ]], 'order' => 2],
            ['type' => 'product-comparison', 'data' => [
                'title' => 'Driver Showdown',
                'productA' => 'Titleist TSR3',
                'productB' => 'TaylorMade Qi10',
                'comparisons' => [
                    [
                        'subtitle' => 'Forgiveness',
                        'items' => [
                            ['value' => 'Good'],
                            ['value' => 'Excellent']
                        ]
                    ],
                    [
                        'subtitle' => 'Ball Speed',
                        'items' => [
                            ['value' => 'High'],
                            ['value' => 'Very High']
                        ]
                    ]
                ]
            ], 'order' => 3],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Subscribe to Our Newsletter',
                    'subtitle' => 'Get the latest fashion news delivered to your inbox',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => false,
                    'showSubject' => false,
                    'showMessage' => false,
                    'submitButtonText' => 'Subscribe Now',
                    'requireName' => true,
                    'requireEmail' => true
                ],
                'order' => 8
            ],
            ['type' => 'image', 'data' => ['src' => 'golf-instruction.jpg', 'alt' => 'Golf lesson graphic'], 'order' => 7] //todo
        ];
    }

    private function createArticles(): void
    {
        $articles = [
            // Target articles matching exact slugs present in getHomepageBlocks()
            [
                'title' => 'Masters 2025: The Shots That Defined Augusta',
                'slug' => 'ping-g430-driver-review',
                'tags' => ['featured', 'driver', 'golf'],
                'categories' => ['Reviews', 'News'],
                'custom_fields' => [
                    'author_name' => 'James Holloway',
                    'read_time' => 7,
                    'excerpt' => 'From impossible bunker escapes to near-mythical putting runs, here are the moments that shaped this year’s Masters.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1502877338535-766e1452684a?auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Augusta Golf Course'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'From impossible bunker escapes to near-mythical putting runs, here are the moments that shaped this year’s Masters.',
                                'We take a deep dive into player choices, course management strategies, and equipment performance under pressure at Augusta National.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'The Rise of Sustainable Golf Courses',
                'slug' => 'short-game-clinic-drills',
                'tags' => ['trending', 'guides', 'golf'],
                'categories' => ['Guides', 'News'],
                'custom_fields' => [
                    'author_name' => 'Lydia Park',
                    'read_time' => 6,
                    'excerpt' => 'Architects and course designers are reshaping golf’s environmental footprint without sacrificing playability.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Eco-Friendly Golf Course'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Architects and course designers are reshaping golf’s environmental footprint without sacrificing playability.',
                                'Sustainable turf management and water conservation techniques are changing how modern layout clinics and maintenance routines are executed.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Inside the World of Elite Golf Training',
                'slug' => 'best-budget-golf-balls-2026',
                'tags' => ['fitness', 'ball', 'golf'],
                'categories' => ['Guides', 'Opinion'],
                'custom_fields' => [
                    'author_name' => 'Daniel Ko',
                    'read_time' => 5,
                    'excerpt' => 'A look at the biomechanics, tech, and mental conditioning behind today’s best players.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Golf Training'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'A look at the biomechanics, tech, and mental conditioning behind today’s best players.',
                                'From launch monitor data to ball selection protocols, modern elite instruction leaves no detail unexamined.'
                            ]
                        ]
                    ]
                ]
            ],
            // Standard site content
            [
                'title' => 'Foresight GCQuad Review: The Best Golf Simulator on the Market',
                'slug' => 'foresight-gcquad-simulator-review',
                'tags' => ['featured', 'review', 'golf', 'hardware', 'editors-pick', 'equipment'],
                'categories' => ['Reviews', 'Equipment Reviews', 'Simulators'],
                'custom_fields' => [
                    'author_name' => 'Alex Green',
                    'author_bio' => 'PGA-certified instructor and golf tech reviewer.',
                    'read_time' => 10,
                    'game_title' => 'Foresight Sports GCQuad',
                    'developer' => 'Foresight Sports',
                    'publisher' => 'Foresight Sports',
                    'release_date' => 'October 1, 2024',
                    'platforms' => 'Indoor/Outdoor Use, PC Interface',
                    'genre' => 'Launch Monitor',
                    'rating' => 5,
                    'excerpt' => 'Foresight’s GCQuad delivers unmatched accuracy and data, making it the definitive training tool for serious golfers.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1599380629737-142c67623916?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Foresight GCQuad Launch Monitor',
                            'caption' => 'The GCQuad’s four-camera system provides incredible data accuracy',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The GCQuad is a game-changer. After years of using various launch monitors, Foresight Sports has delivered a machine that doesn\'t just tell you what happened, but precisely *why* it happened.',
                                'This quadrascopic technology translates the real-world ball flight and club data with stunning accuracy, leveraging the power of high-speed cameras and photometric analysis to create something truly special.',
                                'From the moment you hit your first shot, the GCQuad grabs you and never lets go of your swing data.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'award',
                        'data' => [
                            'subcategory' => 'Best in Class Hardware',
                            'productName' => 'Foresight GCQuad',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1599380629737-142c67623916?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'winner' => true,
                            'rating' => 5.0,
                            'strapline' => 'The new gold standard for golf simulation',
                            'caption' => 'A masterclass in data precision and reliability'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Data That Transforms Your Game',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The sheer volume of customizable data in the GCQuad is staggering. Metrics like club path, angle of attack, face angle, and loft are all tracked with sub-millimeter precision.',
                                'I’ve used the monitor in three different settings—indoor simulator, outdoor range, and short game area—and the consistency was phenomenal. Minor adjustments I made to my grip or stance were instantly reflected in the numbers.',
                                'The software integration is phenomenal. Every session is logged, allowing for deep analysis of performance trends and personalized coaching.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Simulation Experience',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Paired with the FSX software, the simulator experience is second to none. The courses look spectacular, and the ball flight physics feel entirely realistic.',
                                'Unlike radar-based systems, the GCQuad uses photometric cameras to capture the moment of impact directly, making putting and chipping far more accurate and rewarding.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Pro Tip: Always calibrate your metallic club-dots before each session to ensure maximum club data accuracy.'
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Category', 'Score', 'Comment'],
                                ['Data Accuracy', '10/10', 'Unmatched ball and club data'],
                                ['Simulation Feel', '9/10', 'Incredible course realism'],
                                ['Portability', '7/10', 'Heavier than competitors'],
                                ['Ease of Use', '9/10', 'Intuitive interface and setup'],
                                ['Value (Professional)', '10/10', 'Essential tool for coaches'],
                                ['Value (Amateur)', '7/10', 'High initial cost']
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'GCQuad Launch Monitor',
                            'brand' => 'Foresight Sports',
                            'productName' => 'Base Model',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1599380629737-142c67623916?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'price' => 15999.00,
                            'currency' => '£',
                            'description' => 'The industry-leading launch monitor for indoor and outdoor use. Price includes FSX 2020 software.',
                            'link' => 'https://example.com/gcquad',
                            'linkText' => 'Check Pricing & Packages',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 5.0,
                                'pros' => [
                                    'Superior accuracy across all shot types',
                                    'Excellent club data capture',
                                    'Robust build quality and reliability',
                                    'Deep analytical software'
                                ],
                                'cons' => [
                                    'The price tag is significant',
                                    'Requires marked balls for spin-axis data',
                                    'Large form factor'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The GCQuad isn\'t just a simulator—it\'s a fully-fledged professional analysis tool that belongs in every serious golfer\'s bag.',
                            'attribution' => 'Alex Green, GolfTech Magazine'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Verdict',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Foresight GCQuad is a monumental achievement that has set a new, impossibly high bar for launch monitor technology. It justifies its premium cost with unparalleled accuracy and functionality.',
                                'While the investment is substantial, for coaches, custom fitters, and dedicated amateurs, this device is essential for true game improvement.',
                                'If you’re serious about golf data, the GCQuad is the new gold standard.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'The 2028 Ryder Cup: Everything We Know About the Next Venue and Format Changes',
                'slug' => 'ryder-cup-2028-venue-format-rumors',
                'tags' => ['featured', 'news', 'golf', 'tournament', 'breaking-news', 'europe-vs-usa'],
                'categories' => ['News', 'Tournament Updates', 'Ryder Cup'],
                'custom_fields' => [
                    'author_name' => 'Adrian Scott',
                    'author_bio' => 'Golf analyst covering major tournaments and course architecture.',
                    'read_time' => 8,
                    'game_title' => '2028 Ryder Cup',
                    'developer' => 'PGA/European Tour',
                    'publisher' => 'International Golf Federation',
                    'release_date' => 'September 29, 2028 (Scheduled)',
                    'platforms' => 'Bethpage Black, USA (Rumored)',
                    'genre' => 'Team Tournament',
                    'rating' => 4,
                    'excerpt' => 'The next US-hosted Ryder Cup is coming. Here\'s every rumored venue detail, captain prediction, and proposed format change compiled in one place.'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Ryder Cup 2028: The Battle for Bethpage?',
                            'subtitle' => 'Everything we know about the next US-hosted venue',
                            'ctaText' => 'Read Venue Analysis',
                            'ctaUrl' => '#specs',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1546961129-3fd426210f81?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'While the dust settles on the 2025 event, speculation about the 2028 Ryder Cup is already building. Set to be hosted by the USA, industry insiders and regional golf bodies are hinting strongly at a return to a legendary public course.',
                                'Based on leaked committee notes and municipal spending, here\'s everything we currently know—and what we can reasonably predict—about the 2028 clash.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Remember: The official announcement is pending. All course and captain information is based on rumors and analyst predictions.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Expected Venue & Logistics',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Component', 'Rumored Spec', 'Source'],
                                ['Host Course', 'Bethpage Black, New York', 'Municipal documentation leaks'],
                                ['US Captain', 'Tiger Woods / Phil Mickelson', 'PGA insider predictions'],
                                ['European Captain', 'Graeme McDowell / Ian Poulter', 'Sky Sports analysts'],
                                ['Ticket Allocation', '75,000 Spectators Daily', 'Logistics planning documents'],
                                ['Format Change', 'Friday Four-balls Only', 'Committee meeting minutes (unconfirmed)'],
                                ['Release Window', 'Late September 2028', 'Standard tournament scheduling']
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Predicted Match Statistics',
                            'stats' => [
                                ['number' => '7350', 'label' => 'Yardage (Approx.)', 'icon' => '⛳'],
                                ['number' => '11', 'label' => 'Major Events Hosted', 'icon' => '🏆'],
                                ['number' => '2x', 'label' => 'Expected Course Difficulty', 'icon' => '⛰️'],
                                ['number' => '£200', 'label' => 'Daily Ticket Price (Est.)', 'icon' => '💷']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Proposed Format and Course Adjustments',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Full Monday-Sunday practice and media week planned to ease congestion',
                                'Potential removal of the controversial 17th hole bunker (The Black Hole)',
                                'AI-powered crowd control and augmented reality fan experiences',
                                'Increased prize money for team members (non-sanctioned prize fund change)',
                                'A dedicated "LIV vs PGA" charity challenge match on Wednesday',
                                'The European team may field a younger roster focused on accuracy over power'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Bethpage Black 18th',
                                    'description' => 'The iconic finishing hole',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1546961129-3fd426210f81?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Bethpage 18th hole'
                                ],
                                [
                                    'title' => 'Potential Team USA Captain',
                                    'description' => 'A former US team legend is heavily rumored',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1628126744312-d815779148d8?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Golf Captain Concept'
                                ],
                                [
                                    'title' => 'New York City Backdrop',
                                    'description' => 'The metropolitan location is a key draw',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1574765955613-39f5c2a13f9c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'New York City skyline'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'testimonial',
                        'data' => [
                            'layout' => 'grid',
                            'testimonials' => [
                                [
                                    'text' => 'Bethpage Black is truly the most challenging public course in the world. It will test the nerve of every player.',
                                    'author' => 'Mark O\'Meara',
                                    'role' => 'Former Major Winner',
                                    'rating' => 5
                                ],
                                [
                                    'text' => 'Moving Four-ball to only Friday would dramatically change the Sunday Singles dynamic. It\'s a fascinating tactical move.',
                                    'author' => 'Sarah Davies',
                                    'role' => 'Golf Strategy Analyst',
                                    'rating' => 4
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Analyst Prediction',
                            'paragraphs' => [
                                'The selection of Bethpage Black signals the USA team\'s intent: brute-force strategic golf. This course demands long, straight drives and pinpoint iron play, favoring the current generation of American bombers.',
                                'The European team will need captains who can out-strategize the opposition in the Foursomes to overcome the raw power advantage of the US team on this layout.',
                                'Official news is expected in late 2026 at the earliest.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'The US Open Course Guide: How to Master Pinehurst No. 2, Hole by Hole Strategy',
                'slug' => 'pinehurst-us-open-course-strategy-guide',
                'tags' => ['featured', 'guide', 'golf', 'strategy', 'how-to', 'us-open'],
                'categories' => ['Guides', 'Advanced Strategies', 'Tournament Updates'],
                'custom_fields' => [
                    'author_name' => 'Adrian Scott',
                    'author_bio' => 'Golf analyst covering major tournaments and course architecture.',
                    'read_time' => 25,
                    'game_title' => 'US Open 2029 (Pinehurst No. 2)',
                    'developer' => 'USGA',
                    'publisher' => 'PGA Tour',
                    'release_date' => 'June 2029',
                    'platforms' => 'Pinehurst No. 2, North Carolina',
                    'genre' => 'Tournament Strategy',
                    'rating' => 5,
                    'excerpt' => 'Conquer the legendary turtleback greens and sandy native areas. This guide provides step-by-step strategies for every challenging hole at Pinehurst No. 2.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1546961129-3fd426210f81?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Pinehurst No. 2 course aerial',
                            'caption' => 'The legendary turtleback greens of Pinehurst No. 2 demand accuracy and humility.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Pinehurst No. 2 is not a course you attack; it\'s a course you manage. The primary defense isn\'t length, but the severely sloped, dome-shaped "turtleback" greens that repel anything short of a perfect approach.',
                                'Our comprehensive guide breaks down the essential preparation and specific strategy needed to navigate the 18 holes of this US Open venue and avoid the infamous "Pinehurst Shuffle."'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => '⚠️ The rough at Pinehurst is native sand and wiregrass. Always prefer a greenside bunker to being short-sided in the native area.'
                        ]
                    ],
                    [
                        'type' => 'schema',
                        'data' => [
                            'schemaType' => 'how-to',
                            'title' => 'How to Prepare for Pinehurst\'s Greens',
                            'description' => 'Follow these steps to develop a Pinehurst-ready short game',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1546961129-3fd426210f81?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80']
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Practice the "Texas Wedge": Learn to putt from 20-30 yards off the green.',
                                'Master the Punch Shot: Keep approaches low and running onto the green surface.',
                                'Prioritize Green Center: Never aim for pins tucked on the edges; safety is paramount.',
                                'Simulate Bailout Areas: Practice hitting your chip/pitch to a specific 10x10 foot area.',
                                'Know Your Miss: Always miss below the hole or toward the largest collection area.',
                                'Commit to the Speed: The greens are fast; focus 80% of effort on pace control.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Right Gear for the Job',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Recommended Ball and Driver Setup',
                            'subtitle' => 'Maximize distance while minimizing spin on approaches',
                            'specs' => [
                                ['text' => 'Driver Loft', 'value' => '9.5° or Higher'],
                                ['text' => 'Ball Type', 'value' => 'Low-Spin/High-Speed (Pro V1x/TP5x)'],
                                ['text' => 'Long Iron Choice', 'value' => '3-Hybrid (for high launching approach)'],
                                ['text' => 'Wedge Configuration', 'value' => '54°/58° (High Bounce Sand Wedge)'],
                                ['text' => 'Putter Insert', 'value' => 'Firm (for better speed control)'],
                                ['text' => 'Focus Stat', 'value' => 'Greens in Regulation (GIR)']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Low spin balls help control distance on the hard turf',
                                'High bounce wedges prevent digging in the native sand',
                                'A hybrid is more reliable than a long iron into firm greens'
                            ],
                            'cons' => [
                                'Sacrificing spin can make short chips harder to control',
                                'High loft on driver may sacrifice some rollout distance'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Strategy Focus: Hole 4 (The Short Par-4)',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Hole 4 is only 390 yards but is a notorious card-wrecker. The green is heavily guarded and features one of the most severe fall-offs on the course. A par here is a massive win.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Shot Phase', 'Target', 'Counter Strategy'],
                                ['Tee Shot (Driver)', 'Left edge of fairway', 'Avoid the right-side bunkers at all costs'],
                                ['Approach (100-120 yd)', 'Front-center of the green', 'Never go long or past the pin'],
                                ['Missed Green Chip', 'Aim for the back of the green', 'Use Texas Wedge or high-bounce 58°'],
                                ['Green Reading', 'Always break less than you think', 'The slope is deceptive; trust your line'],
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Pro Strategy for Hole 4',
                            'paragraphs' => [
                                'Lay up your tee shot to precisely 110 yards from the front edge. This guarantees a full wedge shot, which offers the most spin control for hitting the small target. A half-swing wedge is a recipe for disaster.',
                                'If you miss the green, your second shot is still likely to be 30 yards. Focus on getting the ball to stop near the middle—two-putt is the objective.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Wedge Type Comparison for Pinehurst',
                            'productA' => 'High-Bounce Grind (12°+)',
                            'productB' => 'Low-Bounce Grind (4°-8°)',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Native Area Recovery',
                                    'items' => [
                                        ['value' => 'Excellent (Prevents digging)'],
                                        ['value' => 'Poor (Prone to burying the leading edge)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Tight Lie Pitching',
                                    'items' => [
                                        ['value' => 'Acceptable (Requires precise strike)'],
                                        ['value' => 'Superior (Cleaner contact on hardpan)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Primary Use',
                                    'items' => [
                                        ['value' => 'Sand, Rough, Soft Conditions'],
                                        ['value' => 'Fairway, Hardpan, Texas Wedge']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Recommendation',
                                    'items' => [
                                        ['value' => 'Essential for Bunker Play'],
                                        ['value' => 'Useful for Off-Green Putts']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Hidden Tips and Local Secrets',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'accordion',
                        'data' => [
                            'title' => 'Pinehurst Caddie Secrets',
                            'items' => [
                                [
                                    'question' => 'What is the safest way to approach the 18th green?',
                                    'answer' => 'Aim for the absolute center of the green, regardless of pin position. A 40-foot putt up or down the slope is better than chipping from off the green.',
                                    'isOpen' => true
                                ],
                                [
                                    'question' => 'Where is the hidden spectator viewing area for Hole 9?',
                                    'answer' => 'Behind the first native area on the left of the fairway, there is a small, elevated mound where caddies recommend setting up camp for the best view of the approach shots.'
                                ],
                                [
                                    'question' => 'How to play the notorious "waste area" on Hole 5?',
                                    'answer' => 'Use a 7-iron or 9-iron to punch the ball out low and maintain momentum. Do not attempt to hit a high-lofted wedge—it will catch grass and fly nowhere.'
                                ]
                            ],
                            'allowMultipleOpen' => false
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Pinehurst No. 2 is a second-shot golf course. You hit the ball in the center, and you trust it. Nothing cute. Nothing fancy.',
                            'attribution' => 'Payne Stewart, 1999 US Open Champion'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'The Open Championship 2029 Review: A Masterclass in Links Strategy and Weather Management',
                'slug' => 'the-open-championship-2029-review',
                'tags' => ['featured', 'review', 'golf', 'tournament', 'links-golf', 'major-championship'],
                'categories' => ['Reviews', 'Tournament Analysis', 'Features'],
                'custom_fields' => [
                    'author_name' => 'Adrian Scott',
                    'author_bio' => 'Golf analyst covering major tournaments and course architecture.',
                    'read_time' => 10,
                    'game_title' => 'The 158th Open Championship',
                    'developer' => 'R&A',
                    'publisher' => 'Royal St George\'s',
                    'release_date' => 'July 2029',
                    'platforms' => 'Links Golf Course',
                    'genre' => 'Tournament',
                    'rating' => 5,
                    'excerpt' => 'This year\'s Open was less a tournament and more a test of endurance. A breathtaking display of links strategy and emotional resilience, it redefined the concept of a major championship.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1546961129-3fd426210f81?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Links golf course with heavy weather',
                            'caption' => 'The final day brought relentless rain and wind, challenging the leaders to the very end.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Open Championship 2029',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1546961129-3fd426210f81?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'subtitle' => 'A generational major that reminded us what links golf is all about.',
                            'pros' => [
                                'Unforgettable final-round duel',
                                'Brutal, but fair, weather conditions',
                                'Superb course setup by the R&A',
                                'Emotional resilience and strategy on full display'
                            ],
                            'cons' => [
                                'Early round play was slow',
                                'Uncertainty over final-round tee times'
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The 158th Open Championship will be remembered not for the sunny skies of Thursday, but for the relentless, horizontal rain of Sunday. It was a weather system so ferocious it stripped the field down to two players: the seasoned links veteran and the young, aggressive newcomer.',
                                'The course setup was a masterpiece of controlled chaos. The R&A allowed the native grass to grow, tightening the fairways, but kept the green speeds manageable despite the moisture, forcing players to hit the proper trajectory and angle of attack.',
                                'It was a golf tournament that delivered a story of dramatic tactical depth, where every shot carried the weight of a decade of preparation. This event cemented the links format as the purest test in golf.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Tactical Depth of the Final Round',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Watching the winner, [Champion Name], navigate the back nine was like witnessing a chess grandmaster play a blitz match. His decision to putt from 40 yards on the 15th, rather than chip, was a stroke of genius that saved a certain bogey and maintained his momentum.',
                                'The wind was the ultimate antagonist, dictating club selection from the moment the sun rose. On the 475-yard par-4 13th, the champion hit a 4-iron off the tee and a 3-wood into the green—a sequence that perfectly captured the defensive, strategic mindset required.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Titleist Pro V1 Golf Balls',
                            'brand' => 'Titleist',
                            'productName' => 'Tour Performance Golf Balls',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1593113595394-2c1d457a4e69?auto=format&fit=crop&w=800&q=80'
                            ],
                            'price' => 47.99,
                            'currency' => '£',
                            'description' => 'The #1 ball in golf. Exceptional distance, control, and greenside spin trusted by PGA pros.',
                            'link' => 'https://example.com/titleist-pro-v1',
                            'linkText' => 'Check Price',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 4.9,
                                'pros' => [
                                    'Outstanding control and feel',
                                    'Long distance performance',
                                    'Excellent durability',
                                    'Tour-trusted design'
                                ],
                                'cons' => [
                                    'Premium price point',
                                    'Beginners may not notice full benefits'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Statistical Breakdown: The Champion\'s Edge',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Statistic', 'Champion (Rank)', 'Field Average'],
                                ['Strokes Gained: Off-the-Tee', '4.5 (1st)', '-0.2'],
                                ['Strokes Gained: Approach', '1.2 (10th)', '-1.5'],
                                ['Sand Save %', '85% (1st)', '55%'],
                                ['Scrambling from Rough', '62% (2nd)', '41%'],
                                ['3-Putt Avoidance', '98% (1st)', '88%'],
                                ['Total Score', '-12', '+3'],
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'The Champion\'s mastery of the short game (Sand Save and 3-Putt Avoidance) was the critical difference on the fast, sloped greens.'
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'The Open demands that you respect the course. You don\'t beat the course; you survive it. That\'s the lesson every year.',
                            'attribution' => 'Tom Watson, Open Champion'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Verdict',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The 2029 Open Championship was an instant classic. It delivered the strategic complexity, emotional drama, and raw spectacle that defines a true sporting contest.',
                                'While the weather was unforgiving, the contest was pure. It was a reminder that golf, at its most elemental, is a battle against the elements and against oneself. This is the new benchmark for a major championship.',
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => '2026 Driver Buying Guide: The Best Big Sticks for Every Swing Speed and Handicap',
                'slug' => 'best-golf-drivers-2026-buying-guide',
                'tags' => ['featured', 'guide', 'golf', 'equipment', 'buying-guide', 'gear-review'],
                'categories' => ['Guides', 'Equipment Reviews', 'Gear'],
                'custom_fields' => [
                    'author_name' => 'Adrian Scott',
                    'author_bio' => 'Golf analyst covering major tournaments and course architecture.',
                    'read_time' => 10,
                    'game_title' => 'Golf Driver Technology 2026',
                    'developer' => 'Callaway, Titleist, TaylorMade',
                    'publisher' => 'Golf Digest',
                    'release_date' => 'January 2026',
                    'platforms' => 'Golf Course',
                    'genre' => 'Equipment',
                    'rating' => 4,
                    'excerpt' => 'Find your perfect match for distance and forgiveness. We break down the best drivers of 2026, comparing launch, spin, and adjustability across different budget levels.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1546961129-3fd426210f81?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Professional golf driver on a tee',
                            'caption' => 'Choosing the right driver can add 20+ yards to your drives and improve accuracy.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The 2026 driver market is saturated with technology, but not all of it will benefit your specific swing. This guide cuts through the marketing hype to match you with a driver that suits your swing speed, typical ball flight, and handicap.',
                                'Our picks focus on three key categories: Maximum Forgiveness, Ultimate Distance (Low Spin), and The Mid-Handicapper All-Rounder.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Budget Pick: Maximum Forgiveness',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'The Best Driver for High-Handicappers',
                            'subtitle' => 'Focus on maximizing the Center of Gravity (CG) for huge sweet spots',
                            'specs' => [
                                ['text' => 'Recommended Model', 'value' => 'Cobra Air-X 2026'],
                                ['text' => 'Target Swing Speed', 'value' => 'Below 95 mph (Slower)'],
                                ['text' => 'Spin Rate', 'value' => 'High (2,800+ RPM)'],
                                ['text' => 'Weighting', 'value' => 'Fixed Heel Weight (Draw Bias)'],
                                ['text' => 'Custom Shaft Cost', 'value' => 'Low (Standard Graphite)'],
                                ['text' => 'Price Tier', 'value' => '$$'],
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Extremely lightweight, promoting higher clubhead speed.',
                                'Strong anti-slice (draw) bias built-in.',
                                'Largest sweet spot on the market for 2026.'
                            ],
                            'cons' => [
                                'Higher spin limits maximum distance for fast hitters.',
                                'Lack of advanced adjustability.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Premium Pick: Ultimate Distance',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                '**Model:** TaylorMade Stealth 7.0',
                                '**Price:** $$$$ (Premium Tier)',
                                '**Target:** Tour Players and Single-Digit Handicappers',
                                '**Key Feature:** Extreme forward CG for ultra-low spin (under 2,000 RPM)',
                                '**Ideal Launch Angle:** 10–13 degrees',
                                '**Required Accuracy:** High (Misses are heavily punished)',
                                '**Verdict:** The fastest ball speed in golf, but requires a consistent strike.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Comparison: Spin vs. Forgiveness',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Driver Head Technology Comparison',
                            'productA' => 'Low-Spin/Forward CG (Distance)',
                            'productB' => 'High-MOI/Rear CG (Forgiveness)',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Ball Speed Off Center',
                                    'items' => [
                                        ['value' => 'High (But speed drops quickly)'],
                                        ['value' => 'High (Maintains speed across the face)'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Launch Angle',
                                    'items' => [
                                        ['value' => 'Lower Trajectory'],
                                        ['value' => 'Higher Trajectory'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Typical User',
                                    'items' => [
                                        ['value' => 'Fast Swing Speeds (105+ mph)'],
                                        ['value' => 'Slow/Moderate Swing Speeds'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Adjustable Weighting',
                                    'items' => [
                                        ['value' => 'Forward Track (Fine-tuning spin)'],
                                        ['value' => 'Rear/Perimeter (Max Forgiveness)'],
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Final Fitting Tip',
                            'paragraphs' => [
                                'The shaft is just as important as the clubhead. Do not buy off the rack. A stiff shaft that is too heavy, or a regular shaft that is too light, will undo all the benefits of the premium head.',
                                'Always get professionally fitted to determine the ideal shaft flex, weight, and kick point for your unique swing profile.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'The 2027 Ryder Cup Wishlist: 5 Changes the US Team Needs to Win on European Soil',
                'slug' => 'us-ryder-cup-wishlist-2027-europe',
                'tags' => ['featured', 'opinion', 'golf', 'ryder-cup', 'team-usa', 'wishlist'],
                'categories' => ['Opinion', 'Tournament Analysis', 'Features'],
                'custom_fields' => [
                    'author_name' => 'Adrian Scott',
                    'author_bio' => 'Golf analyst covering major tournaments and course architecture.',
                    'read_time' => 7,
                    'game_title' => 'The Ryder Cup',
                    'developer' => 'PGA/DP World Tour',
                    'publisher' => 'Team Competition',
                    'release_date' => 'September 2027',
                    'platforms' => 'Europe',
                    'genre' => 'Team Strategy',
                    'rating' => 4,
                    'excerpt' => 'The US team’s continued struggles in Europe demand structural changes, not just new captains. We outline the five essential shifts needed to break the overseas losing streak.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1546961129-3fd426210f81?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Ryder Cup team celebration',
                            'caption' => 'The US team needs to find a way to translate its overwhelming talent into cohesion abroad.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The pattern is grimly familiar: dominant US victories at home, followed by soul-crushing defeats on European soil. The next contest in Ireland is two years away, but the planning must start now. Europe is simply better at team dynamics and handling the pressure of a hostile crowd.',
                                'It’s time to move past the "best 12 players" model and embrace a strategy built on cohesion, specialization, and adapting to links-style pressure.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Wishlist Item 1: The Permanent Captain',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Europe has benefited from a consistent pool of senior leadership (McGinley, Clarke, Donald). The US needs to appoint a "Permanent Captain" or Chairman of the Ryder Cup Committee (similar to what Steve Stricker did in 2021) who stays in the role for 4-6 years. This ensures institutional knowledge isn\'t lost every two years.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'You cannot build a winning culture in 18 months. You need legacy knowledge, trust, and a consistent vision that stretches across multiple events.',
                            'attribution' => 'Paul Azinger, Former US Ryder Cup Captain'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Remaining 4 Essential Changes',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'items' => [
                                '**More Captain’s Picks, Fewer Points:** Reduce the number of automatic qualifiers to eight (from six). This grants the captain more flexibility to choose players who are *in form* and, crucially, players who excel in match play or links conditions, regardless of their world ranking.',
                                '**Mandatory Team Training Week:** The US players need to practice as a unit on the host course at least once during the season, not just three days before the event. This builds chemistry and provides course strategy exposure under pressure.',
                                '**Specialized Vice Captains:** Appoint specific vice captains for Foursomes and Fourballs. One coach should focus exclusively on the alternate-shot format, which is where the US team consistently struggles due to a lack of pre-planned partnerships.',
                                '**Embrace the Underdog Role:** Stop trying to treat the Ryder Cup like a regular tournament. Encourage the team to lean into the hostility and the team element. The European team’s fire is contagious; the US needs to find its own competitive edge and not rely on quiet confidence.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'The US team needs at least two "Grinders"—players known for their fighting spirit and short-game dominance, even if they aren\'t Top 10 in the world ranking.'
                        ]
                    ]
                ]
            ]
        ];

        foreach ($articles as $article) {
            $this->createArticle($article);
        }
    }

    private function createArticle(array $data): void
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => $data['title'] . ' - VOGUE NOIR',
            'meta_description' => $data['custom_fields']['excerpt'] ?? '',
            'site_id' => $this->site->id,
        ]);

        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, $this->site->id);;
            $page->tags(true)->attach($tag->id);
        }

        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, $this->site->id);;
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
            'site_id' => $this->site->id,
            'title' => 'About Golf Monthly',
            'slug' => 'about-us',
            'status' => 'published',
            'meta_title' => 'About Golf Monthly',
            'meta_description' => 'The best golf equipment reviews, instruction tips, and tour news.',
            'page_type' => 'landing-page',
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'About The Golf Journal',
                    'subtitle' => 'Expert coverage of the game, courses, and culture',
                    'ctaText' => 'Meet the Team',
                    'ctaUrl' => '#team',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1502877338535-766e1452684a?auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The Golf Journal was founded to celebrate the sport’s traditions while covering modern innovations in technique, equipment and course design. We provide rigorous analysis and first-hand reporting from the world’s top tournaments.',
                        'Our editorial team includes former players, course architects, and instructors who test equipment, interview leading figures, and travel to play and review courses around the globe.',
                        'Whether you are a weekend player or a touring professional, The Golf Journal aims to deepen your understanding of the game and help you enjoy it more.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Our Reach & Impact',
                    'stats' => [
                        ['number' => '15', 'label' => 'Years Covering Pro Tours', 'icon' => '🏆'],
                        ['number' => '1,200+', 'label' => 'Courses Played & Reviewed', 'icon' => '⛳'],
                        ['number' => '800k', 'label' => 'Monthly Readers', 'icon' => '📖'],
                        ['number' => '40+', 'label' => 'Countries Covered', 'icon' => '🌍']
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Expert Team',
                    'subtitle' => 'Players, Coaches & Course Experts',
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
                            'name' => 'Michael Harrington PGA',
                            'role' => 'Head Analyst — Tour Performance',
                            'bio' => 'Former PGA Tour coach with two decades of experience analyzing swing mechanics and tournament strategy.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'michael.harrington@golfjournal.com'
                        ],
                        [
                            'name' => 'Laura Kim',
                            'role' => 'Course Architecture Editor',
                            'bio' => 'Specialist in course design and sustainability, writing detailed reviews of layouts both historic and new.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'laura.kim@golfjournal.com'
                        ],
                        [
                            'name' => 'Samir Patel',
                            'role' => 'Equipment & Technology Editor',
                            'bio' => 'Former club engineer and fitter who tests clubs, balls and launch-monitor tech for performance gains.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1544725176-7c40e5a2c9f1?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'samir.patel@golfjournal.com'
                        ],
                        [
                            'name' => 'Grace O’Connor',
                            'role' => 'Women’s Golf Correspondent',
                            'bio' => 'Covers the LPGA and rising stars, with a focus on player development and championship coverage.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1507003216992-7f0f35a1c79b?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'grace.oconnor@golfjournal.com'
                        ],
                        [
                            'name' => 'Carlos Mendes',
                            'role' => 'Travel & Course Reporter',
                            'bio' => 'Road-tested on every continent — Carlos uncovers bucket-list courses and local gems.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'carlos.mendes@golfjournal.com'
                        ],
                        [
                            'name' => 'Emily Harper',
                            'role' => 'Training & Player Development Editor',
                            'bio' => 'Instructor and sports-science advocate helping amateurs and pros improve through practical drills.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'emily.harper@golfjournal.com'
                        ]
                    ]
                ],
                'order' => 5
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Evaluation Standards',
                    'level' => 2
                ],
                'order' => 6
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Our course and equipment evaluations are based on standardized testing protocols and blind comparative reviews. We rate playability, design integrity, conditions, and value to give readers clear guidance.'
                    ]
                ],
                'order' => 7
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'A — Championship Quality: Course plays to tour-level standards',
                        'B — Excellent: Outstanding design and condition for serious players',
                        'C — Good: Solid course for regular play and enjoyment',
                        'D — Fair: Serviceable with notable issues',
                        'E — Poor: Significant shortcomings'
                    ]
                ],
                'order' => 8
            ],
            [
                'type' => 'note',
                'data' => [
                    'title' => 'Our Independence',
                    'paragraphs' => [
                        'We pay for all course stays and equipment reviewed, and maintain editorial independence from manufacturers and resorts.',
                        'Our recommendations are based solely on quality, value, and reader benefit.'
                    ],
                    'alignment' => 'fullscreen'
                ],
                'order' => 9
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'Golf is deceptively simple and endlessly complicated.',
                    'attribution' => 'Arnold Palmer'
                ],
                'order' => 10
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createContactPage(): void
    {
        $page = Page::create([
            'title' => 'Contact Golf Monthly',
            'page_type' => 'landing-page',
            'slug' => 'contact-us',
            'status' => 'published',
            'meta_title' => 'Contact Golf Monthly',
            'meta_description' => 'Get in touch with the Golf Monthly editorial team.',
            'site_id' => $this->site->id,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Get In Touch',
                    'subtitle' => 'Questions about courses, gear, or lessons? We’re here to help.',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1502877338535-766e1452684a?auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'GolfDaily Editorial',
                    'role' => 'Contact Information',
                    'email' => 'editorial@golfdaily.com',
                    'phone' => '+1 (555) 672-8891',
                    'address' => "GolfDaily Media\n18 Fairway Drive\nScottsdale, AZ 85255\n\nOffice Hours:\nMon–Fri: 9AM–5PM MST",
                    'displayType' => 'contact',
                    'image' => [
                        'src' => 'https://images.unsplash.com/photo-1593113598332-cd6a9f67b39f?auto=format&fit=crop&w=800&q=80',
                        'alt' => 'Golf Editorial Office'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'We welcome submissions from golf pros, course photographers, and equipment experts. Pitch your ideas to editorial@golfdaily.com.',
                        'For website support or technical issues, please email support@golfdaily.com.',
                        'Press or sponsorship inquiries should be sent to press@golfdaily.com.'
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Send Us a Message',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => false,
                    'showSubject' => true,
                    'showMessage' => true,
                    'submitButtonText' => 'Send Message',
                    'requireName' => true,
                    'requireEmail' => true,
                    'requireMessage' => true
                ],
                'order' => 4
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createMenuNavItems(): void
    {
        // Fetch the required pages only after they have been created in run()
        $pages = Page::where('site_id', $this->site->id)->get();

        // Lookups are now safe and will find the pages
        $aboutPage = $pages->where('slug', 'about-us')->first();
        $contactPage = $pages->where('slug', 'contact-us')->first();
        $homePage = $pages->where('slug', 'home')->first();

        if ($aboutPage) {
            MenuItem::create([
                'label' => $aboutPage->title,
                'menu_id' => $this->menu->id,
                'target_type' => 'page',
                'target_id' => $aboutPage->id,
                'is_active' => true,
                'sort_order' => 30
            ]);
        }

        if ($contactPage) {
            MenuItem::create([
                'label' => $contactPage->title,
                'menu_id' => $this->menu->id,
                'target_type' => 'page',
                'target_id' => $contactPage->id,
                'is_active' => true,
                'sort_order' => 30
            ]);
        }

        if ($homePage) {
            MenuItem::create([
                'label' => $homePage->title,
                'menu_id' => $this->menu->id,
                'target_type' => 'page',
                'target_id' => $homePage->id,
                'is_active' => true,
                'sort_order' => 30
            ]);
        }
    }
}