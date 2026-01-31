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

class GamesadarDealSeeder extends Seeder
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
        $this->site = Site::find(38); // Assuming GamesRadar is site ID 4

        if (!$this->site) {
            echo "GamesRadar site not found.\n";
            return;
        }

        $dealPages = $this->createDealPages();
        $this->addDealSectionToHomepage($dealPages);
    }

    private function createDealPages(): array
    {
        $deals = [
            [
                'title' => 'PS5 Slim Bundle - Includes Spider-Man 2 and Extra Controller',
                'slug' => 'ps5-slim-spiderman-bundle-deal',
                'category' => 'Console Deals',
                'tag' => 'sony',
                'image' => 'https://images.unsplash.com/photo-1606144042614-b2417e99c4e3?q=80&w=1170&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'Sony',
                'product' => 'PlayStation 5 Slim Console Bundle',
                'price' => 599.99,
                'salePrice' => 499.99,
                'voucherId' => 'PS5SAVE100',
                'excerpt' => 'Save £100 on the new PS5 Slim bundle. Includes the critically acclaimed Marvel\'s Spider-Man 2 and a second DualSense controller.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1634591461427-d0d5b3d6f7a6?w=2340',
                            'alt' => 'PlayStation 5 Slim Console',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🎮 Hot Console Bundle',
                            'productName' => 'PS5 Slim w/ Spider-Man 2 & Extra Controller',
                            'brand' => 'Sony',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1634591461427-d0d5b3d6f7a6?w=800'],
                            'price' => 599.99,
                            'salePrice' => 499.99,
                            'currency' => '£',
                            'description' => 'The slimmer, lighter PS5 with a 1TB SSD. Comes with the ultimate superhero game, Marvel\'s Spider-Man 2, and an extra controller for co-op play. Save £100 total.',
                            'link' => 'https://example.com/ps5-slim-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'PS5SAVE100',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Why This Bundle is a Winner', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'This is the best value PS5 deal we\'ve tracked this year, bundling the console with one of its biggest exclusive titles and an essential second controller. Great for starting your next-gen library.',
                                'The PS5 Slim is 30% smaller and offers the same powerful performance for 4K 120fps gaming. Grab it before it sells out – stock is limited.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'PS5 Slim Console (1TB SSD)',
                                'Marvel\'s Spider-Man 2 (Digital Code)',
                                'Two DualSense Wireless Controllers',
                                '4K 120fps support and Ray Tracing',
                                '£100 Total Saving'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Xbox Series X - FREE Diablo IV and 3 Months Game Pass',
                'slug' => 'xbox-series-x-diablo-deal',
                'category' => 'Console Deals',
                'tag' => 'microsoft',
                'image' => 'https://images.unsplash.com/photo-1621259182978-fbf93132d53d?q=80&w=1332&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'Microsoft',
                'product' => 'Xbox Series X Console',
                'price' => 479.99,
                'salePrice' => 479.99, // Value added via free items
                'voucherId' => 'XBOXFREE',
                'excerpt' => 'Full price, but get £100+ of free value: a copy of Diablo IV and a 3-month subscription to Xbox Game Pass Ultimate included.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1610444365775-816766150247?w=2340',
                            'alt' => 'Xbox Series X Console',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🔥 Value-Added Deal',
                            'productName' => 'Xbox Series X Console',
                            'brand' => 'Microsoft',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1610444365775-816766150247?w=800'],
                            'price' => 479.99,
                            'salePrice' => 479.99,
                            'currency' => '£',
                            'description' => 'The most powerful Xbox console. This deal includes the full game of Diablo IV and 3 months of Game Pass Ultimate (£40 value), giving you instant access to hundreds of titles.',
                            'link' => 'https://example.com/xbox-series-x-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'XBOXFREE',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Jump into Next-Gen Gaming', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Xbox Series X is a powerhouse console, capable of true 4K resolution at up to 120fps. With its 12 teraflops of processing power, it delivers lightning-fast loading and superior graphical fidelity.',
                                'This offer is excellent for newcomers, as the 3 months of Game Pass Ultimate lets you start playing hundreds of games, including Starfield and Forza Horizon 5, right out of the box.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Nintendo Switch OLED - £30 Off & Free Mario Kart 8 Deluxe',
                'slug' => 'nintendo-switch-oled-mario-kart-deal',
                'category' => 'Console Deals',
                'tag' => 'nintendo',
                'image' => 'https://images.unsplash.com/photo-1612036781124-847f8939b154?q=80&w=1170&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'Nintendo',
                'product' => 'Nintendo Switch OLED Model',
                'price' => 349.99,
                'salePrice' => 319.99,
                'voucherId' => 'SWITCH30',
                'excerpt' => 'The best price we\'ve seen for the Switch OLED, plus a free copy of the essential Mario Kart 8 Deluxe (worth £49.99). Total saving of nearly £80.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1618585474661-d775176c117e?w=2340',
                            'alt' => 'Nintendo Switch OLED',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🍄 Family Gaming Bargain',
                            'productName' => 'Nintendo Switch OLED Model',
                            'brand' => 'Nintendo',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1618585474661-d775176c117e?w=800'],
                            'price' => 349.99,
                            'salePrice' => 319.99,
                            'currency' => '£',
                            'description' => 'The console with the vibrant 7-inch OLED screen, improved kickstand, and enhanced audio. Get the console for £30 less and receive Mario Kart 8 Deluxe free - a perfect pairing for multiplayer fun.',
                            'link' => 'https://example.com/switch-oled-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'SWITCH30',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Why Buy the OLED Model?', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The OLED model is the definitive way to play the Nintendo Switch in handheld mode. The screen difference is night and day, offering deeper blacks and brighter colours for games like Tears of the Kingdom and Metroid Dread.',
                                'This is a fantastic bundle for families or anyone looking to jump into the essential Nintendo exclusives. This deal will not last long due to high demand.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Razer BlackShark V2 Pro Wireless Headset - Half Price at £90',
                'slug' => 'razer-blackshark-v2-pro-deal',
                'category' => 'Peripherals',
                'tag' => 'pc-gaming',
                'image' => 'https://images.unsplash.com/photo-1674989844487-722ec77b9b81?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'Razer',
                'product' => 'BlackShark V2 Pro Wireless Headset',
                'price' => 179.99,
                'salePrice' => 89.99,
                'voucherId' => 'RAZER50',
                'excerpt' => 'Massive 50% discount on one of the best competitive gaming headsets. Lightweight, long-lasting battery, and crystal-clear mic.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1612444641973-7186a51d45c5?w=2340',
                            'alt' => 'Razer BlackShark V2 Pro headset',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🎧 50% Off Pro Audio',
                            'productName' => 'Razer BlackShark V2 Pro Wireless Headset',
                            'brand' => 'Razer',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1612444641973-7186a51d45c5?w=800'],
                            'price' => 179.99,
                            'salePrice' => 89.99,
                            'currency' => '£',
                            'description' => 'Esports-ready headset with 50mm Triforce Titanium drivers and a pro-grade HyperClear Supercardioid Mic. Features a 24-hour battery and noise-isolating earcups. Unbeatable price for this quality.',
                            'link' => 'https://example.com/razer-blackshark-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'RAZER50',
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'The Edge in Competitive Gaming', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The BlackShark V2 Pro is a favourite among pro gamers for its comfort and precision audio. The 50% discount is the lowest price point we have ever reported for this model.',
                                'It\'s compatible with PC, PS5, PS4, and Nintendo Switch via its 2.4GHz wireless dongle. Perfect for long sessions and clear communication with your squad.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Elden Ring Deluxe Edition (PC) - 60% Off Steam Key',
                'slug' => 'elden-ring-deluxe-deal',
                'category' => 'PC Gaming',
                'tag' => 'rpg',
                'image' => 'https://plus.unsplash.com/premium_photo-1687854992749-e15cba89631d?q=80&w=627&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'FromSoftware',
                'product' => 'Elden Ring Deluxe Edition (PC)',
                'price' => 79.99,
                'salePrice' => 31.99,
                'voucherId' => 'ELDRING60',
                'excerpt' => 'Huge 60% saving on the Game of the Year 2022 and its deluxe content. The perfect time to jump into The Lands Between before the expansion.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1634639908610-d0f7f2b1c6d1?w=2340',
                            'alt' => 'Elden Ring game key art',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '⚔️ GOTY Price Drop',
                            'productName' => 'Elden Ring Deluxe Edition (Steam Key)',
                            'brand' => 'FromSoftware',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1634639908610-d0f7f2b1c6d1?w=800'],
                            'price' => 79.99,
                            'salePrice' => 31.99,
                            'currency' => '£',
                            'description' => 'The massive open-world fantasy RPG from the creators of Dark Souls. Deluxe Edition includes the digital artbook and soundtrack. Prepare for the Shadow of the Erdtree expansion with this huge saving.',
                            'link' => 'https://example.com/elden-ring-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'ELDRING60',
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Conquer The Lands Between', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Elden Ring is a masterpiece of open-world design, offering hundreds of hours of exploration, challenging combat, and deep lore. This 60% discount is unprecedented for the Deluxe Edition.',
                                'With the highly anticipated Shadow of the Erdtree expansion approaching, now is the perfect time to experience the base game. Offer is for a global Steam digital key.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Logitech G Pro X Superlight Mouse - Back Down to £85',
                'slug' => 'logitech-g-pro-superlight-deal',
                'category' => 'Peripherals',
                'tag' => 'esports',
                'image' => 'https://images.unsplash.com/photo-1762180463317-b5e7886b38c1?q=80&w=1331&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'Logitech',
                'product' => 'Logitech G Pro X Superlight Wireless Mouse',
                'price' => 139.99,
                'salePrice' => 84.99,
                'voucherId' => 'SUPERLIGHT',
                'excerpt' => '£55 off the wireless esports mouse of choice. Weighing less than 63 grams, it offers flawless performance with the HERO sensor and Lightspeed wireless tech.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1598075762694-817342838e55?w=2340',
                            'alt' => 'Logitech G Pro X Superlight mouse',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🖱️ Pro-Grade Mouse Deal',
                            'productName' => 'Logitech G Pro X Superlight Wireless Mouse',
                            'brand' => 'Logitech',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1598075762694-817342838e55?w=800'],
                            'price' => 139.99,
                            'salePrice' => 84.99,
                            'currency' => '£',
                            'description' => 'Used by top esports professionals worldwide. Ultra-light design, sub-1ms Lightspeed wireless, and the highly accurate HERO 25K sensor. The perfect mouse for fast-paced shooters and strategy games.',
                            'link' => 'https://example.com/logitech-superlight-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'SUPERLIGHT',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'The Competitive Edge', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'At just £84.99, this is a phenomenal price for the gold standard in wireless competitive gaming mice. Its low weight significantly reduces fatigue during long gaming sessions.',
                                'The battery life is stellar, offering up to 70 hours of continuous motion, meaning you can game for days on a single charge. Highly recommended for PC gamers.'
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
                'meta_title' => $dealData['title'] . ' - GamesRadar',
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

        echo "Adding deals to GamesRadar homepage.\n";

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
                'title' => '🔥 GamesRadar\'s Top Deals',
                'subtitle' => 'The hottest price drops on consoles, games, and hardware',
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