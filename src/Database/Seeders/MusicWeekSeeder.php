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
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\BlockParserService;

class MusicWeekSeeder extends Seeder
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
        $this->site = Site::create([
            'name' => 'Music Week - Music Industry News & Analysis',
            'slug' => 'music-week',
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

    private function createTags(): void
    {
        $tags = [
            'featured', 'breaking-news', 'exclusive', 'trending',
            'uk-music', 'us-music', 'global', 'chart-news',
            'streaming', 'vinyl', 'physical', 'digital',
            'record-labels', 'independent', 'major-labels',
            'music-tech', 'ai-music', 'blockchain', 'nfts',
            'live-music', 'festivals', 'tours', 'venues',
            'music-publishing', 'sync', 'licensing', 'royalties',
            'artist-development', 'a-and-r', 'marketing',
            'industry-analysis', 'market-data', 'statistics',
            'interviews', 'profiles', 'opinion', 'editorial',
            'awards', 'brit-awards', 'grammy', 'mercury-prize',
            'radio', 'playlisting', 'bbc', 'commercial-radio'
        ];

        foreach ($tags as $tagName) {
            $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
        }
    }

    private function createCategories(): void
    {
        $categories = [
            'News' => [
                'Breaking News' => [],
                'Chart News' => [],
                'Business News' => [],
                'Global News' => ['UK', 'US', 'Europe', 'Asia', 'Latin America']
            ],
            'Features' => [
                'Interviews' => [],
                'Profiles' => [],
                'Opinion' => [],
                'Analysis' => []
            ],
            'Sectors' => [
                'Recorded Music' => ['Streaming', 'Physical', 'Digital Downloads'],
                'Live Music' => ['Festivals', 'Tours', 'Venues', 'Agents', 'Promoters'],
                'Publishing' => ['Sync', 'Licensing', 'PROs', 'Sheet Music'],
                'Music Tech' => ['AI', 'Blockchain', 'Apps', 'Hardware']
            ],
            'Data & Charts' => [
                'UK Charts' => ['Singles', 'Albums', 'Compilations'],
                'US Charts' => [],
                'Streaming Charts' => [],
                'Market Data' => []
            ],
            'Radio' => ['BBC', 'Commercial Radio', 'Digital Radio', 'Podcasts'],
            'Awards' => ['BRITs', 'Mercury Prize', 'Grammys', 'Industry Awards'],
            'Events' => ['Conferences', 'Networking', 'Showcases', 'Masterclasses']
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
            ['key' => 'excerpt', 'name' => 'Article Excerpt', 'type' => 'textarea'],
            ['key' => 'article_type', 'name' => 'Article Type', 'type' => 'select', 'options' => '{"news":"News","feature":"Feature","interview":"Interview","analysis":"Analysis","opinion":"Opinion"}'],
            ['key' => 'region', 'name' => 'Region', 'type' => 'select', 'options' => '{"uk":"UK","us":"US","europe":"Europe","asia":"Asia","global":"Global"}'],
            ['key' => 'sector', 'name' => 'Sector', 'type' => 'select', 'options' => '{"recorded":"Recorded Music","live":"Live Music","publishing":"Publishing","tech":"Music Tech","radio":"Radio"}'],
            ['key' => 'related_artists', 'name' => 'Related Artists', 'type' => 'text'],
            ['key' => 'related_companies', 'name' => 'Related Companies', 'type' => 'text'],
            ['key' => 'chart_position', 'name' => 'Chart Position', 'type' => 'text'],
            ['key' => 'market_data', 'name' => 'Market Data', 'type' => 'textarea'],
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
            'title' => 'Music Week - The Business of Music',
            'page_type' => 'content',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'Music Week - Music Industry News, Charts, Data & Analysis',
            'meta_description' => 'The leading source of music industry news, charts, data and analysis. Essential reading for labels, publishers, agents, promoters and music professionals worldwide.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Home',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
            'sort_order' => 1
        ]);

        $featuredTag = $this->tagRepository->findOrCreateByName('featured', $this->site->id);
        $page->tags(true)->attach($featuredTag->id);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'The Business of Music',
                    'subtitle' => 'Breaking news, analysis and data from the global music industry',
                    'ctaText' => 'Latest News',
                    'ctaUrl' => '#news',
                    'secondaryCtaText' => 'Subscribe',
                    'secondaryCtaUrl' => '/subscribe',
                    'showSearch' => true,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'info',
                'data' => [
                    'infoType' => 'info',
                    'description' => '🔥 BREAKING: UK streaming hits record 183 billion streams in 2024 - Full analysis →'
                ],
                'order' => 2
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Top Stories',
                    'subtitle' => 'Latest breaking news from the music industry',
                    'level' => 2
                ],
                'order' => 3
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'layout' => 'grid',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'showMeta' => true,
                    'pages' => [
                        [
                            'title' => 'Universal Music Group Reports Record Streaming Revenue Growth',
                            'slug' => 'umg-streaming-revenue-2024',
                            'excerpt' => 'Major label posts 12.3% increase in streaming revenues as subscription growth accelerates globally.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Music streaming'
                            ],
                            'badge' => [
                                'text' => 'Breaking News',
                                'color' => 'primary'
                            ],
                            'meta' => [
                                'author' => 'Mark Sutherland',
                                'date' => 'November 28, 2024',
                                'readTime' => '4 min read'
                            ]
                        ],
                        [
                            'title' => 'Live Nation Announces Major UK Venue Expansion',
                            'slug' => 'live-nation-uk-venues-2024',
                            'excerpt' => 'Promoter giant reveals plans for three new arenas and significant investment in grassroots venues.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Concert venue'
                            ],
                            'badge' => [
                                'text' => 'Exclusive',
                                'color' => 'success'
                            ],
                            'meta' => [
                                'author' => 'Gordon Masson',
                                'date' => 'November 27, 2024',
                                'readTime' => '6 min read'
                            ]
                        ],
                        [
                            'title' => 'AI Music Tools Face New UK Regulatory Framework',
                            'slug' => 'ai-music-regulation-uk-2024',
                            'excerpt' => 'Government unveils comprehensive rules for AI-generated music amid industry concerns over rights.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1677442136019-21780ecad995?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'AI technology'
                            ],
                            'badge' => [
                                'text' => 'Analysis',
                                'color' => 'warning'
                            ],
                            'meta' => [
                                'author' => 'Ben Cardew',
                                'date' => 'November 27, 2024',
                                'readTime' => '8 min read'
                            ]
                        ]
                    ]
                ],
                'order' => 4
            ],
            [
                'type' => 'divider',
                'data' => [
                    'style' => 'solid'
                ],
                'order' => 5
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'UK Music Market 2024',
                    'stats' => [
                        ['number' => '183B', 'label' => 'Annual Streams', 'icon' => '📊'],
                        ['number' => '£1.4B', 'label' => 'Industry Value', 'icon' => '💰'],
                        ['number' => '4.2M', 'label' => 'Vinyl Sales', 'icon' => '💿'],
                        ['number' => '85%', 'label' => 'Streaming Share', 'icon' => '📈']
                    ]
                ],
                'order' => 6
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'This Week\'s Charts',
                    'subtitle' => 'Official UK chart roundup',
                    'level' => 2
                ],
                'order' => 7
            ],
            [
                'type' => 'table',
                'data' => [
                    'hasHeader' => true,
                    'rows' => [
                        ['Pos', 'Artist', 'Title', 'Label', 'Streams'],
                        ['1', 'Taylor Swift', 'Is It Over Now?', 'EMI', '4.2M'],
                        ['2', 'The Weeknd', 'Blinding Lights', 'Republic', '3.8M'],
                        ['3', 'Ed Sheeran', 'Eyes Closed', 'Atlantic', '3.5M'],
                        ['4', 'Dua Lipa', 'Houdini', 'Warner', '3.2M'],
                        ['5', 'Olivia Rodrigo', 'Vampire', 'Geffen', '2.9M']
                    ]
                ],
                'order' => 8
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Featured Analysis',
                    'subtitle' => 'In-depth industry insights',
                    'level' => 2
                ],
                'order' => 9
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'layout' => 'grid',
                    'columns' => 2,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'pages' => [
                        [
                            'title' => 'The State of UK Independent Labels 2024',
                            'slug' => 'uk-indie-labels-report-2024',
                            'excerpt' => 'Exclusive research reveals how indies are thriving in the streaming era.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Independent record label'
                            ],
                            'badge' => [
                                'text' => 'Report',
                                'color' => 'primary'
                            ]
                        ],
                        [
                            'title' => 'Festival Season Preview: What to Expect in 2025',
                            'slug' => 'uk-festivals-2025-preview',
                            'excerpt' => 'Major promoters reveal their plans for next summer\'s festival circuit.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Music festival'
                            ],
                            'badge' => [
                                'text' => 'Preview',
                                'color' => 'success'
                            ]
                        ]
                    ]
                ],
                'order' => 10
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Stay Informed',
                    'subtitle' => 'Get daily music industry news delivered to your inbox',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => false,
                    'showSubject' => false,
                    'showMessage' => false,
                    'submitButtonText' => 'Subscribe to Newsletter',
                    'requireName' => true,
                    'requireEmail' => true
                ],
                'order' => 11
            ],
            [
                'type' => 'news-feed',
                'data' => [
                    'title' => 'Latest Industry News',
                    'layout' => 'grid',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'showDate' => true,
                    'showAuthor' => true,
                    'showCategory' => true,
                    'items' => [
                        [
                            'title' => 'UK Music Streaming Hits 200 Billion Annual Plays',
                            'excerpt' => 'BPI reports unprecedented growth in streaming as vinyl sales also reach decade high',
                            'imageUrl' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'pageUrl' => '/streaming-milestone-2025',
                            'author' => 'Sarah Johnson',
                            'date' => 'November 28, 2025',
                            'category' => 'Charts & Data',
                            'readTime' => '5 min read'
                        ],
                        [
                            'title' => 'Major Labels Report Strong Q4 Results',
                            'excerpt' => 'Universal, Sony and Warner all post impressive quarterly figures driven by catalogue streaming',
                            'imageUrl' => 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'pageUrl' => '/major-labels-q4-2025',
                            'author' => 'Mark Stevens',
                            'date' => 'November 27, 2025',
                            'category' => 'Business',
                            'readTime' => '8 min read'
                        ],
                        [
                            'title' => 'Live Music Recovery Continues with Festival Boom',
                            'excerpt' => 'Summer 2026 festival season already seeing strong advance ticket sales',
                            'imageUrl' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'pageUrl' => '/festival-boom-2026',
                            'author' => 'Emma Williams',
                            'date' => 'November 26, 2025',
                            'category' => 'Live',
                            'readTime' => '6 min read'
                        ]
                    ]
                ],
                'order' => 3
            ],
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
            // Article 1: UMG Streaming Revenue
            [
                'title' => 'Universal Music Group Reports Record Streaming Revenue Growth in Q4 2024',
                'slug' => 'umg-streaming-revenue-2024',
                'tags' => ['featured', 'breaking-news', 'streaming', 'major-labels', 'industry-analysis'],
                'categories' => ['News', 'Business News'],
                'custom_fields' => [
                    'author_name' => 'Mark Sutherland',
                    'author_bio' => 'Senior Editor covering major labels and global music business.',
                    'read_time' => 6,
                    'excerpt' => 'Universal Music Group posted a 12.3% increase in streaming revenues as subscription growth accelerates globally, with emerging markets driving significant gains.',
                    'article_type' => 'news',
                    'region' => 'global',
                    'sector' => 'recorded',
                    'related_companies' => 'Universal Music Group, Spotify, Apple Music'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Music streaming on phone',
                            'caption' => 'Streaming continues to drive major label revenue growth',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Universal Music Group has reported record-breaking streaming revenues for Q4 2024, with subscription-based streaming delivering a 12.3% year-on-year increase to reach €3.2 billion for the quarter.',
                                'The results, announced at the company\'s quarterly earnings call, were driven by continued growth in paid subscriptions across major platforms, particularly in emerging markets including Latin America, India, and Southeast Asia.',
                                'UMG CEO Lucian Grainge highlighted the company\'s strategic partnerships with streaming services and the strength of its artist roster in capitalizing on the expanding global subscriber base, which now exceeds 650 million paid accounts worldwide.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Key Financial Highlights',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Q4 2024 Performance',
                            'stats' => [
                                ['number' => '€3.2B', 'label' => 'Streaming Revenue', 'icon' => '💰'],
                                ['number' => '+12.3%', 'label' => 'YoY Growth', 'icon' => '📈'],
                                ['number' => '650M', 'label' => 'Global Subscribers', 'icon' => '👥'],
                                ['number' => '42%', 'label' => 'Market Share', 'icon' => '📊']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Platform Performance',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The streaming giant saw particularly strong performance on Spotify, which grew its subscriber base by 18% year-on-year, and Apple Music, which reported 22% growth in premium tier subscriptions.',
                                'YouTube Music also contributed significantly to the quarter\'s results, with the platform\'s expanding user base in India and Southeast Asia driving substantial catalog consumption.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Platform', 'Subscribers', 'YoY Growth', 'UMG Market Share'],
                                ['Spotify', '574M', '+18%', '32.1%'],
                                ['Apple Music', '88M', '+22%', '29.8%'],
                                ['Amazon Music', '82M', '+15%', '28.4%'],
                                ['YouTube Music', '80M', '+34%', '31.7%'],
                                ['Tencent Music', '78M', '+12%', '24.3%']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Regional Breakdown',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Developed vs Emerging Markets',
                            'productA' => 'US/UK/Europe',
                            'productB' => 'Latin America/Asia',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Revenue Growth',
                                    'items' => [
                                        ['value' => '+8.4% YoY'],
                                        ['value' => '+28.7% YoY']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Subscriber Growth',
                                    'items' => [
                                        ['value' => '+6.2%'],
                                        ['value' => '+35.4%']
                                    ]
                                ],
                                [
                                    'subtitle' => 'ARPU',
                                    'items' => [
                                        ['value' => '$9.84'],
                                        ['value' => '$3.12']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Market Maturity',
                                    'items' => [
                                        ['value' => 'Saturated'],
                                        ['value' => 'High growth potential']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Industry Reaction',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'These results demonstrate the strength and resilience of the recorded music industry. Streaming continues to create new opportunities for artists and drive sustainable growth across all markets.',
                            'attribution' => 'Lucian Grainge, CEO, Universal Music Group'
                        ]
                    ],
                    [
                        'type' => 'testimonial',
                        'data' => [
                            'layout' => 'grid',
                            'testimonials' => [
                                [
                                    'text' => 'The continued growth in emerging markets validates the global appeal of music and the scalability of streaming platforms.',
                                    'author' => 'Michael Nash',
                                    'role' => 'Chief Digital Officer, Universal Music Group',
                                    'rating' => 5
                                ],
                                [
                                    'text' => 'These figures are encouraging for the entire industry. Rising tide lifts all boats.',
                                    'author' => 'Sarah Johnson',
                                    'role' => 'CEO, Independent Label Association',
                                    'rating' => 5
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Looking Ahead',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'UMG executives expressed optimism about 2025, citing continued platform innovation, expanding artist rosters, and the growing adoption of higher-tier subscription products featuring lossless audio and immersive formats.',
                                'The company also highlighted its investments in emerging technologies, including AI-powered discovery tools and enhanced metadata systems designed to improve catalog monetization and artist discovery.',
                                'Industry analysts predict that global streaming revenues will exceed $25 billion in 2025, with UMG expected to maintain its market-leading position through strategic partnerships and continued investment in A&R and catalog acquisition.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'note',
                            'description' => 'Editor\'s Note: This story was updated on November 28, 2024 to include additional market analysis and platform performance data.'
                        ]
                    ]
                ]
            ],

            // Article 2: Live Nation UK Venues
            [
                'title' => 'Live Nation Announces £500M UK Venue Expansion with Three New Arenas',
                'slug' => 'live-nation-uk-venues-2024',
                'tags' => ['featured', 'exclusive', 'live-music', 'venues', 'uk-music'],
                'categories' => ['News', 'Live Music', 'Venues'],
                'custom_fields' => [
                    'author_name' => 'Gordon Masson',
                    'author_bio' => 'Live music editor covering touring, festivals and the live sector.',
                    'read_time' => 7,
                    'excerpt' => 'Promoter giant reveals ambitious plans for three new arenas and significant grassroots venue investment, creating 5,000 jobs across the UK.',
                    'article_type' => 'news',
                    'region' => 'uk',
                    'sector' => 'live',
                    'related_companies' => 'Live Nation, AEG, Academy Music Group'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Concert venue interior',
                            'caption' => 'Live Nation plans to build three new 15,000-capacity arenas',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Live Nation Entertainment has unveiled plans for a £500 million investment in UK live music infrastructure, including three new 15,000-capacity arenas and a £100 million fund to support grassroots venues.',
                                'The announcement, made exclusively to Music Week, represents the largest single investment in UK live music infrastructure in over a decade and is expected to create approximately 5,000 permanent jobs across venue operations, technical production, and hospitality.',
                                'The new arenas, planned for Birmingham, Leeds, and Bristol, will feature state-of-the-art acoustics, sustainable design principles, and integrated technology aimed at enhancing both artist and fan experiences.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Arena Locations and Specifications',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Birmingham Arena',
                                    'description' => '15,000 capacity • Opening Q4 2026',
                                    'image' => 'https://images.unsplash.com/photo-1514320291840-2e0a9bf2a9ae?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Birmingham location'
                                ],
                                [
                                    'title' => 'Leeds Arena',
                                    'description' => '14,500 capacity • Opening Q2 2027',
                                    'image' => 'https://images.unsplash.com/photo-1429962714451-bb934ecdc4ec?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Leeds location'
                                ],
                                [
                                    'title' => 'Bristol Arena',
                                    'description' => '15,500 capacity • Opening Q1 2028',
                                    'image' => 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Bristol location'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Investment Breakdown',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => '£500M Investment Plan',
                            'stats' => [
                                ['number' => '£350M', 'label' => 'Three New Arenas', 'icon' => '🏟️'],
                                ['number' => '£100M', 'label' => 'Grassroots Fund', 'icon' => '🎵'],
                                ['number' => '£30M', 'label' => 'Sustainability Tech', 'icon' => '♻️'],
                                ['number' => '£20M', 'label' => 'Training & Jobs', 'icon' => '👥']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Grassroots Venue Support',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'In addition to the arena development, Live Nation has committed £100 million to its Grassroots Music Venue Fund, which will provide grants, low-interest loans, and business support to small and mid-sized venues across the UK.',
                                'The fund aims to help 200+ venues with capacity upgrades, soundproofing, accessibility improvements, and energy efficiency measures. Priority will be given to venues in areas underserved by live music infrastructure.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Grants of up to £250,000 for capital improvements',
                                'Low-interest loans for equipment and technology upgrades',
                                'Free business mentoring and financial planning support',
                                'Access to preferred supplier rates on sound and lighting',
                                'Joint marketing and promotion through Live Nation channels',
                                'Training programs for venue staff and technical crews'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Sustainability Focus',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Green Venue Initiative',
                            'paragraphs' => [
                                'All three new arenas will be designed to achieve BREEAM Outstanding certification, the highest environmental rating for buildings.',
                                'Features will include solar panels, rainwater harvesting, electric vehicle charging infrastructure, zero single-use plastic policies, and carbon-neutral operations from day one.',
                                'Live Nation estimates the venues will reduce carbon emissions by 60% compared to traditional arena designs.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Industry Response',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'This investment will transform the UK live music landscape. It addresses critical capacity shortages while supporting the grassroots venues that are the lifeblood of artist development.',
                            'attribution' => 'Michael Rapino, CEO, Live Nation Entertainment'
                        ]
                    ],
                    [
                        'type' => 'testimonial',
                        'data' => [
                            'layout' => 'grid',
                            'testimonials' => [
                                [
                                    'text' => 'Major infrastructure investment is desperately needed. These plans could genuinely change the game for live music in the UK.',
                                    'author' => 'Jon Collins',
                                    'role' => 'CEO, LIVE (Live music Industry Venues & Entertainment)',
                                    'rating' => 5
                                ],
                                [
                                    'text' => 'The grassroots fund is particularly welcome. Small venues are struggling and this support could save many from closure.',
                                    'author' => 'Beverly Whitrick',
                                    'role' => 'Music Venue Trust',
                                    'rating' => 5
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Economic Impact',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Metric', 'Direct Impact', 'Indirect Impact', 'Total'],
                                ['Jobs Created', '5,000', '12,000', '17,000'],
                                ['Annual GVA', '£180M', '£420M', '£600M'],
                                ['Tourism Revenue', '£85M', '£215M', '£300M'],
                                ['Tax Contribution', '£45M', '£95M', '£140M']
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Independent economic analysis commissioned by Live Nation estimates the project will contribute over £600 million annually to the UK economy once all venues are operational.',
                                'The expansion is expected to attract major international touring artists who previously bypassed certain UK regions due to lack of suitable venues, potentially adding 500+ concerts annually to the UK live music calendar.'
                            ]
                        ]
                    ]
                ]
            ],
            // Article 3: AI Music Regulation
            [
                'title' => 'UK Government Unveils Comprehensive AI Music Regulatory Framework',
                'slug' => 'ai-music-regulation-uk-2024',
                'tags' => ['featured', 'music-tech', 'ai-music', 'industry-analysis', 'uk-music'],
                'categories' => ['Features', 'Analysis'],
                'custom_fields' => [
                    'author_name' => 'Ben Cardew',
                    'author_bio' => 'Technology editor covering music tech, AI, and digital innovation.',
                    'read_time' => 9,
                    'excerpt' => 'New regulations aim to balance innovation with rights protection as government sets out mandatory transparency requirements for AI-generated music.',
                    'article_type' => 'analysis',
                    'region' => 'uk',
                    'sector' => 'tech'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'The AI Music Debate',
                            'subtitle' => 'How new regulations will reshape the industry',
                            'ctaText' => 'Read Full Analysis',
                            'ctaUrl' => '#analysis',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1677442136019-21780ecad995?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The UK government has published its long-awaited regulatory framework for AI-generated music, introducing mandatory transparency requirements, rights protection measures, and new licensing structures designed to balance technological innovation with creative rights.',
                                'The 147-page document, released by the Department for Culture, Media and Sport following an 18-month consultation with industry stakeholders, represents one of the most comprehensive attempts globally to regulate AI\'s impact on the music sector.',
                                'Key provisions include mandatory disclosure when AI tools are used in music creation, new compensation mechanisms for rights holders whose works are used in AI training, and the establishment of an independent AI Music Standards Authority.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Key Regulatory Requirements',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'accordion',
                        'data' => [
                            'title' => 'Main Provisions',
                            'items' => [
                                [
                                    'question' => 'Transparency and Disclosure',
                                    'answer' => 'All music created with substantial AI involvement must be clearly labeled. Commercial releases must include metadata specifying which elements were AI-generated and what training data was used.'
                                ],
                                [
                                    'question' => 'Rights and Licensing',
                                    'answer' => 'AI companies must obtain licenses before using copyrighted music in training datasets. A new statutory licensing scheme will facilitate this process with predetermined rates.'
                                ],
                                [
                                    'question' => 'Compensation Mechanisms',
                                    'answer' => 'Rights holders whose works are used in AI training will receive compensation through a collective licensing system administered by the new AI Music Rights Collective.'
                                ],
                                [
                                    'question' => 'Voice and Likeness Protection',
                                    'answer' => 'Using AI to replicate an artist\'s voice or style without permission will be explicitly prohibited. Artists gain new rights to control AI usage of their distinctive characteristics.'
                                ],
                                [
                                    'question' => 'Standards Authority',
                                    'answer' => 'A new independent body will set technical standards, adjudicate disputes, and ensure compliance with the framework. Powers include fines up to 4% of global revenue for serious violations.'
                                ]
                            ],
                            'allowMultipleOpen' => false,
                            'openFirstByDefault' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Industry Reactions',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Stakeholder Perspectives',
                            'productA' => 'Music Industry View',
                            'productB' => 'Tech Company View',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Overall Assessment',
                                    'items' => [
                                        ['value' => 'Cautiously optimistic'],
                                        ['value' => 'Concerns about overreach']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Main Priorities',
                                    'items' => [
                                        ['value' => 'Rights protection and fair compensation'],
                                        ['value' => 'Innovation and market growth']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Key Concerns',
                                    'items' => [
                                        ['value' => 'Enforcement and compliance'],
                                        ['value' => 'Compliance costs and complexity']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Requested Changes',
                                    'items' => [
                                        ['value' => 'Stronger penalties for violations'],
                                        ['value' => 'More flexible licensing terms']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'This framework strikes an appropriate balance. It protects the rights and livelihoods of music creators while allowing space for innovation and technological development.',
                            'attribution' => 'Geoff Taylor, CEO, BPI (British Recorded Music Industry)'
                        ]
                    ],
                    [
                        'type' => 'testimonial',
                        'data' => [
                            'layout' => 'grid',
                            'testimonials' => [
                                [
                                    'text' => 'Clear rules are welcome, but we need to ensure enforcement doesn\'t stifle legitimate innovation. The devil will be in the implementation.',
                                    'author' => 'David Martin',
                                    'role' => 'CEO, Featured Artists Coalition',
                                    'rating' => 4
                                ],
                                [
                                    'text' => 'Some provisions may be workable, but the compliance burden could disadvantage UK-based AI companies versus international competitors.',
                                    'author' => 'Sarah Chen',
                                    'role' => 'Director, Tech UK Music & Media',
                                    'rating' => 3
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Implementation Timeline',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Phase', 'Date', 'Requirements', 'Penalties'],
                                ['Phase 1', 'Jan 2025', 'Registration & disclosure', 'Warnings only'],
                                ['Phase 2', 'July 2025', 'Licensing compliance', 'Up to £50k'],
                                ['Phase 3', 'Jan 2026', 'Full technical standards', 'Up to £500k'],
                                ['Phase 4', 'July 2026', 'Complete framework', 'Up to 4% revenue']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Global Context',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The UK framework arrives as jurisdictions worldwide grapple with AI regulation. The EU\'s AI Act includes provisions for creative works, while several US states have introduced their own AI music legislation.',
                                'Industry observers suggest the UK approach could become a model for other territories, particularly Commonwealth nations. However, the lack of international coordination raises concerns about regulatory fragmentation and compliance complexity for global platforms.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Economic Impact Analysis',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Market Implications',
                            'stats' => [
                                ['number' => '£180M', 'label' => 'Estimated Annual Rights Payments', 'icon' => '💰'],
                                ['number' => '£50M', 'label' => 'Compliance Cost (Year 1)', 'icon' => '📊'],
                                ['number' => '2,500', 'label' => 'New Jobs (Music + Tech)', 'icon' => '👥'],
                                ['number' => '£420M', 'label' => 'Projected Market Size 2027', 'icon' => '📈']
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Editor\'s Analysis',
                            'paragraphs' => [
                                'This regulatory framework represents a significant moment for the music industry. Whether it successfully balances innovation with protection will depend heavily on implementation.',
                                'Key questions remain: Will penalties be sufficient to ensure compliance? Can the Standards Authority keep pace with rapid technological change? How will international platforms respond?',
                                'The next 18 months will be crucial as industry adapts to the new rules. We\'ll be watching closely and providing ongoing analysis of developments.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What This Means for You',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Artists: You gain new rights to control AI usage of your work and voice',
                                'Labels: New licensing revenue streams, but also compliance responsibilities',
                                'Publishers: Additional royalty collections through collective licensing',
                                'Tech Companies: Clear rules but significant compliance investment needed',
                                'Consumers: Better transparency about AI-generated content in music',
                                'Investors: New market opportunities but regulatory risk considerations'
                            ]
                        ]
                    ]
                ]
            ],

            // Article 4: Independent Labels Report
            [
                'title' => 'The State of UK Independent Labels 2024: Thriving in the Streaming Era',
                'slug' => 'uk-indie-labels-report-2024',
                'tags' => ['featured', 'independent', 'industry-analysis', 'streaming', 'uk-music'],
                'categories' => ['Features', 'Analysis'],
                'custom_fields' => [
                    'author_name' => 'Charlotte Chambers',
                    'author_bio' => 'Senior writer covering independent labels and artist development.',
                    'read_time' => 11,
                    'excerpt' => 'Exclusive research reveals how UK indies captured 28.4% of streaming market share in 2024, with innovative strategies driving unprecedented growth.',
                    'article_type' => 'feature',
                    'region' => 'uk',
                    'sector' => 'recorded'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Independent record label office',
                            'caption' => 'UK indies are capturing unprecedented market share',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'UK independent labels have achieved record market share of 28.4% in 2024, according to exclusive research conducted by Music Week in partnership with the AIM (Association of Independent Music).',
                                'The comprehensive study, based on data from 420 UK independent labels ranging from boutique imprints to mid-sized powerhouses, reveals how indies are not just surviving but thriving in the streaming era through innovative artist development, data-driven A&R, and agile marketing strategies.',
                                'Total revenues for UK independent labels reached £1.2 billion in 2024, representing 18.3% growth year-on-year and significantly outpacing the major label sector\'s 11.2% growth rate.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'award',
                        'data' => [
                            'subcategory' => 'Industry Achievement',
                            'productName' => 'UK Independent Music Sector',
                            'winner' => true,
                            'rating' => 4.8,
                            'strapline' => 'Record-breaking market share and revenue growth in 2024',
                            'caption' => 'Indies captured 28.4% of UK streaming market'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Market Performance Highlights',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => '2024 Independent Sector Performance',
                            'stats' => [
                                ['number' => '28.4%', 'label' => 'Market Share', 'icon' => '📊'],
                                ['number' => '£1.2B', 'label' => 'Total Revenue', 'icon' => '💰'],
                                ['number' => '+18.3%', 'label' => 'YoY Growth', 'icon' => '📈'],
                                ['number' => '420', 'label' => 'Active Labels Surveyed', 'icon' => '🏢']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Revenue Breakdown by Source',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Revenue Source', '2023', '2024', 'Growth', '% of Total'],
                                ['Streaming', '£720M', '£856M', '+18.9%', '71.3%'],
                                ['Physical', '£145M', '£168M', '+15.9%', '14.0%'],
                                ['Downloads', '£32M', '£28M', '-12.5%', '2.3%'],
                                ['Sync & Licensing', '£68M', '£82M', '+20.6%', '6.8%'],
                                ['Live & Merch', '£45M', '£66M', '+46.7%', '5.5%']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Success Factors: What\'s Working',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Our research identified five key factors driving independent label success in 2024: agile decision-making, authentic artist relationships, data-driven marketing, catalog monetization expertise, and strategic partnership models.',
                                'Indies are particularly excelling at identifying and developing niche genres and underserved audiences that major labels often overlook, with electronic, alternative, and international music sectors showing exceptional growth.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'items' => [
                                'Faster decision-making enables indies to capitalize on emerging trends before majors',
                                'Lower overhead costs allow for more artist-friendly deal structures',
                                'Deep genre expertise and cultural authenticity resonates with audiences',
                                'Sophisticated data analytics rival or exceed major label capabilities',
                                'Flexible distribution partnerships maximize reach while maintaining independence',
                                'Focus on long-term artist development over short-term commercial pressure',
                                'Innovative use of social media and direct-to-fan platforms',
                                'Strategic catalog acquisition and monetization programs'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Case Studies: Success Stories',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'page_grid',
                        'data' => [
                            'layout' => 'grid',
                            'columns' => 3,
                            'showExcerpt' => true,
                            'showImage' => false,
                            'pages' => [
                                [
                                    'title' => 'Beggars Group',
                                    'excerpt' => '4AD, XL Recordings, and Rough Trade delivered 34% revenue growth through genre-defining releases and savvy catalog management.',
                                    'badge' => [
                                        'text' => 'Major Indie',
                                        'color' => 'primary'
                                    ]
                                ],
                                [
                                    'title' => 'Dirty Hit',
                                    'excerpt' => 'The 1975 and Beabadoobee\'s label exemplifies modern indie success with streaming-first strategy and strong artist development.',
                                    'badge' => [
                                        'text' => 'Mid-Size',
                                        'color' => 'success'
                                    ]
                                ],
                                [
                                    'title' => 'Young Turks',
                                    'excerpt' => 'XL Recordings imprint demonstrates how boutique labels can compete globally through artist authenticity and cultural credibility.',
                                    'badge' => [
                                        'text' => 'Boutique',
                                        'color' => 'warning'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Challenges and Opportunities',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Independent Labels: Challenges vs Opportunities',
                            'productA' => 'Key Challenges',
                            'productB' => 'Key Opportunities',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Financial Resources',
                                    'items' => [
                                        ['value' => 'Limited capital for advances/marketing'],
                                        ['value' => 'Lower costs enable risk-taking']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Scale',
                                    'items' => [
                                        ['value' => 'Cannot match major marketing spend'],
                                        ['value' => 'Agility and authenticity resonate']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Competition',
                                    'items' => [
                                        ['value' => 'Majors acquiring successful artists'],
                                        ['value' => 'Niches majors don\'t serve']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Technology',
                                    'items' => [
                                        ['value' => 'Keeping pace with innovation costs'],
                                        ['value' => 'New tools level playing field']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Independent labels are the research and development arm of the music industry. We take creative risks that majors often can\'t justify, and the market is rewarding that approach.',
                            'attribution' => 'Paul Pacifico, CEO, AIM (Association of Independent Music)'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Looking Ahead: 2025 and Beyond',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Industry experts surveyed for this report are optimistic about independent label prospects in 2025, with 78% expecting continued market share gains and 82% planning to increase their artist rosters.',
                                'Key focus areas for 2025 include international expansion (particularly in Latin America and Southeast Asia), catalog acquisition and development, sustainability initiatives, and leveraging emerging technologies including AI tools for production and marketing.',
                                'However, concerns remain around streaming economics, with 64% of labels surveyed expressing frustration with per-stream rates and 71% calling for more transparent platform algorithms and fairer pro-rata distribution systems.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Methodology Note',
                            'paragraphs' => [
                                'This report is based on comprehensive data from 420 UK independent labels across all size categories, representing approximately 85% of total UK independent sector revenues.',
                                'Data collection took place between September and November 2024, with financial figures reflecting the 12-month period ending August 31, 2024.',
                                'Market share calculations are based on Official Charts Company data for physical and digital sales, combined with representative streaming data from major DSP partners.',
                                'Full methodology and detailed sector breakdowns are available in the complete 87-page report, available to Music Week subscribers.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ]
                ]
            ],

            // Article 5: Festival Preview
            [
                'title' => 'UK Festival Season 2025: Major Promoters Reveal Summer Plans',
                'slug' => 'uk-festivals-2025-preview',
                'tags' => ['featured', 'live-music', 'festivals', 'uk-music', 'exclusive'],
                'categories' => ['News', 'Live Music', 'Festivals'],
                'custom_fields' => [
                    'author_name' => 'Gordon Masson',
                    'author_bio' => 'Live music editor covering touring, festivals and the live sector.',
                    'read_time' => 8,
                    'excerpt' => 'Exclusive interviews with top promoters reveal ambitious plans for UK festival season 2025, with capacity increases and new events across multiple genres.',
                    'article_type' => 'feature',
                    'region' => 'uk',
                    'sector' => 'live'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Festival crowd',
                            'caption' => 'UK festivals gear up for record-breaking 2025 season',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The UK festival season is set for significant expansion in 2025, with major promoters announcing capacity increases, new events, and ambitious programming across genres from rock and pop to electronic and urban music.',
                                'Music Week has conducted exclusive interviews with the teams behind Glastonbury, Reading & Leeds, Download, Wireless, Parklife, and other major events to bring you the definitive preview of what to expect next summer.',
                                'Total festival capacity is projected to reach 4.2 million attendees across 350+ events, representing 8.4% growth year-on-year and creating an estimated £1.8 billion in economic impact.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => '2025 Festival Season Overview',
                            'stats' => [
                                ['number' => '350+', 'label' => 'Total Events', 'icon' => '🎪'],
                                ['number' => '4.2M', 'label' => 'Projected Attendees', 'icon' => '👥'],
                                ['number' => '£1.8B', 'label' => 'Economic Impact', 'icon' => '💰'],
                                ['number' => '+8.4%', 'label' => 'Growth vs 2024', 'icon' => '📈']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Major Festival Updates',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'accordion',
                        'data' => [
                            'title' => 'Festival-by-Festival Breakdown',
                            'items' => [
                                [
                                    'question' => 'Glastonbury Festival',
                                    'answer' => 'Returns June 25-29 with full capacity of 210,000. Headliners TBA in late January. New acoustic stage and expanded Shangri-La area confirmed. Ticket sales broke records with 2025 allocation selling out in 34 minutes.'
                                ],
                                [
                                    'question' => 'Reading & Leeds',
                                    'answer' => 'August 22-24. Capacity increased to 105,000 per site. Festival Republic promises "biggest rock and alternative lineup in our history." New VIP village and improved camping facilities. Early bird tickets sold out in record time.'
                                ],
                                [
                                    'question' => 'Download Festival',
                                    'answer' => 'June 13-15 at Donington Park. Capacity stable at 111,000. Lineup to feature "unprecedented" number of heritage metal acts alongside next-gen headliners. Enhanced accessibility provisions including sensory-friendly areas.'
                                ],
                                [
                                    'question' => 'Wireless Festival',
                                    'answer' => 'July 4-6 in Finsbury Park. Capacity increased to 82,000 daily. Live Nation promises "biggest UK hip-hop and R&B event ever." Three stages featuring 100+ artists. VIP and premium packages expanded.'
                                ],
                                [
                                    'question' => 'Parklife',
                                    'answer' => 'June 14-15 in Manchester. Heaton Park capacity increased to 90,000 daily. Lineup spanning electronic, hip-hop, indie rock. New Warehouse Project partnership brings underground techno stage. Improved public transport links confirmed.'
                                ]
                            ],
                            'allowMultipleOpen' => true,
                            'openFirstByDefault' => false
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'New Festivals Launching in 2025',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'page_grid',
                        'data' => [
                            'layout' => 'grid',
                            'columns' => 3,
                            'showExcerpt' => true,
                            'showImage' => false,
                            'pages' => [
                                [
                                    'title' => 'Electric Fields',
                                    'excerpt' => 'Three-day electronic music festival in Scottish Highlands. July 18-20. Capacity 25,000. Focuses on sustainability and immersive experience.',
                                    'badge' => [
                                        'text' => 'New',
                                        'color' => 'success'
                                    ]
                                ],
                                [
                                    'title' => 'Coastal Sounds',
                                    'excerpt' => 'Boutique indie/alternative event in Cornwall. August 1-3. Capacity 12,000. Beachside location with camping and glamping options.',
                                    'badge' => [
                                        'text' => 'New',
                                        'color' => 'success'
                                    ]
                                ],
                                [
                                    'title' => 'Northern Soul Revival',
                                    'excerpt' => 'Rare groove and funk festival in Leeds. September 5-7. Capacity 8,000. Indoor/outdoor hybrid format celebrating 60s-70s soul.',
                                    'badge' => [
                                        'text' => 'New',
                                        'color' => 'success'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Industry Trends and Innovations',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Sustainability: 89% of major festivals now carbon neutral or carbon negative',
                                'Cashless: 94% of events adopting fully cashless systems with RFID technology',
                                'Accessibility: Major investment in facilities for disabled festival-goers',
                                'Dynamic Pricing: More festivals adopting tiered pricing strategies',
                                'Food & Beverage: 73% increasing plant-based options and local supplier partnerships',
                                'Safety Tech: Enhanced security including facial recognition and AI crowd monitoring',
                                'Payment Plans: More events offering installment options to improve accessibility',
                                'Late-Night Programming: Extended hours and after-party areas becoming standard'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => '2025 will be a landmark year for UK festivals. We\'re seeing unprecedented demand, innovation in programming, and a real commitment across the sector to sustainability and inclusivity.',
                            'attribution' => 'Melvin Benn, Managing Director, Festival Republic'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Ticket Pricing Analysis',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Festival', 'General Admission', 'VIP', 'YoY Change'],
                                ['Glastonbury', '£355 + fees', 'N/A', '+£20'],
                                ['Reading/Leeds', '£295 + fees', '£595', '+£15'],
                                ['Download', '£280 + fees', '£550', '+£10'],
                                ['Wireless (Day)', '£89.50', '£199', '+£6.50'],
                                ['Parklife (Weekend)', '£139.50', '£275', '+£9.50']
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Average ticket prices have risen by approximately 5.4% year-on-year, slightly below the UK inflation rate. Promoters cite increased artist fees, enhanced production values, and improved facilities as key drivers.',
                                'Despite price increases, early-bird ticket sales for 2025 events have been robust, with several festivals selling out opening allocations in record time, suggesting strong consumer confidence in the UK festival market.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Festival Survival Guide',
                            'paragraphs' => [
                                'Book early: Most festivals offer significant savings on early bird tickets',
                                'Check payment plans: Many now offer monthly installments',
                                'Consider transport: Coach packages often cheaper than independent travel',
                                'Look for deals: Group booking discounts and student rates available',
                                'Check what\'s included: Some VIP packages offer genuine value',
                                'Don\'t forget camping: Glamping and boutique options growing in quality and availability'
                            ],
                            'alignment' => 'left'
                        ]
                    ]
                ]
            ],
            // Article 6: Streaming Economics Interview
            [
                'title' => 'Interview: Spotify\'s UK MD on Streaming Economics, AI Tools and Artist Discovery',
                'slug' => 'spotify-uk-md-interview-2024',
                'tags' => ['featured', 'interviews', 'streaming', 'music-tech', 'exclusive'],
                'categories' => ['Features', 'Interviews'],
                'custom_fields' => [
                    'author_name' => 'Tim Ingham',
                    'author_bio' => 'Features Editor covering streaming platforms and digital music business.',
                    'read_time' => 13,
                    'excerpt' => 'In an exclusive interview, Spotify\'s UK Managing Director discusses streaming royalties, new AI-powered features, and the platform\'s commitment to artist development.',
                    'article_type' => 'interview',
                    'region' => 'uk',
                    'sector' => 'recorded',
                    'related_companies' => 'Spotify, Apple Music, Amazon Music'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1614680376593-902f74cf0d41?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Spotify office',
                            'caption' => 'Inside Spotify\'s London headquarters',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Victor Hu, Managing Director of Spotify UK, sits down with Music Week for an in-depth conversation about the streaming giant\'s UK business, addressing persistent questions about artist compensation, explaining new AI-powered discovery tools, and outlining the company\'s vision for the future of music distribution.',
                                'Speaking from Spotify\'s London headquarters, Hu is candid about both the platform\'s successes and the challenges it faces in balancing the interests of artists, labels, and listeners in an increasingly complex music ecosystem.',
                                'With UK subscriber numbers approaching 18 million and the platform\'s influence over music discovery stronger than ever, Hu\'s perspective offers crucial insight into where the streaming economy is headed.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'person',
                        'data' => [
                            'name' => 'Victor Hu',
                            'role' => 'Managing Director, Spotify UK',
                            'bio' => 'Victor Hu joined Spotify in 2019 and became UK Managing Director in 2022. Previously VP of Digital at Universal Music UK.',
                            'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                            'displayType' => 'profile'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'On Streaming Economics and Artist Compensation',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Music Week: Let\'s address the elephant in the room. Many artists complain that streaming doesn\'t pay enough. What\'s your response?',
                                'Victor Hu: I understand the frustration, but I think there are some misconceptions about how the system works. Spotify pays out approximately 70% of all revenue to rights holders - labels, distributors, and collecting societies. That\'s higher than most other digital services.',
                                'The challenge is that this money then goes through traditional music industry structures. An independent artist who owns their masters and publishing might see 50-60% of what Spotify pays out. But an artist on a traditional label deal might see 15-20% after the label takes their cut, the distributor takes theirs, and so on.',
                                'So when artists say "Spotify doesn\'t pay enough," what they often mean is "I\'m not receiving enough from the system." Those are related but different problems. We can work on our side to grow the pie - which we\'re doing - but the industry also needs to look at deal structures.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'We paid out £5.2 billion globally in 2024. The question isn\'t whether we\'re paying enough to the industry - it\'s whether the industry\'s deal structures are serving artists well.',
                            'attribution' => 'Victor Hu, Managing Director, Spotify UK'
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Spotify UK 2024 by Numbers',
                            'stats' => [
                                ['number' => '17.8M', 'label' => 'UK Subscribers', 'icon' => '👥'],
                                ['number' => '£487M', 'label' => 'Paid to UK Rights Holders', 'icon' => '💰'],
                                ['number' => '100M+', 'label' => 'Tracks Available', 'icon' => '🎵'],
                                ['number' => '6M+', 'label' => 'Podcasts', 'icon' => '🎙️']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'On AI-Powered Discovery Tools',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'MW: Spotify has introduced several AI-powered features this year. Can you walk us through the strategy?',
                                'VH: Absolutely. Our AI DJ feature, which launched in the UK in March, has been phenomenal. It creates a personalized radio experience with an AI voice that introduces tracks and explains why you might like them. It\'s like having a knowledgeable friend curating music for you.',
                                'We\'ve also enhanced our recommendation algorithms significantly. The "Discover Weekly" playlist now factors in more contextual data - what you listen to during different times of day, your mood, even weather patterns. The results have been impressive: users are discovering 40% more new artists compared to last year.',
                                'But I want to be clear: AI is a tool for discovery, not for creation. We\'re not in the business of generating music. We use AI to help listeners find music created by human artists.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'New AI Features Coming in 2025',
                            'paragraphs' => [
                                'Voice-activated playlist creation: "Create me a playlist for cooking dinner"',
                                'Mood-based smart radio: Detects your mood from listening patterns',
                                'Lyrics-based search: Find songs by remembering just one line',
                                'Collaborative AI DJ: Group listening with AI curation',
                                'Enhanced audiobook recommendations: Based on music taste'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'On Artist Development and Discovery',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'MW: Critics say Spotify favors established artists over emerging talent. How do you respond?',
                                'VH: The data doesn\'t support that narrative. In 2024, over 120,000 artists reached 10,000+ monthly listeners for the first time - that\'s a 34% increase year-over-year. And these aren\'t all major label artists. More than 60% are independent or self-released.',
                                'We\'ve also invested heavily in tools specifically for emerging artists. Spotify for Artists now offers real-time analytics, demographic breakdowns, playlist pitching tools, and even marketing campaign support. These are tools that previously only major label artists had access to.',
                                'Our editorial team actively seeks new talent. Every week, they comb through millions of uploads looking for gems. When they find something special, they can put it in front of millions of listeners instantly. That\'s unprecedented democratization of music discovery.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Artist Tier', 'Number of Artists', 'Avg Monthly Listeners', 'Share of Streams'],
                                ['Superstar (1M+)', '15,420', '5.2M', '42%'],
                                ['Professional (100K-1M)', '82,350', '324K', '31%'],
                                ['Developing (10K-100K)', '387,000', '28K', '18%'],
                                ['Emerging (<10K)', '11.2M', '850', '9%']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'On Competition and Market Dynamics',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'MW: Apple Music has been aggressively marketing higher audio quality. Are you concerned about losing market share?',
                                'VH: Competition is healthy. It keeps everyone innovating. Yes, Apple has made HiFi audio a selling point. But honestly, most listeners don\'t prioritize audio quality above discovery, convenience, and social features - areas where we excel.',
                                'That said, we\'re not ignoring quality. Spotify HiFi is coming - we\'ve been working on it for a while and want to get it right. But we\'ve also been investing in things Apple can\'t easily replicate: our algorithms, our social features, our podcast integration, our partner ecosystem.',
                                'The reality is that the streaming market is still growing. This isn\'t winner-take-all. There\'s room for multiple strong platforms, each serving slightly different user needs.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'On Podcasts and Audiobooks',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'MW: Spotify has invested billions in podcasts and recently added audiobooks. What\'s the strategy?',
                                'VH: We want to be the global audio platform, not just a music streaming service. The data shows that users who engage with both music and podcasts are more loyal, spend more time in-app, and churn at lower rates.',
                                'Podcasts have been hugely successful for us. We\'re now the number one podcast platform globally, and in the UK, one in three podcast listeners uses Spotify as their primary platform.',
                                'Audiobooks are newer, but early signs are positive. We\'ve seen strong crossover: people discover audiobooks through Spotify and vice versa. It\'s another way we provide value beyond just music streaming.',
                                'This diversification also helps us negotiate with music rights holders from a position of strength. We\'re not just another streaming service - we\'re building a comprehensive audio ecosystem.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'On the Future of Streaming',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'MW: Final question: Where do you see streaming in five years?',
                                'VH: I think we\'ll see three major shifts. First, immersive audio - spatial audio, binaural recordings - will become standard, not premium. Second, AI will make discovery incredibly personalized while also helping artists reach their ideal audiences more efficiently.',
                                'Third, and most importantly, I think we\'ll see new monetization models emerge. The current pro-rata system isn\'t perfect. I could envision user-centric payment systems, premium tier expansions, and new revenue streams like virtual concerts or exclusive experiences.',
                                'The streaming wars narrative is overblown. The real story is how streaming transforms from a music replacement product to a music enhancement product - something that makes the entire experience of being a music fan better, while creating more opportunities for artists to build careers.',
                                'We\'re still in the early innings of the streaming revolution. The best is yet to come.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Streaming isn\'t killing music - it\'s creating more opportunities for more artists than ever before. But we need to keep evolving the model to serve creators better.',
                            'attribution' => 'Victor Hu, Managing Director, Spotify UK'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'UK Music Streaming Hits 200 Billion Annual Plays',
                'slug' => 'streaming-milestone-2025',
                'tags' => ['breaking-news', 'streaming', 'uk-charts', 'bpi'],
                'categories' => ['Charts & Data', 'Streaming Data'],
                'custom_fields' => [
                    'author_name' => 'Sarah Johnson',
                    'author_title' => 'Senior Editor',
                    'read_time' => 5,
                    'excerpt' => 'BPI reports unprecedented growth in streaming as vinyl sales also reach decade high'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Music streaming on smartphones',
                            'caption' => 'UK streaming reaches new heights in 2025',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The UK music industry has reached a historic milestone, with official figures from the BPI confirming that British consumers have streamed more than 200 billion tracks in 2025, representing a 15% increase on the previous year.',
                                'The remarkable growth comes as the streaming market shows continued resilience, with both audio and video streaming contributing to the record-breaking figures. Spotify, Apple Music, and YouTube Music continue to dominate the market, while newer entrants including Amazon Music and Deezer have also reported strong subscriber growth.',
                                'Perhaps most striking is the performance of catalogue music, with tracks over 18 months old now accounting for 35% of all streams, up from 28% just two years ago. This trend has significant implications for rights holders and demonstrates the long-term value of music investments.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Breaking Down the Numbers',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Platform', '2024 Market Share', '2025 Market Share', 'Growth'],
                                ['Spotify', '38%', '39%', '+2.6%'],
                                ['Apple Music', '24%', '25%', '+4.2%'],
                                ['YouTube Music', '18%', '17%', '-5.6%'],
                                ['Amazon Music', '12%', '13%', '+8.3%'],
                                ['Others', '8%', '6%', '-25%']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'These figures demonstrate the incredible appetite for music in the UK and the central role streaming now plays in how people discover and enjoy their favourite artists.',
                            'attribution' => 'Geoff Taylor, BPI Chief Executive'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Impact on Artists and Labels',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The streaming growth has translated into increased revenues for both major and independent labels. Industry sources suggest that streaming now accounts for over 80% of recorded music revenue in the UK, with physical sales and downloads making up the remainder.',
                                'For artists, the picture is more nuanced. While top-tier artists continue to generate substantial streaming income, emerging and mid-tier artists often struggle to convert streams into sustainable income. This has reignited debates around streaming royalties and equitable remuneration models.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Industry experts estimate that an artist needs approximately 1 million monthly streams across platforms to generate a full-time income from streaming alone.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What This Means for the Future',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Continued investment in AI-powered recommendation systems',
                                'Growth of spatial audio and high-fidelity streaming tiers',
                                'Increased focus on podcast and video content within music platforms',
                                'Greater emphasis on direct-to-fan platforms and artist services',
                                'Expansion of emerging markets, particularly in Asia and Africa'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Major Labels Report Strong Q4 Results',
                'slug' => 'major-labels-q4-2025',
                'tags' => ['business', 'major-label', 'streaming', 'industry-insights'],
                'categories' => ['Business', 'Record Labels'],
                'custom_fields' => [
                    'author_name' => 'Mark Stevens',
                    'author_title' => 'Business Editor',
                    'read_time' => 8,
                    'excerpt' => 'Universal, Sony and Warner all post impressive quarterly figures driven by catalogue streaming'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Music business meeting',
                            'caption' => 'Major labels celebrate strong quarterly performance',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The world\'s three major record labels have all reported strong financial results for Q4 2025, with Universal Music Group, Sony Music Entertainment, and Warner Music Group posting year-on-year revenue growth driven primarily by streaming and catalogue performance.',
                                'Universal Music Group led the pack with recorded music revenues up 18% to €2.9 billion, while Sony Music Entertainment reported a 16% increase to $2.4 billion. Warner Music Group, the smallest of the three majors, nonetheless posted impressive 14% growth to $1.6 billion.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Major Label Q4 Performance',
                            'stats' => [
                                ['number' => '€2.9bn', 'label' => 'UMG Recorded Revenue', 'icon' => '📈'],
                                ['number' => '$2.4bn', 'label' => 'Sony Music Revenue', 'icon' => '💰'],
                                ['number' => '$1.6bn', 'label' => 'Warner Revenue', 'icon' => '💵'],
                                ['number' => '16%', 'label' => 'Average Growth', 'icon' => '📊']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Catalogue Performance Drives Growth',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'A significant factor in the strong performance has been the continued strength of catalogue recordings. Classic albums and heritage artists have seen remarkable streaming growth, with playlists and TikTok virality introducing vintage tracks to new generations of listeners.',
                                'This trend has led to increased investment in catalogue acquisitions, with major labels and private equity firms paying premium prices for established artist catalogues and back catalogues from independent labels.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Revenue Mix: New vs Catalogue',
                            'productA' => 'New Releases (<18 months)',
                            'productB' => 'Catalogue (>18 months)',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Share of Streaming',
                                    'items' => [
                                        ['value' => '65%'],
                                        ['value' => '35%']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Physical Sales',
                                    'items' => [
                                        ['value' => '45%'],
                                        ['value' => '55%']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Licensing Revenue',
                                    'items' => [
                                        ['value' => '25%'],
                                        ['value' => '75%']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Our catalogue continues to be a remarkable growth driver, demonstrating the enduring value and cultural relevance of great music.',
                            'attribution' => 'Sir Lucian Grainge, UMG Chairman & CEO'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Live Music Recovery Continues with Festival Boom',
                'slug' => 'festival-boom-2026',
                'tags' => ['live-music', 'festivals', 'touring', 'industry-insights'],
                'categories' => ['Live', 'Festivals'],
                'custom_fields' => [
                    'author_name' => 'Emma Williams',
                    'author_title' => 'Live Editor',
                    'read_time' => 6,
                    'excerpt' => 'Summer 2026 festival season already seeing strong advance ticket sales'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Music festival crowd',
                            'caption' => 'UK festivals experiencing unprecedented demand',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The UK live music sector is experiencing its strongest period since before the pandemic, with major festivals reporting record advance ticket sales for summer 2026. Glastonbury, Reading & Leeds, and Download have all sold out months ahead of the events.',
                                'Industry analysts attribute the boom to pent-up demand, strong artist lineups, and improving economic conditions. Festival capacity has also expanded, with several events increasing their size and new festivals entering the market.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Glastonbury 2026',
                                    'description' => 'Sold out in record 45 minutes',
                                    'image' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Glastonbury Festival'
                                ],
                                [
                                    'title' => 'Reading & Leeds',
                                    'description' => 'Weekend tickets exhausted',
                                    'image' => 'https://images.unsplash.com/photo-1506157786151-b8491531f063?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Reading Festival'
                                ],
                                [
                                    'title' => 'Download Festival',
                                    'description' => 'Rock and metal fans secure early bird tickets',
                                    'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Download Festival'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Economic Impact',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The festival boom is having significant economic benefits beyond ticket sales. Local economies benefit from accommodation, food, transport, and tourism spending. A typical major UK festival generates £20-30 million in regional economic impact.',
                                'Employment has also grown, with festivals creating thousands of temporary and permanent jobs in production, security, catering, and event management.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'UK Festival Market 2026',
                            'stats' => [
                                ['number' => '1.2m', 'label' => 'Festival Attendees', 'icon' => '🎪'],
                                ['number' => '£450m', 'label' => 'Ticket Revenue', 'icon' => '💷'],
                                ['number' => '850+', 'label' => 'Festival Events', 'icon' => '🎵'],
                                ['number' => '45k', 'label' => 'Jobs Created', 'icon' => '👥']
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'The Rise of AI in Music Production: Opportunities and Challenges',
                'slug' => 'ai-music-production-2025',
                'tags' => ['technology', 'ai', 'production', 'industry-insights'],
                'categories' => ['Technology', 'Innovation'],
                'custom_fields' => [
                    'author_name' => 'David Chen',
                    'author_title' => 'Technology Editor',
                    'read_time' => 10,
                    'excerpt' => 'How artificial intelligence is transforming music creation while raising important questions about creativity and copyright'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1677442136019-21780ecad995?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'AI music production',
                            'caption' => 'AI tools are revolutionizing music creation',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Artificial intelligence is rapidly transforming the music industry, from composition and production to distribution and discovery. AI-powered tools are now capable of generating melodies, suggesting chord progressions, mastering tracks, and even creating complete songs.',
                                'While these developments offer exciting new creative possibilities, they also raise complex questions about authorship, copyright, and the future role of human creativity in music production.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Current Applications of AI in Music',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Automated mastering services like LANDR and iZotope Ozone',
                                'AI-assisted composition tools including Amper Music and AIVA',
                                'Stem separation technology for remixing and sampling',
                                'Voice synthesis and deepfake vocals',
                                'Personalized playlist generation and music recommendation',
                                'Predictive analytics for hit potential and A&R'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'The use of AI-generated music in commercial contexts remains a legal grey area, with ongoing debates about copyright ownership and royalty distribution.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Industry Perspectives',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'testimonial',
                        'data' => [
                            'layout' => 'grid',
                            'testimonials' => [
                                [
                                    'text' => 'AI is a tool, not a replacement for human creativity. The best results come from combining AI capabilities with human artistic vision.',
                                    'author' => 'Rick Rubin',
                                    'role' => 'Producer',
                                    'rating' => 5
                                ],
                                [
                                    'text' => 'We need clear regulations around AI-generated music to protect artists\' rights and ensure fair compensation.',
                                    'author' => 'Geoff Taylor',
                                    'role' => 'BPI Chief Executive',
                                    'rating' => 5
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Copyright and Legal Challenges',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The legal framework surrounding AI-generated music remains unsettled. Key questions include: Who owns the copyright to AI-generated compositions? Can AI training on copyrighted works constitute infringement? How should royalties be distributed?',
                                'Several high-profile lawsuits are currently working through the courts, and their outcomes will significantly shape the future relationship between AI and music creation.'
                            ]
                        ]
                    ]
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
            'meta_title' => $data['title'] . ' - Music Week',
            'meta_description' => $data['custom_fields']['excerpt'] ?? '',
            'site_id' => $this->site->id,
        ]);

        // Add menu item for each article
        MenuItem::create([
            'label' => strlen($data['title']) > 40 ? substr($data['title'], 0, 37) . '...' : $data['title'],
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
            'sort_order' => 10
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
            $fieldDef = CustomFieldDefinition::where('key', $key)->where('site_id', $this->site->id)->first();
            if ($fieldDef) {
                PageCustomField::create([
                    'custom_field_definition_id' => $fieldDef->id,
                    'field_value' => (string)$value,
                    'page_id' => $page->id
                ]);
            }
        }

        foreach ($data['content'] as $index => $blockData) {

            if (!isset($blockData['type'])) {
                echo $page->title . ' - ' . $page->slug;
                echo '<pre>';
                print_r($blockData);
                die;
            }

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
            'title' => 'About Music Week',
            'page_type' => 'content',
            'slug' => 'about',
            'status' => 'published',
            'meta_title' => 'About Us - Music Week',
            'meta_description' => 'Learn about Music Week - Your trusted source for music industry news, analysis and data since 1959.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'About',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
            'sort_order' => 90
        ]);

        $blocks = [
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'About Music Week',
                    'level' => 1
                ],
                'order' => 1
            ],
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'About Music Week',
                    'subtitle' => 'Celebrating the business of music since 1959',
                    'ctaText' => 'Our Team',
                    'ctaUrl' => '#team',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'For over 65 years, Music Week has been the definitive source of music industry news, charts, data and analysis. From our headquarters in London, we deliver essential business intelligence to labels, publishers, managers, agents, promoters, and music professionals worldwide.',
                        'Our award-winning journalism team provides unparalleled coverage of the recorded music, live, publishing, and music tech sectors. We break the stories that matter, analyze the trends that shape the industry, and deliver the data that drives business decisions.',
                        'Music Week is published by Future plc, one of the world\'s leading media companies. Our portfolio includes print, digital, events, and awards properties that serve the global music community.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'image',
                'data' => [
                    'src' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                    'alt' => 'Music Week magazine covers',
                    'caption' => 'Over 60 years of music industry coverage',
                    'layout' => 'wide',
                    'alignment' => 'center'
                ],
                'order' => 3
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'What We Do',
                    'level' => 2
                ],
                'order' => 4
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Breaking news and analysis on the UK and global music business',
                        'Official UK music charts and comprehensive streaming data',
                        'In-depth features and interviews with industry leaders',
                        'Market reports and business intelligence',
                        'Industry events including the Music Week Awards',
                        'Digital subscription services and print magazine'
                    ]
                ],
                'order' => 5
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Music Week by Numbers',
                    'stats' => [
                        ['number' => '65+', 'label' => 'Years of Authority', 'icon' => '📰'],
                        ['number' => '180K+', 'label' => 'Industry Professionals', 'icon' => '👥'],
                        ['number' => '2.4M', 'label' => 'Monthly Web Users', 'icon' => '🌐'],
                        ['number' => '50+', 'label' => 'Industry Events Annually', 'icon' => '🎯']
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Editorial Team',
                    'subtitle' => 'Award-winning music industry journalists',
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
                            'name' => 'Mark Sutherland',
                            'role' => 'Editor-in-Chief',
                            'bio' => 'Mark has led Music Week since 2015, previously serving as US Editor for Billboard and European Editor for Music Week.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'mark.sutherland@musicweek.com'
                        ],
                        [
                            'name' => 'Gordon Masson',
                            'role' => 'Live Music Editor',
                            'bio' => 'Gordon covers the live sector with unmatched expertise, from grassroots venues to stadium tours and festival circuits worldwide.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'gordon.masson@musicweek.com'
                        ],
                        [
                            'name' => 'Tim Ingham',
                            'role' => 'Features Editor',
                            'bio' => 'Tim leads our long-form journalism, covering streaming, music tech, and the evolving business models reshaping the industry.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'tim.ingham@musicweek.com'
                        ],
                        [
                            'name' => 'Charlotte Chambers',
                            'role' => 'Independent Music Writer',
                            'bio' => 'Charlotte specializes in independent labels, artist development, and the DIY music sector across all genres.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'charlotte.chambers@musicweek.com'
                        ],
                        [
                            'name' => 'Ben Cardew',
                            'role' => 'Technology Editor',
                            'bio' => 'Ben covers music tech, AI, blockchain, and digital innovation, analyzing how technology reshapes music creation and distribution.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'ben.cardew@musicweek.com'
                        ],
                        [
                            'name' => 'Sarah Johnson',
                            'role' => 'Data & Charts Editor',
                            'bio' => 'Sarah oversees Music Week\'s comprehensive chart coverage and market data analysis, partnering with Official Charts Company.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'],
                            'email' => 'sarah.johnson@musicweek.com'
                        ]
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
                    'text' => 'What We Cover',
                    'level' => 2
                ],
                'order' => 7
            ],
            [
                'type' => 'services',
                'data' => [
                    'title' => 'Our Coverage Areas',
                    'services' => [
                        [
                            'title' => 'Recorded Music',
                            'description' => 'Labels, streaming, physical, downloads, charts, and global market analysis',
                            'icon' => '💿',
                            'url' => '/sectors/recorded-music'
                        ],
                        [
                            'title' => 'Live Music',
                            'description' => 'Touring, festivals, venues, agents, promoters, and ticketing',
                            'icon' => '🎤',
                            'url' => '/sectors/live-music'
                        ],
                        [
                            'title' => 'Music Publishing',
                            'description' => 'Sync, licensing, PROs, and songwriter economics',
                            'icon' => '📝',
                            'url' => '/sectors/publishing'
                        ],
                        [
                            'title' => 'Music Technology',
                            'description' => 'AI, platforms, innovation, and digital disruption',
                            'icon' => '🤖',
                            'url' => '/sectors/music-tech'
                        ],
                        [
                            'title' => 'Radio & Broadcast',
                            'description' => 'BBC, commercial radio, digital radio, and podcasts',
                            'icon' => '📻',
                            'url' => '/sectors/radio'
                        ],
                        [
                            'title' => 'Industry Analysis',
                            'description' => 'Market data, trends, reports, and strategic insights',
                            'icon' => '📊',
                            'url' => '/sectors/analysis'
                        ]
                    ]
                ],
                'order' => 8
            ],
            [
                'type' => 'divider',
                'data' => [
                    'style' => 'solid'
                ],
                'order' => 9
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Awards & Recognition',
                    'level' => 2
                ],
                'order' => 10
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Music Week has been recognized with numerous industry awards for journalism excellence, including multiple PPA (Professional Publishers Association) awards for Business Media Brand of the Year.',
                        'We also host the prestigious Music Week Awards, celebrating the best of the UK music industry across labels, publishers, live, and retail sectors.'
                    ]
                ],
                'order' => 11
            ],
            [
                'type' => 'divider',
                'data' => [
                    'style' => 'solid'
                ],
                'order' => 12
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our History',
                    'level' => 2
                ],
                'order' => 13
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Founded in 1959, Music Week has chronicled the evolution of the music business through every major shift - from vinyl to cassettes, CDs to downloads, and now streaming. We\'ve covered the rise of independent labels, the digital revolution, and the globalization of music.',
                        'Throughout our history, we\'ve maintained our commitment to independent, authoritative journalism that serves the entire music community. From breaking news about major deals to championing grassroots talent, Music Week has been there for every pivotal moment.',
                        'Today, as the industry faces new challenges and opportunities with AI, Web3, and changing consumer behaviors, Music Week continues to provide the insight and analysis the business needs to thrive.'
                    ]
                ],
                'order' => 14
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'Music Week has been an essential read for music industry professionals for over six decades. Its authoritative coverage and deep industry connections make it invaluable.',
                    'attribution' => 'Industry Executive'
                ],
                'order' => 15
            ],
            [
                'type' => 'divider',
                'data' => [
                    'style' => 'solid'
                ],
                'order' => 16
            ],
            [
                'type' => 'testimonial',
                'data' => [
                    'layout' => 'grid',
                    'testimonials' => [
                        [
                            'text' => 'Music Week is essential reading for anyone serious about the music business. Their coverage is comprehensive, accurate, and always ahead of the curve.',
                            'author' => 'Industry Executive',
                            'role' => 'Major Label',
                            'rating' => 5
                        ],
                        [
                            'text' => 'The depth of analysis and quality of journalism sets Music Week apart. It\'s the first thing I read every morning.',
                            'author' => 'Music Publisher',
                            'role' => 'Independent Publishing',
                            'rating' => 5
                        ]
                    ]
                ],
                'order' => 9
            ],
            [
                'type' => 'cta',
                'data' => [
                    'title' => 'Subscribe to Music Week',
                    'description' => 'Get unlimited access to breaking news, analysis, data, and events',
                    'buttonText' => 'View Subscription Options',
                    'buttonUrl' => '/subscribe',
                    'alignment' => 'center'
                ],
                'order' => 10
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Subscribe to Music Week',
                    'subtitle' => 'Get the latest music industry news and analysis delivered to your inbox',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => false,
                    'showSubject' => false,
                    'showMessage' => false,
                    'submitButtonText' => 'Subscribe Now',
                    'requireName' => true,
                    'requireEmail' => true
                ],
                'order' => 17
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createContactPage(): void
    {
        $page = Page::create([
            'title' => 'Contact Music Week',
            'page_type' => 'content',
            'slug' => 'contact',
            'status' => 'published',
            'meta_title' => 'Contact Us - Music Week',
            'meta_description' => 'Get in touch with the Music Week editorial team.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Contact',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
            'sort_order' => 100
        ]);

        $blocks = [
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Contact Music Week',
                    'level' => 1
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'We\'re here to help. Whether you have a story tip, advertising inquiry, subscription question, or general feedback, we want to hear from you.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Get In Touch',
                    'subtitle' => 'We\'d love to hear from you',
                    'showSearch' => false
                ],
                'order' => 1
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Music Week Editorial',
                    'role' => 'Contact Information',
                    'email' => 'editorial@musicweek.com',
                    'phone' => '+44 20 7921 8347',
                    'address' => 'Future Publishing Ltd\nQuay House, The Ambury\nBath BA1 1UA\nUnited Kingdom',
                    'displayType' => 'contact'
                ],
                'order' => 2
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'For editorial enquiries, story tips, or press releases, please email editorial@musicweek.com',
                        'For advertising opportunities: advertising@musicweek.com',
                        'For subscription enquiries: subscriptions@musicweek.com',
                        'For event enquiries: events@musicweek.com'
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
}
