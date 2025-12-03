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
use App\Repositories\BlockRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;
use App\Services\BlockParserService;

class SpaceComSeeder extends Seeder
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
//        $this->createSite();
//        $this->createTags();
//        $this->createCategories();
//        $this->createMenu();
//        $this->createHomepage();
//        $this->createArticles();
//        $this->createAboutPage();
//        $this->createContactPage();
        $this->createPageGrid();
        //$this->createMenuNavItems();
    }

    private function createPageGrid(): void
    {
        $items = [];

        $this->site = Site::find(50);

        $articles = Page::where('page_type', 'content')->where('status', 'published')->where('site_id', 50)->get();


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
            'name' => 'Space.com',
            'slug' => 'space-com',
            'is_active' => true,
        ]);
    }

    private function createTags(): void
    {
        $tagsData = ['NASA', 'SpaceX', 'ISS', 'Mars', 'Webb', 'Aurora', 'Exoplanets'];
        foreach ($tagsData as $name) {
            Tag::create(['site_id' => $this->site->id, 'name' => $name, 'slug' => strtolower(str_replace(' ', '-', $name))]);
        }
    }

    private function createCategories(): void
    {
        $categoriesData = ['Reviews', 'News', 'Guides', 'Opinion'];
        foreach ($categoriesData as $name) {
            $this->categories[] = Category::create(['site_id' => $this->site->id, 'name' => $name, 'slug' => strtolower(str_replace(' ', '-', $name))]);
        }
    }

    private function createMenu(): void
    {
        $this->menu = Menu::create([
            'name' => 'Main Menu',
            'site_id' => $this->site->id,
            'slug' => 'main-menu'
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
            'title' => 'Space.com',
            'page_type' => 'content',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'Space.com: The Latest in Astronomy',
            'meta_description' => 'Your daily source for NASA, SpaceX, and cosmic discoveries.',
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
                'order' => $blockData['order']
            ]);
        }
    }

    private function getHomepageBlocks(array $articles = []): array
    {
        return [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Space.com: The Latest in Astronomy',
                    'subtitle' => 'Your daily source for NASA, SpaceX, and cosmic discoveries.',
                    'backgroundImage' => 'homepage-hero.jpg', //todo
                    'ctaText' => 'See Today\'s Night Sky',
                    'ctaUrl' => '/stargazing/tonight'
                ],
                'order' => 1
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
                            'title' => 'Europa Mission: NASA Reveals New Ocean Data',
                            'slug' => 'jwst-radiation-star-nursery',
                            'excerpt' => 'The latest probe readings offer the strongest evidence yet of a warm, salt-rich ocean beneath Europa’s icy shell.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Jupiter Moon Europa'
                            ],
                            'badge' => [
                                'text' => 'Breaking',
                                'color' => 'primary'
                            ],
                            'meta' => [
                                'author' => 'Dr. Aria Noor',
                                'date' => 'May 2, 2025',
                                'readTime' => '9 min read'
                            ]
                        ],
                        [
                            'title' => 'How Private Spaceflight Is Rewriting Earth Orbit',
                            'slug' => 'spacex-falcon-9-starlink',
                            'excerpt' => 'Commercial launch companies are transforming satellite access, tourism, and deep-space research.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1581093458791-9f3c2e26a47d?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Private Rocket Launch'
                            ],
                            'badge' => [
                                'text' => 'Trending',
                                'color' => 'success'
                            ],
                            'meta' => [
                                'author' => 'Mira Sanchez',
                                'date' => 'April 29, 2025',
                                'readTime' => '7 min read'
                            ]
                        ],
                        [
                            'title' => 'Life on Mars? Scientists Debate New Soil Findings',
                            'slug' => 'planet-venus-toxic-twin',
                            'excerpt' => 'A new chemical analysis has triggered heated discussion among astrobiologists worldwide.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1516849841032-87cfb1c37f72?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Mars Surface'
                            ],
                            'badge' => [
                                'text' => 'Exclusive',
                                'color' => 'warning'
                            ],
                            'meta' => [
                                'author' => 'Kenji Morita',
                                'date' => 'April 25, 2025',
                                'readTime' => '6 min read'
                            ]
                        ]
                    ]
                ],
                'order' => 3
            ],
            ['type' => 'text', 'data' => ['paragraphs' => ['Our mission is to explore the universe and bring the wonder of space to you. We cover everything from the deepest black holes to the nearest planets.', 'The James Webb Space Telescope continues to deliver breathtaking images and revolutionary data, reshaping our understanding of cosmology.']], 'order' => 2],
            ['type' => 'quote', 'data' => ['text' => 'Exploration is in our nature. We began as wanderers, and we are wanderers still.', 'attribution' => 'Carl Sagan'], 'order' => 3],
            ['type' => 'list', 'data' => ['title' => 'Hot Topics', 'items' => [['text' => 'The Search for Life'], ['text' => 'The Next Mars Mission'], ['text' => 'Telescope Buying Guide']], 'listType' => 'ordered'], 'order' => 4],
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
                            'title' => 'Deep Space Exploration',
                            'description' => 'New missions push farther than ever before',
                            'image' => 'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Space Exploration',
                            'link' => '/space/deep-exploration'
                        ],
                        [
                            'title' => 'Living on Mars',
                            'description' => 'Research reveals what sustainable life beyond Earth may require',
                            'image' => 'https://images.unsplash.com/photo-1535223289827-42f1e1a9e57c?auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Mars Colony Concept',
                            'link' => '/space/mars-living'
                        ],
                        [
                            'title' => 'Next-Gen Rockets',
                            'description' => 'Reusable engines are transforming space travel',
                            'image' => 'https://images.unsplash.com/photo-1581091012184-5c7a9e3f33b1?auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Rocket Launch',
                            'link' => '/space/next-gen-rockets'
                        ],
                    ]
                ],

                'order' => 6
            ],
            ['type' => 'cta', 'data' => [
                'text' => 'Watch Our Live Stream', 'url' => '/live'], 'order' => 6],

            ['type' => 'info', 'data' => ['infoType' => 'note', 'description' => 'Never miss a rocket launch! Check our calendar for the next scheduled flight.'], 'order' => 7],
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
            ]
        ];
    }

    private function createArticles(): void
    {
        $articles = [
            [
                'title' => 'Perseverance Mars Rover: Mission Review & Long-Term Impact Analysis',
                'slug' => 'perseverance-rover-mission-review',
                'tags' => ['featured', 'analysis', 'space', 'review', 'science', 'nasa'],
                'categories' => ['Reviews', 'Mission Analysis', 'Jezero Crater'],
                'custom_fields' => [
                    'author_name' => 'Dr. Lena Rodriguez',
                    'author_bio' => 'Aero-astro engineer and space policy analyst.',
                    'read_time' => 14,
                    'game_title' => 'Perseverance Rover Mission',
                    'developer' => 'NASA Jet Propulsion Laboratory (JPL)',
                    'publisher' => 'NASA',
                    'release_date' => 'February 18, 2021 (Landing)',
                    'platforms' => 'Mars Surface',
                    'genre' => 'Astrobiology Mission',
                    'rating' => 5,
                    'excerpt' => 'NASA\'s Perseverance Rover is a triumph of engineering and scientific exploration, fundamentally changing our understanding of Mars.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1610444589988-2ff779435422?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Perseverance Rover on Mars Surface',
                            'caption' => 'The Perseverance Rover in Jezero Crater, searching for signs of ancient life',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Perseverance is more than just a rover—it\'s the vanguard of a sample return mission and a technological marvel. The mission has not only succeeded in its primary objectives but has redefined planetary exploration.',
                                'Equipped with the MOXIE instrument (generating oxygen from the Martian atmosphere) and the Ingenuity Helicopter (the first powered flight on another world), the rover leverages advanced technology to create something truly special.',
                                'From the moment it successfully landed in Jezero Crater, the Perseverance mission has captured the world\'s imagination.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'award',
                        'data' => [
                            'subcategory' => 'Mission of the Decade',
                            'productName' => 'Perseverance Mars Rover',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1610444589988-2ff779435422?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'winner' => true,
                            'rating' => 5.0,
                            'strapline' => 'A technological and scientific triumph',
                            'caption' => 'A masterclass in remote exploration and astrobiology'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Scientific Payload',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The narrative complexity of Perseverance\'s scientific journey is staggering. Its primary goal is to search for signs of ancient microbial life by collecting and caching carefully selected rock and soil samples.',
                                'The SHERLOC and PIXL instruments have delivered critical data, analyzing the chemical composition and mineralogy of the Jezero Crater lakebed. The discoveries about carbonate-rich rocks have fundamentally changed the outlook for the sample return program.',
                                'The writing is phenomenal—or, rather, the science is. Every instrument has depth and nuance, with data streams that explore themes of planetary history and habitability.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Ingenuity: The Game-Changing Companion',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Ingenuity helicopter, a technology demonstration, has proven to be a vital scouting companion. Its ability to quickly survey the terrain has guided the rover\'s movements and optimized its sample collection route.',
                                'This autonomous aerial exploration is a brilliant success, consistently rewarding mission control with invaluable tactical data and paving the way for future aerial vehicles on Mars.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Pro Tip: Follow the raw image feed from the Mastcam-Z instrument to see new discoveries almost instantly.'
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Category', 'Score', 'Comment'],
                                ['Scientific Yield', '10/10', 'Exceptional data and samples'],
                                ['Technological Innovation', '10/10', 'MOXIE and Ingenuity are historic'],
                                ['Engineering Reliability', '9/10', 'Remarkable performance in harsh conditions'],
                                ['Public Engagement', '10/10', 'Highly engaging public outreach'],
                                ['Sample Return Potential', '10/10', 'Paving the way for future missions'],
                                ['Cost Effectiveness', '8/10', 'High total cost, but exceptional return on investment']
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Perseverance Rover Mission',
                            'brand' => 'NASA',
                            'productName' => 'Astrobiology Mission',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1610444589988-2ff779435422?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'price' => 2.7, // Billion USD
                            'currency' => '$',
                            'description' => 'NASA’s Mars 2020 mission searching for signs of ancient life and collecting rock samples for return to Earth.',
                            'link' => 'https://example.com/perseverance',
                            'linkText' => 'View Mission Data',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 5.0,
                                'pros' => [
                                    'Groundbreaking discoveries in Martian geology',
                                    'Successful deployment of the Ingenuity Helicopter',
                                    'Robust sample collection and caching system',
                                    'Exceptional engineering and longevity'
                                ],
                                'cons' => [
                                    'Data transfer lag is unavoidable',
                                    'Cannot cover the entire planet',
                                    'Future sample return phase is complex and costly'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Perseverance isn\'t just a return to Mars—it\'s an evolution that has raised the bar for what robotic planetary explorers can achieve.',
                            'attribution' => 'Dr. Lena Rodriguez, Space Policy Analyst'
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
                                'The Perseverance mission is a monumental achievement that will be studied and celebrated for decades to come. It proves that ambitious, deep-space science is alive and thriving.',
                                'While the cost is high and the challenges are immense, the scientific return and technological demonstrations are minor blemishes on an otherwise masterful experience.',
                                'Whether you\'re an engineer, a scientist, or simply a citizen of Earth, Perseverance is essential. This is the new gold standard for planetary exploration.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Artemis III Mission: Every Rumored Spec, Crew Member, and Landing Site Prediction',
                'slug' => 'artemis-3-moon-mission-preview',
                'tags' => ['featured', 'news', 'space', 'nasa', 'moon-landing', 'breaking-news'],
                'categories' => ['News', 'Mission Analysis', 'Deep Space'],
                'custom_fields' => [
                    'author_name' => 'Dr. Elara Khan',
                    'author_bio' => 'Aerospace journalist and space policy specialist.',
                    'read_time' => 10,
                    'game_title' => 'Artemis III Mission',
                    'developer' => 'NASA, SpaceX, Axiom Space',
                    'publisher' => 'NASA',
                    'release_date' => 'September 2027 (Target Window)',
                    'platforms' => 'Lunar Surface (South Pole)',
                    'genre' => 'Human Spaceflight',
                    'rating' => 5,
                    'excerpt' => 'The mission to return humans to the Moon is coming. Here\'s every rumored crew member, hardware spec, and landing zone compiled in one definitive report.'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Artemis III: Humans Return to the Moon',
                            'subtitle' => 'Everything we know about NASA\'s historic South Pole landing',
                            'ctaText' => 'Read Specs & Crew',
                            'ctaUrl' => '#specs',
                            'backgroundImage' => '[https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80](https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80)'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Artemis III is set to be the first human landing on the Moon since 1972 and the first ever crewed mission to the lunar South Pole. The stakes are immense, driving intense speculation about the final hardware and crew selections.',
                                'Based on leaked NASA tender documents, contractor presentations, and analyst conversations, here\'s everything we currently know—and what we can reasonably predict—about this monumental mission.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Remember: The crew selection and definitive launch date are subject to change based on SLS/Starship readiness and vehicle testing.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Hardware and Mission Specifications',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Component', 'Confirmed/Rumored Spec', 'Source'],
                                ['Launch Vehicle', 'SLS Block 1B', 'NASA Official Plan'],
                                ['Lunar Lander', 'Starship Human Landing System (HLS)', 'SpaceX Contract'],
                                ['Crew Size', '4 total (2 land on Moon)', 'NASA Directive'],
                                ['Lunar EVA Time', 'Up to 24 hours total', 'Mission Planning Docs'],
                                ['Landing Site', 'Peak near Shackleton Crater', 'Geological analysis'],
                                ['Mission Duration', 'Approx. 30 days', 'Orion Capsule limits']
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Predicted Mission Stats',
                            'stats' => [
                                ['number' => '4x', 'label' => 'Total Lunar Time vs Apollo', 'icon' => '🌙'],
                                ['number' => '4', 'label' => 'Total Crew Members', 'icon' => '🧑‍🚀'],
                                ['number' => '384,400km', 'label' => 'Distance to Target', 'icon' => '🛰️'],
                                ['number' => '2027', 'label' => 'Target Launch Year', 'icon' => '📅']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Crew and Scientific Objectives',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'First woman and first person of color to walk on the Moon',
                                'Detailed geological sampling of water ice deposits near the South Pole',
                                'Deployment of a long-term lunar scientific instrumentation package',
                                'Testing of the next-generation Lunar Roving Vehicle (LRV)',
                                'Confirmation of sustained human presence capability beyond short stays',
                                'In-situ resource utilization (ISRU) demonstration readiness'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Starship HLS Concept',
                                    'description' => 'The massive lander design',
                                    'image' => ['src' => '[https://images.unsplash.com/photo-1628126744312-d815779148d8?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80](https://images.unsplash.com/photo-1628126744312-d815779148d8?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80)'],
                                    'alt' => 'Starship HLS'
                                ],
                                [
                                    'title' => 'Lunar South Pole',
                                    'description' => 'Target landing region near Shackleton Crater',
                                    'image' => ['src' => '[https://images.unsplash.com/photo-1574765955613-39f5c2a13f9c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80](https://images.unsplash.com/photo-1574765955613-39f5c2a13f9c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80)'],
                                    'alt' => 'Lunar South Pole'
                                ],
                                [
                                    'title' => 'Orion Capsule',
                                    'description' => 'The crew vehicle for transit',
                                    'image' => ['src' => '[https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80](https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80)'],
                                    'alt' => 'Orion Capsule'
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
                                    'text' => 'Artemis III will be the riskiest, yet most rewarding, mission in decades. The complexity of HLS alone is immense.',
                                    'author' => 'Scott Kelly',
                                    'role' => 'Former NASA Astronaut',
                                    'rating' => 5
                                ],
                                [
                                    'text' => 'Targeting the South Pole is a game-changer. It means going for water, and water means a permanent base.',
                                    'author' => 'Dr. Clive Harding',
                                    'role' => 'Planetary Geologist',
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
                                'The reliance on Starship HLS, while revolutionary, introduces significant schedule risk. SpaceX needs several successful orbital refuelings and lunar test landings before NASA commits a crew.',
                                'If successful, Artemis III validates the entire long-term vision of sustained lunar presence. If there are delays, the geopolitical pressure will mount significantly.',
                                'Expect the official crew announcement to be a major global media event, likely 6-12 months before the targeted launch.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Mars Habitat Construction Guide: Step-by-Step for Setting Up a Permanent Red Planet Base',
                'slug' => 'mars-habitat-construction-deployment-guide',
                'tags' => ['featured', 'guide', 'space', 'how-to', 'nasa', 'colonization'],
                'categories' => ['Guides', 'Engineering', 'Deep Space'],
                'custom_fields' => [
                    'author_name' => 'Dr. Elara Khan',
                    'author_bio' => 'Aerospace journalist and space policy specialist.',
                    'read_time' => 30,
                    'game_title' => 'Ares Base Deployment',
                    'developer' => 'SpaceX/NASA',
                    'publisher' => 'International Mars Consortium',
                    'release_date' => '2040 (Projected)',
                    'platforms' => 'Valles Marineris, Mars',
                    'genre' => 'In-Situ Resource Utilization (ISRU)',
                    'rating' => 5,
                    'excerpt' => 'From initial site selection to full life support activation, this guide provides the complex steps and essential equipment for deploying a permanent human habitat on Mars.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => '[https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80](https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80)',
                            'alt' => 'Mars habitat construction concept',
                            'caption' => 'The ultimate engineering challenge: establishing a permanent, self-sustaining base.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Establishing a permanent human presence on Mars requires perfect execution of a highly complex, multi-phase deployment plan. A single failure in the life support chain can result in mission-critical failure.',
                                'This step-by-step guide breaks down the pre-deployment requirements, the habitat construction sequence, and the critical systems needed to ensure long-term survivability for the first crew.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => '⚠️ Note: Phase 1 (pre-deployment) requires at least three successful heavy-lift uncrewed cargo missions to deliver all necessary hardware, power, and ISRU equipment.'
                        ]
                    ],
                    [
                        'type' => 'schema',
                        'data' => [
                            'schemaType' => 'how-to',
                            'title' => 'How to Deploy the First Surface Habitation Module',
                            'description' => 'The critical sequence for automated setup of the pressurized structure',
                            'image' => ['src' => '[https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80](https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80)']
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Select Landing Site: Verify high-water ice concentration and low dust activity.',
                                'Deploy Nuclear Fission Reactor: Activate and stabilize primary power source (requires 30-day cool-down).',
                                'Unload Primary Hab Module: Use rover crane to position the inflatable structure.',
                                'Inflate and Pressurize: Activate internal air pumps to establish baseline atmospheric pressure.',
                                'Activate Sabatier Reactor (ISRU): Begin producing water and oxygen from atmospheric CO2.',
                                'Apply Radiation Shielding: Use autonomous machinery to cover hab with Martian regolith (takes 72 hours).'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Essential Tools and Redundancy',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Critical ISRU and Maintenance Tools',
                            'subtitle' => 'Hardware required for survival and base expansion',
                            'specs' => [
                                ['text' => 'Primary Power', 'value' => '20kW Fission Reactor'],
                                ['text' => 'Backup Power', 'value' => '100m² Flexible Solar Arrays'],
                                ['text' => 'Oxygen Source', 'value' => 'Sabatier Reactor + MOXIE unit'],
                                ['text' => 'Water Source', 'value' => 'Regolith Heater/Extractor'],
                                ['text' => 'Construction Tool', 'value' => 'Autonomous D-Shape 3D Printer'],
                                ['text' => 'EVA Suit Type', 'value' => 'Axiom Space Hard Suit (X-Suit)'],
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Redundant power sources for safety margin',
                                'Utilizes local resources (regolith) for construction and shielding',
                                'Closed-loop life support minimizes reliance on resupply',
                                'Advanced EVA suits offer superior dexterity and radiation protection'
                            ],
                            'cons' => [
                                'ISRU systems are heavy and require significant pre-testing',
                                'Fission reactor deployment carries public perception risk',
                                'Solar array performance is severely limited by dust storms'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'System Management: Dealing with Critical Failures',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The main challenge on Mars is system management. Unlike Earth, there is no immediate rescue. Every critical system must have at least N+2 redundancy. Crew training focuses on rapid, autonomous repair.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Failure Scenario', 'Detection Time', 'Counter Strategy'],
                                ['Primary Power Failure', 'Instantaneous', 'Switch to Solar/Battery (Max 48 hrs)'],
                                ['Hab Pressure Breach (Small)', '1-2 minutes', 'Identify breach source, apply self-sealing patch'],
                                ['Oxygen Generation Loss', '5 minutes', 'Activate high-pressure storage tanks (Max 5 days supply)'],
                                ['Dust Storm/Solar Flare', '8-24 hours warning', 'Seal all vents, shelter in radiation-shielded core'],
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Critical Protocol: Pressure Breach',
                            'paragraphs' => [
                                'Any pressure drop requires immediate donning of personal pressure suits. If the leak cannot be stopped within 15 minutes, the crew must retreat to the sealed, armored core of the habitat (the "Storm Cellar").',
                                'The most common breach point is the EVA airlock seal. Always double-check seal integrity before and after every excursion.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Power Generation Comparison',
                            'productA' => 'Fission Reactor (Primary)',
                            'productB' => 'Solar Arrays (Backup)',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Power Output',
                                    'items' => [
                                        ['value' => 'High (20 kW continuous)'],
                                        ['value' => 'Variable (Max 5 kW)'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Dust Storm Impact',
                                    'items' => [
                                        ['value' => 'Minimal'],
                                        ['value' => 'Severe (near-zero output)'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Deployment Complexity',
                                    'items' => [
                                        ['value' => 'High (Heavy, complex cooling)'],
                                        ['value' => 'Low (Flexible, easily deployed)'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Recommendation',
                                    'items' => [
                                        ['value' => 'Essential for long-term sustainability'],
                                        ['value' => 'Critical for initial setup and emergency power'],
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Long-Term Base Expansion Secrets',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'accordion',
                        'data' => [
                            'title' => 'Future Expansion Planning',
                            'items' => [
                                [
                                    'question' => 'What is the fastest way to expand living space?',
                                    'answer' => 'Use 3D-printed, underground lava tube tunnels. The tubes offer natural radiation shielding and stable temperatures, requiring less energy for heating/cooling.',
                                    'isOpen' => true
                                ],
                                [
                                    'question' => 'How can the crew grow food?',
                                    'answer' => 'Implement closed-loop vertical aeroponics within a dedicated, pressurized greenhouse. Use Martian regolith as a growing medium after removing perchlorates.'
                                ],
                                [
                                    'question' => 'What is the long-term goal for the base?',
                                    'answer' => 'To become a self-sufficient colony capable of launching its own return-to-Earth missions using locally produced methane/oxygen propellant, eliminating Earth resupply dependency.'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The first Martian base will be built not with rockets, but with dirt and smart engineering. Failure is simply not an option.',
                            'attribution' => 'Elon Musk, CEO of SpaceX'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'JWST Year Three Science Review: Unveiling the Universe\'s First Stars and Galaxies',
                'slug' => 'jwst-year-three-science-review-cosmology',
                'tags' => ['featured', 'review', 'space', 'science', 'astronomy', 'jwst'],
                'categories' => ['Reviews', 'Mission Analysis', 'Deep Space'],
                'custom_fields' => [
                    'author_name' => 'Dr. Elara Khan',
                    'author_bio' => 'Aerospace journalist and space policy specialist.',
                    'read_time' => 12,
                    'game_title' => 'James Webb Space Telescope (Year 3)',
                    'developer' => 'NASA, ESA, CSA',
                    'publisher' => 'Baltimore STScI',
                    'release_date' => 'October 2024 (Review Start)',
                    'platforms' => 'Lagrange Point 2 (L2)',
                    'genre' => 'Observatory Science',
                    'rating' => 5,
                    'excerpt' => 'JWST’s third year has delivered a paradigm shift in cosmology. The unprecedented clarity and depth of its infrared observations are rewriting textbooks on the early universe.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => '[https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80](https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80)',
                            'alt' => 'James Webb Space Telescope image of a deep field',
                            'caption' => 'The deep field observations from NIRCam are revealing galaxies formed just 250 million years after the Big Bang.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'JWST Year 3 Performance',
                            'image' => ['src' => '[https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80](https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80)'],
                            'subtitle' => 'The most powerful observatory ever built continues to exceed all expectations.',
                            'pros' => [
                                'Unprecedented sensitivity in the mid-infrared range (MIRI)',
                                'Confirmed existence of very early, massive galaxies',
                                'Detailed exoplanet atmospheric composition data',
                                'Exceptional longevity due to fuel efficiency'
                            ],
                            'cons' => [
                                'Over-demand leads to competitive time allocation',
                                'Data reduction is highly complex'
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The James Webb Space Telescope (JWST) has reached the end of its third full year of science operations, and the returns are staggering. Its primary mirror, cooled to near-absolute zero, acts as a time machine, viewing light redshifted from the first cosmic dawn.',
                                'The most significant discovery has been the sheer size and maturity of galaxies formed much earlier than standard models predicted. We are seeing fully formed spiral arms and large stellar masses in a universe that should have been mostly primordial gas.',
                                'This telescope is not just confirming existing theories; it is fundamentally challenging the timelines of modern cosmology and stellar evolution.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Exoplanets: Detailed Atmospheric Chemistry',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Beyond deep space, JWST has revolutionized exoplanet science. Using the Transit Spectroscopy technique, the telescope has identified complex molecules—including signs of carbon dioxide, methane, and sulfur compounds—in the atmospheres of several potentially habitable worlds.',
                                'The depth of the data allows scientists to model cloud structures and temperature gradients with detail previously impossible. The search for true biosignatures is now a reality, not just a theoretical goal.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Celestron NexStar 6SE',
                            'brand' => 'Celestron',
                            'productName' => 'Astronomy Telescope',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=800&q=80'
                            ],
                            'price' => 899.00,
                            'currency' => '£',
                            'description' => 'A premium computerized telescope perfect for deep-sky observation and astrophotography beginners.',
                            'link' => 'https://example.com/nexstar-6se',
                            'linkText' => 'Explore the Cosmos',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 4.8,
                                'pros' => [
                                    'Computerized GoTo mount',
                                    'Great for beginners and enthusiasts',
                                    'Clear and bright planetary views',
                                    'Solid build quality'
                                ],
                                'cons' => [
                                    'Requires power supply',
                                    'Learning curve for first-time users'
                                ]
                            ]
                        ]
                    ],

                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Key Discoveries and Instrument Performance',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Instrument', 'Key Capability', 'Year 3 Highlight'],
                                ['NIRCam', 'Near-Infrared Imaging', 'Discovery of 6 ultra-redshift galaxies (z>13)'],
                                ['MIRI', 'Mid-Infrared Imaging', 'Detailed thermal maps of Jupiter and Saturn moons'],
                                ['NIRSpec', 'Spectroscopy', 'Confirmed water vapor in 5 exoplanet atmospheres'],
                                ['Fine Guidance Sensor', 'Pointing Stability', 'Achieved 99.9% uptime and stability for 10-hour exposures'],
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Astronomers have shifted focus from simply detecting galaxies to understanding their chemical composition using the telescope\'s spectroscopic instruments.'
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'Every time we point the JWST at a known target, we learn something new. When we look at the unknown, we rewrite entire chapters of cosmology.',
                            'attribution' => 'Dr. Thomas Zurbuchen, Former NASA Associate Administrator'
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
                                'JWST’s third year has confirmed its status as a revolutionary scientific instrument. It is functioning flawlessly and delivering science that is genuinely reshaping humanity\'s understanding of the cosmos.',
                                'While the sheer volume of data is challenging to process, the discoveries—from early galaxy formation to exoplanet atmospheres—are nothing short of spectacular. This mission is an unqualified 5/5 triumph.',
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Future Space Telescope Comparison 2030: Choosing the Best Observatory for Deep-Field Astronomy',
                'slug' => 'future-space-telescopes-buying-guide',
                'tags' => ['featured', 'guide', 'space', 'astronomy', 'telescope', 'buying-guide'],
                'categories' => ['Guides', 'Future Missions', 'Astrophysics'],
                'custom_fields' => [
                    'author_name' => 'Dr. Elara Khan',
                    'author_bio' => 'Aerospace journalist and space policy specialist.',
                    'read_time' => 12,
                    'game_title' => 'Next Generation Space Observatories',
                    'developer' => 'NASA, ESA, CalTech',
                    'publisher' => 'Decadal Survey',
                    'release_date' => '2030-2035',
                    'platforms' => 'Orbiting Observatories',
                    'genre' => 'Science Mission',
                    'rating' => 5,
                    'excerpt' => 'Which revolutionary telescope will succeed JWST? We compare the key instruments, cost, and mission goals of the next generation of space observatories, from UV to X-ray bands.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => '[https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb4.0.3&auto=format&fit=crop&w=2340&q=80](https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb4.0.3&auto=format&fit=crop&w=2340&q=80)',
                            'alt' => 'Future space telescope mirror concept',
                            'caption' => 'The next generation of telescopes will focus on direct exoplanet imaging and biosignature detection.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The future of astronomy lies in space, where atmospheric interference is eliminated. While JWST dominates the infrared, the next Decadal Survey highlights three critical areas for future missions: high-resolution UV/Optical, X-Ray chronology, and gravitational wave detection.',
                                'Our guide compares the three leading candidates for the 2030s, helping you understand their scientific niches and massive engineering challenges.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Mission 1: The Habitable Worlds Observatory (HWO)',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'HWO: Direct Exoplanet Imaging',
                            'subtitle' => 'The premier mission for detecting biosignatures in UV/Visible light',
                            'specs' => [
                                ['text' => 'Aperture Size (Mirror)', 'value' => '6 meters (Monolithic)'],
                                ['text' => 'Primary Wavelength', 'value' => 'UV, Visible, Near-IR'],
                                ['text' => 'Key Instrument', 'value' => 'High-Contrast Coronagraph'],
                                ['text' => 'Primary Goal', 'value' => 'Directly image Earth-like exoplanets'],
                                ['text' => 'Estimated Cost', 'value' => '$11 Billion+'],
                                ['text' => 'Launch Window', 'value' => '2035 (Target)'],
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Ability to filter starlight for direct planet observation.',
                                'Detection of biosignatures (O2, H2O, CH4) is primary goal.',
                                'Provides Hubble-level resolution across much larger field.'
                            ],
                            'cons' => [
                                'Extremely high technical risk and cost.',
                                'Coronagraph stability is a massive engineering challenge.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Mission 2: Lynx X-ray Observatory',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                '**Primary Band:** X-Ray (Soft and Hard)',
                                '**Key Focus:** Supermassive Black Holes and Early Galaxy Formation',
                                '**Resolution Goal:** 0.5 arcsecond angular resolution (100x better than Chandra)',
                                '**Optics:** Nested Mirrors (Grazing Incidence)',
                                '**Orbit:** High Earth Orbit or L2',
                                '**Verdict:** Essential for understanding the life cycle of hot, energetic objects in the cosmos.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Comparison: HWO vs. Roman Space Telescope',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Deep-Space Survey Capability',
                            'productA' => 'HWO (Habitable Worlds)',
                            'productB' => 'Roman (Wide-Field Infrared)',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Field of View',
                                    'items' => [
                                        ['value' => 'Extremely Narrow (Targeted)'],
                                        ['value' => 'Extremely Wide (Survey)'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Wavelength Focus',
                                    'items' => [
                                        ['value' => 'Visible/UV (Atmospheric Science)'],
                                        ['value' => 'Infrared (Dark Energy, Galaxy Mapping)'],
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'The Exoplanet Imperative',
                            'paragraphs' => [
                                'The Habitable Worlds Observatory represents a clear strategic shift by NASA: the next decade of space science is dominated by the search for life. HWO’s unique coronagraph will be the ultimate tool for this endeavor.',
                                'The primary technical bottleneck remains the deployment of its massive, fragile sunshield and mirror components—a lesson learned (and hopefully mastered) from JWST.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'The Future of Space Travel: 5 Breakthrough Technologies We Need by 2050',
                'slug' => 'future-space-tech-wishlist-2050',
                'tags' => ['featured', 'opinion', 'space', 'technology', 'future', 'wishlist'],
                'categories' => ['Opinion', 'Future Missions', 'Technology'],
                'custom_fields' => [
                    'author_name' => 'Dr. Elara Khan',
                    'author_bio' => 'Aerospace journalist and space policy specialist.',
                    'read_time' => 9,
                    'game_title' => 'Interstellar Ambition',
                    'developer' => 'NASA, SpaceX, Blue Origin',
                    'publisher' => 'The Stars',
                    'release_date' => '2050 (Target)',
                    'platforms' => 'Solar System',
                    'genre' => 'Advanced Propulsion',
                    'rating' => 5,
                    'excerpt' => 'Current chemical rockets are the horse-and-buggy of space travel. To truly colonize Mars and explore the outer solar system, we need radical, revolutionary breakthroughs in propulsion, power, and life support.'
                ]
                ,
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => '[https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80](https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80)',
                            'alt' => 'Conceptual spaceship with advanced propulsion drive',
                            'caption' => 'Reaching Mars in weeks instead of months requires breaking the tyranny of chemical propulsion.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'While humanity is focused on Mars, the multi-month travel time remains the greatest barrier to routine, safe travel. The long journey exposes astronauts to cosmic radiation, microgravity health issues, and psychological strain.',
                                'Our wishlist focuses on solutions that radically change the mass-to-power ratio of spacecraft, enabling fast transit and true in-situ self-sufficiency.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Wishlist Item 1: Fusion Propulsion (The Warp Drive Precursor)',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Chemical propulsion is slow. Fission power (like in Project Orion) is risky. Fusion propulsion—using controlled nuclear fusion to generate a massive, sustained thrust—offers the high specific impulse (efficiency) and high thrust needed to drastically cut transit times to weeks. This is the single biggest enabler for colonizing the Solar System.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'Until we achieve a technology with a specific impulse 100 times greater than current chemical rockets, we are essentially stuck in low Earth orbit. Fusion is the key.',
                            'attribution' => 'Dr. Michio Kaku, Theoretical Physicist'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Remaining 4 Essential Technologies',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'items' => [
                                '**Closed-Loop Bioregenerative Life Support:** Today\'s systems are mostly "open-loop" (rely on resupply). A true closed-loop system—like a self-sustaining miniature ecosystem using algae and bacteria—is required for missions beyond Mars, recycling 99% of air, water, and waste.',
                                '**AI-Driven Autonomous Repair:** Human crews on deep-space missions cannot afford to wait for instructions from Earth. AI systems must be able to detect, diagnose, and autonomously repair 90% of all system failures (e.g., repairing micro-fractures in hulls or fixing reactor issues).',
                                '**Effective Artificial Gravity (Constant Spin):** Long-term exposure to microgravity causes bone density loss, vision damage, and muscle atrophy. We need standardized, large-scale spacecraft designs that rotate (via tethers or structural spin) to provide 1G of constant gravity for crew health.',
                                '**Advanced Radiation Shielding Materials:** Current shielding relies on heavy polyethylene. We need breakthroughs in lighter, active shielding (like high-powered magnetic fields) or advanced structural materials that integrate shielding into the hull without adding prohibitive mass.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'The goal is to turn spacecraft into self-sufficient mini-worlds. The emphasis must shift from propulsion to survivability and autonomy for distances further than the Moon.'
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
            'title' => 'About Space.com',
            'slug' => 'about-us',
            'status' => 'published',
            'meta_title' => 'About Space.com: The Editorial Team',
            'meta_description' => 'Learn more about the editorial team behind Space.com.',
            'page_type' => 'landing-page',
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'About Cosmos Report',
                    'subtitle' => 'News and insight from space science to commercial exploration',
                    'ctaText' => 'Meet the Team',
                    'ctaUrl' => '#team',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Cosmos Report brings authoritative coverage of astrophysics, planetary science, and the growing commercial space sector. We translate complex research into accurate, engaging stories for scientists, enthusiasts, and policymakers.',
                        'Our reporters and editors include researchers, mission scientists, and former industry engineers who provide first-hand analysis of missions, technology and discoveries.',
                        'From probe telemetry to orbital dynamics and human spaceflight policy, we cover the developments shaping humanity’s presence in space.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Impact & Coverage',
                    'stats' => [
                        ['number' => '12', 'label' => 'Years of Reporting', 'icon' => '🚀'],
                        ['number' => '200+', 'label' => 'Mission Briefings & Features', 'icon' => '🛰️'],
                        ['number' => '1M+', 'label' => 'Monthly Readers', 'icon' => '📖'],
                        ['number' => '30+', 'label' => 'Scientific Contributors', 'icon' => '🔭']
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Contributors',
                    'subtitle' => 'Scientists, Engineers & Reporters',
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
                            'name' => 'Dr. Helena Vargas',
                            'role' => 'Astrophysics Director',
                            'bio' => 'Astrophysicist with extensive experience on exoplanet surveys and space telescope science teams.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'helena.vargas@cosmosreport.com'
                        ],
                        [
                            'name' => 'Marcus Li',
                            'role' => 'Space Technology Editor',
                            'bio' => 'Covers propulsion, vehicle design and the commercialization of access to orbit.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'marcus.li@cosmosreport.com'
                        ],
                        [
                            'name' => 'Dr. Sara Monteiro',
                            'role' => 'Planetary Science Correspondent',
                            'bio' => 'Research scientist focused on planetary geology and astrobiology with field experience in analog environments.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1529626455594-4ff0802cfb7e?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'sara.monteiro@cosmosreport.com'
                        ],
                        [
                            'name' => 'Col. David Armstrong (Ret.)',
                            'role' => 'Human Spaceflight Analyst',
                            'bio' => 'Former test pilot and mission specialist offering operational insight into crewed missions.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'david.armstrong@cosmosreport.com'
                        ],
                        [
                            'name' => 'Aisha Rahman',
                            'role' => 'Satellite & Communications Reporter',
                            'bio' => 'Covers Earth observation, communications constellations and remote sensing applications.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1544723795-3fb6469f5b39?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'aisha.rahman@cosmosreport.com'
                        ],
                        [
                            'name' => 'Leo Parker',
                            'role' => 'Education & Outreach Lead',
                            'bio' => 'Science communicator building public programs and educational partnerships in astronomy.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1463453091185-61582044d556?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'leo.parker@cosmosreport.com'
                        ]
                    ]
                ],
                'order' => 5
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Reporting Principles',
                    'level' => 2
                ],
                'order' => 6
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'We prioritize accuracy, reproducibility and clear sourcing. Where applicable we consult mission teams and peer-reviewed literature to ensure our coverage reflects the best available science.'
                    ]
                ],
                'order' => 7
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Mission Briefs — Technical summaries and implications',
                        'Research Highlights — Key findings from peer-reviewed work',
                        'Policy Analysis — The intersection of space and government',
                        'Feature Reporting — Human stories from the space sector'
                    ]
                ],
                'order' => 8
            ],
            [
                'type' => 'note',
                'data' => [
                    'title' => 'Editorial Independence',
                    'paragraphs' => [
                        'We accept no sponsored content that compromises scientific accuracy. Our mission is to inform and educate with impartiality.',
                        'When conflicts of interest arise we disclose them clearly in our reporting.'
                    ],
                    'alignment' => 'fullscreen'
                ],
                'order' => 9
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'To confine our attention to terrestrial matters would be to limit the human spirit.',
                    'attribution' => 'Stephen Hawking'
                ],
                'order' => 10
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createContactPage(): void
    {
        $page = Page::create([
            'title' => 'Contact Space.com Monthly',
            'page_type' => 'landing-page',
            'slug' => 'contact-us',
            'status' => 'published',
            'meta_title' => 'Contact Space.com',
            'meta_description' => 'Get in touch with the Space.com editorial team.',
            'site_id' => $this->site->id,
        ]);


        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Contact Mission Control',
                    'subtitle' => 'Share discoveries, research questions, or story proposals.',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Cosmos Report Editorial',
                    'role' => 'Contact Information',
                    'email' => 'hq@cosmosreport.com',
                    'phone' => '+1 (555) 993-2020',
                    'address' => "Cosmos Report\nOrbital Research Center\nHouston, TX 77058\n\nComms Window:\nMon–Fri: 0900–1700 CST",
                    'displayType' => 'contact',
                    'image' => [
                        'src' => 'https://images.unsplash.com/photo-1581091870622-1e7ab090a966?auto=format&fit=crop&w=800&q=80',
                        'alt' => 'Space Communications Office'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'We welcome article proposals from astronomers, aerospace engineers, and space science communicators.',
                        'For technical issues with the site or app, email support@cosmosreport.com.',
                        'Media inquiries and partnerships should be directed to press@cosmosreport.com.'
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Send a Transmission',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => false,
                    'showSubject' => true,
                    'showMessage' => true,
                    'submitButtonText' => 'Send Transmission',
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