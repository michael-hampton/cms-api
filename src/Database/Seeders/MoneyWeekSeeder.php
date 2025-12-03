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

class MoneyWeekSeeder extends Seeder
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
//        $this->createMenuNavItems();
    }

    private function createPageGrid(): void
    {
        $items = [];

        $this->site = Site::find(49);

        $articles = Page::where('page_type', 'content')->where('status', 'published')->where('site_id', 49)->get();


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
            'name' => 'MoneyWeek',
            'slug' => 'money-week',
            'is_active' => true,
        ]);
    }

    private function createTags(): void
    {
        $tagsData = ['Stocks', 'Bonds', 'Property', 'ISA', 'Pension', 'Inflation'];
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


        // Category Links (Dropdown children)
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
            'title' => 'MoneyWeek: Actionable Investment Advice',
            'page_type' => 'content',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'MoneyWeek: Actionable Investment Advice',
            'meta_description' => 'Your essential guide to wealth and investment.',
            'site_id' => $this->site->id,
        ]);

        $this->createBlocksForPage($page->id, $this->getHomepageBlocks([], 'cta')); // Empty articles, will be filled later
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

    private function getHomepageBlocks(array $articles = [], $str): array
    {
        return [
            // Block 1: Hero - REQUIRED
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'MoneyWeek: Actionable Investment Advice',
                    'subtitle' => 'Your essential guide to wealth and investment.',
                    'backgroundImage' => 'moneyweek-hero.jpg', //todo
                    'ctaText' => 'Start Investing',
                    'ctaUrl' => '/investing/guide',
                    'secondaryCtaText' => 'Subscribe',
                    'secondaryCtaUrl' => '/subscribe',
                    'showSearch' => false,
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
                            'title' => 'Markets 2025: Tech Stocks Lead a Surprising Rally',
                            'slug' => 'best-global-stock-picks-2026',
                            'excerpt' => 'Despite early-year volatility, AI and green-energy firms have pushed the NASDAQ to unexpected highs.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1569025690938-a00729c9e1f9?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Stock Market Charts'
                            ],
                            'badge' => [
                                'text' => 'Featured',
                                'color' => 'primary'
                            ],
                            'meta' => [
                                'author' => 'Riley Chen',
                                'date' => 'March 20, 2025',
                                'readTime' => '8 min read'
                            ]
                        ],
                        [
                            'title' => 'The Future of Cashless Economies',
                            'slug' => 'maximize-tax-free-isa-allowance',
                            'excerpt' => 'Digital wallets, central bank currencies, and behavioral data are reshaping how societies transact.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Digital Money'
                            ],
                            'badge' => [
                                'text' => 'Trending',
                                'color' => 'success'
                            ],
                            'meta' => [
                                'author' => 'Hannah Lee',
                                'date' => 'March 18, 2025',
                                'readTime' => '6 min read'
                            ]
                        ],
                        [
                            'title' => 'How Gen Z Is Reshaping Personal Finance',
                            'slug' => 'maximize-tax-free-isa-allowance',
                            'excerpt' => 'From micro-investing to crypto savings, young investors are rewriting the rules.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1553729459-efe14ef6055d?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Personal Finance'
                            ],
                            'badge' => [
                                'text' => 'Exclusive',
                                'color' => 'warning'
                            ],
                            'meta' => [
                                'author' => 'Elijah Patel',
                                'date' => 'March 16, 2025',
                                'readTime' => '5 min read'
                            ]
                        ]
                    ]
                ],
                'order' => 3
            ],
            // Block 2: Text/Intro
            ['type' => 'text', 'data' => ['paragraphs' => ['Welcome to MoneyWeek, where we cut through the noise to deliver clear, actionable financial insight. We analyze global markets, personal finance trends, and economic shifts.', 'Understanding inflation, interest rate movements, and long-term investment vehicles is more crucial than ever before.']], 'order' => 2],
            // Block 3: Quote
            ['type' => 'quote', 'data' => ['text' => 'Risk comes from not knowing what you\'re doing.', 'attribution' => 'Warren Buffett'], 'order' => 3],
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
                            'title' => 'Investment Hotspots',
                            'description' => 'Sectors outperforming expectations in 2025',
                            'image' => 'https://images.unsplash.com/photo-1569025690938-a00729c9e1f9?auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Financial Charts',
                            'link' => '/money/investment-hotspots'
                        ],
                        [
                            'title' => 'Smart Budgeting',
                            'description' => 'Modern tools redefining personal finance management',
                            'image' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Budget Planning',
                            'link' => '/money/smart-budgeting'
                        ],
                        [
                            'title' => 'Crypto & Digital Assets',
                            'description' => 'How blockchain is reshaping global markets',
                            'image' => 'https://images.unsplash.com/photo-1642790551107-e07df4b1ad51?auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Cryptocurrency Coins',
                            'link' => '/money/digital-assets'
                        ],
                    ]
                ],

                'order' => 6
            ],

            // Block 4: Product Comparison
            ['type' => 'product-comparison', 'data' => [
                'title' => 'Savings Account Comparison',
                'productA' => 'Top Easy Access ISA',
                'productB' => 'Top Fixed-Rate Bond',
                'comparisons' => [['subtitle' => 'Rate (AER)', 'items' => [['value' => '4.2%'], ['value' => '5.1%']]], ['subtitle' => 'Access', 'items' => [['value' => 'Instant'], ['value' => '1 Year Lock']]]]], 'order' => 4],
            // Block 6: Call to Action (CTA) - MINIMUM 6 BLOCKS
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


            // Block 7: Info
            ['type' => 'info', 'data' => ['infoType' => 'warning', 'description' => 'The value of investments can fall as well as rise. Always seek professional financial advice.'], 'order' => 7]
        ];
    }

    private function createArticles(): void
    {
        $articles = [
            [
                'title' => 'Interactive Brokers Review 2025: The Platform for Serious Investors',
                'slug' => 'interactive-brokers-review',
                'tags' => ['featured', 'review', 'money', 'finance', 'investing', 'trading-platform'],
                'categories' => ['Reviews', 'Brokerage Reviews', 'Online Trading'],
                'custom_fields' => [
                    'author_name' => 'Michael Sterling',
                    'author_bio' => 'Certified Financial Analyst (CFA) specializing in retail trading platforms.',
                    'read_time' => 9,
                    'game_title' => 'Interactive Brokers (IBKR)',
                    'developer' => 'Interactive Brokers LLC',
                    'publisher' => 'Interactive Brokers Group',
                    'release_date' => 'Platform constantly updated',
                    'platforms' => 'Desktop (TWS), Web, Mobile',
                    'genre' => 'Online Brokerage',
                    'rating' => 4,
                    'excerpt' => 'Interactive Brokers offers the lowest commissions and widest range of assets for sophisticated traders, but the interface can be daunting.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1611997235222-0a5dc6100c7d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Stock market chart on trading platform',
                            'caption' => 'Interactive Brokers\' Trader Workstation (TWS) is designed for professional use',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Interactive Brokers (IBKR) is a triumph in the brokerage world. It caters to the serious investor who demands a vast inventory of global assets, razor-thin commissions, and powerful trading tools.',
                                'This platform is the most faithful digital interpretation of a global trading desk ever created, translating the complexity of international markets with stunning power while offering professional-grade tools.',
                                'From the moment you log into the Trader Workstation (TWS), the platform grabs you and demands your full attention.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'award',
                        'data' => [
                            'subcategory' => 'Best for Advanced Traders',
                            'productName' => 'Interactive Brokers',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1611997235222-0a5dc6100c7d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'winner' => false,
                            'rating' => 4.5,
                            'strapline' => 'Unbeatable asset selection and pricing',
                            'caption' => 'High barrier to entry but unmatched power'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Global Assets and Low Commissions',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The asset complexity in Interactive Brokers is staggering. You can trade stocks, options, futures, forex, bonds, and mutual funds across 150 global markets in 33 countries.',
                                'I’ve executed complex options strategies and micro-trades on foreign exchanges, and the platform handled it flawlessly. The commissions are the lowest among major brokers, especially for large-volume traders.',
                                'The customer service, however, feels less intuitive and can be challenging to navigate.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'TWS: Power Over Simplicity',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Trader Workstation (TWS) is the flagship desktop application, a platform of tactical brilliance. Using highly customizable modules, every screen is a puzzle that rewards experience and familiarity.',
                                'The environmental interaction is particularly impressive. You can build watchlists, set up complex algorithmic orders, and access fundamental data with ease. The platform consistently rewards outside-the-box, analytical thinking.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Pro Tip: Start with the simplified IBKR Lite mobile app before diving into the full TWS desktop experience.'
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Category', 'Score', 'Comment'],
                                ['Asset Selection', '10/10', 'Best in the industry for variety'],
                                ['Commissions/Fees', '10/10', 'Extremely low, especially for volume'],
                                ['Platform Power', '10/10', 'Unmatched tools in TWS'],
                                ['Ease of Use (Beginner)', '4/10', 'Steep learning curve'],
                                ['Customer Support', '6/10', 'Often slow and automated'],
                                ['Mobile Experience', '8/10', 'Improved, but still less intuitive than rivals']
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Interactive Brokers',
                            'brand' => 'Interactive Brokers Group',
                            'productName' => 'Pro Account',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1611997235222-0a5dc6100c7d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'price' => 0.00,
                            'currency' => '£',
                            'description' => 'Professional-grade trading platform with access to global markets and extremely low commissions. Requires minimum activity for best pricing.',
                            'link' => 'https://example.com/ibkr',
                            'linkText' => 'Open Account',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 4.5,
                                'pros' => [
                                    'Access to 150 global exchanges',
                                    'Lowest commissions for high-volume trading',
                                    'Powerful desktop and mobile platforms',
                                    'Vast range of tradable assets (Forex, Futures, Options)'
                                ],
                                'cons' => [
                                    'Complex interface is intimidating',
                                    'Poor customer service response times',
                                    'Market data fees can add up'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Interactive Brokers is the closest a retail trader can get to a Wall Street-level institutional platform—for better and for worse.',
                            'attribution' => 'Michael Sterling, Financial Analyst'
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
                                'Interactive Brokers is a monumental achievement that will be studied and celebrated by serious traders for years to come. It proves that a platform can be powerful and cost-effective.',
                                'While it has a steep learning curve and the design might intimidate newcomers, these are minor blemishes on an otherwise essential experience for sophisticated investors.',
                                'If you are a frequent trader with complex needs, Interactive Brokers is essential. This is the new gold standard for global trading.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Central Bank Digital Currencies (CBDC): Everything We Know About the US Digital Dollar Roadmap',
                'slug' => 'us-digital-dollar-cbdc-roadmap-preview',
                'tags' => ['featured', 'news', 'money', 'finance', 'cbdc', 'federal-reserve', 'breaking-news'],
                'categories' => ['News', 'Fintech', 'Monetary Policy'],
                'custom_fields' => [
                    'author_name' => 'Elizabeth Gray',
                    'author_bio' => 'Fintech policy analyst covering central bank innovation and digital currencies.',
                    'read_time' => 9,
                    'game_title' => 'US Digital Dollar (CBDC)',
                    'developer' => 'Federal Reserve, MIT Digital Currency Initiative',
                    'publisher' => 'US Treasury',
                    'release_date' => 'Pilot Program Q4 2026 (Rumored)',
                    'platforms' => 'Digital Ledger Technology, Mobile Wallet',
                    'genre' => 'Financial Policy',
                    'rating' => 3,
                    'excerpt' => 'The Federal Reserve\'s digital currency project is advancing. Here\'s every rumored design spec, policy goal, and implementation timeline compiled in one definitive report.'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'The Digital Dollar: CBDC on the Horizon',
                            'subtitle' => 'Everything we know about the Federal Reserve\'s next-gen currency',
                            'ctaText' => 'Read Policy Analysis',
                            'ctaUrl' => '#specs',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1579621970588-a35d0e7ab936?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Central Bank Digital Currencies (CBDCs) represent the biggest shift in monetary policy since the end of the gold standard. The US Federal Reserve is actively researching and developing a prototype, driven by competitive pressure from global rivals.',
                                'Based on internal Federal Reserve working papers, congressional testimonies, and think tank reports, here\'s everything we currently know—and what we can reasonably predict—about the US Digital Dollar.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Remember: A full rollout of a US CBDC requires Congressional authorization, which is currently a major political hurdle.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Design Architecture and Policy Specs',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Component', 'Rumored Spec', 'Source'],
                                ['Technology', 'Permissioned Blockchain/DLT', 'MIT Project Hamilton'],
                                ['Interest-Bearing', 'No (Zero-interest)', 'Federal Reserve Working Paper'],
                                ['Privacy Level', 'High (Pseudonymous)', 'Congressional testimony'],
                                ['Intermediaries', 'Retail Banks (Two-tier system)', 'Fed white papers'],
                                ['Holding Limit', '$5,000 per person (Pilot)', 'Analyst modeling'],
                                ['Release Window', 'Q4 2026 (Pilot Program)', 'Technology contractor schedules']
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Predicted Economic Impact',
                            'stats' => [
                                ['number' => '10%', 'label' => 'Reduction in Payment Costs', 'icon' => '💸'],
                                ['number' => '2%', 'label' => 'GDP Boost (Est.)', 'icon' => '📈'],
                                ['number' => '100%', 'label' => 'Real-time Settlement', 'icon' => '⏱️'],
                                ['number' => '1', 'label' => 'New Central Bank Tool', 'icon' => '🏛️']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Potential Features and Controversy',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Near-instantaneous cross-border payments settlement',
                                'Potential for "programmable money" (e.g., timed stimulus checks)',
                                'Increased financial inclusion for unbanked populations',
                                'Major concerns regarding government surveillance and control',
                                'Competition with stablecoins and commercial bank deposits',
                                'Ability to distribute disaster relief funds with zero delay'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Digital Wallet Mockup',
                                    'description' => 'Concept UI for FedNow integration',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1579621970588-a35d0e7ab936?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Digital Dollar Wallet'
                                ],
                                [
                                    'title' => 'Federal Reserve HQ',
                                    'description' => 'The home of US monetary policy',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1543152115-ff99c7595d2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Federal Reserve Building'
                                ],
                                [
                                    'title' => 'Blockchain Ledger',
                                    'description' => 'DLT technology is the rumored backbone',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1611997235222-0a5dc6100c7d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Blockchain concept'
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
                                    'text' => 'A well-designed CBDC could make payments cheaper and faster, securing the dollar\'s place in the digital age.',
                                    'author' => 'Jerome Powell',
                                    'role' => 'Federal Reserve Chairman',
                                    'rating' => 4
                                ],
                                [
                                    'text' => 'The privacy implications of a central digital currency are immense. Congress must act to create clear legal safeguards.',
                                    'author' => 'Senator Richard Durbin',
                                    'role' => 'Chair, Judiciary Committee',
                                    'rating' => 2
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Editor\'s Analysis',
                            'paragraphs' => [
                                'The US is moving slower than China and Europe, prioritizing a careful, consensus-driven approach. This has created political friction.',
                                'The biggest challenge is not the technology, but the politics: overcoming public skepticism about surveillance and convincing commercial banks of their continued role.',
                                'The Digital Dollar is coming, but its final form—and the extent of its centralization—remains the key mystery.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Long-Term Investment Guide: Your Step-by-Step Walkthrough to Building a 20-Year Portfolio',
                'slug' => 'long-term-investment-strategy-guide',
                'tags' => ['featured', 'guide', 'money', 'finance', 'how-to', 'investing'],
                'categories' => ['Guides', 'Financial Planning', 'Monetary Policy'],
                'custom_fields' => [
                    'author_name' => 'Elizabeth Gray',
                    'author_bio' => 'Fintech policy analyst covering central bank innovation and digital currencies.',
                    'read_time' => 15,
                    'game_title' => 'Long-Term Wealth Building',
                    'developer' => 'Bogleheads/Fidelity',
                    'publisher' => 'The Stock Market',
                    'release_date' => 'Ongoing',
                    'platforms' => 'Brokerage Accounts, Retirement Funds (401k/IRA)',
                    'genre' => 'Personal Finance',
                    'rating' => 4,
                    'excerpt' => 'Start building your financial future today. This complete guide walks you through setting up a diversified, low-cost portfolio designed to weather market volatility for decades.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1579621970588-a35d0e7ab936?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Stock market graphs and charts',
                            'caption' => 'Long-term investing is about consistency, not timing the market.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Building wealth requires discipline and a solid, long-term strategy. Forget day trading and speculative bets—our focus is on systematic, low-cost investing designed to maximize compounding returns over 20 years or more.',
                                'This step-by-step walkthrough is designed for beginners and seasoned investors alike, providing actionable steps and expert strategies for portfolio construction.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => '⚠️ Disclaimer: All investments carry risk. This guide provides general financial education, not personalized investment advice. Consult a licensed financial advisor.'
                        ]
                    ],
                    [
                        'type' => 'schema',
                        'data' => [
                            'schemaType' => 'how-to',
                            'title' => 'How to Start Your Investment Journey',
                            'description' => 'The essential first steps to setting up your long-term portfolio',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1579621970588-a35d0e7ab936?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80']
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Establish Emergency Fund: Save 6 months of expenses in a high-yield savings account.',
                                'Open Retirement Account: Max out tax-advantaged accounts (401k/IRA) first.',
                                'Choose a Brokerage: Select a low-cost platform with access to mutual funds/ETFs.',
                                'Define Asset Allocation: Determine your risk tolerance and set target percentages (e.g., 70% Stocks, 30% Bonds).',
                                'Automate Contributions: Set up automatic weekly or monthly deposits.',
                                'Rebalance Annually: Adjust your holdings back to your target allocation once per year.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Recommended Allocation Strategy',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'The "Three-Fund Portfolio" Strategy',
                            'subtitle' => 'A simple, effective, and globally diversified approach',
                            'specs' => [
                                ['text' => 'Fund 1', 'value' => 'Total US Stock Market Index Fund (VTSAX)'],
                                ['text' => 'Fund 2', 'value' => 'Total International Stock Index Fund (VTIAX)'],
                                ['text' => 'Fund 3', 'value' => 'Total US Bond Market Index Fund (VBTLX)'],
                                ['text' => 'Suggested Ratio (Age 30)', 'value' => '50% / 30% / 20%'],
                                ['text' => 'Expense Ratio', 'value' => '0.04% or less'],
                                ['text' => 'Key Principle', 'value' => 'Low Costs, High Diversification'],
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Extremely low maintenance and time commitment',
                                'Highly diversified across global equities and debt',
                                'Minimal expense ratios maximize net returns',
                                'Easy to automate and rebalance without emotion'
                            ],
                            'cons' => [
                                'Will not outperform speculative stock picking (sometimes)',
                                'Requires discipline to hold during market crashes',
                                'Bond component may drag returns in low-interest environments'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Strategy: Countering Market Volatility',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Market crashes are inevitable. The true long-term investor focuses on consistent contributions and avoiding emotional decisions during downturns. Downturns are sales, not emergencies.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Market Event', 'Investor Emotion', 'Counter Strategy'],
                                ['20% Bear Market Drop', 'Panic/Fear', 'Continue automated contributions (Dollar-Cost Average)'],
                                ['50% Tech Bubble Spike', 'Greed/FOMO', 'Sell over-allocated assets, rebalance to target percentage'],
                                ['Sudden Job Loss', 'Anxiety', 'Utilize Emergency Fund (Do NOT sell investments)'],
                                ['Inflation Surge', 'Worry', 'Hold asset allocation; stocks typically beat inflation long-term'],
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'The Power of Compounding',
                            'paragraphs' => [
                                'Compounding interest is your greatest ally. $1,000 invested at age 25 earning 8% for 40 years is worth significantly more than the same $1,000 invested at age 35 for 30 years.',
                                'The goal of long-term strategy is simply to give compounding the longest possible runway, meaning: start early, and never stop contributing.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Brokerage Comparison: Low-Cost Accounts',
                            'productA' => 'Vanguard (The Original)',
                            'productB' => 'Fidelity (The Modern Giant)',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Expense Ratios',
                                    'items' => [
                                        ['value' => 'Best-in-class (0.03%-0.05%)'],
                                        ['value' => 'Matches Vanguard (0.03%-0.05%)'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Platform Usability',
                                    'items' => [
                                        ['value' => 'Basic/Functional'],
                                        ['value' => 'Excellent/Modern App'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Account Types',
                                    'items' => [
                                        ['value' => 'All standard accounts'],
                                        ['value' => 'All standard accounts'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Recommendation',
                                    'items' => [
                                        ['value' => 'Best for pure, set-and-forget index investing'],
                                        ['value' => 'Best for users who want a robust app and integrated advice'],
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Advanced Tax & Retirement Secrets',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'accordion',
                        'data' => [
                            'title' => 'Secrets to Maximizing Tax Efficiency',
                            'items' => [
                                [
                                    'question' => 'What is "Tax-Loss Harvesting"?',
                                    'answer' => 'A strategy where you sell investments at a loss to offset capital gains realized from selling profitable investments, reducing your overall tax bill.',
                                    'isOpen' => true
                                ],
                                [
                                    'question' => 'Should I use a Roth or Traditional 401k?',
                                    'answer' => 'Roth is generally better if you expect to be in a higher tax bracket in retirement. Traditional is better if you are in a higher tax bracket today.'
                                ],
                                [
                                    'question' => 'How can I access my retirement money early?',
                                    'answer' => 'Consider the "Roth Conversion Ladder," which allows tax-free, penalty-free withdrawals of Roth IRA conversion funds after a five-year seasoning period (complex strategy, consult advisor).'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The single greatest edge an investor can have is a long-term orientation.',
                            'attribution' => 'Seth Klarman, Investor'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Robo-Advisor Showdown 2026: Wealthfront vs. Betterment for Long-Term Growth',
                'slug' => 'wealthfront-betterment-robo-advisor-review-2026',
                'tags' => ['featured', 'review', 'money', 'investing', 'fintech', 'robo-advisor'],
                'categories' => ['Reviews', 'Brokerage Reviews', 'Personal Finance'],
                'custom_fields' => [
                    'author_name' => 'Michael Sterling',
                    'author_bio' => 'Certified Financial Analyst (CFA) specializing in retail trading platforms.',
                    'read_time' => 8,
                    'game_title' => 'Robo-Advisor Comparison',
                    'developer' => 'Wealthfront and Betterment',
                    'publisher' => 'Fintech Industry',
                    'release_date' => 'January 2026 (Updated Review)',
                    'platforms' => 'Mobile and Web',
                    'genre' => 'Automated Investing',
                    'rating' => 4,
                    'excerpt' => 'The two giants of automated investing—Wealthfront and Betterment—face off. We analyze fees, tax efficiency, planning tools, and long-term performance to crown the 2026 winner.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1579621970588-a35d0e7ab936?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Automated investing interface on a tablet',
                            'caption' => 'Robo-advisors offer low-cost, automated portfolio management for the modern investor.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Betterment vs. Wealthfront',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1579621970588-a35d0e7ab936?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'subtitle' => 'Betterment edges out Wealthfront for its comprehensive financial planning tools and accessibility.',
                            'pros' => [
                                'Extremely low management fees (0.25% or less)',
                                'Automated daily Tax-Loss Harvesting (TLH)',
                                'Excellent goal-based planning tools (Betterment)',
                                'Sophisticated factor-based investing (Wealthfront)'
                            ],
                            'cons' => [
                                'Customer service is automated/slow',
                                'Limited asset customization compared to human advisors'
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'For the average investor, a robo-advisor is one of the smartest financial decisions they can make. These platforms automate rebalancing, tax-loss harvesting, and portfolio diversification, removing human emotion and minimizing cost.',
                                'The 2026 showdown between Betterment and Wealthfront is tighter than ever. While both offer industry-leading automated tax strategies and similar 0.25% management fees, their core focus areas have diverged.',
                                'Betterment leans heavily into retirement planning and accessibility, while Wealthfront focuses on advanced factor-based investing and a streamlined user interface.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Feature Showdown: Tax-Loss Harvesting',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Both platforms offer automated Tax-Loss Harvesting (TLH), a crucial feature for minimizing tax drag in taxable accounts. TLH alone can often pay for the entire annual management fee.',
                                'Wealthfront boasts a more aggressive, daily TLH strategy across multiple asset classes, slightly outperforming Betterment’s execution, especially in high-volatility years.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Ledger Nano X Hardware Wallet',
                            'brand' => 'Ledger',
                            'productName' => 'Crypto Security Hardware Wallet',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1621413755574-54eaa839f474?auto=format&fit=crop&w=800&q=80'
                            ],
                            'price' => 129.00,
                            'currency' => '£',
                            'description' => 'Secure your crypto with industry-leading hardware wallet encryption and mobile connectivity.',
                            'link' => 'https://example.com/ledger-nano-x',
                            'linkText' => 'Secure Your Assets',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 4.7,
                                'pros' => [
                                    'Top-tier security features',
                                    'Bluetooth mobile access',
                                    'Supports 5,500+ assets',
                                    'User-friendly app'
                                ],
                                'cons' => [
                                    'More expensive than Nano S',
                                    'Initial setup can be intimidating for beginners'
                                ]
                            ]
                        ]
                    ],

                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Data: Side-by-Side Comparison',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Feature', 'Wealthfront', 'Betterment'],
                                ['Management Fee', '0.25% (First $5k Free)', '0.25% (First $0 Free)'],
                                ['Tax-Loss Harvesting', 'Daily, Multi-Asset', 'Continuous, Core Assets'],
                                ['Access to Human Advisor', 'Available for large accounts only', 'Available for all (flat fee)'],
                                ['Portfolio Customization', 'High (Risk Parity, Stock Following)', 'Moderate (Target Allocation)'],
                                ['Cash Management APY', '4.75% APY (Variable)', '5.10% APY (Variable)'],
                                ['Minimum Investment', '$500', '$10'],
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'For investors with over $100k, the sophisticated factor-based investing (Risk Parity) offered by Wealthfront may yield slightly higher returns over the long run.'
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'The best way to win the investing game is to stop playing. Robo-advisors take the emotion out of the process, which is invaluable.',
                            'attribution' => 'Michael Sterling, CFA'
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
                                '**Betterment is the overall winner for the average investor.** Its comprehensive goal-based planning, integrated human advice option, and superior cash management account make it the more complete financial package.',
                                '**Wealthfront remains superior for sophisticated investors** seeking advanced portfolio models and aggressive tax optimization. It is a fantastic tool, but its partner, Betterment, simply offers a more accessible and well-rounded service.',
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Retirement Account Showdown: 401k vs. Roth IRA vs. HSA—Which is Best for You?',
                'slug' => 'retirement-savings-account-comparison-guide',
                'tags' => ['featured', 'guide', 'money', 'finance', 'retirement', 'taxes'],
                'categories' => ['Guides', 'Financial Planning', 'Tax Strategy'],
                'custom_fields' => [
                    'author_name' => 'Elizabeth Gray',
                    'author_bio' => 'Fintech policy analyst covering central bank innovation and digital currencies.',
                    'read_time' => 8,
                    'game_title' => 'Tax-Advantaged Investing',
                    'developer' => 'IRS, Financial Planners',
                    'publisher' => 'US Treasury',
                    'release_date' => 'Tax Year 2026',
                    'platforms' => 'Brokerage Accounts',
                    'genre' => 'Personal Finance',
                    'rating' => 5,
                    'excerpt' => 'Don\'t leave money on the table. We compare the three most powerful tax-advantaged accounts to help you decide where to prioritize your savings for maximum long-term benefit.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1579621970588-a35d0e7ab936?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Financial planning charts and calculator',
                            'caption' => 'The key to retiring early is understanding and maximizing your tax-advantaged savings vehicles.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'For most Americans, the biggest factor in long-term wealth accumulation is tax efficiency. The US government offers powerful incentives—the 401k, Roth IRA, and Health Savings Account (HSA)—that shield your investments from the IRS.',
                                'Our strategy is simple: contribute enough to capture any employer match, then prioritize the account offering the best balance of tax benefits, access, and long-term growth.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Account Priority Breakdown (The Tier List)',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Tier 1: Maximize the HSA (The Triple Tax Advantage)',
                            'subtitle' => 'The most powerful, yet often overlooked, savings vehicle',
                            'specs' => [
                                ['text' => 'Contribution Tax', 'value' => 'Pre-Tax Deduction'],
                                ['text' => 'Growth Tax', 'value' => 'Tax-Deferred'],
                                ['text' => 'Withdrawal Tax', 'value' => 'Tax-Free (for medical expenses)'],
                                ['text' => 'Access (Age)', 'value' => 'Anytime (Medical) / 65 (Retirement)'],
                                ['text' => 'Required Plan', 'value' => 'High-Deductible Health Plan (HDHP)'],
                                ['text' => 'Verdict', 'value' => 'Best overall tax vehicle, if eligible.'],
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Triple tax advantage (deductible, grows tax-free, withdrawn tax-free)',
                                'Funds roll over year-to-year (unlike Flex Spending)',
                                'Can be used as a traditional retirement account after age 65.'
                            ],
                            'cons' => [
                                'Requires enrollment in an HDHP (Higher deductible).',
                                'Cannot be contributed to without an active HDHP.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Traditional vs. Roth: The Tax-Now vs. Tax-Later Debate',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                '**Traditional 401k/IRA:** Contributions are pre-tax (tax deduction now), withdrawals are taxed as ordinary income in retirement (Tax-Later).',
                                '**Roth 401k/IRA:** Contributions are after-tax (no deduction now), withdrawals are completely tax-free in retirement (Tax-Now).',
                                '**The Rule:** Choose Roth if you expect to be in a *higher* tax bracket in retirement. Choose Traditional if you are in a *higher* tax bracket today.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Side-by-Side Account Comparison',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Retirement Account Feature Matrix',
                            'productA' => '401(k) (Employer Plan)',
                            'productB' => 'Roth IRA (Individual Plan)',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Annual Max Contribution (2026 Est.)',
                                    'items' => [
                                        ['value' => 'High ($23,500+)'],
                                        ['value' => 'Low ($7,000+)'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Employer Match Potential',
                                    'items' => [
                                        ['value' => 'Yes (Crucial Free Money)'],
                                        ['value' => 'No (Individual Contribution)'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Income Limits',
                                    'items' => [
                                        ['value' => 'No Income Limit'],
                                        ['value' => 'Yes (Phases out at high income)'],
                                    ]
                                ],
                                [
                                    'subtitle' => 'Access to Contributions (Pre-Retirement)',
                                    'items' => [
                                        ['value' => 'Difficult (Penalty/Loan)'],
                                        ['value' => 'Easy (Tax-free, penalty-free)'],
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'The Investment Order of Operations',
                            'paragraphs' => [
                                '1. Contribute to 401k/Retirement Account just enough to get the full Employer Match.',
                                '2. Max out your HSA (Health Savings Account).',
                                '3. Max out your Roth IRA or Traditional IRA (depending on tax bracket expectation).',
                                '4. Return to and max out your 401k.',
                                '5. Invest in a taxable brokerage account.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Central Bank Digital Currency (CBDC) Wishlist: 5 Features to Protect Privacy and Promote Inclusion',
                'slug' => 'cbdc-digital-currency-wishlist-features',
                'tags' => ['featured', 'opinion', 'money', 'finance', 'cbdc', 'wishlist'],
                'categories' => ['Opinion', 'Financial Policy', 'Digital Currency'],
                'custom_fields' => [
                    'author_name' => 'Elizabeth Gray',
                    'author_bio' => 'Fintech policy analyst covering central bank innovation and digital currencies.',
                    'read_time' => 8,
                    'game_title' => 'Tax-Advantaged Investing',
                    'developer' => 'IRS, Financial Planners',
                    'publisher' => 'US Treasury',
                    'release_date' => 'Tax Year 2026',
                    'platforms' => 'Brokerage Accounts',
                    'genre' => 'Personal Finance',
                    'rating' => 5,
                    'excerpt' => 'Don\'t leave money on the table. We compare the three most powerful tax-advantaged accounts to help you decide where to prioritize your savings for maximum long-term benefit.'
                ],
                'author_bio' => 'Fintech policy analyst covering central bank innovation and digital currencies.',
                'read_time' => 7,
                'game_title' => 'Digital Dollar Implementation',
                'developer' => 'Federal Reserve, Treasury',
                'publisher' => 'Central Banks',
                'release_date' => '2028 (Projected)',
                'platforms' => 'Digital Wallet',
                'genre' => 'Monetary Policy',
                'rating' => 3,
                'excerpt' => 'As central banks explore digital currencies, privacy and government control remain major concerns. We outline the five non-negotiable features needed to make a CBDC beneficial, not totalitarian.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1579621970588-a35d0e7ab936?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Digital currency symbol with lock icon',
                            'caption' => 'The debate over CBDC is not about technology, but about control and privacy in a digital economy.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The prospect of a Central Bank Digital Currency (CBDC)—a digital version of sovereign currency—is fraught with both promise and danger. Proponents see faster payments and better monetary policy control. Critics fear government surveillance and the death of financial anonymity.',
                                'If a CBDC is to be successfully adopted, it must prioritize the trust and rights of the user. These five features are essential safeguards against abuse.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Wishlist Item 1: Full Anonymity for Low-Value Transactions',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'A CBDC must function like digital cash. Small, daily transactions (e.g., under $500) should be conducted without requiring KYC (Know Your Customer) or transactional logging beyond what is necessary for anti-money laundering thresholds. This guarantees basic financial privacy for the average citizen.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'The freedom of a cashless society must not come at the expense of surveillance capitalism or government control over every purchase.',
                            'attribution' => 'Christine Lagarde, ECB President (on privacy concerns)'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Remaining 4 Essential Features',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'items' => [
                                '**No Expiration Date or Programmability:** The currency must function as a true store of value. Central banks should be strictly prohibited from setting expiration dates or limiting *what* users can purchase (e.g., "no spending on X product").',
                                '**Offline Functionality:** To ensure financial inclusion and resilience during power outages or connectivity loss, the CBDC wallet must be able to hold and transfer value securely without requiring real-time internet access (like tapping a bus pass).',
                                '**Interoperability with Existing Banks:** A CBDC should not replace commercial banks; it must complement them. The digital currency must be easily convertible to and from traditional deposit accounts, preventing a mass bank run during a financial panic.',
                                '**Statutory Separation from Fiscal Policy:** There must be a legal firewall preventing the central bank from using the CBDC to directly implement fiscal policy, such as direct taxation or targeted stimulus payments, without explicit legislative approval.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'The greatest risk is the potential for "digital shelf-life"—giving authorities the power to control when and where people can spend their money. This must be a legislative red line.'
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
            'title' => 'About MoneyWeek',
            'slug' => 'about-us',
            'status' => 'published',
            'meta_title' => 'About MoneyWeek',
            'meta_description' => 'MoneyWeek is a financial journalism company that provides independent, actionable advice.',
            'page_type' => 'landing-page',
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'About Financial Digest',
                    'subtitle' => 'Market insight, policy analysis, and personal finance guidance',
                    'ctaText' => 'Meet the Team',
                    'ctaUrl' => '#team',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1569025690938-a00729c9e1f9?auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Financial Digest delivers timely market intelligence, independent research, and practical advice for investors and policy-makers. Our coverage spans macroeconomics, corporate strategy, fintech innovation, and personal finance.',
                        'Our editorial staff includes chartered analysts, economists, and former regulators who bring rigorous analysis to our reporting.',
                        'We aim to equip readers with the tools to make better financial decisions, whether managing household budgets or allocating institutional capital.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'By the Numbers',
                    'stats' => [
                        ['number' => '18', 'label' => 'Years Publishing', 'icon' => '💼'],
                        ['number' => '5,000+', 'label' => 'Company Analyses', 'icon' => '📊'],
                        ['number' => '1.5M', 'label' => 'Monthly Readers', 'icon' => '📖'],
                        ['number' => '120+', 'label' => 'Contributing Analysts', 'icon' => '🌐']
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Analysts',
                    'subtitle' => 'Economists, Strategists & Reporters',
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
                            'name' => 'Rebecca Lin CFA',
                            'role' => 'Chief Market Strategist',
                            'bio' => 'Global equities expert with nearly two decades of experience in macro and sector strategy.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1524504542391-1278720119d7?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'rebecca.lin@financialdigest.com'
                        ],
                        [
                            'name' => 'Jonathan Reyes',
                            'role' => 'Fintech & Innovation Editor',
                            'bio' => 'Covers blockchain, payments infrastructure and the policy questions driving fintech adoption.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1552058544-f2b08422138a?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'jonathan.reyes@financialdigest.com'
                        ],
                        [
                            'name' => 'Emily Novak',
                            'role' => 'Personal Finance Columnist',
                            'bio' => 'Writes practical guides on budgeting, saving and long-term planning for households.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1529626455594-4ff0802cfb7e?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'emily.novak@financialdigest.com'
                        ],
                        [
                            'name' => 'Dr. Robert Stein',
                            'role' => 'Global Economics Analyst',
                            'bio' => 'Economist focused on trade dynamics, inflation and fiscal policy across regions.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1507003216992-7f0f35a1c79b?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'robert.stein@financialdigest.com'
                        ],
                        [
                            'name' => 'Carmen Delgado',
                            'role' => 'Emerging Markets Reporter',
                            'bio' => 'Reports on growth markets, investment flows, and regional macro trends.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'carmen.delgado@financialdigest.com'
                        ],
                        [
                            'name' => 'Peter Wallace',
                            'role' => 'Education & Consumer Insights',
                            'bio' => 'Focuses on investor education and improving financial literacy across audiences.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1463453091185-61582044d556?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'peter.wallace@financialdigest.com'
                        ]
                    ]
                ],
                'order' => 5
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'How We Analyze',
                    'level' => 2
                ],
                'order' => 6
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Our analyses combine fundamental research, quantitative models, and on-the-ground reporting. We disclose methodology and conflicts where relevant to preserve transparency.'
                    ]
                ],
                'order' => 7
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Buy — Strong conviction, attractive valuation',
                        'Hold — Fair value, watch catalysts',
                        'Sell — Deteriorating fundamentals or valuation',
                        'Macro Watch — Monitoring policy and economic indicators'
                    ]
                ],
                'order' => 8
            ],
            [
                'type' => 'note',
                'data' => [
                    'title' => 'Disclosure',
                    'paragraphs' => [
                        'Financial Digest maintains independence from market participants. Analysts may hold positions but disclose them when relevant to published recommendations.',
                        'Our content is for informational purposes and should not be taken as individualized investment advice.'
                    ],
                    'alignment' => 'fullscreen'
                ],
                'order' => 9
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'The stock market is a device for transferring money from the impatient to the patient.',
                    'attribution' => 'Warren Buffett'
                ],
                'order' => 10
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createContactPage(): void
    {

        $page = Page::create([
            'title' => 'Contact Money Week',
            'page_type' => 'landing-page',
            'slug' => 'contact-us',
            'status' => 'published',
            'meta_title' => 'Contact Money Week',
            'meta_description' => 'Get in touch with the Money Week editorial team.',
            'site_id' => $this->site->id,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Contact Financial Insights',
                    'subtitle' => 'Investor questions, analysis requests, or contributions—reach out anytime.',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1508385082359-f38ae991e8f2?auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Financial Insights Editorial',
                    'role' => 'Contact Information',
                    'email' => 'editor@financialinsights.com',
                    'phone' => '+1 (555) 445-7744',
                    'address' => "Financial Insights Media\n100 Market Street\nNew York, NY 10004\n\nOffice Hours:\nMon–Fri: 9AM–6PM EST",
                    'displayType' => 'contact',
                    'image' => [
                        'src' => 'https://images.unsplash.com/photo-1520607162513-77705c0f0d4a?auto=format&fit=crop&w=800&q=80',
                        'alt' => 'Finance Editorial Office'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'We’re open to contributions from economists, analysts, and financial writers. Send pitches to editorial@financialinsights.com.',
                        'For account or website issues, please email support@financialinsights.com.',
                        'Press inquiries and corporate communications should be sent to press@financialinsights.com.'
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