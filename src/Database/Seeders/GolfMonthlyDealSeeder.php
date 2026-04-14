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

class GolfMonthlyDealSeeder extends Seeder
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
        $this->site = Site::find(11); // Assuming Golf Monthly is site ID 5

        if (!$this->site) {
            echo "Golf Monthly site not found.\n";
            return;
        }

        $dealPages = $this->createDealPages();
        $this->addDealSectionToHomepage($dealPages);
    }

    private function createDealPages(): array
    {
        $deals = [
            [
                'title' => 'TaylorMade Stealth 2 Driver - Massive £200 Off Sale',
                'slug' => 'taylormade-stealth-2-driver-deal',
                'category' => 'Clubs',
                'tag' => 'driver',
                'image' => 'https://images.unsplash.com/photo-1566861699964-e44a47383c3f?q=80&w=1173&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'TaylorMade',
                'product' => 'Stealth 2 Driver (10.5°)',
                'price' => 529.00,
                'salePrice' => 329.00,
                'voucherId' => 'TAYLORMADE200',
                'excerpt' => 'Huge £200 discount on the carbon-crowned Stealth 2. Incredible forgiveness and distance. Limited stock in 10.5° right-hand.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1598370774780-60b5e9007f3c?w=2340',
                            'alt' => 'TaylorMade Stealth 2 Driver',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🏌️ Top Driver Deal',
                            'productName' => 'TaylorMade Stealth 2 Driver',
                            'brand' => 'TaylorMade',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1598370774780-60b5e9007f3c?w=800'],
                            'price' => 529.00,
                            'salePrice' => 329.00,
                            'currency' => '£',
                            'description' => 'The second generation of TaylorMade\'s carbon face technology. Delivers explosive ball speed and high MOI for maximum forgiveness off the tee. Custom shaft options available at no extra cost.',
                            'link' => 'https://example.com/stealth-2-driver',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'TAYLORMADE200',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Why Upgrade to Stealth 2', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Stealth 2 is a significant improvement on its predecessor, particularly in its increased forgiveness across the clubface. It scored 5/5 stars in our recent review for its blend of speed and playability.',
                                'This is an end-of-season clearance sale, making it the cheapest price you\'ll find on a near-current model flagship driver. Don\'t miss out on this power upgrade.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                '60X Carbon Twist Face',
                                'Massive £200 Saving',
                                'High MOI and Forgiveness',
                                'Stock: 10.5° R/H only'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Bushnell Tour V6 Shift Rangefinder - £100 Price Drop',
                'slug' => 'bushnell-tour-v6-deal',
                'category' => 'Tech',
                'tag' => 'rangefinder',
                'image' => 'https://images.unsplash.com/photo-1697448524524-99416ed5dbba?q=80&w=1026&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'Bushnell',
                'product' => 'Tour V6 Shift Laser Rangefinder',
                'price' => 399.00,
                'salePrice' => 299.00,
                'voucherId' => 'LASER100',
                'excerpt' => 'Save £100 on the best laser rangefinder on the market. Features slope-switch technology and enhanced JOLT visual feedback.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1606132616231-c003d790f9b6?w=2340',
                            'alt' => 'Bushnell Tour V6 Shift Rangefinder',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🎯 Precision Tech Deal',
                            'productName' => 'Bushnell Tour V6 Shift Rangefinder',
                            'brand' => 'Bushnell',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1606132616231-c003d790f9b6?w=800'],
                            'price' => 399.00,
                            'salePrice' => 299.00,
                            'currency' => '£',
                            'description' => 'PinSeeker technology with Visual JOLT ensures you lock onto the flag. The Shift function allows you to switch between slope-compensated distance and tournament-legal non-slope distance instantly. Essential for serious golfers.',
                            'link' => 'https://example.com/bushnell-v6-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'LASER100',
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Know Your Exact Distance', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Tour V6 Shift is a perennial winner in our testing. Its clarity, speed, and accuracy are unmatched. The magnetic mount is also incredibly useful for attaching to your trolley or cart.',
                                'A £100 saving on a new Bushnell model is exceptionally rare. This deal levels up your course management immediately.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'FootJoy Pro|SL Golf Shoes - 30% Off All Styles',
                'slug' => 'footjoy-pro-sl-shoe-deal',
                'category' => 'Apparel',
                'tag' => 'shoes',
                'image' => 'https://images.unsplash.com/photo-1697448524500-717d056bc8ad?q=80&w=764&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'FootJoy',
                'product' => 'Pro|SL Spikeless Golf Shoe',
                'price' => 150.00,
                'salePrice' => 105.00,
                'voucherId' => 'FJPROSL30',
                'excerpt' => 'The #1 shoe in golf for performance and comfort. Save 30% on the award-winning spikeless model in black, white, and navy. Limited sizes remaining.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1594917637841-8f5f8b9e67d2?w=2340',
                            'alt' => 'FootJoy Pro|SL Golf Shoes',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '👟 Best Footwear Deal',
                            'productName' => 'FootJoy Pro|SL Spikeless Shoe',
                            'brand' => 'FootJoy',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1594917637841-8f5f8b9e67d2?w=800'],
                            'price' => 150.00,
                            'salePrice' => 105.00,
                            'currency' => '£',
                            'description' => 'Tour-level spikeless performance with a waterproof ChromoSkin leather upper. Features a Fine Tuned Foam (FTF) midsole for supreme comfort. The best walking and swinging shoe you can buy.',
                            'link' => 'https://example.com/footjoy-prosl-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'FJPROSL30',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Walk The Course in Comfort', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Pro|SL is a staple on professional tours for its incredible grip and all-day comfort. The spikeless design offers fantastic traction while remaining comfortable enough to wear off the course.',
                                'This 30% off is one of the only times the shoe is discounted below £110. Sizes are going fast, so check availability immediately.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Motocaddy M7 Remote Electric Trolley - Free Accessory Bundle',
                'slug' => 'motocaddy-m7-remote-deal',
                'category' => 'Equipment',
                'tag' => 'trolley',
                'image' => 'https://images.unsplash.com/photo-1574252757301-440c0cc8951f?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'Motocaddy',
                'product' => 'M7 Remote Electric Trolley',
                'price' => 1299.00,
                'salePrice' => 1299.00, // Value added via free accessories
                'voucherId' => 'M7BUNDLE',
                'excerpt' => 'Buy the industry-leading remote control trolley and receive a FREE Accessory Bundle (worth £150): Umbrella Holder, Drink Holder, and Scorecard Holder.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1627916945532-349f2b8a7f29?w=2340',
                            'alt' => 'Motocaddy M7 Remote Trolley',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🔋 Best Trolley Bundle',
                            'productName' => 'Motocaddy M7 Remote Trolley',
                            'brand' => 'Motocaddy',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1627916945532-349f2b8a7f29?w=800'],
                            'price' => 1299.00,
                            'salePrice' => 1299.00,
                            'currency' => '£',
                            'description' => 'The compact, easy-folding M7 is controlled by a slimline remote for effortless navigation of the course. Features a rechargeable handset and automatic Downhill Control. FREE accessory bundle included.',
                            'link' => 'https://example.com/motocaddy-m7-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'M7BUNDLE',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Upgrade Your Walk', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The M7 takes the strain out of your round, allowing you to focus purely on your game. It’s highly reliable, responsive, and folds down small enough to fit in the smallest car boot.',
                                'The free accessory bundle is a genuine value-add, as these items are essential for play in all weather conditions. Offer valid for a limited time.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Puma Cloudspun Golf Polo Shirts - 2 for £70 Deal',
                'slug' => 'puma-cloudspun-polo-deal',
                'category' => 'Apparel',
                'tag' => 'clothing',
                'image' => 'https://plus.unsplash.com/premium_photo-1727895548212-89f92ff240a2?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'Puma',
                'product' => 'Cloudspun Polo Shirt',
                'price' => 90.00,
                'salePrice' => 70.00,
                'voucherId' => 'PUMA2FOR70',
                'excerpt' => 'Buy two of Puma\'s ultra-soft, performance Cloudspun polos for just £70. Normally £45 each. Choose from 10 different colours.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1634571932902-6e216262451f?w=2340',
                            'alt' => 'Golf Polo Shirts',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '👕 Apparel Multi-Buy',
                            'productName' => 'Puma Cloudspun Polo (2 for £70)',
                            'brand' => 'Puma',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1634571932902-6e216262451f?w=800'],
                            'price' => 90.00,
                            'salePrice' => 70.00,
                            'currency' => '£',
                            'description' => 'The ultimate blend of performance and comfort. Cloudspun fabric is ultra-soft, moisture-wicking, and has 4-way stretch for unrestricted movement. Buy two for a great saving.',
                            'link' => 'https://example.com/puma-polo-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'PUMA2FOR70',
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Play Your Best in Comfort', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Puma\'s Cloudspun range is a staff favourite for its feel and breathability. This multi-buy offer allows you to refresh your wardrobe with quality apparel for summer rounds.',
                                'The shirts are designed to prevent clinging and dry quickly, keeping you comfortable on hot days. Mix and match colours and sizes while stock is available.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Titleist Pro V1 Balls - £10 Off Dozen (Limited Edition)',
                'slug' => 'titleist-pro-v1-deal',
                'category' => 'Balls',
                'tag' => 'titleist',
                'image' => 'https://images.unsplash.com/photo-1703293024077-db8b6da5391c?q=80&w=879&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'Titleist',
                'product' => 'Titleist Pro V1 Golf Balls (Dozen)',
                'price' => 49.99,
                'salePrice' => 39.99,
                'voucherId' => 'PROV1TEN',
                'excerpt' => 'A rare discount on the industry standard tour ball. Save £10 on a dozen Pro V1 or Pro V1x. Stock up while this deal lasts.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1544520977-626209581333?w=2340',
                            'alt' => 'Titleist Pro V1 Golf Balls',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '⚪ Essential Ball Deal',
                            'productName' => 'Titleist Pro V1 Golf Balls (Dozen)',
                            'brand' => 'Titleist',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1544520977-626209581333?w=800'],
                            'price' => 49.99,
                            'salePrice' => 39.99,
                            'currency' => '£',
                            'description' => 'The world\'s most played golf ball. Offers exceptional distance, piercing flight, and unmatched Drop-and-Stop™ greenside control. This discount applies to both Pro V1 and the lower-spinning Pro V1x.',
                            'link' => 'https://example.com/pro-v1-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'PROV1TEN',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Elevate Your Short Game', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Pro V1 balls rarely see a price drop, so this £10 saving is an opportunity for any golfer using a premium ball. Their consistent performance in all conditions is why they dominate the world tours.',
                                'Stock is highly volatile for this offer. Max two dozen per customer to ensure fair access.'
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
                'meta_title' => $dealData['title'] . ' - Golf Monthly',
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

        echo "Adding deals to Golf Monthly homepage.\n";

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
                'title' => '⛳ Hot Golf Deals',
                'subtitle' => 'The best savings on clubs, equipment, and apparel',
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