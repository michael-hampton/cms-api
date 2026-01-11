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
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\BlockParserService;

class TheWeekSeeder extends Seeder
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
        $this->site = Site::find(51);

        $articles = Page::where('page_type', 'content')->where('status', 'published')->where('site_id', 51)->get();


        $items = [];
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
            'name' => 'The Week',
            'slug' => 'the-week',
            'is_active' => true,
        ]);
    }

    private function createTags(): void
    {
        $tagsData = ['US', 'UK', 'Global', 'News', 'Opinion', 'Arts', 'Books'];
        foreach ($tagsData as $name) {
            Tag::create(['site_id' => $this->site->id, 'name' => $name, 'slug' => strtolower(str_replace(' ', '-', $name))]);
        }
    }

    private function createCategories(): void
    {
        $categoriesData = ['Features', 'News', 'Guides', 'Reviews'];
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
            'title' => 'The Week - Home',
            'page_type' => 'lading-page',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'The Week - Home',
            'meta_description' => 'The Week: All You Need To Know',
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
                    'title' => 'The Week: All You Need To Know',
                    'subtitle' => 'The most essential news, summarized and analyzed from all sides.',
                    'backgroundImage' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80', //todo
                    'ctaText' => 'Read Today\'s Briefing',
                    'ctaUrl' => '/news/today',
                    'secondaryCtaText' => 'Subscribe',
                    'secondaryCtaUrl' => '/subscribe',
                    'showSearch' => false,
                ], 'order' => 1
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
                            'title' => 'Global Climate Accord Reaches Historic Agreement',
                            'slug' => 'new-political-party-works',
                            'excerpt' => 'World leaders finalized a landmark plan targeting emissions, renewable adoption, and ocean protection.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1502786129293-79981df4e689?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Climate Agreement Conference'
                            ],
                            'badge' => [
                                'text' => 'Breaking',
                                'color' => 'primary'
                            ],
                            'meta' => [
                                'author' => 'Nadia Torres',
                                'date' => 'May 3, 2025',
                                'readTime' => '7 min read'
                            ]
                        ],
                        [
                            'title' => 'Tech Giants Face New Antitrust Regulations',
                            'slug' => 'free-birth-society-explainer',
                            'excerpt' => 'A sweeping set of policies aims to reduce monopolistic control in digital markets worldwide.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1556741533-f6acd647d2fb?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Government Regulation'
                            ],
                            'badge' => [
                                'text' => 'Trending',
                                'color' => 'success'
                            ],
                            'meta' => [
                                'author' => 'Oliver West',
                                'date' => 'May 1, 2025',
                                'readTime' => '6 min read'
                            ]
                        ],
                        [
                            'title' => 'Global Food Prices Shift After Supply Chain Reforms',
                            'slug' => 'rising-gold-price-economy',
                            'excerpt' => 'New trade agreements and agricultural automation are reshaping costs worldwide.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1523475496153-3d6cc150b8a7?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Global Food Market'
                            ],
                            'badge' => [
                                'text' => 'Exclusive',
                                'color' => 'warning'
                            ],
                            'meta' => [
                                'author' => 'Sanjay Rao',
                                'date' => 'April 28, 2025',
                                'readTime' => '5 min read'
                            ]
                        ]
                    ]
                ],
                'order' => 3
            ],
            ['type' => 'text', 'data' => [
                'paragraphs' => [
                    'We synthesize and analyze the most important news from over 200 sources to give you a balanced, non-partisan view of the week\'s events.']
            ], 'order' => 2],
            ['type' => 'quote', 'data' => ['text' => 'The pursuit of truth and beauty is a sphere of activity in which we are permitted to remain children all our lives.', 'attribution' => 'Albert Einstein'], 'order' => 3],
            ['type' => 'list', 'data' => ['title' => 'Key Features', 'items' => ['The Explainer Deep Dives', 'Balanced Political Analysis', 'Curated Arts & Culture'], 'listType' => 'ordered'], 'order' => 4],
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
                            'title' => 'Global Climate Action',
                            'description' => 'Nations reach new agreements to address rising temperatures',
                            'image' => 'https://images.unsplash.com/photo-1502786129293-79981df4e689?auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Climate Conference',
                            'link' => '/news/climate-action'
                        ],
                        [
                            'title' => 'Tech Policy Shifts',
                            'description' => 'Governments introduce major changes to digital privacy regulations',
                            'image' => 'https://images.unsplash.com/photo-1556741533-f6acd647d2fb?auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Technology Regulation',
                            'link' => '/news/tech-policy'
                        ],
                        [
                            'title' => 'Economic Outlook 2025',
                            'description' => 'Analysts weigh in on global inflation, jobs, and trade',
                            'image' => 'https://images.unsplash.com/photo-1523475496153-3d6cc150b8a7?auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Global Economy',
                            'link' => '/news/economic-outlook'
                        ],
                    ]
                ],

                'order' => 6
            ],
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
            ['type' => 'info', 'data' => ['infoType' => 'note', 'description' => 'Our commitment: Non-partisan reporting, every single week.'], 'order' => 7]
        ];
    }

    private function createArticles(): void
    {

        $articles = [
            [
                'title' => 'The Global Climate Summit 2025: A Critical Review of the New Accords',
                'slug' => 'global-climate-summit-2025-review',
                'tags' => ['featured', 'report', 'current-events', 'analysis', 'politics', 'environment'],
                'categories' => ['Features', 'Policy Deep Dives', 'Global Diplomacy'],
                'custom_fields' => [
                    'author_name' => 'Dr. Eleanor Vance',
                    'author_bio' => 'Senior geopolitical and environmental policy correspondent.',
                    'read_time' => 11,
                    'game_title' => 'Global Climate Summit 2025 (COP30)',
                    'developer' => 'United Nations',
                    'publisher' => 'International Community',
                    'release_date' => 'November 20, 2025',
                    'platforms' => 'International Treaty',
                    'genre' => 'Geopolitical Review',
                    'rating' => 3,
                    'excerpt' => 'The latest global climate summit delivered incremental progress, but major emissions targets remain contentious and non-binding.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1549419363-d3c54d58079d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'International summit conference room',
                            'caption' => 'Delegates at the 2025 Global Climate Summit negotiating emissions targets',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Global Climate Summit 2025 concluded with a mixed verdict. After two weeks of intense, often fraught negotiations, the "Accord of Glasgow II" was signed, promising new pathways for climate finance and technology transfer.',
                                'This document is a classic case of diplomatic compromise—honoring the spirit of cooperation while leveraging national interests to soften the most ambitious mandates. The core challenge remains: translating pledges into binding, enforceable action.',
                                'From the opening plenary session, the tension between developed and developing nations was palpable.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'award',
                        'data' => [
                            'subcategory' => 'Policy Impact Rating',
                            'productName' => 'Accord of Glasgow II',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1549419363-d3c54d58079d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'winner' => false,
                            'rating' => 3.0,
                            'strapline' => 'Necessary step, but insufficient ambition',
                            'caption' => 'A compromise document that lacks teeth'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'A Compromise That Falls Short',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The narrative complexity surrounding fossil fuel phase-out was staggering. Several key emitting nations succeeded in downgrading the language from a "phase-out" to a "phase-down" of coal-fired power, with critical loopholes for carbon capture technology.',
                                'The financial commitment for adaptation and loss-and-damage was the primary victory, with wealthier nations finally agreeing to a new funding mechanism. This was the most significant breakthrough of the summit.',
                                'The non-binding nature of the ultimate 2035 emissions targets, however, leaves the document feeling like a set of noble intentions rather than a firm global contract.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Key Policy Breakthroughs',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Despite the limitations, there were pockets of policy brilliance. A global methane reduction pledge received widespread support, and several smaller nations committed to achieving 100% renewable energy by 2030.',
                                'The environmental interaction—or rather, the geopolitical maneuvering—was intense. Side-deals and bilateral agreements were consistently rewarding the most strategically adept diplomats.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Policy Tip: The success of the accord now rests on national legislative bodies and follow-up bilateral agreements, not the text itself.'
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Category', 'Score', 'Comment'],
                                ['Emissions Targets', '5/10', 'Softened language, non-binding'],
                                ['Climate Finance', '9/10', 'Significant breakthrough on adaptation funds'],
                                ['Global Participation', '10/10', 'Near-universal attendance and signing'],
                                ['Enforcement Mechanism', '2/10', 'Virtually none, relies on self-reporting'],
                                ['Public Support', '7/10', 'High public interest, moderate support'],
                                ['Long-Term Impact', '6/10', 'Avoids collapse, but lacks urgency']
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Accord of Glasgow II',
                            'brand' => 'United Nations',
                            'productName' => 'Climate Treaty',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1549419363-d3c54d58079d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'price' => 100, // Billion USD per year in climate finance
                            'currency' => '$',
                            'description' => 'The international agreement signed at COP30 to address climate change mitigation and adaptation.',
                            'link' => 'https://example.com/cop30-accord',
                            'linkText' => 'Read Full Text',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 3.0,
                                'pros' => [
                                    'Secured new funding for climate adaptation',
                                    'Near-universal commitment to the Paris Agreement',
                                    'Established framework for methane reduction',
                                    'Avoided geopolitical breakdown'
                                ],
                                'cons' => [
                                    'Weakened language on fossil fuel phase-out',
                                    'Emissions targets are voluntary, not binding',
                                    'Insufficient ambition to meet 1.5°C goal'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The Accord of Glasgow II is the diplomatic equivalent of rolling a 1 on the dice—it saved the game, but only just, and now we face a tougher challenge ahead.',
                            'attribution' => 'Dr. Eleanor Vance, Senior Correspondent'
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
                                'The 2025 Climate Summit was a monumental effort that will be studied and dissected by policy experts for years to come. It proves that global cooperation is possible, but painfully slow.',
                                'While it avoids political total collapse, the fundamental lack of ambition on emissions targets is a major blemish on an otherwise masterful effort in procedural diplomacy.',
                                'The Accord is essential for continuity, but whether it is enough to save the planet is still an open question. This is a fragile, conditional new gold standard for climate policy.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Next US Presidential Election: Every Candidate, Key Policy Position, and State Prediction',
                'slug' => 'us-presidential-election-2028-preview',
                'tags' => ['featured', 'news', 'current-events', 'politics', 'election', 'breaking-news'],
                'categories' => ['News', 'Geopolitics', 'Policy Analysis'],
                'custom_fields' => [
                    'author_name' => 'Dr. Vincent Rhodes',
                    'author_bio' => 'Senior political correspondent and election forecasting specialist.',
                    'read_time' => 12,
                    'game_title' => 'US Presidential Election 2028',
                    'developer' => 'Democratic/Republican Parties',
                    'publisher' => 'Electoral College',
                    'release_date' => 'November 7, 2028 (Election Day)',
                    'platforms' => '50 US States, Electoral College',
                    'genre' => 'Political Analysis',
                    'rating' => 5,
                    'excerpt' => 'The race for the White House is heating up. Here\'s every primary candidate, their stance on key issues, and our state-by-state prediction map.'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'The 2028 Race: The New Political Map',
                            'subtitle' => 'Everything we know about the candidates and swing states',
                            'ctaText' => 'View Candidate Profiles',
                            'ctaUrl' => '#specs',
                            'backgroundImage' => '[https://images.unsplash.com/photo-1546961129-3fd426210f81?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80](https://images.unsplash.com/photo-1546961129-3fd426210f81?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80)'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'With incumbent terms ending, the 2028 election represents a wide-open field on both sides, making it one of the most unpredictable cycles in modern history. The primaries are already seeing fierce competition and shifting alliances.',
                                'Based on polling data, donor filings, and campaign staffing announcements, here\'s everything we currently know—and what we can reasonably predict—about the path to 270 electoral votes.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Remember: Polling numbers change weekly. Our predictions are based on current data and historic trends, not finalized results.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Key Policy Positions and Candidates',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Candidate (Party)', 'Policy Stance', 'Primary Support'],
                                ['Rep. Harris (D)', 'Climate/Tech Regulation', 'Progressive/Centrist Alliance'],
                                ['Gov. Johnson (D)', 'Economic Populism/Infrastructure', 'Labor/Midwestern States'],
                                ['Sen. Davis (R)', 'Tax Cuts/National Security', 'Traditional Conservatives'],
                                ['CEO Miller (R)', 'Deregulation/Immigration Reform', 'Business/MAGA Base'],
                                ['Independent (I)', 'Anti-Partisan/Fiscal Hawk', 'Suburban Voters (Wildcard)'],
                                ['Swing States', 'PA, AZ, GA, WI, NC', 'Battleground Analysis']
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Electoral College Predictions',
                            'stats' => [
                                ['number' => '10%', 'label' => 'Uncommitted Voters', 'icon' => '❓'],
                                ['number' => '5x', 'label' => 'Spending Increase vs 2024', 'icon' => '💰'],
                                ['number' => '270', 'label' => 'Votes to Win', 'icon' => '🏛️'],
                                ['number' => '5', 'label' => 'Key Swing States', 'icon' => '🇺🇸']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Swing State Battleground',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Pennsylvania: Will the urban/rural divide be decisive, or will suburbs flip?',
                                'Arizona: The Sun Belt continues its evolution from red to purple, focusing on water and border policy.',
                                'Georgia: The core battle for minority voter turnout and youth engagement.',
                                'Wisconsin: The state most reliant on razor-thin margins and last-minute campaign swings.',
                                'North Carolina: A new, highly competitive state driven by population growth and demographic shifts.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Debate Stage Concept',
                                    'description' => 'The first televised debate is set for September 2028',
                                    'image' => ['src' => '[https://images.unsplash.com/photo-1546961129-3fd426210f81?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80](https://images.unsplash.com/photo-1546961129-3fd426210f81?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80)'],
                                    'alt' => 'Political Debate Stage'
                                ],
                                [
                                    'title' => 'Campaign Rally',
                                    'description' => 'Crowd sizes are already exceeding 2024 numbers',
                                    'image' => ['src' => '[https://images.unsplash.com/photo-1574765955613-39f5c2a13f9c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80](https://images.unsplash.com/photo-1574765955613-39f5c2a13f9c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80)'],
                                    'alt' => 'Political Rally'
                                ],
                                [
                                    'title' => 'Electoral Map',
                                    'description' => 'Our current map shows 5 true toss-up states',
                                    'image' => ['src' => '[https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80](https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80)'],
                                    'alt' => 'Electoral Map'
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
                                    'text' => 'The economy is the central issue, but the culture wars are dictating candidate selection in the primaries.',
                                    'author' => 'Dr. Lena Thompson',
                                    'role' => 'Political Science Professor',
                                    'rating' => 5
                                ],
                                [
                                    'text' => 'We\'ve never seen so many powerful independent voters. They could swing the entire election if they coalesce.',
                                    'author' => 'David Axelrod',
                                    'role' => 'Election Strategist',
                                    'rating' => 4
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Editor\'s Analysis',
                            'paragraphs' => [
                                'The most significant factor is the age of the candidates; both parties are looking for a generational refresh. The primary season will be longer and more expensive than ever before.',
                                'The battle for Pennsylvania and Arizona, specifically, will determine the winner. No path to 270 exists without capturing at least two of the five main swing states.',
                                'Expect unprecedented levels of campaign spending, with digital advertising outpacing television for the first time.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Election Polls Guide: How to Read, Interpret, and Spot Biases in Political Surveys',
                'slug' => 'election-polling-interpretation-guide',
                'tags' => ['featured', 'guide', 'current-events', 'politics', 'how-to', 'analysis'],
                'categories' => ['Guides', 'Policy Analysis', 'News'],
                'custom_fields' => [
                    'author_name' => 'Dr. Vincent Rhodes',
                    'author_bio' => 'Senior political correspondent and election forecasting specialist.',
                    'read_time' => 18,
                    'game_title' => 'Political Polling',
                    'developer' => 'Polling Firms & Data Scientists',
                    'publisher' => 'Major Media Outlets',
                    'release_date' => 'Ongoing',
                    'platforms' => 'National & State Surveys',
                    'genre' => 'Political Science',
                    'rating' => 5,
                    'excerpt' => 'Don\'t fall for misleading headlines. Master the fundamentals of polling, from margin of error to demographic weighting, and become a smarter consumer of election data.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => '[https://images.unsplash.com/photo-1546961129-3fd426210f81?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80](https://images.unsplash.com/photo-1546961129-3fd426210f81?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80)',
                            'alt' => 'Electoral map and polling numbers',
                            'caption' => 'Understanding the numbers is key to navigating modern election coverage.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Political polls are the backbone of modern election coverage, yet they are often misunderstood and frequently misused. Knowing the difference between a high-quality, weighted poll and a simple online straw poll is crucial for spotting biases and predicting outcomes.',
                                'This comprehensive guide will walk you through the anatomy of a reliable poll, show you how to read the margin of error correctly, and provide strategies for filtering out noise.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => '⚠️ Key Rule: A single poll, even a good one, should never be taken as definitive truth. Always compare results across multiple reputable sources.'
                        ]
                    ],
                    [
                        'type' => 'schema',
                        'data' => [
                            'schemaType' => 'how-to',
                            'title' => 'How to Deconstruct a Modern Poll',
                            'description' => 'Breaking down the core components of a public opinion survey',
                            'image' => ['src' => '[https://images.unsplash.com/photo-1546961129-3fd426210f81?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80](https://images.unsplash.com/photo-1546961129-3fd426210f81?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80)']
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Check the Polling Firm: Look for a track record of accuracy (A-rated firms).',
                                'Check the Sample Size (N): N must be over 500 for state polls, 1000+ for national.',
                                'Check the MoE: The Margin of Error (MoE) dictates how close the race *actually* is.',
                                'Check the Sample Type: Is it "Registered Voters" (RV) or "Likely Voters" (LV)? LV is more predictive.',
                                'Check the Weighting: Ensure the firm adjusts for demographics (age, education, race) to reflect the electorate.',
                                'Check the Dates: Polls conducted too far out (6+ months) have low predictive value.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Filtering the Signal from the Noise',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Recommended Polling Aggregators',
                            'subtitle' => 'The best sources for weighted, average polling data',
                            'specs' => [
                                ['text' => 'Source 1', 'value' => 'FiveThirtyEight (Statistical/Model-Driven)'],
                                ['text' => 'Source 2', 'value' => 'RealClearPolitics (Simple Average)'],
                                ['text' => 'Source 3', 'value' => 'The Economist (Model-Driven)'],
                                ['text' => 'Key Metric', 'value' => 'The Poll Average (Trend Line)'],
                                ['text' => 'Focus Stat', 'value' => 'Likely Voter (LV) Sample'],
                                ['text' => 'Warning Sign', 'value' => 'Single-day poll with N < 500'],
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Aggregators remove single-poll volatility and outliers',
                                'Models incorporate non-polling data (economy, history)',
                                'Simple averages provide an easy-to-understand baseline',
                                'Focusing on trends reduces media headline impact'
                            ],
                            'cons' => [
                                'Models can be overly complex and prone to structural error',
                                'Simple averages treat all polls (good/bad) equally',
                                'Aggregators are only as good as the underlying polls'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Spotting Polling Biases and Hidden Data',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Bias is often unintentional, a result of methodological choices or simple poor execution. The smart reader looks past the simple "X is leading Y" headline to the methodology section.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Bias Type', 'Indicator', 'Corrective Action'],
                                ['Sampling Bias', 'RV sample used 2 months out', 'Look for LV-only polls closer to election day'],
                                ['Question Order Bias', 'Question 1 is highly emotional', 'Ignore the internal breakdown; look only at the final margin'],
                                ['Non-Response Bias', 'High rate of non-college respondents', 'Look for weighting that explicitly corrects for education level'],
                                ['House Effect', 'Same firm always favors one party', 'Discard the poll, or only compare its results to itself over time'],
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Understanding Margin of Error (MoE)',
                            'paragraphs' => [
                                'If Candidate A leads Candidate B by 3%, and the MoE is +/- 4%, the race is mathematically tied! The true result could be anywhere from A leading by 7% to B leading by 1%.',
                                'Only a lead greater than the MoE is considered outside the statistical margin of error.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Polling Methodology Comparison',
                            'productA' => 'Traditional Landline/Cell Call',
                            'productB' => 'Online/Internet Panel',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Cost',
                                    'items' => [
                                        ['value' => 'Very High'],
                                        ['value' => 'Lower'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Response Rate',
                                    'items' => [
                                        ['value' => 'Low (Decreasing)'],
                                        ['value' => 'High (Controlled Panel)'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Demographic Bias',
                                    'items' => [
                                        ['value' => 'Over-represents older voters'],
                                        ['value' => 'Can over-represent highly online/engaged voters'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Recommendation',
                                    'items' => [
                                        ['value' => 'Still considered the gold standard when executed perfectly'],
                                        ['value' => 'Most common method; requires heavy weighting to correct bias'],
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Historical Polling Anomalies',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'accordion',
                        'data' => [
                            'title' => 'Notable Polling Failures',
                            'items' => [
                                [
                                    'question' => 'What was the major error in the 2016 US election polls?',
                                    'answer' => 'A systematic failure to properly weight for education level, which led to undercounting non-college-educated white voters in key swing states.',
                                    'isOpen' => true
                                ],
                                [
                                    'question' => 'What is the "Shy Tory/Shy Trump" effect?',
                                    'answer' => 'A theory that some voters support a controversial candidate but are unwilling to admit it to a pollster, leading to an artificially low count for that candidate.'
                                ],
                                [
                                    'question' => 'Why did the 1948 Dewey vs. Truman polls fail?',
                                    'answer' => 'Pollsters stopped polling too early in the cycle, missing a late surge for Truman, and relied heavily on landlines, skewing the sample to wealthier individuals.'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Polls are snapshots, not predictions. They tell you where the race is today, not where it will be on election day.',
                            'attribution' => 'Nate Silver, Statistician'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Global AI Governance Summit Review: The Great Divide Between Regulation and Innovation',
                'slug' => 'global-ai-governance-summit-review',
                'tags' => ['featured', 'review', 'current-events', 'policy', 'ai-regulation', 'geopolitics'],
                'categories' => ['Reviews', 'Policy Deep Dives', 'Technology'],
                'custom_fields' => [
                    'author_name' => 'Dr. Eleanor Vance',
                    'author_bio' => 'Senior geopolitical and environmental policy correspondent.',
                    'read_time' => 11,
                    'game_title' => 'Global AI Governance Summit 2026',
                    'developer' => 'G7 Nations and Tech Leaders',
                    'publisher' => 'International Policy Forums',
                    'release_date' => 'May 2026',
                    'platforms' => 'International Treaty',
                    'genre' => 'Geopolitical Review',
                    'rating' => 3,
                    'excerpt' => 'The much-anticipated global AI summit failed to bridge the gap between cautious EU regulators and laissez-faire US innovators, resulting in a toothless framework on core safety issues.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => '[https://images.unsplash.com/photo-1549419363-d3c54d58079d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80](https://images.unsplash.com/photo-1549419363-d3c54d58079d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80)',
                            'alt' => 'AI summit delegates discussing regulation',
                            'caption' => 'The debate was fierce: how to regulate powerful AI models without stifling technological progress.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'AI Governance Summit 2026',
                            'image' => ['src' => '[https://images.unsplash.com/photo-1549419363-d3c54d58079d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80](https://images.unsplash.com/photo-1549419363-d3c54d58079d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80)'],
                            'subtitle' => 'A necessary conversation that ultimately produced more rhetoric than regulatory substance.',
                            'pros' => [
                                'Established a global dialogue channel for AI risks',
                                'Mandated transparency on model training data',
                                'Agreement on preventing autonomous weapon proliferation (narrow scope)',
                                'Successfully brought industry and government to the table'
                            ],
                            'cons' => [
                                'No binding legislation on high-risk models',
                                'Failed to resolve jurisdictional conflicts (US vs. EU approach)',
                                'Security protocols remain voluntary for key players'
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Global AI Governance Summit 2026 was meant to be the international equivalent of the Bretton Woods conference for technology. Instead, it highlighted the fundamental geopolitical divide over the future of artificial intelligence.',
                                'The core conflict was simple: The European block and several developing nations demanded enforceable safety standards and liability for powerful models (General Purpose AI). The US and China, driven by their domestic tech giants, pushed for voluntary commitments and rapid innovation.',
                                'The resulting "Palo Alto Declaration" is a noble statement of intent, but it utterly lacks the legal teeth required to manage existential risks.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Critical Failure: High-Risk Model Liability',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The summit’s most pressing issue—liability for high-risk AI in areas like healthcare and finance—remains unresolved. Developers successfully lobbied to keep accountability vague, arguing that innovation speed would be compromised by premature regulation.',
                                'This political maneuvering means that if a next-generation model causes catastrophic market failure or misdiagnosis, it will be handled by existing, antiquated tort law rather than specialized AI regulation.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'EcoFlow DELTA 2 Power Station',
                            'brand' => 'EcoFlow',
                            'productName' => 'Portable Home Backup Power',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1603791452906-bab9829fbcf1?auto=format&fit=crop&w=800&q=80'
                            ],
                            'price' => 999.00,
                            'currency' => '£',
                            'description' => 'A reliable portable power station trending globally for emergency preparedness and off-grid capability.',
                            'link' => 'https://example.com/ecoflow-delta-2',
                            'linkText' => 'Check Availability',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 4.9,
                                'pros' => [
                                    'Fast recharge in under 80 minutes',
                                    'Expandable battery capacity',
                                    'Powers appliances up to 1800W',
                                    'Great for emergencies and travel'
                                ],
                                'cons' => [
                                    'Heavy to transport',
                                    'Higher price vs competitors'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Summit Outcome Breakdown',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Policy Area', 'Success Level (1-5)', 'Outcome Status'],
                                ['Autonomous Weapons (Lethal)', '5', 'Mandatory ban agreed by all major powers'],
                                ['Mandatory Audits (High-Risk)', '2', 'Voluntary commitment only, no enforcement'],
                                ['Model Open-Sourcing', '1', 'No agreement, strong opposition from Big Tech'],
                                ['Data Transparency', '4', 'Mandatory labeling for synthetic/AI-generated content'],
                                ['International Body Creation', '3', 'Advisory council created, no regulatory power'],
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Focus on the mandatory labeling agreement. This will significantly impact digital media and content creation starting next year.'
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'The Declaration is a beautiful document for a safe world that already exists. It does little to address the dangerous world we are currently building.',
                            'attribution' => 'Meredith Whittaker, Tech Policy Expert'
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
                                'The Global AI Governance Summit was a necessary, though disappointing, milestone. It successfully framed the debate but failed utterly to provide the robust, binding solutions required to manage the escalating risks of cutting-edge AI.',
                                'The result is a fragmented regulatory landscape where the power centers (US, China) continue to prioritize speed over safety, leaving the rest of the world to play catch-up. A vital first step, but a policy failure on the major items.',
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Smart Grid Investment Guide: Best Technologies and Companies for the Future of Energy Infrastructure',
                'slug' => 'smart-grid-technology-investment-guide',
                'tags' => ['featured', 'guide', 'current-events', 'technology', 'energy', 'investment'],
                'categories' => ['Guides', 'Technology Deep Dives', 'Infrastructure'],
                'custom_fields' => [
                    'author_name' => 'Dr. Eleanor Vance',
                    'author_bio' => 'Senior geopolitical and environmental policy correspondent.',
                    'read_time' => 11,
                    'game_title' => 'Global Energy Transition',
                    'developer' => 'Utility Companies, Energy Tech Firms',
                    'publisher' => 'DOE, International Energy Agency',
                    'release_date' => 'Ongoing',
                    'platforms' => 'Global Infrastructure',
                    'genre' => 'Investment Analysis',
                    'rating' => 4,
                    'excerpt' => 'The $10 trillion global energy grid is getting smarter. This guide identifies the key technologies driving the transition and the best investment targets for capitalizing on grid modernization.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => '[https://images.unsplash.com/photo-1549419363-d3c54d58079d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80](https://images.unsplash.com/photo-1549419363-d3c54d58079d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80)',
                            'alt' => 'Modern solar farm with electrical lines and smart meters',
                            'caption' => 'The shift from fossil fuels to renewables requires a total overhaul of the existing electrical infrastructure.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The traditional, centralized power grid is ill-equipped to handle the intermittency of renewable sources (solar, wind) and the demands of electric vehicles. The solution is the "Smart Grid"—a digitized, two-way communication system that can intelligently manage energy flow.',
                                'We highlight the three most critical components of this transition and provide a look at the leading companies spearheading their deployment.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Core Component 1: Energy Storage (The Battery)',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Grid-Scale Battery Solutions',
                            'subtitle' => 'Enabling 24/7 reliability for intermittent solar and wind power',
                            'specs' => [
                                ['text' => 'Dominant Technology', 'value' => 'Lithium-Ion (Li-Ion)'],
                                ['text' => 'Emerging Technology', 'value' => 'Flow Batteries, Solid-State'],
                                ['text' => 'Key Investment Area', 'value' => 'Megapack Deployment & Software Integration'],
                                ['text' => 'Growth Rate (Projected)', 'value' => '30% CAGR through 2030'],
                                ['text' => 'Key Risk', 'value' => 'Raw material cost/supply chain dependence'],
                                ['text' => 'Investment Type', 'value' => 'Growth Stock'],
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Essential for grid stability and demand management.',
                                'Massive government subsidies accelerating deployment.',
                                'Enables retirement of fossil fuel peak-plants.'
                            ],
                            'cons' => [
                                'High capital cost per kWh of storage.',
                                'End-of-life battery recycling logistics are complex.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Core Component 2: Advanced Metering and Sensors',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                '**Definition:** The replacement of old analog meters with "Smart Meters" that communicate real-time usage data.',
                                '**Benefit:** Allows utility companies to implement dynamic pricing and detect outages instantly.',
                                '**Key Investment:** Companies manufacturing high-performance sensors and secure communications chips.',
                                '**Primary Challenge:** Cybersecurity and data privacy concerns from consumers.',
                                '**Verdict:** A foundational element—deployment is inevitable but margins may be thin.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Comparison: Centralized vs. Decentralized Generation',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Power Generation Model Comparison',
                            'productA' => 'Centralized (Coal/Gas/Nuclear)',
                            'productB' => 'Decentralized (Solar/Wind/Storage)',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Efficiency of Distribution',
                                    'items' => [
                                        ['value' => 'Low (High transmission loss)'],
                                        ['value' => 'High (Local generation/consumption)'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Risk Profile',
                                    'items' => [
                                        ['value' => 'High (Single point of failure)'],
                                        ['value' => 'Low (Resilient, localized grid cells)'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Capital Intensity',
                                    'items' => [
                                        ['value' => 'Extremely High (Large-scale plant construction)'],
                                        ['value' => 'Moderate (Modular, scalable deployment)'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Investment Target',
                                    'items' => [
                                        ['value' => 'Utility stocks, fossil fuel giants'],
                                        ['value' => 'Battery tech, renewable operators, software analytics'],
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Regulatory Impact',
                            'paragraphs' => [
                                'The passage of major infrastructure bills worldwide is the primary catalyst for Smart Grid investment. Look for companies with favorable government contracts and strong lobbying presence in key policy centers.',
                                'The sector is currently moving from a "Green Technology" niche to a core "Infrastructure" mandate, significantly de-risking long-term investment profiles.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Global Climate Change Policy Wishlist: 5 Radical Actions We Need Beyond the Paris Agreement',
                'slug' => 'climate-policy-wishlist-radical-action',
                'tags' => ['featured', 'opinion', 'current-events', 'climate', 'policy', 'wishlist'],
                'categories' => ['Opinion', 'Policy Deep Dives', 'Environmental'],
                'custom_fields' => [
                    'author_name' => 'Dr. Eleanor Vance',
                    'author_bio' => 'Senior geopolitical and environmental policy correspondent.',
                    'read_time' => 9,
                    'game_title' => 'Global Decarbonization',
                    'developer' => 'UN, G7 Nations',
                    'publisher' => 'International Treaties',
                    'release_date' => '2030 (Target)',
                    'platforms' => 'Global Policy',
                    'genre' => 'Geopolitical Strategy',
                    'rating' => 4,
                    'excerpt' => 'Voluntary agreements and pledges are failing. We need bold, non-negotiable policy mechanisms—from border taxes to radical R&D investment—to halt warming and meet the 1.5°C target.'
                ]
                ,
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => '[https://images.unsplash.com/photo-1549419363-d3c54d58079d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80](https://images.unsplash.com/photo-1549419363-d3c54d58079d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80)',
                            'alt' => 'Solar panels and wind turbines in a modern landscape',
                            'caption' => 'The climate crisis requires global economic restructuring, not incremental changes.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Paris Agreement relies on Nationally Determined Contributions (NDCs), which are voluntary and collectively insufficient to limit warming to 1.5°C. The window for incremental change has closed. The next decade demands a shift to binding mechanisms that restructure global trade and energy production.',
                                'Our radical wishlist outlines five policies that require political courage but promise the systemic change needed to safeguard the future.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Wishlist Item 1: Global Carbon Border Adjustment Mechanism (CBAM)',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'A CBAM would levy a tax on imported goods from countries that do not have adequate carbon pricing or regulatory regimes. This prevents "carbon leakage" (moving manufacturing to less-regulated countries) and forces global parity in environmental standards. It makes low-carbon domestic production economically competitive.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'We can no longer afford to subsidize polluters simply because they operate outside our borders. Trade policy must enforce climate policy.',
                            'attribution' => 'Ursula von der Leyen, European Commission President'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Remaining 4 Essential Actions',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'items' => [
                                '**Mandatory Fossil Fuel Phase-Out Timeline:** A binding international treaty must be signed that sets a non-negotiable end date (e.g., 2040) for all new fossil fuel exploration and production, regardless of domestic energy reserves.',
                                '**Tripling of Public Green R&D Funding:** Governments must dramatically increase funding for high-risk, high-reward technologies like green hydrogen, sustainable aviation fuel, and utility-scale carbon capture, treating it as a global "race to space" for the climate.',
                                '**Global Just Transition Fund (GTF):** A massive international fund—contributed to proportionally by wealthy nations—dedicated exclusively to helping developing economies leapfrog fossil fuels and adapt to climate change impacts.',
                                '**Sovereign Wealth Fund Decarbonization Mandate:** Implement global regulation forcing all sovereign wealth funds and public pension funds to divest from fossil fuel assets and invest only in companies aligned with 1.5°C targets.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'The most effective policy is likely a combination of the CBAM and the Mandatory Phase-Out Timeline, as one creates the financial incentive and the other provides the hard deadline.'
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
            'title' => 'About Us',
            'slug' => 'about-us',
            'status' => 'published',
            'meta_title' => 'About Us',
            'meta_description' => 'The Week is a non-partisan news organization that provides a balanced, diverse perspective on the world\'s news and politics.',
            'page_type' => 'landing-page',
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'About World Journal',
                    'subtitle' => 'Independent reporting on global affairs, policy and society',
                    'ctaText' => 'Meet the Team',
                    'ctaUrl' => '#team',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1502786129293-79981df4e689?auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'World Journal is dedicated to clear, impartial reporting on geopolitics, social issues, and the policies that shape daily life. Our correspondents report from capitals, conflict zones and communities to bring context and verification to important stories.',
                        'We combine investigative reporting, expert analysis, and on-the-ground dispatches to help readers understand how global events affect local lives.',
                        'Our newsroom adheres to strict fact-checking and sourcing standards to maintain credibility and public trust.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Our Editorial Footprint',
                    'stats' => [
                        ['number' => '25', 'label' => 'Years of Reporting', 'icon' => '🗞️'],
                        ['number' => '500+', 'label' => 'Investigations & Features', 'icon' => '🔎'],
                        ['number' => '3M+', 'label' => 'Monthly Readers', 'icon' => '📖'],
                        ['number' => '60+', 'label' => 'International Bureaus', 'icon' => '🌍']
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Reporters',
                    'subtitle' => 'Correspondents & Analysts',
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
                            'name' => 'Nadia Torres',
                            'role' => 'Global Affairs Editor',
                            'bio' => 'International reporter with decades of experience covering diplomacy, conflict, and multilateral negotiations.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1524504542391-1278720119d7?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'nadia.torres@worldjournal.com'
                        ],
                        [
                            'name' => 'Ethan Brooks',
                            'role' => 'Tech Policy Analyst',
                            'bio' => 'Focuses on regulation, antitrust and the social impact of emerging technologies.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1552058544-f2b08422138a?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'ethan.brooks@worldjournal.com'
                        ],
                        [
                            'name' => 'Lila Montgomery',
                            'role' => 'Social Issues Correspondent',
                            'bio' => 'Reports on education, labor and public health, with an emphasis on solutions journalism.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1529626455594-4ff0802cfb7e?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'lila.montgomery@worldjournal.com'
                        ],
                        [
                            'name' => 'Raj Mehta',
                            'role' => 'Economic Policy Reporter',
                            'bio' => 'Analyzes budgets, inflation trends and trade policies across regions.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1507003216992-7f0f35a1c79b?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'raj.mehta@worldjournal.com'
                        ],
                        [
                            'name' => 'Amara Diallo',
                            'role' => 'International Conflict Analyst',
                            'bio' => 'Covers security, humanitarian crises and peace processes from frontlines and capitals.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'amara.diallo@worldjournal.com'
                        ],
                        [
                            'name' => 'Thomas Green',
                            'role' => 'Public Knowledge Editor',
                            'bio' => 'Focuses on explanatory journalism that breaks down complex policy for general audiences.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1463453091185-61582044d556?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'thomas.green@worldjournal.com'
                        ]
                    ]
                ],
                'order' => 5
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Editorial Standards',
                    'level' => 2
                ],
                'order' => 6
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Our reporting follows clear standards for sourcing, verification, and corrections. We publish methodologies for data-driven pieces and disclose conflicts where relevant.'
                    ]
                ],
                'order' => 7
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Verified — Confirmed by multiple sources',
                        'Investigative — In-depth original reporting',
                        'Analysis — Expert-driven context',
                        'Explainer — Clear, accessible breakdowns of complex topics'
                    ]
                ],
                'order' => 8
            ],
            [
                'type' => 'note',
                'data' => [
                    'title' => 'Independence & Transparency',
                    'paragraphs' => [
                        'World Journal maintains editorial independence from political actors and commercial interests. We disclose funding and correct errors transparently.',
                        'Our priority is public interest reporting that informs civic debate.'
                    ],
                    'alignment' => 'fullscreen'
                ],
                'order' => 9
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'Journalism is printing what someone else does not want printed. Everything else is public relations.',
                    'attribution' => 'George Orwell'
                ],
                'order' => 10
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createContactPage(): void
    {
        $page = Page::create([
            'title' => 'Contact The Week',
            'page_type' => 'landing-page',
            'slug' => 'contact-us',
            'status' => 'published',
            'meta_title' => 'Contact The Week',
            'meta_description' => 'Get in touch with the The Week editorial team.',
            'site_id' => $this->site->id,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Contact The Daily Brief',
                    'subtitle' => 'News tips, corrections, or story leads—reach our newsroom anytime.',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1520607162513-77705c0f0d4a?auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'The Daily Brief Newsroom',
                    'role' => 'Contact Information',
                    'email' => 'newsroom@dailybrief.com',
                    'phone' => '+1 (555) 901-2233',
                    'address' => "The Daily Brief\n400 Press Avenue\nWashington, DC 20001\n\nDesk Hours:\nMon–Sun: 7AM–11PM EST",
                    'displayType' => 'contact',
                    'image' => [
                        'src' => 'https://images.unsplash.com/photo-1525182008055-f88b95ff7980?auto=format&fit=crop&w=800&q=80',
                        'alt' => 'Modern Newsroom'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'If you have a news tip, confidential information, or a story we should investigate, email tips@dailybrief.com.',
                        'For subscription or website issues, contact support@dailybrief.com.',
                        'Press, media, and partnership inquiries: press@dailybrief.com.'
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