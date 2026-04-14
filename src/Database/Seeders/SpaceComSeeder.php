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
        $this->createSite();
        $this->createTags();
        $this->createCategories();
        $this->createMenu();
        $this->createHomepage();
        $this->createArticles();
        $this->createAboutPage();
        $this->createContactPage();
        $this->createPageGrid();
        $this->createMenuNavItems();
    }

    private function createPageGrid(): void
    {
        $items = [];

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
                'title' => 'SpaceX Starship: The Ultimate Deep-Space Vehicle or Overhyped Promise?',
                'slug' => 'spacex-starship-deep-space-analysis',
                'tags' => ['SpaceX', 'featured', 'analysis'],
                'categories' => ['Opinion', 'Technology'],
                'custom_fields' => [
                    'author_name' => 'Marcus Li',
                    'author_bio' => 'Space technology editor covering propulsion and vehicle design.',
                    'read_time' => 11,
                    'excerpt' => 'SpaceX\'s fully reusable super-heavy-lift launch system promises to revolutionize space access, but significant hurdles remain before it can deliver on its ambitious goals.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1581093458791-9f3c2e26a47d?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Starship on launch pad',
                            'caption' => 'The fully stacked Starship vehicle represents the most powerful rocket ever built',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'SpaceX\'s Starship program has captured global attention with its audacious goal: a fully reusable spacecraft capable of carrying 100+ tons to Mars. The vehicle\'s sheer scale is staggering—standing at 120 meters tall with 33 Raptor engines on the Super Heavy booster alone.',
                                'But behind the spectacular test flights and explosive prototypes lies a complex engineering reality. Is Starship truly the vehicle that will make humanity multi-planetary, or is it a case of over-promising and under-delivering?'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Starship By The Numbers',
                            'stats' => [
                                ['number' => '120m', 'label' => 'Total Height', 'icon' => '📏'],
                                ['number' => '150t', 'label' => 'Payload to LEO', 'icon' => '🚀'],
                                ['number' => '33', 'label' => 'Raptor Engines (Booster)', 'icon' => '🔥'],
                                ['number' => '100+', 'label' => 'Passengers to Mars', 'icon' => '👥']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Reusability Challenge',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The cornerstone of Starship\'s economics is full reusability. Unlike Falcon 9, which recovers only the first stage, Starship aims to recover and rapidly refly both stages. This requires unprecedented thermal protection, precision landing, and turnaround speed.',
                                'Current test campaigns show promise but also reveal the immense difficulty. Heat shield tiles continue to be a pain point, with multiple tiles lost or damaged during reentry on recent flights.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Aspect', 'Target', 'Current Status'],
                                ['Booster Recovery', '100% reusable', 'Demonstrated (tower catch)'],
                                ['Ship Recovery', '100% reusable', 'In development'],
                                ['Reflight Time', '<24 hours', 'Months (testing phase)'],
                                ['Orbital Refueling', 'Essential for Mars', 'Not yet demonstrated']
                            ]
                        ]
                    ],
                    [
                        'type' => 'accordion',
                        'data' => [
                            'title' => 'Key Technical Hurdles',
                            'items' => [
                                [
                                    'question' => 'Why is orbital refueling so critical?',
                                    'answer' => 'Starship cannot reach Mars on a single launch. It requires multiple tanker launches to transfer propellant in orbit—a process never attempted at this scale. Without proven orbital refueling, the entire Mars architecture collapses.',
                                    'isOpen' => true
                                ],
                                [
                                    'question' => 'What about the heat shield reliability?',
                                    'answer' => 'The ship uses thousands of ceramic tiles similar to the Space Shuttle. However, unlike the Shuttle, Starship must withstand reentry dozens of times. Tile attachment, thermal cycling fatigue, and inspection/replacement logistics remain major engineering challenges.'
                                ],
                                [
                                    'question' => 'Can Starship really carry 100 people to Mars?',
                                    'answer' => 'The cabin volume supports it, but life support, radiation shielding, psychological factors, and emergency abort capabilities for 100+ passengers on a 6-month journey remain largely theoretical. Current designs focus on cargo missions first.'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Starship is the most ambitious aerospace program since Apollo. It will either revolutionize spaceflight or become a cautionary tale of overreach.',
                            'attribution' => 'Dr. Robert Zubrin, Mars Society Founder'
                        ]
                    ],
                    [
                        'type' => 'testimonial',
                        'data' => [
                            'layout' => 'grid',
                            'testimonials' => [
                                [
                                    'text' => 'The iterative test approach is brilliant. SpaceX learns faster by flying hardware than by endless simulations.',
                                    'author' => 'Dr. Helen Zhao',
                                    'role' => 'Propulsion Engineer',
                                    'rating' => 5
                                ],
                                [
                                    'text' => 'I\'m skeptical of the timeline. Mars by 2030? We haven\'t even proven orbital refueling yet.',
                                    'author' => 'James Carter',
                                    'role' => 'Aerospace Analyst',
                                    'rating' => 3
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Verdict: Promise vs. Reality',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Starship represents genuine innovation and the best chance humanity has for affordable deep-space access. The rapid iteration and willingness to fail publicly are refreshing in an industry often paralyzed by risk aversion.',
                                'However, the gap between current capabilities and stated goals remains vast. Orbital refueling, long-duration life support, and Mars EDL (Entry, Descent, Landing) are all unsolved problems at Starship scale.',
                                'The vehicle will likely succeed as a revolutionary LEO/cislunar transport. Mars colonization? That timeline needs significant adjustment.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Beginner\'s Guide to Astrophotography: Capturing the Night Sky on a Budget',
                'slug' => 'beginner-astrophotography-guide-budget',
                'tags' => ['guide', 'astronomy', 'featured'],
                'categories' => ['Guides', 'Stargazing'],
                'custom_fields' => [
                    'author_name' => 'Leo Parker',
                    'author_bio' => 'Science communicator and amateur astronomer.',
                    'read_time' => 15,
                    'excerpt' => 'You don\'t need expensive equipment to photograph stunning images of stars, nebulae, and galaxies. This comprehensive guide shows you how to start with gear you may already own.'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Astrophotography for Beginners',
                            'subtitle' => 'Capture the cosmos without breaking the bank',
                            'ctaText' => 'Start Learning',
                            'ctaUrl' => '#equipment',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1419242902214-272b3f66ee7a?auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Astrophotography combines the wonder of astronomy with the art of photography. While professional setups can cost thousands, stunning results are achievable with modest equipment and patience.',
                                'This guide covers everything from your first Milky Way shot to tracking deep-sky objects, all on a reasonable budget.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => '💡 Pro Tip: Start with wide-field Milky Way photography before investing in telescopes. It teaches essential skills and requires minimal equipment.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Essential Equipment',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Budget Astrophotography Starter Kit',
                            'subtitle' => 'Everything you need for under £1,000',
                            'specs' => [
                                ['text' => 'Camera Body', 'value' => 'Used DSLR or mirrorless (£300-500)'],
                                ['text' => 'Lens', 'value' => 'Fast wide-angle 14-24mm f/2.8 (£200-400)'],
                                ['text' => 'Tripod', 'value' => 'Sturdy with ball head (£80-150)'],
                                ['text' => 'Remote Shutter', 'value' => 'Wired or wireless (£15-30)'],
                                ['text' => 'Optional: Star Tracker', 'value' => 'For longer exposures (£200-400)']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Entry-level gear produces impressive results',
                                'Skills transfer to advanced setups',
                                'Used market offers excellent value',
                                'Most equipment has resale value'
                            ],
                            'cons' => [
                                'Older cameras have higher noise at high ISO',
                                'Budget lenses may show coma at edges',
                                'Manual focusing required in the dark'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'DSLR vs. Mirrorless for Astrophotography',
                            'productA' => 'DSLR (e.g., Canon 6D)',
                            'productB' => 'Mirrorless (e.g., Sony A7 III)',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Low-Light Performance',
                                    'items' => [
                                        ['value' => 'Good (Full-frame sensor)'],
                                        ['value' => 'Excellent (Better dynamic range)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Live View Focus',
                                    'items' => [
                                        ['value' => 'Slower, uses contrast detection'],
                                        ['value' => 'Fast, with focus peaking']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Battery Life',
                                    'items' => [
                                        ['value' => 'Excellent (optical viewfinder)'],
                                        ['value' => 'Limited (electronic viewfinder)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Price (Used)',
                                    'items' => [
                                        ['value' => '£400-600'],
                                        ['value' => '£800-1,200']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Step-by-Step: Your First Milky Way Photo',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'schema',
                        'data' => [
                            'schemaType' => 'how-to',
                            'title' => 'Capturing the Milky Way',
                            'description' => 'The complete process from location scouting to image processing',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1419242902214-272b3f66ee7a?auto=format&fit=crop&w=800&q=80']
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Find a Dark Sky Site: Use light pollution maps (lightpollutionmap.info) to locate areas with Bortle class 3 or darker.',
                                'Check the Weather and Moon Phase: Clear skies and a new moon (or moon below horizon) are essential.',
                                'Setup and Focus: Mount camera on tripod, switch to manual focus, use live view to focus on a bright star.',
                                'Camera Settings: Manual mode, f/2.8 or widest aperture, ISO 3200-6400, shutter speed 15-25 seconds (use "500 rule": 500 ÷ focal length = max shutter speed).',
                                'Compose and Shoot: Include foreground interest, take multiple frames.',
                                'Post-Processing: Stack images if desired, adjust white balance, lift shadows, reduce noise, enhance contrast.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Focal Length', '500 Rule Max Shutter', 'Typical ISO'],
                                ['14mm', '35 seconds', '3200-6400'],
                                ['20mm', '25 seconds', '3200-6400'],
                                ['24mm', '20 seconds', '4000-6400'],
                                ['35mm', '14 seconds', '5000-8000']
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Understanding the 500 Rule',
                            'paragraphs' => [
                                'The 500 rule prevents star trailing due to Earth\'s rotation. Divide 500 by your lens focal length to get the maximum shutter speed before stars appear as streaks rather than points.',
                                'Example: With a 20mm lens, 500 ÷ 20 = 25 seconds maximum.',
                                'For crop-sensor cameras, multiply focal length by crop factor first (typically 1.5x or 1.6x).'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Advanced Techniques: Deep-Sky Objects',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Once comfortable with wide-field imaging, deep-sky objects (nebulae, galaxies, star clusters) become accessible with a star tracker and telephoto lens or small telescope.',
                                'A star tracker compensates for Earth\'s rotation, allowing exposures of several minutes. This dramatically improves signal-to-noise ratio and reveals faint structures.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Orion Nebula',
                                    'description' => 'Captured with 200mm lens and star tracker',
                                    'image' => 'https://images.unsplash.com/photo-1462331940025-496dfbfc7564?auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Orion Nebula astrophotography'
                                ],
                                [
                                    'title' => 'Andromeda Galaxy',
                                    'description' => 'Stack of 50x 3-minute exposures',
                                    'image' => 'https://images.unsplash.com/photo-1543722530-d2c3201371e7?auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Andromeda Galaxy'
                                ],
                                [
                                    'title' => 'North America Nebula',
                                    'description' => 'Wide-field with 135mm lens',
                                    'image' => 'https://images.unsplash.com/photo-1419242902214-272b3f66ee7a?auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'North America Nebula region'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'accordion',
                        'data' => [
                            'title' => 'Common Issues and Solutions',
                            'items' => [
                                [
                                    'question' => 'My images are blurry or have star trails',
                                    'answer' => 'Check focus (use live view at 10x magnification), ensure tripod is stable, reduce shutter speed following the 500 rule, or add a star tracker for longer exposures.',
                                    'isOpen' => false
                                ],
                                [
                                    'question' => 'Too much noise/grain in my photos',
                                    'answer' => 'Take multiple exposures and stack them (reduces random noise), use dark frames for calibration, consider a camera with better high-ISO performance, or shoot in RAW for better post-processing latitude.'
                                ],
                                [
                                    'question' => 'Colors look wrong or washed out',
                                    'answer' => 'Shoot in RAW format, adjust white balance in post-processing (typically 3400-4000K for night sky), enhance saturation carefully, use curves to bring out subtle nebulosity.'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Astrophotography taught me patience. Each photon traveling millions of light-years deserves careful capture.',
                            'attribution' => 'Leo Parker, Astrophotographer'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Breaking: China Announces Permanent Lunar Research Station with Russia by 2035',
                'slug' => 'china-russia-lunar-station-2035',
                'tags' => ['breaking-news', 'Moon', 'ISS'],
                'categories' => ['News', 'Mission Analysis'],
                'custom_fields' => [
                    'author_name' => 'Dr. Aria Noor',
                    'author_bio' => 'International space policy correspondent.',
                    'read_time' => 8,
                    'excerpt' => 'The China National Space Administration and Roscosmos have jointly announced ambitious plans for the International Lunar Research Station, aiming for continuous human presence at the Moon\'s south pole by 2035.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1614730321146-b6fa6a46bcb4?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Lunar base concept',
                            'caption' => 'Artist rendering of the proposed International Lunar Research Station',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => '🚨 Breaking News: This announcement was made during a joint press conference in Beijing on December 23, 2025.'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'In a major development for lunar exploration, China and Russia have formalized their partnership for the International Lunar Research Station (ILRS), with construction phases beginning in 2030 and continuous human occupation targeted for 2035.',
                                'The station will be located near the lunar south pole, specifically targeting the Shackleton Crater rim area known for its near-permanent sunlight and suspected water ice deposits in permanently shadowed regions.',
                                'Unlike NASA\'s Artemis program, which emphasizes periodic visits, the ILRS is designed for permanent habitation with rotating crews staying for 6-month durations.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'ILRS Mission Profile',
                            'stats' => [
                                ['number' => '2030', 'label' => 'Construction Begins', 'icon' => '🏗️'],
                                ['number' => '2035', 'label' => 'Permanent Crew', 'icon' => '👨‍🚀'],
                                ['number' => '6', 'label' => 'Crew Capacity', 'icon' => '👥'],
                                ['number' => '14', 'label' => 'Partner Nations (Target)', 'icon' => '🌍']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Mission Architecture',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Phase 1 (2026-2030): Robotic reconnaissance, resource mapping, site preparation',
                                'Phase 2 (2030-2032): Core module delivery, power systems deployment, initial habitat construction',
                                'Phase 3 (2032-2035): Full infrastructure completion, life support testing, first long-duration crews',
                                'Phase 4 (2035+): Science operations, ISRU demonstration, commercial partnerships'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Component', 'Provider', 'Target Deployment'],
                                ['Core Habitat Module', 'CNSA', '2030'],
                                ['Power Station (Nuclear)', 'Roscosmos', '2031'],
                                ['Landing Pad', 'Joint Development', '2029'],
                                ['Communications Array', 'CNSA', '2030'],
                                ['ISRU Processing Plant', 'Joint Development', '2033']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Geopolitical Implications',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'This announcement effectively creates competing lunar programs between China-Russia and the US-led Artemis coalition. Both are targeting the same high-value south pole regions, raising questions about resource rights and territorial claims under the Outer Space Treaty.',
                                'Several nations, including Pakistan, UAE, and Venezuela, have expressed interest in joining the ILRS partnership, though technical contributions remain undefined.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'testimonial',
                        'data' => [
                            'layout' => 'grid',
                            'testimonials' => [
                                [
                                    'text' => 'This is a serious challenge to US lunar leadership. The technical capability is real, and the timeline is aggressive but achievable.',
                                    'author' => 'Dr. Laura Chen',
                                    'role' => 'Space Policy Institute',
                                    'rating' => null
                                ],
                                [
                                    'text' => 'The Outer Space Treaty prohibits territorial claims. Multiple nations operating in the same region creates unavoidable conflicts.',
                                    'author' => 'Prof. James Morrison',
                                    'role' => 'International Space Law',
                                    'rating' => null
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Expert Analysis',
                            'paragraphs' => [
                                'The ILRS represents China\'s maturation as a spacefaring power. Their successful Chang\'e missions demonstrated reliable landing capability, sample return, and even a small rover deployed on the far side.',
                                'However, significant technical gaps remain: long-duration life support, rapid crew rotation, and most critically, the heavy-lift launch capacity needed to deliver large modules. China\'s Long March 9 rocket, equivalent to Saturn V, is still in development.',
                                'The 2035 timeline is optimistic. A more realistic assessment would be first permanent crew by 2037-2040.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The Moon is becoming crowded. International coordination is now essential to prevent conflicts and ensure safety.',
                            'attribution' => 'UN Office for Outer Space Affairs'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Exoplanet Biosignatures: How We\'ll Know If We\'ve Found Alien Life',
                'slug' => 'exoplanet-biosignatures-detection-methods',
                'tags' => ['Exoplanets', 'science', 'Webb'],
                'categories' => ['Reviews', 'Astrophysics'],
                'custom_fields' => [
                    'author_name' => 'Dr. Sara Monteiro',
                    'author_bio' => 'Planetary scientist specializing in astrobiology.',
                    'read_time' => 13,
                    'excerpt' => 'The search for life beyond Earth is entering a new phase. With advanced spectroscopy, we can now analyze the atmospheres of distant worlds. But what exactly are we looking for, and how certain can we be?'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'The Search for Alien Life',
                            'subtitle' => 'Understanding biosignatures in exoplanet atmospheres',
                            'ctaText' => 'Explore the Science',
                            'ctaUrl' => '#biosignatures',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1614313913007-2b4ae8ce32d6?auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'We stand at a profound threshold in human history. For the first time, we possess the technology to detect potential signs of life on planets orbiting other stars. But the science is complex, and certainty elusive.',
                                'A biosignature is any substance, pattern, or phenomenon whose presence requires biological activity. On Earth, oxygen in our atmosphere is a prime example—without constant replenishment by photosynthesis, it would be rapidly consumed by geological processes.',
                                'Detecting similar markers on exoplanets thousands of light-years away requires extraordinary precision and careful interpretation.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Primary Biosignature Candidates',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Molecule', 'Significance', 'False Positive Risk'],
                                ['Oxygen (O₂)', 'Produced by photosynthesis', 'Moderate (photodissociation of water)'],
                                ['Ozone (O₃)', 'Byproduct of oxygen', 'Low to moderate'],
                                ['Methane (CH₄)', 'Biological metabolism', 'High (geological sources common)'],
                                ['Phosphine (PH₃)', 'Produced by anaerobic life', 'Low (few abiotic pathways)'],
                                ['Dimethyl Sulfide', 'Marine biological activity', 'Very low (highly specific)'],
                                ['Chlorophyll Red Edge', 'Photosynthetic pigments', 'Very low (requires direct imaging)']
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'note',
                            'description' => 'A single biosignature gas is never sufficient. Scientists look for combinations of gases in disequilibrium—mixtures that would rapidly react away unless constantly replenished.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Detection Methods',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Transit spectroscopy remains our primary tool. As an exoplanet passes in front of its star, starlight filters through the planet\'s atmosphere. Different molecules absorb specific wavelengths, creating a unique spectral fingerprint.',
                                'JWST\'s mid-infrared instruments can detect these absorption features with unprecedented sensitivity. But interpreting the data requires ruling out countless abiotic (non-biological) explanations.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'schema',
                        'data' => [
                            'schemaType' => 'how-to',
                            'title' => 'Transit Spectroscopy Process',
                            'description' => 'How scientists extract atmospheric composition from light curves',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1614313913007-2b4ae8ce32d6?auto=format&fit=crop&w=800&q=80']
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Observe host star spectrum (baseline)',
                                'Observe star+planet spectrum during transit',
                                'Subtract baseline to isolate atmospheric absorption',
                                'Match absorption features to molecular databases',
                                'Model atmospheric structure and chemistry',
                                'Assess probability of biological vs. geological origin'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The K2-18b Controversy',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'In 2023, JWST observations of K2-18b (a "Hycean" world—a water world with a hydrogen-rich atmosphere) detected dimethyl sulfide (DMS), a gas on Earth produced almost exclusively by marine phytoplankton.',
                                'The finding sparked intense debate. DMS is considered a "hard-to-fake" biosignature, but K2-18b orbits a red dwarf star known for violent flares. Could radiation-driven chemistry mimic biology?',
                                'Follow-up observations are ongoing, but this case exemplifies the challenge: even "smoking gun" detections require exhaustive vetting.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'award',
                        'data' => [
                            'subcategory' => 'Most Promising Biosignature Target',
                            'productName' => 'TRAPPIST-1e',
                            'image' => 'https://images.unsplash.com/photo-1614728894747-a83421e2b9c9?auto=format&fit=crop&w=800&q=80',
                            'winner' => true,
                            'rating' => 5.0,
                            'strapline' => 'Earth-sized planet in the habitable zone of a nearby red dwarf',
                            'caption' => 'JWST observations scheduled for 2026 mission cycle'
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Habitable Worlds Observatory',
                            'brand' => 'NASA',
                            'productName' => 'Next-Generation Space Telescope',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1543152115-ff99c7595d2c?auto=format&fit=crop&w=800&q=80'],
                            'price' => 11.0,
                            'currency' => '$',
                            'description' => 'Billion USD estimated cost. Designed specifically for direct imaging of Earth-like exoplanets and biosignature detection.',
                            'link' => 'https://example.com/hwo',
                            'linkText' => 'Mission Details',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => null,
                                'pros' => [
                                    'Ultra-stable coronagraph for star suppression',
                                    'UV/Visible/Near-IR coverage',
                                    'Can image multiple planets per system'
                                ],
                                'cons' => [
                                    'Launch not before 2040',
                                    'Extremely high technical risk',
                                    'Budget subject to political changes'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'accordion',
                        'data' => [
                            'title' => 'Common Misconceptions',
                            'items' => [
                                [
                                    'question' => 'If we detect oxygen, does that prove life?',
                                    'answer' => 'No. Oxygen can be produced abiotically through photodissociation of water vapor by UV radiation. We need oxygen in combination with reduced gases like methane, which react away rapidly unless replenished—a signature of biological disequilibrium.',
                                    'isOpen' => true
                                ],
                                [
                                    'question' => 'Can we detect technological civilizations?',
                                    'answer' => 'Technosignatures (industrial pollutants, artificial light, megastructures) are theoretically detectable but require different instruments and strategies. SETI focuses on radio signals, while future telescopes might detect atmospheric pollutants like CFCs.'
                                ],
                                [
                                    'question' => 'Why focus on Earth-like life? Couldn\'t aliens be completely different?',
                                    'answer' => 'We focus on life-as-we-know-it because it\'s the only chemistry we understand. Silicon-based life, exotic solvents, or energy sources beyond chemistry are speculative. We look for what we can recognize.'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Extraordinary claims require extraordinary evidence. The first confirmed biosignature detection will require years of verification and consensus.',
                            'attribution' => 'Dr. Sara Seager, MIT Astrophysicist'
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'The Road Ahead',
                            'paragraphs' => [
                                'We are in the reconnaissance phase. Current instruments can detect tantalizingclues but rarely definitive proof. The next generation—HWO, LUVOIR concepts, and ground-based ELTs (Extremely Large Telescopes)—will provide the sensitivity needed for certainty.',
                                'The discovery of extraterrestrial life, even microbial, would be the most profound scientific revelation in history. We may be a decade away. Or we may be centuries. But for the first time, we are genuinely looking.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Aurora Alert: Massive Solar Storm Creates Rare Auroras Visible from Mediterranean',
                'slug' => 'solar-storm-aurora-mediterranean-sighting',
                'tags' => ['Aurora', 'breaking-news', 'science'],
                'categories' => ['News', 'Solar Physics'],
                'custom_fields' => [
                    'author_name' => 'Mira Sanchez',
                    'author_bio' => 'Space weather correspondent.',
                    'read_time' => 6,
                    'excerpt' => 'A powerful G5-class geomagnetic storm has pushed the aurora borealis to unusually low latitudes, with confirmed sightings from Greece, southern Spain, and even northern Africa.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1531366936337-7c912a4589a7?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Aurora borealis over mountains',
                            'caption' => 'The aurora australis as seen from Tasmania during the December 2025 geomagnetic storm',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => '⚡ Active G5 Storm: NOAA Space Weather Prediction Center has issued a severe geomagnetic storm warning through December 25, 2025.'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'In a spectacular display of solar fury, a coronal mass ejection (CME) that erupted from sunspot region AR3529 on December 21 has triggered the most powerful geomagnetic storm since the "Halloween Storms" of 2003.',
                                'The storm\'s intensity—rated G5 on the NOAA scale—has compressed Earth\'s magnetosphere dramatically, allowing high-energy particles to penetrate to latitudes as low as 35° north and south.',
                                'Social media exploded with images of crimson and green curtains dancing over Athens, Barcelona, and even fleeting reports from Tunis. For millions in southern Europe and the Mediterranean, it was a once-in-a-lifetime sight.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Storm Metrics',
                            'stats' => [
                                ['number' => 'G5', 'label' => 'Storm Classification (Extreme)', 'icon' => '⚡'],
                                ['number' => '35°', 'label' => 'Lowest Latitude Sighting', 'icon' => '🌍'],
                                ['number' => '-420 nT', 'label' => 'Peak Dst Index', 'icon' => '📊'],
                                ['number' => '48hrs+', 'label' => 'Storm Duration', 'icon' => '⏱️']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What Causes Extreme Auroras?',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Auroras occur when charged particles from the Sun interact with Earth\'s magnetic field and atmosphere. During normal solar activity, these particles are funneled to the polar regions, creating the familiar northern and southern lights.',
                                'During extreme geomagnetic storms, the magnetosphere is compressed and disturbed. The auroral oval expands dramatically toward the equator, and the intensity increases, often producing rare red auroras from excited oxygen atoms at higher altitudes.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Athens, Greece',
                                    'description' => 'Red aurora over the Parthenon',
                                    'image' => 'https://images.unsplash.com/photo-1531366936337-7c912a4589a7?auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Aurora over Athens'
                                ],
                                [
                                    'title' => 'Barcelona, Spain',
                                    'description' => 'Green and purple bands visible from coastline',
                                    'image' => 'https://images.unsplash.com/photo-1579033461380-adb47c3eb938?auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Aurora over Barcelona'
                                ],
                                [
                                    'title' => 'Tasmania, Australia',
                                    'description' => 'Intense aurora australis display',
                                    'image' => 'https://images.unsplash.com/photo-1531366936337-7c912a4589a7?auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Aurora australis'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Infrastructure Impacts',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Power Grid Fluctuations: Several European utilities reported voltage instability. No major outages occurred, but grid operators placed systems in protective mode.',
                                'Satellite Communications: Multiple LEO satellites entered safe mode. GPS accuracy degraded by 10-30 meters in some regions.',
                                'Aviation Rerouting: Polar routes were avoided due to radiation exposure concerns and HF radio blackouts.',
                                'Amateur Radio: HF bands experienced severe disruption, while VHF propagation was enhanced due to ionospheric disturbances.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Historical Context',
                            'paragraphs' => [
                                'G5 storms are rare, occurring on average once per solar cycle (11 years). The last comparable event was in May 2024.',
                                'The most famous historical storm, the 1859 Carrington Event, caused auroras visible from the Caribbean and set telegraph systems on fire. Modern infrastructure is far more vulnerable.',
                                'This event underscores the importance of space weather forecasting and grid hardening as solar maximum approaches in 2025-2026.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'cta',
                        'data' => [
                            'text' => 'Check Current Aurora Forecast',
                            'url' => 'https://www.swpc.noaa.gov/communities/space-weather-enthusiasts'
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'These events remind us that we live on a planet bathed in the wind of a nuclear furnace. Our technology is fragile against the Sun\'s fury.',
                            'attribution' => 'Dr. Tamitha Skov, Space Weather Physicist'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'ISS Decommissioning Guide: Everything You Need to Know About the Station\'s Final Years',
                'slug' => 'iss-decommissioning-end-of-life-plan',
                'tags' => ['ISS', 'guide', 'NASA'],
                'categories' => ['Guides', 'Human Spaceflight'],
                'custom_fields' => [
                    'author_name' => 'Col. David Armstrong (Ret.)',
                    'author_bio' => 'Former ISS mission specialist and spaceflight operations analyst.',
                    'read_time' => 16,
                    'excerpt' => 'After nearly three decades in orbit, the International Space Station will be deorbited in 2030. This comprehensive guide explains the decommissioning process, what comes next, and how to watch the final descent.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1446776653964-20c1d3a81b06?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'International Space Station in orbit',
                            'caption' => 'The ISS has been continuously occupied since November 2000—nearly 25 years of human presence in space',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The International Space Station, humanity\'s greatest achievement in space cooperation, is approaching its end. After supporting over 260 individuals from 20 countries, hosting thousands of experiments, and serving as a testbed for deep-space technology, the station will be safely deorbited in 2030.',
                                'This guide walks through the technical, political, and logistical challenges of retiring the largest human-made object ever to orbit Earth.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'ISS Legacy By The Numbers',
                            'stats' => [
                                ['number' => '25yrs', 'label' => 'Continuous Human Presence', 'icon' => '👨‍🚀'],
                                ['number' => '420t', 'label' => 'Total Mass', 'icon' => '⚖️'],
                                ['number' => '3,000+', 'label' => 'Scientific Experiments', 'icon' => '🔬'],
                                ['number' => '260+', 'label' => 'Visiting Astronauts', 'icon' => '🌍']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Why Deorbit the ISS?',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The decision to retire the ISS stems from three factors: structural fatigue, maintenance costs, and strategic redirection toward lunar and Mars programs.',
                                'The station\'s oldest modules—Zarya and Unity—are over 25 years old. Metal fatigue, micrometeorite impacts, and radiation damage accumulate over time. While the ISS remains safe, extending operations beyond 2030 would require expensive refurbishments.',
                                'Maintenance costs exceed $3 billion annually. NASA and its partners have concluded that these funds are better allocated to the Artemis program and commercial LEO stations.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Module', 'Launch Year', 'Design Life', 'Current Status'],
                                ['Zarya', '1998', '15 years', 'Extended operation, fatigue monitoring'],
                                ['Unity', '1998', '15 years', 'Extended operation, some systems deactivated'],
                                ['Zvezda', '2000', '15 years', 'Primary life support, critical path item'],
                                ['Destiny', '2001', '15 years', 'Good condition, major science lab'],
                                ['Harmony', '2007', '15 years', 'Good condition, berthing hub']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Deorbit Process: Step-by-Step',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'schema',
                        'data' => [
                            'schemaType' => 'how-to',
                            'title' => 'Controlled Deorbit of the ISS',
                            'description' => 'The multi-year process to safely return 420 tons to Earth',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1446776653964-20c1d3a81b06?auto=format&fit=crop&w=800&q=80']
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Phase 1 - Science Ramp-Down (2027-2028): Prioritize high-value experiments, begin returning critical equipment via Dragon/Cygnus.',
                                'Phase 2 - Module Depressurization (2028-2029): Systematically depressurize and power down non-essential modules. Transfer remaining crew to commercial stations.',
                                'Phase 3 - Final Crew Departure (Mid-2029): Last crew departs, leaving station in automated standby mode.',
                                'Phase 4 - Orbital Decay Initiation (Late 2029): Use Progress spacecraft and/or dedicated deorbit tug to lower perigee.',
                                'Phase 5 - Controlled Reentry (January 2030): Final propulsive burn targets remote Pacific "spacecraft cemetery" (Point Nemo). Majority of structure burns up; largest fragments impact ocean.',
                                'Phase 6 - Post-Impact Assessment: Monitor debris field, confirm no hazards to shipping or aviation.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => '⚠️ Critical: The deorbit corridor is extremely narrow. Timing errors of even a few minutes could result in debris falling over populated areas. This is why NASA contracted SpaceX to develop a dedicated deorbit tug.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What Will Replace the ISS?',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'NASA is transitioning from government-owned infrastructure to commercial LEO destinations. Four companies—Axiom Space, Blue Origin, Northrop Grumman, and Voyager Space—are developing private space stations with NASA support.',
                                'These stations will be smaller, more specialized, and operated as commercial services. NASA will be a customer, not the owner.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'ISS vs. Commercial Successors',
                            'productA' => 'ISS',
                            'productB' => 'Commercial Stations (avg)',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Crew Capacity',
                                    'items' => [
                                        ['value' => '7 permanent'],
                                        ['value' => '4-8 (varies by station)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Annual Operating Cost',
                                    'items' => [
                                        ['value' => '$3-4 billion (NASA alone)'],
                                        ['value' => '$200-400 million (shared costs)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Design Focus',
                                    'items' => [
                                        ['value' => 'General purpose, political cooperation'],
                                        ['value' => 'Specialized (manufacturing, tourism, research)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Operational Timeline',
                                    'items' => [
                                        ['value' => '2000-2030'],
                                        ['value' => '2028-2050+ (projected)']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'accordion',
                        'data' => [
                            'title' => 'Common Questions About ISS Retirement',
                            'items' => [
                                [
                                    'question' => 'Can\'t we just boost it higher and leave it as a museum?',
                                    'answer' => 'No. The ISS is not designed for long-term uncrewed operation. Critical systems would fail within months. Additionally, leaving 420 tons of uncontrolled debris in orbit poses a catastrophic collision risk. Controlled deorbit is the only responsible option.',
                                    'isOpen' => true
                                ],
                                [
                                    'question' => 'What about the Russian modules? Will Russia cooperate?',
                                    'answer' => 'Russian cooperation is essential. The Zvezda service module provides critical propulsion and life support. Current agreements extend through 2028, and technical teams continue to coordinate deorbit planning despite political tensions.'
                                ],
                                [
                                    'question' => 'Will pieces survive reentry?',
                                    'answer' => 'Yes. The largest, densest components (engines, gyroscopes, truss segments) will likely survive and impact the ocean. The reentry corridor targets Point Nemo, the most remote location on Earth, 2,700 km from any landmass.'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'How to Watch the Final Descent',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The ISS reentry will be one of the most spectacular artificial events ever witnessed from space. NASA plans comprehensive coverage, including external cameras mounted on trailing spacecraft.',
                                'The event will occur during daylight over the Pacific to maximize visibility and safety monitoring. Expect live streams from NASA, ESA, Roscosmos, and commercial partners.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'A Historic Goodbye',
                            'paragraphs' => [
                                'The ISS represents what humanity can achieve when we prioritize cooperation over conflict. It proved that the US and Russia could work together even during geopolitical tensions. It showed that long-duration spaceflight is survivable. It demonstrated the commercial viability of LEO.',
                                'Its retirement is not an ending but a transition. The era of government-dominated space infrastructure is giving way to a vibrant commercial LEO economy. The lessons learned on the ISS will enable humanity\'s next giant leap: sustainable presence beyond Earth orbit.',
                                'On that final day in 2030, when the ISS\'s fiery streak illuminates the Pacific sky, we won\'t just be watching metal burn. We\'ll be witnessing the close of one chapter and the opening of another in humanity\'s journey to the stars.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The ISS taught us not just how to survive in space, but how to thrive there. It will be missed, but its legacy will live on in every station, every outpost, every settlement that follows.',
                            'attribution' => 'Col. David Armstrong, Former ISS Crew Member'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Space Suit Technology Evolution: From Mercury to Artemis III',
                'slug' => 'space-suit-evolution-mercury-to-artemis',
                'tags' => ['NASA', 'guide', 'technology'],
                'categories' => ['Guides', 'Technology', 'Human Spaceflight'],
                'custom_fields' => [
                    'author_name' => 'Marcus Li',
                    'author_bio' => 'Space technology editor specializing in life support systems.',
                    'read_time' => 14,
                    'excerpt' => 'The space suit is humanity\'s most personal spacecraft. This visual journey traces 60+ years of pressure suit evolution, from the silver Mercury suits to the cutting-edge Axiom xEVA designed for lunar surface operations.'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'The Evolution of Space Suits',
                            'subtitle' => 'Sixty years of keeping humans alive in the void',
                            'ctaText' => 'Explore the Timeline',
                            'ctaUrl' => '#timeline',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1454789548928-9efd52dc4031?auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'A space suit is not clothing—it\'s a wearable spacecraft. It must provide oxygen, remove carbon dioxide, regulate temperature, maintain pressure, shield against radiation, and enable mobility, all while operating in the harshest environment humans have ever faced.',
                                'From the rigid silver suits of the Mercury era to today\'s modular, gender-neutral designs, space suit technology reflects six decades of hard-won engineering lessons.',
                                'This guide explores the key innovations, design philosophies, and the surprising compromises that define each generation of this remarkable technology.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Generation 1: Mercury & Gemini (1961-1966)',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The first American space suits were adapted from high-altitude pressure suits used by U-2 pilots. They were designed for survival, not mobility. Astronauts were essentially passengers encased in a pressurized envelope.',
                                'Mercury suits operated at 5.5 psi (compared to 14.7 psi at sea level), which reduced suit stiffness but required pre-breathing pure oxygen to avoid decompression sickness.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Mercury Suit',
                                    'description' => 'Silver aluminized nylon, 1961-1963',
                                    'image' => 'https://images.unsplash.com/photo-1446776653964-20c1d3a81b06?auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Mercury space suit'
                                ],
                                [
                                    'title' => 'Gemini Suit',
                                    'description' => 'First suit designed for EVA, 1965-1966',
                                    'image' => 'https://images.unsplash.com/photo-1454789548928-9efd52dc4031?auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Gemini EVA suit'
                                ],
                                [
                                    'title' => 'Apollo A7L',
                                    'description' => 'Lunar surface suit, 1969-1972',
                                    'image' => 'https://images.unsplash.com/photo-1614728894747-a83421e2b9c9?auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Apollo lunar suit'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Generation 2: Apollo A7L (1968-1972)',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Apollo A7L: The Moon Suit',
                            'subtitle' => 'The most iconic space suit ever built',
                            'specs' => [
                                ['text' => 'Operating Pressure', 'value' => '3.75 psi (pure oxygen)'],
                                ['text' => 'Layers', 'value' => '21 (including micrometeorite protection)'],
                                ['text' => 'Life Support Duration', 'value' => '7 hours (PLSS backpack)'],
                                ['text' => 'Thermal Control', 'value' => 'Liquid cooling garment + reflective outer layer'],
                                ['text' => 'Mobility', 'value' => 'Waist, hip, knee, ankle joints (limited)'],
                                ['text' => 'Weight (Earth)', 'value' => '180 lbs (suit + PLSS)']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Proven reliability across six lunar landings',
                                'Integrated thermal/micrometeorite protection',
                                'Portable life support system (PLSS) was revolutionary',
                                'Gloves allowed basic tool manipulation'
                            ],
                            'cons' => [
                                'Extremely stiff when pressurized (hand grip strength reduced 80%)',
                                'Custom-fit required for each astronaut (expensive)',
                                'No emergency backup systems',
                                'Lunar dust infiltration caused abrasion damage'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Generation 3: Space Shuttle EMU (1982-2011)',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Shuttle era brought the Extravehicular Mobility Unit (EMU), the first modular, reusable space suit. Unlike custom-fitted Apollo suits, the EMU used interchangeable components (torso, arms, legs, gloves) sized to fit a range of body types.',
                                'This design dramatically reduced costs and enabled multi-mission use, but it also introduced the infamous "one-size-fits-most" problem that restricted some astronauts\' ability to participate in EVAs.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Feature', 'Apollo A7L', 'Shuttle EMU', 'ISS EMU (Current)'],
                                ['Pressure', '3.75 psi', '4.3 psi', '4.3 psi'],
                                ['EVA Duration', '7 hours', '8 hours', '8.5 hours'],
                                ['Modularity', 'None (custom)', 'High (5 sizes)', 'High (6 sizes)'],
                                ['In-flight Repairs', 'Not designed', 'Limited', 'Extensive'],
                                ['Cost per Unit', '$100K (1960s $)', '$12M', '$12M (refurb)']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Generation 4: Next-Gen Suits for Moon and Mars',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'NASA\'s Artemis program is introducing the xEVA (Exploration Extravehicular Activity) suit, developed by Axiom Space. This represents a paradigm shift: the first commercially developed NASA suit, designed from the ground up for lunar surface operations.',
                                'Key innovations include a rear-entry hatch (eliminating the need for an airlock "suit port"), advanced mobility bearings allowing squatting and kneeling, and integrated helmet-mounted displays with AR overlays.'
                            ]
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