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

class GamesRadarSeeder extends Seeder
{
    private $pageRepository;
    private $blockRepository;
    private $tagRepository;
    private $categoryRepository;
    private $blockParserService;
    private \App\Models\Model $site;
    private \App\Models\Model $menu;
    private \App\Models\Model $footerMenu;

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
        $this->createFooterMenu();
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
        $this->site = Site::create([
            'name' => 'GamesRadar+ - Gaming News & Reviews',
            'slug' => 'gamesradar',
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

    private function createFooterMenu(): void
    {
        $this->footerMenu = Menu::create([
            'name' => 'Footer Menu',
            'slug' => 'footer-menu',
            'site_id' => $this->site->id,
            'is_active' => true,
        ]);
    }

    private function createTags(): void
    {
        $tags = [
            'featured', 'breaking-news', 'exclusive', 'trending', 'editors-pick',
            'ps5', 'xbox-series-x', 'nintendo-switch', 'pc', 'mobile',
            'action', 'rpg', 'fps', 'strategy', 'adventure', 'indie',
            'review', 'preview', 'news', 'guide', 'opinion',
            'multiplayer', 'single-player', 'co-op', 'battle-royale',
            'playstation', 'xbox', 'nintendo', 'steam',
            'e3', 'gamescom', 'game-awards', 'summer-game-fest',
            'best-of', 'game-of-the-year', 'must-play'
        ];

        foreach ($tags as $tagName) {
            $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
        }
    }

    private function createCategories(): void
    {
        $categories = [
            'News' => ['Industry News', 'Game Announcements', 'Updates & Patches'],
            'Reviews' => [
                'Game Reviews' => ['PS5', 'Xbox Series X', 'Nintendo Switch', 'PC', 'Mobile'],
                'Hardware Reviews' => ['Consoles', 'Accessories', 'Gaming Monitors', 'Headsets']
            ],
            'Previews' => ['Hands-On', 'First Look', 'Coming Soon'],
            'Guides' => [
                'Walkthroughs' => ['Tips & Tricks', 'Easter Eggs', 'Secrets'],
                'How To' => ['Beginner Guides', 'Advanced Strategies', 'Builds']
            ],
            'Features' => ['Deep Dives', 'Interviews', 'Opinion', 'Lists'],
            'Platforms' => ['PlayStation', 'Xbox', 'Nintendo', 'PC', 'Mobile'],
            'Genres' => ['Action', 'RPG', 'FPS', 'Strategy', 'Adventure', 'Indie']
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
            ['key' => 'game_title', 'name' => 'Game Title', 'type' => 'text'],
            ['key' => 'developer', 'name' => 'Developer', 'type' => 'text'],
            ['key' => 'publisher', 'name' => 'Publisher', 'type' => 'text'],
            ['key' => 'release_date', 'name' => 'Release Date', 'type' => 'text'],
            ['key' => 'platforms', 'name' => 'Platforms', 'type' => 'text'],
            ['key' => 'genre', 'name' => 'Genre', 'type' => 'text'],
            ['key' => 'rating', 'name' => 'Rating (out of 5)', 'type' => 'number'],
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
            'title' => 'GamesRadar+ - The Home of Gaming News & Reviews',
            'page_type' => 'content',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'GamesRadar+ - Gaming News, Reviews, Guides & Features',
            'meta_description' => 'Your ultimate destination for gaming news, game reviews, walkthroughs, features, and the latest gaming industry updates.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Home',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        MenuItem::create([
            'label' => 'About',
            'menu_id' => $this->footerMenu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Welcome to GamesRadar+',
                    'subtitle' => 'Your ultimate destination for gaming news, reviews, and guides',
                    'ctaText' => 'Latest Reviews',
                    'ctaUrl' => '#featured',
                    'secondaryCtaText' => 'Browse Guides',
                    'secondaryCtaUrl' => '/guides',
                    'showSearch' => true,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1538481199705-c710c4e965fc?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'info',
                'data' => [
                    'infoType' => 'info',
                    'description' => '🎮 Breaking: New AAA title announced at Summer Game Fest! Read our exclusive hands-on preview →'
                ],
                'order' => 2
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Featured Stories',
                    'subtitle' => 'The biggest gaming news and reviews',
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
                            'title' => 'Baldur\'s Gate 3 Review: A Modern RPG Masterpiece',
                            'slug' => 'baldurs-gate-3-review',
                            'excerpt' => 'Larian Studios delivers the definitive D&D experience with stunning depth, incredible writing, and endless possibilities.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Baldur\'s Gate 3'
                            ],
                            'badge' => [
                                'text' => '⭐ Editor\'s Choice',
                                'color' => 'primary'
                            ],
                            'meta' => [
                                'author' => 'Sarah Mitchell',
                                'date' => 'March 15, 2025',
                                'readTime' => '12 min read',
                                'category' => 'Review'
                            ]
                        ],
                        [
                            'title' => 'PlayStation 6: Everything We Know So Far',
                            'slug' => 'playstation-6-rumors',
                            'excerpt' => 'Sony\'s next-gen console is coming. Here\'s every leaked spec, rumored feature, and expert prediction.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1606144042614-b2417e99c4e3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'PlayStation console'
                            ],
                            'badge' => [
                                'text' => 'Breaking News',
                                'color' => 'success'
                            ],
                            'meta' => [
                                'author' => 'James Chen',
                                'date' => 'March 14, 2025',
                                'readTime' => '8 min read',
                                'category' => 'News'
                            ]
                        ],
                        [
                            'title' => 'Elden Ring DLC Guide: Shadow of the Erdtree Walkthrough',
                            'slug' => 'elden-ring-dlc-guide',
                            'excerpt' => 'Master the toughest bosses and find every secret in FromSoftware\'s massive expansion.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1552820728-8b83bb6b773f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Elden Ring'
                            ],
                            'badge' => [
                                'text' => 'Guide',
                                'color' => 'warning'
                            ],
                            'meta' => [
                                'author' => 'Marcus Reed',
                                'date' => 'March 13, 2025',
                                'readTime' => '25 min read',
                                'category' => 'Guides'
                            ]
                        ]
                    ]
                ],
                'order' => 4
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Why Gamers Trust Us',
                    'stats' => [
                        ['number' => '20M+', 'label' => 'Monthly Readers', 'icon' => '🎮'],
                        ['number' => '25+', 'label' => 'Years of Experience', 'icon' => '🏆'],
                        ['number' => '10,000+', 'label' => 'Games Reviewed', 'icon' => '⭐'],
                        ['number' => '50+', 'label' => 'Expert Writers', 'icon' => '✍️']
                    ]
                ],
                'order' => 5
            ],
            [
                'type' => 'divider',
                'data' => [
                    'style' => 'solid'
                ],
                'order' => 6
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Latest Gaming News',
                    'subtitle' => 'Stay up to date with the industry',
                    'level' => 2
                ],
                'order' => 7
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Microsoft announces new Xbox Game Pass Ultimate tier with day-one releases',
                        'Nintendo Direct showcases 15 upcoming Switch titles for 2025',
                        'Steam breaks concurrent user record with 35 million players online',
                        'Epic Games Store gives away three AAA titles this week',
                        'PlayStation Plus reveals March 2025 lineup with blockbuster additions',
                        'Indie darling "Hollow Dreams" sells 1 million copies in first week'
                    ]
                ],
                'order' => 8
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

    private function createArticles(): void
    {
        $articles = [
            [
                'title' => 'Baldur\'s Gate 3 Review: A Modern RPG Masterpiece That Redefines the Genre',
                'slug' => 'baldurs-gate-3-review',
                'tags' => ['featured', 'review', 'rpg', 'pc', 'ps5', 'editors-pick'],
                'categories' => ['Reviews', 'Game Reviews', 'PC'],
                'custom_fields' => [
                    'author_name' => 'Sarah Mitchell',
                    'author_bio' => 'Senior RPG reviewer with 15 years covering role-playing games.',
                    'read_time' => 12,
                    'game_title' => 'Baldur\'s Gate 3',
                    'developer' => 'Larian Studios',
                    'publisher' => 'Larian Studios',
                    'release_date' => 'August 3, 2023',
                    'platforms' => 'PC, PS5, Xbox Series X/S',
                    'genre' => 'RPG',
                    'rating' => 5,
                    'excerpt' => 'Larian Studios delivers the definitive D&D experience with stunning depth, incredible writing, and endless possibilities.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Baldur\'s Gate 3 gameplay',
                            'caption' => 'Baldur\'s Gate 3 sets a new standard for RPG storytelling',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Baldur\'s Gate 3 is a triumph. After three years in early access and decades of anticipation, Larian Studios has delivered an RPG that not only honors its legendary predecessors but surpasses them in nearly every way.',
                                'This is the most faithful digital interpretation of Dungeons & Dragons ever created, translating the tabletop experience with stunning accuracy while leveraging the advantages of the video game medium to create something truly special.',
                                'From the moment you emerge from the crashed nautiloid ship, Baldur\'s Gate 3 grabs you and never lets go.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'award',
                        'data' => [
                            'subcategory' => 'Editor\'s Choice Award',
                            'productName' => 'Baldur\'s Gate 3',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'winner' => true,
                            'rating' => 5.0,
                            'strapline' => 'The new gold standard for RPGs',
                            'caption' => 'A masterclass in player agency and storytelling'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'A Story That Reacts to Everything',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The narrative complexity in Baldur\'s Gate 3 is staggering. Your choices matter in ways both obvious and subtle, with consequences that ripple throughout your entire playthrough.',
                                'I\'ve completed the game three times now, and each playthrough felt completely different. Characters I saved in one run were enemies in another. Romance options that seemed impossible suddenly became available based on specific dialogue choices made dozens of hours earlier.',
                                'The writing is phenomenal. Every companion has depth and nuance, with personal quests that explore themes of identity, redemption, and sacrifice. Even minor NPCs feel fully realized, with their own motivations and storylines.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'D&D Combat Done Right',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Combat in Baldur\'s Gate 3 is turn-based tactical brilliance. Using D&D 5th Edition rules, every encounter is a puzzle that rewards creativity and experimentation.',
                                'The environmental interaction is particularly impressive. You can shove enemies off cliffs, dip weapons in fire for extra damage, or use oil and fire to create devastating combos. The game consistently rewards outside-the-box thinking.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Pro Tip: Save often and experiment! The quicksave feature makes it easy to try different approaches to challenging encounters.'
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Category', 'Score', 'Comment'],
                                ['Story & Writing', '10/10', 'Exceptional narrative depth'],
                                ['Gameplay', '9/10', 'Tactical combat perfection'],
                                ['Graphics', '9/10', 'Stunning visuals and animations'],
                                ['Sound & Music', '10/10', 'Masterful orchestral score'],
                                ['Replayability', '10/10', 'Endless possibilities'],
                                ['Performance', '8/10', 'Some optimization issues']
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Baldur\'s Gate 3',
                            'brand' => 'Larian Studios',
                            'productName' => 'Standard Edition',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'price' => 59.99,
                            'currency' => '£',
                            'description' => 'Available on PC, PS5, and Xbox Series X/S. Includes all launch content and free updates.',
                            'link' => 'https://example.com/bg3',
                            'linkText' => 'Buy Now',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 5.0,
                                'pros' => [
                                    'Incredible narrative depth and player agency',
                                    'Faithful D&D 5E implementation',
                                    'Outstanding voice acting and motion capture',
                                    'Virtually endless replayability',
                                    'Cooperative multiplayer adds new dimension'
                                ],
                                'cons' => [
                                    'Performance issues in Act 3',
                                    'Steep learning curve for D&D newcomers',
                                    'Some minor bugs in complex interactions'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Baldur\'s Gate 3 isn\'t just a return to form for the RPG genre—it\'s an evolution that raises the bar for what these games can achieve.',
                            'attribution' => 'Sarah Mitchell, GamesRadar+'
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
                                'Baldur\'s Gate 3 is a monumental achievement that will be studied and celebrated for years to come. It proves that complex, deep RPGs can thrive in the modern gaming landscape.',
                                'While it has some technical hiccups and the learning curve might intimidate newcomers, these are minor blemishes on an otherwise masterful experience.',
                                'Whether you\'re a longtime D&D player or completely new to the genre, Baldur\'s Gate 3 is essential. This is the new gold standard for RPGs.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'PlayStation 6: Everything We Know About Sony\'s Next-Gen Console',
                'slug' => 'playstation-6-rumors',
                'tags' => ['featured', 'news', 'playstation', 'ps5', 'breaking-news'],
                'categories' => ['News', 'Industry News'],
                'custom_fields' => [
                    'author_name' => 'James Chen',
                    'author_bio' => 'Hardware specialist covering console technology and industry trends.',
                    'read_time' => 8,
                    'excerpt' => 'Sony\'s next-gen console is coming. Here\'s every leaked spec, rumored feature, and expert prediction compiled in one place.'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'PlayStation 6: The Future of Gaming',
                            'subtitle' => 'Everything we know about Sony\'s next console',
                            'ctaText' => 'Read Analysis',
                            'ctaUrl' => '#specs',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1606144042614-b2417e99c4e3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'While the PlayStation 5 continues to dominate the current console generation, speculation about Sony\'s next hardware is already heating up. Industry insiders, patent filings, and supply chain leaks paint an intriguing picture of what the PS6 might bring.',
                                'Based on extensive research and conversations with developers, here\'s everything we currently know—and what we can reasonably predict—about PlayStation 6.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Remember: Most PS6 information is based on rumors, leaks, and industry analysis. Sony has not officially announced the console.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Expected Specifications',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Component', 'Rumored Spec', 'Source'],
                                ['CPU', 'AMD Zen 5 Architecture', 'Supply chain leaks'],
                                ['GPU', 'AMD RDNA 4, 60+ TFLOPs', 'Industry analysts'],
                                ['RAM', '32GB GDDR7', 'Developer sources'],
                                ['Storage', '2TB+ NVMe SSD', 'Patent filings'],
                                ['Ray Tracing', 'Hardware RT Gen 3', 'AMD roadmap'],
                                ['Target Resolution', '8K/120fps capable', 'Sony statements'],
                                ['Release Window', 'Late 2027 - Early 2028', 'Analyst predictions']
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Predicted Performance Leap',
                            'stats' => [
                                ['number' => '3-4x', 'label' => 'More Powerful Than PS5', 'icon' => '⚡'],
                                ['number' => '8K', 'label' => 'Native Resolution Target', 'icon' => '📺'],
                                ['number' => '120fps', 'label' => 'Standard Frame Rate', 'icon' => '🎮'],
                                ['number' => '£599', 'label' => 'Expected Launch Price', 'icon' => '💷']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Revolutionary Features',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Full backwards compatibility with PS4, PS5, and possibly PS3 games',
                                'AI-powered upscaling technology (PlayStation Super Resolution 2.0)',
                                'Advanced haptic feedback with temperature simulation',
                                'Built-in cloud gaming and streaming at 4K/60fps',
                                'Modular design allowing GPU upgrades',
                                'VR2 integration with wireless PSVR3 headset',
                                'Sustainability focus with 80% recyclable materials'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Patent Filing: Cooling System',
                                    'description' => 'Sony patent shows advanced liquid cooling',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1612287230202-1ff1d85d1bdf?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'PlayStation patent'
                                ],
                                [
                                    'title' => 'Concept: Modular Design',
                                    'description' => 'Leaked concept shows removable components',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1606144042614-b2417e99c4e3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'PS6 concept'
                                ],
                                [
                                    'title' => 'Next-Gen Controller',
                                    'description' => 'DualSense 2 with enhanced haptics',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1606318434629-f3c2c4b6b52a?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'DualSense 2'
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
                                    'text' => 'If these specs are accurate, PS6 could deliver a generational leap we haven\'t seen since PS3 to PS4.',
                                    'author' => 'Dr. Lisa Zhang',
                                    'role' => 'Hardware Analyst, TechInsights',
                                    'rating' => 5
                                ],
                                [
                                    'text' => 'The rumored AI upscaling tech alone would be game-changing for performance optimization.',
                                    'author' => 'Marcus Blackwood',
                                    'role' => 'Senior Developer, Anonymous Studio',
                                    'rating' => 5
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Editor\'s Analysis',
                            'paragraphs' => [
                                'Based on Sony\'s historical console cycle (6-7 years), a 2027-2028 launch seems most likely. However, semiconductor shortages and manufacturing costs could push this timeline.',
                                'The rumored specs suggest Sony is targeting true 8K gaming and ray tracing that matches or exceeds current high-end PCs. If achieved, this would justify a premium price point.',
                                'Expect official announcements to begin 18-24 months before launch, likely at a major gaming event in 2026.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Elden Ring DLC Complete Guide: Shadow of the Erdtree Walkthrough',
                'slug' => 'elden-ring-dlc-guide',
                'tags' => ['featured', 'guide', 'rpg', 'elden-ring', 'walkthrough'],
                'categories' => ['Guides', 'Walkthroughs'],
                'custom_fields' => [
                    'author_name' => 'Marcus Reed',
                    'author_bio' => 'Soulsborne expert with thousands of hours across FromSoftware titles.',
                    'read_time' => 25,
                    'game_title' => 'Elden Ring: Shadow of the Erdtree',
                    'developer' => 'FromSoftware',
                    'excerpt' => 'Master every boss, discover every secret, and conquer FromSoftware\'s most challenging DLC yet with our complete walkthrough.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1552820728-8b83bb6b773f?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Elden Ring Shadow of the Erdtree',
                            'caption' => 'Prepare for FromSoftware\'s most challenging DLC',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Shadow of the Erdtree is FromSoftware\'s most ambitious DLC expansion to date, adding a massive new region, challenging bosses, and cryptic lore to Elden Ring.',
                                'This comprehensive guide will walk you through every area, boss strategy, and secret in the expansion. Whether you\'re a Soulsborne veteran or struggling with the difficulty, we\'ve got you covered.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => '⚠️ Recommended Level: 150+ | This DLC is significantly harder than the base game. Come prepared!'
                        ]
                    ],
                    [
                        'type' => 'schema',
                        'data' => [
                            'schemaType' => 'how-to',
                            'title' => 'How to Access Shadow of the Erdtree',
                            'description' => 'Follow these steps to enter the DLC area',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1552820728-8b83bb6b773f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80']
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Defeat Starscourge Radahn and Mohg, Lord of Blood in the base game',
                                'Travel to Mohgwyn Palace and approach Miquella\'s Cocoon',
                                'Interact with the withered arm extending from the cocoon',
                                'You\'ll be transported to the Land of Shadow',
                                'Receive the "Blessing of the Erdtree" buff (essential for survival)',
                                'Begin your journey at the Gravesite Plain'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Essential Tips for Success',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Recommended Build: Strength/Faith Paladin',
                            'subtitle' => 'Excellent for first-time DLC players',
                            'specs' => [
                                ['text' => 'Vigor', 'value' => '60+'],
                                ['text' => 'Strength', 'value' => '50'],
                                ['text' => 'Faith', 'value' => '40'],
                                ['text' => 'Weapon', 'value' => 'Sacred Relic Sword +10'],
                                ['text' => 'Armor', 'value' => 'Bull-Goat Set'],
                                ['text' => 'Talismans', 'value' => 'Dragoncrest, Erdtree\'s Favor +2']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'High survivability with heavy armor',
                                'Versatile damage types (physical + holy)',
                                'Strong healing incantations',
                                'Excellent poise for trading hits'
                            ],
                            'cons' => [
                                'Slower dodge rolls require timing mastery',
                                'Faith scaling requires significant investment',
                                'Limited ranged options'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Boss Guide: Messmer the Impaler',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Messmer the Impaler is the main story boss of Shadow of the Erdtree and one of FromSoftware\'s most challenging encounters. Here\'s how to defeat him.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Attack Pattern', 'Tell', 'Counter Strategy'],
                                ['Spear Combo', 'Winds up right arm', 'Dodge roll toward him, punish'],
                                ['Flame Sweep', 'Red glow buildup', 'Jump over or distance yourself'],
                                ['Impale Grab', 'Left hand reaches forward', 'Dodge roll backward immediately'],
                                ['Phase 2 Serpent', 'Removes eye covering', 'Stay close, avoid serpent strikes'],
                                ['Ultimate: Rain of Spears', 'Leaps into air', 'Sprint constantly, don\'t panic roll']
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Pro Strategy',
                            'paragraphs' => [
                                'Messmer is extremely aggressive but has low poise. Jumping heavy attacks with colossal weapons can stagger him quickly.',
                                'In Phase 2, the serpent adds fire damage to most attacks. Flame protection incantations or the Flamedrake Talisman +3 are essential.',
                                'His grab attack is always fatal—prioritize dodging this over dealing damage.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Best Spirit Ash for Messmer Fight',
                            'productA' => 'Mimic Tear Ashes +10',
                            'productB' => 'Black Knife Tiche +10',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Survivability',
                                    'items' => [
                                        ['value' => 'Excellent (matches your HP)'],
                                        ['value' => 'Good (high dodge rate)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Damage Output',
                                    'items' => [
                                        ['value' => 'Matches your build'],
                                        ['value' => 'Consistent % health damage']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Aggro Management',
                                    'items' => [
                                        ['value' => 'Excellent tank'],
                                        ['value' => 'Mobile, shares aggro well']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Melee/hybrid builds'],
                                        ['value' => 'Ranged/caster builds']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Secret Areas & Optional Bosses',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'accordion',
                        'data' => [
                            'title' => 'Hidden Content',
                            'items' => [
                                [
                                    'question' => 'How do I find the Ancient Dragon-Man boss?',
                                    'answer' => 'From the Cathedral of Manus Metyr, take the hidden path behind the altar. You\'ll need the Abyssal Lantern (found in Darkwater Catacombs) to reveal the path.',
                                    'isOpen' => true
                                ],
                                [
                                    'question' => 'Where is the Fingerprint Shield +25?',
                                    'answer' => 'In the Consecrated Snowfield of Shadow, defeat the invisible Black Knife Assassin near the frozen lake. The shield is in a chest behind where the boss spawns.'
                                ],
                                [
                                    'question' => 'How to access the secret ending?',
                                    'answer' => 'Complete all of Leda\'s questline, defeat Romina without using spirit ashes, and choose "Reject" when Miquella offers his hand. This unlocks the Age of Compassion ending.'
                                ]
                            ],
                            'allowMultipleOpen' => false
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Shadow of the Erdtree is FromSoftware at their absolute peak—punishing, rewarding, and utterly unforgettable.',
                            'attribution' => 'Marcus Reed, GamesRadar+'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'The Legend of Zelda: Tears of the Kingdom Review - Nintendo\'s Masterpiece',
                'slug' => 'zelda-tears-kingdom-review',
                'tags' => ['featured', 'review', 'nintendo-switch', 'adventure', 'editors-pick'],
                'categories' => ['Reviews', 'Game Reviews', 'Nintendo Switch'],
                'custom_fields' => [
                    'author_name' => 'Emma Thompson',
                    'author_bio' => 'Nintendo specialist covering first-party titles for 10 years.',
                    'read_time' => 10,
                    'game_title' => 'The Legend of Zelda: Tears of the Kingdom',
                    'developer' => 'Nintendo EPD',
                    'publisher' => 'Nintendo',
                    'platforms' => 'Nintendo Switch',
                    'genre' => 'Action-Adventure',
                    'rating' => 5,
                    'excerpt' => 'Nintendo somehow topped Breath of the Wild with creative freedom that defies belief.'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Tears of the Kingdom',
                            'subtitle' => 'A sequel that surpasses perfection',
                            'ctaText' => 'Read Review',
                            'ctaUrl' => '#verdict',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1578374173705-0a5dc6100c7d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'How do you follow up one of the greatest games ever made? If you\'re Nintendo, you give players the ability to build vehicles, construct weapons, and literally reverse time itself.',
                                'Tears of the Kingdom takes everything that made Breath of the Wild special and adds layers of creative freedom that fundamentally change how you interact with Hyrule.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'award',
                        'data' => [
                            'subcategory' => 'Game of the Year Contender',
                            'productName' => 'Tears of the Kingdom',
                            'winner' => true,
                            'rating' => 5.0,
                            'strapline' => 'Nintendo\'s creative opus',
                            'caption' => 'The new benchmark for open-world design'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Ultrahand: The Game-Changer',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Ultrahand ability is genius. It lets you fuse objects together to create... anything. Bridges, vehicles, weapons, siege engines—if you can imagine it, you can probably build it.',
                                'I\'ve seen players create working helicopters, catapults, and even a functional mech suit. The physics system is so robust that these wild contraptions actually work as intended.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'carousel',
                            'slides' => [
                                [
                                    'title' => 'Sky Islands',
                                    'description' => 'Explore mysterious floating archipelagos',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1586182987320-4f376d39d787?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80']
                                ],
                                [
                                    'title' => 'The Depths',
                                    'description' => 'Venture into the dark underground',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1614465620879-0f17a44fc6d0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80']
                                ],
                                [
                                    'title' => 'Vehicle Building',
                                    'description' => 'Create incredible contraptions',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1578374173705-0a5dc6100c7d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80']
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Aspect', 'BOTW', 'TOTK', 'Winner'],
                                ['World Size', 'Large', 'Massive (3 layers)', 'TOTK'],
                                ['Creative Freedom', 'High', 'Limitless', 'TOTK'],
                                ['Story Depth', 'Good', 'Excellent', 'TOTK'],
                                ['Performance', 'Solid', 'Some frame drops', 'BOTW'],
                                ['Overall', '10/10', '10/10', 'Both!']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Nintendo has created a game where the only limit is your imagination—and that\'s terrifying in the best possible way.',
                            'attribution' => 'Emma Thompson, GamesRadar+'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Gaming PC Build Guide 2025: Best Specs for Every Budget',
                'slug' => 'pc-build-guide-2025',
                'tags' => ['guide', 'pc', 'hardware', 'how-to'],
                'categories' => ['Guides', 'How To'],
                'custom_fields' => [
                    'author_name' => 'David Park',
                    'author_bio' => 'PC hardware expert and system builder with 12 years experience.',
                    'read_time' => 18,
                    'excerpt' => 'Build the perfect gaming PC with our comprehensive 2025 buying guide covering every budget tier.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Gaming PC build',
                            'caption' => 'Build your dream gaming rig in 2025',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Building a gaming PC in 2025 offers incredible value across all price points. With GPU prices stabilized and new architectures from AMD and Nvidia, there\'s never been a better time to build.',
                                'This guide breaks down the best component choices for three budget tiers, with real-world performance data and future-proofing recommendations.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => '2025 PC Gaming Market',
                            'stats' => [
                                ['number' => '£750', 'label' => 'Entry Level Build', 'icon' => '💷'],
                                ['number' => '£1,500', 'label' => 'Mid-Range Sweet Spot', 'icon' => '🎮'],
                                ['number' => '£3,000+', 'label' => 'Enthusiast Tier', 'icon' => '🚀'],
                                ['number' => '40%', 'label' => 'Price Drop vs 2023', 'icon' => '📉']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Budget Build: 1080p High Settings (£750)',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'The Perfect Entry-Level Gaming PC',
                            'subtitle' => 'Excellent 1080p performance for under £800',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1591488320449-011701bb6704?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'specs' => [
                                ['text' => 'CPU', 'value' => 'AMD Ryzen 5 7600'],
                                ['text' => 'GPU', 'value' => 'Nvidia RTX 4060'],
                                ['text' => 'RAM', 'value' => '16GB DDR5-5600'],
                                ['text' => 'Storage', 'value' => '1TB NVMe SSD'],
                                ['text' => 'PSU', 'value' => '650W 80+ Gold'],
                                ['text' => 'Performance', 'value' => '60-100fps @ 1080p High']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Solid 1080p gaming across all titles',
                                'Upgradeable to mid-range GPU later',
                                'Excellent value per frame',
                                'PCIe 5.0 ready for future upgrades'
                            ],
                            'cons' => [
                                'Limited ray tracing performance',
                                '1440p gaming requires settings compromises',
                                'May need GPU upgrade in 2-3 years'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Mid-Range Build: 1440p Ultra (£1,500)',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'AMD Ryzen 7 7800X3D',
                            'brand' => 'AMD',
                            'productName' => 'Gaming CPU King',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1555617981-dac3880eac6e?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'price' => 399.99,
                            'currency' => '£',
                            'description' => 'The absolute best gaming CPU. 3D V-Cache delivers unmatched gaming performance.',
                            'link' => 'https://example.com/7800x3d',
                            'linkText' => 'Check Price',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 5.0,
                                'pros' => [
                                    'Best gaming performance available',
                                    'Excellent efficiency and temps',
                                    'Future-proof for 5+ years',
                                    'Lower power consumption than Intel'
                                ],
                                'cons' => [
                                    'Premium pricing',
                                    'Lower productivity performance vs 7950X'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Nvidia vs AMD: 2025 GPU Battle',
                            'productA' => 'RTX 4070 Ti Super',
                            'productB' => 'RX 7900 XTX',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Price',
                                    'items' => [
                                        ['value' => '£799'],
                                        ['value' => '£899']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Rasterization',
                                    'items' => [
                                        ['value' => 'Excellent'],
                                        ['value' => 'Outstanding']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Ray Tracing',
                                    'items' => [
                                        ['value' => 'Superior'],
                                        ['value' => 'Good']
                                    ]
                                ],
                                [
                                    'subtitle' => 'VRAM',
                                    'items' => [
                                        ['value' => '16GB GDDR6X'],
                                        ['value' => '24GB GDDR6']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'RT games, DLSS 3.5'],
                                        ['value' => 'High VRAM needs, raw power']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Expert Recommendation',
                            'paragraphs' => [
                                'For most gamers, the £1,500 mid-range build offers the best balance of performance, longevity, and value.',
                                'This tier handles 1440p Ultra settings in every modern game while remaining viable for 4K gaming with some settings adjustments.',
                                'Expect this build to remain relevant for 4-5 years before requiring GPU upgrades.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'The Last of Us Part III: Everything We Want to See',
                'slug' => 'last-of-us-3-wishlist',
                'tags' => ['preview', 'opinion', 'playstation', 'ps5'],
                'categories' => ['Features', 'Opinion'],
                'custom_fields' => [
                    'author_name' => 'Rachel Kim',
                    'author_bio' => 'Narrative gaming specialist focusing on story-driven experiences.',
                    'read_time' => 7,
                    'excerpt' => 'Naughty Dog\'s next chapter is inevitable. Here\'s what would make Part III perfect.'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'The Last of Us Part III',
                            'subtitle' => 'Our wishlist for Naughty Dog\'s next masterpiece',
                            'ctaText' => 'Read More',
                            'ctaUrl' => '#wishlist',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Last of Us Part II ended on a note that demands continuation. While Naughty Dog hasn\'t officially announced Part III, it\'s all but inevitable.',
                                'Based on interviews, the Part II ending, and narrative threads, here\'s everything we want to see in the next chapter of Ellie\'s story.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Story & Characters',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Ellie\'s quest for redemption and closure',
                                'The cure storyline finally resolved',
                                'Return of familiar faces from Jackson',
                                'New companion with unique gameplay mechanics',
                                'Multiple playable perspectives like Part II',
                                'Exploration of Firefly remnants and their goals'
                            ]
                        ]
                    ],
                    [
                        'type' => 'testimonial',
                        'data' => [
                            'layout' => 'grid',
                            'testimonials' => [
                                [
                                    'text' => 'Part III needs to give Ellie the ending she deserves—one of peace, not just survival.',
                                    'author' => 'Troy Baker',
                                    'role' => 'Voice of Joel',
                                    'rating' => 5
                                ],
                                [
                                    'text' => 'The immunity storyline is the key. We need answers about what Ellie represents.',
                                    'author' => 'Neil Druckmann',
                                    'role' => 'Creative Director, Naughty Dog',
                                    'rating' => 5
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Gameplay Evolution',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Feature', 'Part II', 'Part III (Wishlist)'],
                                ['Stealth', 'Excellent', 'AI-driven dynamic encounters'],
                                ['Combat', 'Brutal, visceral', 'Non-lethal options, diplomacy'],
                                ['Exploration', 'Linear+', 'Semi-open world areas'],
                                ['Crafting', 'Resource management', 'Base building elements'],
                                ['Companions', 'AI partners', 'Co-op potential?'],
                                ['Infected', '4 types', 'New evolutions, smarter AI']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Part III could be Naughty Dog\'s magnum opus—if they\'re willing to take risks and deliver the closure this story deserves.',
                            'attribution' => 'Rachel Kim, GamesRadar+'
                        ]
                    ]
                ]
            ]
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
            'meta_title' => $data['title'] . ' - GamesRadar+',
            'meta_description' => $data['custom_fields']['excerpt'] ?? '',
            'site_id' => $this->site->id,
        ]);

        // Add to main menu
        MenuItem::create([
            'label' => $page->title,
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
            $page->tags(true)->attach($tag->id);
        }

        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, 1);
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
            'title' => 'About GamesRadar+',
            'page_type' => 'content',
            'slug' => 'about',
            'status' => 'published',
            'meta_title' => 'About Us - GamesRadar+',
            'meta_description' => 'Learn about GamesRadar+ - your trusted source for gaming news, reviews, and guides since 1999.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'About',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        MenuItem::create([
            'label' => 'About Us',
            'menu_id' => $this->footerMenu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'About GamesRadar+',
                    'subtitle' => 'Your trusted gaming companion since 1999',
                    'ctaText' => 'Meet the Team',
                    'ctaUrl' => '#team',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1538481199705-c710c4e965fc?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'GamesRadar+ is the world\'s premier destination for gaming news, reviews, previews, and features. For over 25 years, we\'ve been committed to delivering honest, insightful coverage of the games and gaming culture that matters most.',
                        'Our team of expert writers, reviewers, and industry analysts brings you the latest news, in-depth analysis, and comprehensive guides across all platforms—from PlayStation and Xbox to PC and Nintendo.',
                        'We believe gaming is for everyone, and our content reflects that philosophy. Whether you\'re a hardcore enthusiast or casual player, GamesRadar+ has something for you.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Our Impact',
                    'stats' => [
                        ['number' => '25+', 'label' => 'Years of Coverage', 'icon' => '🎮'],
                        ['number' => '20M+', 'label' => 'Monthly Readers', 'icon' => '👥'],
                        ['number' => '10,000+', 'label' => 'Games Reviewed', 'icon' => '⭐'],
                        ['number' => '50+', 'label' => 'Expert Team Members', 'icon' => '✍️']
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Editorial Team',
                    'subtitle' => 'Meet the experts behind the coverage',
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
                            'name' => 'Sarah Mitchell',
                            'role' => 'Senior Reviews Editor',
                            'bio' => 'RPG specialist with 15 years covering role-playing games across all platforms.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'sarah.mitchell@gamesradar.com'
                        ],
                        [
                            'name' => 'James Chen',
                            'role' => 'Hardware & Tech Editor',
                            'bio' => 'Console and PC hardware expert covering technology trends and industry developments.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'james.chen@gamesradar.com'
                        ],
                        [
                            'name' => 'Marcus Reed',
                            'role' => 'Guides Editor',
                            'bio' => 'Soulsborne expert creating comprehensive walkthroughs and strategy guides.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'marcus.reed@gamesradar.com'
                        ],
                        [
                            'name' => 'Emma Thompson',
                            'role' => 'Nintendo Editor',
                            'bio' => 'First-party Nintendo specialist covering all things Switch, Mario, and Zelda.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'emma.thompson@gamesradar.com'
                        ],
                        [
                            'name' => 'David Park',
                            'role' => 'PC Gaming Editor',
                            'bio' => 'PC hardware and build specialist with deep expertise in gaming rigs.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'david.park@gamesradar.com'
                        ],
                        [
                            'name' => 'Rachel Kim',
                            'role' => 'Features Editor',
                            'bio' => 'Narrative specialist focusing on story-driven games and industry analysis.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'rachel.kim@gamesradar.com'
                        ]
                    ]
                ],
                'order' => 5
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'Gaming is more than entertainment—it\'s art, competition, community, and escape. We\'re here to celebrate all of it.',
                    'attribution' => 'GamesRadar+ Editorial Mission'
                ],
                'order' => 6
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createContactPage(): void
    {
        $page = Page::create([
            'title' => 'Contact GamesRadar+',
            'page_type' => 'content',
            'slug' => 'contact',
            'status' => 'published',
            'meta_title' => 'Contact Us - GamesRadar+',
            'meta_description' => 'Get in touch with the GamesRadar+ editorial team.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Contact',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        MenuItem::create([
            'label' => 'Contact Us',
            'menu_id' => $this->footerMenu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Get In Touch',
                    'subtitle' => 'Questions, tips, or feedback? We\'re here to help',
                    'showSearch' => false
                ],
                'order' => 1
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'GamesRadar+ Editorial Team',
                    'role' => 'Contact Information',
                    'email' => 'editorial@gamesradar.com',
                    'phone' => '+44 20 7042 4000',
                    'address' => 'Future Publishing Ltd
                    Quay House, The Ambury
Bath, BA1 1UA
United Kingdom
Editorial Office Hours:
Monday-Friday: 9:00 AM - 6:00 PM GMT',
                    'displayType' => 'contact'
                ],
                'order' => 2
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'For editorial inquiries, news tips, or story suggestions, contact us at editorial@gamesradar.com',
                        'For advertising and partnership opportunities, reach out to advertising@futurenet.com',
                        'Technical support for the website can be contacted at support@gamesradar.com',
                        'Press releases and review code submissions should be sent to reviews@gamesradar.com'
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Send Us a Message',
                    'subtitle' => 'Our team typically responds within 48 hours',
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
}