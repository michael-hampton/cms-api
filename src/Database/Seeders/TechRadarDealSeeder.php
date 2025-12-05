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
use App\Repositories\BlockRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;
use App\Services\BlockParserService;

class TechRadarDealSeeder extends Seeder
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
        $this->site = Site::find(2); // TechWeekly/TechRadar

        if (!$this->site) {
            echo "TechRadar site not found.\n";
            return;
        }

        $dealPages = $this->createDealPages();
        $this->addDealSectionToHomepage($dealPages);
    }

    private function createDealPages(): array
    {
        $deals = [
            [
                'title' => 'Samsung Galaxy S24 Ultra - Save £200 on UK\'s Best Camera Phone',
                'slug' => 'samsung-galaxy-s24-ultra-deal',
                'category' => 'Deals',
                'tag' => 'samsung',
                'image' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=800',
                'brand' => 'Samsung',
                'product' => 'Galaxy S24 Ultra 256GB',
                'price' => 1249.00,
                'salePrice' => 1049.00,
                'voucherId' => 'TECH200',
                'excerpt' => 'Massive £200 discount on Samsung\'s flagship with 200MP camera, S Pen, and AI features. Limited time offer with free Galaxy Buds2 Pro worth £219.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=2340',
                            'alt' => 'Samsung Galaxy S24 Ultra',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🔥 Hot Deal Alert',
                            'productName' => 'Samsung Galaxy S24 Ultra 256GB',
                            'brand' => 'Samsung',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=800'],
                            'price' => 1249.00,
                            'salePrice' => 1049.00,
                            'currency' => '£',
                            'description' => 'The Galaxy S24 Ultra is Samsung\'s most advanced phone yet. Features include 200MP camera with AI enhancement, titanium frame, S Pen built-in, and Galaxy AI for intelligent photo editing. This deal includes FREE Galaxy Buds2 Pro (£219 value).',
                            'link' => 'https://example.com/samsung-s24-ultra',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'TECH200',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Why This Deal is Incredible', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'This is the lowest price we\'ve seen for the Galaxy S24 Ultra since launch. The £200 saving combined with free Galaxy Buds2 Pro makes this deal worth over £400 in total savings.',
                                'The S24 Ultra scored 5/5 in our review for its exceptional camera system, stunning 6.8-inch AMOLED display, and all-day battery life. The integrated S Pen makes it perfect for productivity and creative work.',
                                'Stock is limited and this offer ends Sunday. We expect it to sell out before then based on previous Samsung flash sales.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                '200MP main camera with 100x Space Zoom',
                                '6.8" QHD+ 120Hz AMOLED display',
                                'Titanium frame with Gorilla Armor glass',
                                'Built-in S Pen for notes and creativity',
                                'Galaxy AI for photo editing and productivity',
                                'All-day 5000mAh battery',
                                'FREE Galaxy Buds2 Pro included (£219 value)'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Apple AirPods Pro 2 - Lowest Ever Price at £179',
                'slug' => 'airpods-pro-2-deal',
                'category' => 'Deals',
                'tag' => 'apple',
                'image' => 'https://images.unsplash.com/photo-1606841837239-c5a1a4a07af7?w=800',
                'brand' => 'Apple',
                'product' => 'AirPods Pro 2 with USB-C',
                'price' => 229.00,
                'salePrice' => 179.00,
                'voucherId' => 'AIRPODS50',
                'excerpt' => 'Record low price on Apple\'s best earbuds with adaptive audio, personalized spatial audio, and USB-C charging. £50 off RRP.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1606841837239-c5a1a4a07af7?w=2340',
                            'alt' => 'Apple AirPods Pro 2',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => 'Record Low Price',
                            'productName' => 'AirPods Pro 2 (USB-C)',
                            'brand' => 'Apple',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1606841837239-c5a1a4a07af7?w=800'],
                            'price' => 229.00,
                            'salePrice' => 179.00,
                            'currency' => '£',
                            'description' => 'Apple\'s flagship earbuds with adaptive audio that adjusts to your environment, personalized spatial audio with dynamic head tracking, and up to 2x more active noise cancellation. Now with USB-C charging case.',
                            'link' => 'https://example.com/airpods-pro-2',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'AIRPODS50',
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'What Makes This Deal Special', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'At £179, this is the lowest price we\'ve ever tracked for the AirPods Pro 2. The USB-C model normally retails for £229, making this a genuine £50 saving.',
                                'These earned our Editor\'s Choice award for their exceptional sound quality, industry-leading ANC, and seamless Apple ecosystem integration. They\'re the perfect companion for iPhone, iPad, and Mac users.',
                                'This deal is available while stocks last - previous AirPods deals at this price sold out within hours.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'LG C3 OLED 55" TV - Save £500 on Award-Winning 4K OLED',
                'slug' => 'lg-c3-oled-tv-deal',
                'category' => 'Deals',
                'tag' => 'lg',
                'image' => 'https://images.unsplash.com/photo-1593784991095-a205069470b6?w=800',
                'brand' => 'LG',
                'product' => 'C3 55" 4K OLED TV',
                'price' => 1499.00,
                'salePrice' => 999.00,
                'voucherId' => 'OLED500',
                'excerpt' => 'Massive £500 off LG\'s award-winning C3 OLED with perfect blacks, 120Hz gaming, and Dolby Vision. Our TV of the Year 2024.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1593784991095-a205069470b6?w=2340',
                            'alt' => 'LG C3 OLED TV',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => 'TV Deal of the Month',
                            'productName' => 'LG C3 55" 4K OLED',
                            'brand' => 'LG',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1593784991095-a205069470b6?w=800'],
                            'price' => 1499.00,
                            'salePrice' => 999.00,
                            'currency' => '£',
                            'description' => 'Our TV of the Year delivers perfect blacks, infinite contrast, and stunning HDR with Dolby Vision IQ. Features four HDMI 2.1 ports for 4K 120Hz gaming, α9 Gen6 AI processor, and webOS smart platform. Includes 5-year warranty.',
                            'link' => 'https://example.com/lg-c3-oled',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'OLED500',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Why We Love This TV', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The LG C3 is our TV of the Year for good reason. OLED technology delivers perfect blacks and infinite contrast that LCD TVs simply can\'t match. Each pixel produces its own light, creating stunning picture quality.',
                                'Gamers will appreciate four HDMI 2.1 ports supporting 4K at 120Hz, VRR, and ALLM. Input lag is incredibly low at just 5ms, making it perfect for PS5 and Xbox Series X.',
                                'At £999, this is £500 off and matches the lowest price we\'ve seen. It\'s an exceptional deal for premium OLED technology.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Sony WH-1000XM5 Headphones - £100 Off Best Noise-Cancelling',
                'slug' => 'sony-wh1000xm5-deal',
                'category' => 'Deals',
                'tag' => 'sony',
                'image' => 'https://images.unsplash.com/photo-1618366712010-f4ae9c647dcb?w=800',
                'brand' => 'Sony',
                'product' => 'WH-1000XM5 Wireless Headphones',
                'price' => 379.00,
                'salePrice' => 279.00,
                'voucherId' => 'SONY100',
                'excerpt' => 'Industry-leading noise cancellation meets exceptional sound quality. £100 off Sony\'s flagship headphones with 30-hour battery life.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1618366712010-f4ae9c647dcb?w=2340',
                            'alt' => 'Sony WH-1000XM5',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => 'Premium Audio Deal',
                            'productName' => 'Sony WH-1000XM5',
                            'brand' => 'Sony',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1618366712010-f4ae9c647dcb?w=800'],
                            'price' => 379.00,
                            'salePrice' => 279.00,
                            'currency' => '£',
                            'description' => 'The best noise-cancelling headphones you can buy. Features 8 microphones for superior ANC, LDAC hi-res audio, speak-to-chat, and 30-hour battery life. Redesigned for ultimate comfort on long flights or commutes.',
                            'link' => 'https://example.com/sony-xm5',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'SONY100',
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Best in Class Performance', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The XM5 sets the standard for noise-cancelling headphones. With 8 microphones and dual processors, it silences even the loudest airplane cabins. We tested them on a London to New York flight and could barely hear the engines.',
                                'Sound quality is exceptional with LDAC support for hi-res audio. The bass is punchy but never overpowering, mids are clear, and highs sparkle. Multipoint Bluetooth lets you connect to two devices simultaneously.',
                                'At £279, this is the best price since Black Friday and represents excellent value for Sony\'s flagship model.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'iPad Air M2 - £150 Off Apple\'s Best Value Tablet',
                'slug' => 'ipad-air-m2-deal',
                'category' => 'Deals',
                'tag' => 'apple',
                'image' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=800',
                'brand' => 'Apple',
                'product' => 'iPad Air 11" M2 128GB',
                'price' => 599.00,
                'salePrice' => 449.00,
                'voucherId' => 'IPAD150',
                'excerpt' => 'The perfect balance of performance and price. M2 chip, 11" Liquid Retina display, and Apple Pencil Pro support. £150 saving.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=2340',
                            'alt' => 'iPad Air M2',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => 'Best Value iPad',
                            'productName' => 'iPad Air 11" M2 (2024)',
                            'brand' => 'Apple',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=800'],
                            'price' => 599.00,
                            'salePrice' => 449.00,
                            'currency' => '£',
                            'description' => 'The new iPad Air with M2 chip delivers incredible performance for work and play. Features 11" Liquid Retina display, landscape FaceTime camera, and support for Apple Pencil Pro and Magic Keyboard. All-day battery life.',
                            'link' => 'https://example.com/ipad-air-m2',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'IPAD150',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'The Sweet Spot iPad', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The iPad Air hits the perfect balance between the basic iPad and the premium iPad Pro. The M2 chip handles everything from video editing to gaming with ease, yet costs hundreds less than the Pro.',
                                'The 11" Liquid Retina display is stunning for media consumption and content creation. Support for Apple Pencil Pro adds hover features and squeeze gestures for pro-level creative work.',
                                'At £449, this is £150 off and the best price for the new M2 model. It\'s the iPad we recommend to most people.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Dell XPS 13 Plus - Save £400 on Premium Ultrabook',
                'slug' => 'dell-xps-13-plus-deal',
                'category' => 'Deals',
                'tag' => 'dell',
                'image' => 'https://images.unsplash.com/photo-1593642532973-d31b6557fa68?w=800',
                'brand' => 'Dell',
                'product' => 'XPS 13 Plus Intel Core Ultra 7',
                'price' => 1699.00,
                'salePrice' => 1299.00,
                'voucherId' => 'DELL400',
                'excerpt' => 'Stunning design meets powerful performance. Intel Core Ultra 7, 16GB RAM, 512GB SSD, and edge-to-edge 13.4" OLED display.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1593642532973-d31b6557fa68?w=2340',
                            'alt' => 'Dell XPS 13 Plus',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => 'Premium Laptop Deal',
                            'productName' => 'Dell XPS 13 Plus',
                            'brand' => 'Dell',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1593642532973-d31b6557fa68?w=800'],
                            'price' => 1699.00,
                            'salePrice' => 1299.00,
                            'currency' => '£',
                            'description' => 'The most beautiful Windows laptop. Features Intel Core Ultra 7 processor, 16GB LPDDR5 RAM, 512GB PCIe SSD, and stunning 13.4" 3K OLED touchscreen. Zero-lattice keyboard and invisible haptic touchpad create a seamless aesthetic.',
                            'link' => 'https://example.com/dell-xps-13-plus',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'DELL400',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Premium Design and Performance', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The XPS 13 Plus is Dell\'s most ambitious laptop design. The edge-to-edge keyboard, capacitive function row, and invisible haptic touchpad create a minimalist aesthetic that\'s both beautiful and functional.',
                                'Performance is excellent thanks to Intel\'s new Core Ultra 7 processor with AI acceleration. The 3K OLED display is stunning for creative work with 100% DCI-P3 coverage and infinite contrast.',
                                'At £1,299, this is £400 off the retail price and includes Dell\'s premium support warranty. It\'s a rare discount on a laptop that rarely goes on sale.'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $pages = [];
        foreach ($deals as $dealData) {
            $page = Page::create([
                'title' => $dealData['title'],
                'slug' => $dealData['slug'],
                'status' => 'published',
                'page_type' => 'content',
                'meta_title' => $dealData['title'] . ' - TechRadar',
                'meta_description' => $dealData['excerpt'],
                'site_id' => $this->site->id,
            ]);

            $category = Category::where('slug', strtolower(str_replace(' ', '-', $dealData['category'])))->where('site_id', $this->site->id)->first();
            if ($category) {
                PageCategory::create(['page_id' => $page->id, 'category_id' => $category->id]);
            }

            $tag = Tag::where('slug', strtolower($dealData['tag']))->where('site_id', $this->site->id)->first();
            if ($tag) {
                PageTag::create(['page_id' => $page->id, 'tag_id' => $tag->id]);
            }

            $this->createBlocksForPage($page->id, $dealData['content']);
            $pages[] = ['page' => $page, 'data' => $dealData];
        }

        return $pages;
    }

    private function createBlocksForPage(int $pageId, array $blocks): void
    {
        foreach ($blocks as $index => $blockData) {
            $this->blockRepository->create([
                'page_id' => $pageId,
                'type' => $blockData['type'],
                'data' => json_encode($blockData['data']),
                'order' => $index + 1
            ]);
        }
    }

    private function addDealSectionToHomepage(array $dealPages): void
    {
        $homepage = Page::where('slug', 'home')->where('site_id', $this->site->id)->first();
        if (!$homepage) return;

        echo "Adding deals to homepage.\n";

        $dealItems = [];
        foreach ($dealPages as $item) {
            $page = $item['page'];
            $data = $item['data'];

            $savings = $data['price'] - $data['salePrice'];
            $savingsPercent = round(($savings / $data['price']) * 100);

            $dealItems[] = [
                'title' => $page->title,
                'slug' => $page->slug,
                'excerpt' => $data['excerpt'],
                'image' => ['src' => $data['image'], 'alt' => $data['product']],
                'badge' => ['text' => 'Save £' . $savings . ' (' . $savingsPercent . '%)', 'color' => 'success'],
                'meta' => [
                    'brand' => $data['brand'],
                    'product' => $data['product'],
                    'readTime' => 'Deal ends soon'
                ]
            ];
        }

        $dealBlock = [
            'type' => 'page_grid',
            'data' => [
                'title' => '🔥 Hot Tech Deals',
                'subtitle' => 'Limited time offers on our favorite tech products',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'pages' => $dealItems
            ]
        ];

        $maxOrder = $homepage->blocks()->max('order') ?? 0;

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => $dealBlock['type'],
            'data' => json_encode($dealBlock['data']),
            'order' => $maxOrder + 1
        ]);
    }
}