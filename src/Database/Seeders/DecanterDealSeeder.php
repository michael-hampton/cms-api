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

class DecanterDealSeeder extends Seeder
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
        $this->site = Site::find(10);

        if (!$this->site) {
            echo "Decanter site not found.\n";
            return;
        }

        $dealPages = $this->createDealPages();
        $this->addDealSectionToHomepage($dealPages);
    }

    private function createDealPages(): array
    {
        $deals = [
            [
                'title' => 'Châteauneuf-du-Pape 2020 - 25% Off Case of 6 from Rhône',
                'slug' => 'chateauneuf-du-pape-2020-deal',
                'category' => 'Offers',
                'tag' => 'rhone',
                'image' => 'https://media.istockphoto.com/id/89921058/photo/wine.jpg?s=2048x2048&w=is&k=20&c=zYAu4YsZA_pvyey_CNhYbM7ueIDGiUIjurVtHkOYoVw=',
                'brand' => 'Domaine du Vieux Télégraphe',
                'product' => 'Châteauneuf-du-Pape 2020',
                'price' => 240.00,
                'salePrice' => 180.00,
                'voucherId' => 'DECANTER25',
                'excerpt' => 'Exceptional 25% off on a case of 6 of this highly-rated vintage. Rich, complex, and perfect for cellaring or drinking now.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1541624898144-839556277b06?w=2340',
                            'alt' => 'Bottle of Châteauneuf-du-Pape',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🍷 Cellar Favourite Deal',
                            'productName' => 'Châteauneuf-du-Pape 2020 (Case of 6)',
                            'brand' => 'Domaine du Vieux Télégraphe',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1541624898144-839556277b06?w=800'],
                            'price' => 240.00,
                            'salePrice' => 180.00,
                            'currency' => '£',
                            'description' => 'A structured and powerful Rhône blend, highly praised by critics. Notes of dark fruit, spice, and Garrigue herbs. Save £60 on a full case.',
                            'link' => 'https://example.com/chateauneuf-du-pape',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'DECANTER25',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Why This Rhône is a Must-Buy', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The 2020 vintage in Châteauneuf-du-Pape is considered superb, offering both immediate pleasure and aging potential. This is an incredible opportunity to stock up at a 25% discount.',
                                'The wine showcases the classic blend of Grenache, Syrah, and Mourvèdre, delivering layers of flavour and a long, elegant finish. It pairs beautifully with slow-cooked lamb or game.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Vintage: 2020 (Excellent)',
                                'Region: Châteauneuf-du-Pape, Rhône',
                                'Critics\' Score: 96 Points (Decanter Panel)',
                                'Primary Grapes: Grenache, Syrah, Mourvèdre',
                                'Discount: 25% off Case of 6'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Coravin Pivot Wine Preservation System - Save £40',
                'slug' => 'coravin-pivot-deal',
                'category' => 'Accessories',
                'tag' => 'gadgets',
                'image' => 'https://media.istockphoto.com/id/1253192816/photo/adult-caucasian-man-opening-bottle-of-wine-at-home.jpg?s=2048x2048&w=is&k=20&c=0Vs8TlD3fO8qwzjZq8eCm0vz-HOj39spZzTTbvyoZ7g=',
                'brand' => 'Coravin',
                'product' => 'Coravin Pivot Wine System',
                'price' => 120.00,
                'salePrice' => 80.00,
                'voucherId' => 'SAVE40PIVOT',
                'excerpt' => 'Save £40 on the essential tool for enjoying a single glass without committing to the whole bottle. Preserve your wine for up to 4 weeks.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1616142125749-c18742d475d6?w=2340',
                            'alt' => 'Coravin Pivot Wine Preservation System',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => 'Essential Wine Tool Deal',
                            'productName' => 'Coravin Pivot Wine Preservation System',
                            'brand' => 'Coravin',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1616142125749-c18742d475d6?w=800'],
                            'price' => 120.00,
                            'salePrice' => 80.00,
                            'currency' => '£',
                            'description' => 'The perfect system for preserving open bottles. Enjoy your favourite wine by the glass for up to four weeks with no loss of freshness. Includes two Coravin Pure™ Capsules.',
                            'link' => 'https://example.com/coravin-pivot',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'SAVE40PIVOT',
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Why Coravin is a Game-Changer', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Coravin Pivot uses Argon gas to replace the wine you pour, preventing oxidation and keeping the remaining wine perfectly fresh for weeks. It’s ideal for tasting multiple bottles or enjoying a premium wine over several nights.',
                                'The Pivot is a more accessible model than the Needle systems, designed for bottles you plan to finish within a month. At this price, it\'s an investment every wine lover should make.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Luxury Champagne Case (6 Bottles) - Half Price Offer',
                'slug' => 'luxury-champagne-case-deal',
                'category' => 'Champagne',
                'tag' => 'sparkling',
                'image' => 'https://plus.unsplash.com/premium_photo-1677434158244-ef5aebc03951?q=80&w=764&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'Moët & Chandon',
                'product' => 'Moët Impérial Brut NV (Case of 6)',
                'price' => 300.00,
                'salePrice' => 150.00,
                'voucherId' => 'CHAMP50',
                'excerpt' => 'Unbeatable half-price deal on a case of 6 bottles of classic Non-Vintage Brut Champagne. Limited availability for celebrations.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1636181755100-3486c558d11c?w=2340',
                            'alt' => 'Champagne glasses and bottles',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🍾 Champagne Super Saver',
                            'productName' => 'Moët Impérial Brut NV (Case of 6)',
                            'brand' => 'Moët & Chandon',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1636181755100-3486c558d11c?w=800'],
                            'price' => 300.00,
                            'salePrice' => 150.00,
                            'currency' => '£',
                            'description' => 'The world\'s most loved champagne. A vibrant, generous, and elegant Brut NV with notes of white-fleshed fruit, citrus, and a subtle toastiness. Perfect for any occasion. £150 saving.',
                            'link' => 'https://example.com/moet-champagne-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'CHAMP50',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'A Celebration Essential', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Finding a deal like this on a globally-recognised Champagne is rare. At £25 a bottle, this is an excellent value for a consistent and high-quality Non-Vintage Brut.',
                                'This offer is strictly limited to one case per customer and is expected to sell out extremely quickly. Stock up for your festive season now.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Australian Shiraz Mixed Case - 12 Bottles for £79.99',
                'slug' => 'australian-shiraz-case-deal',
                'category' => 'Mixed Cases',
                'tag' => 'australia',
                'image' => 'https://plus.unsplash.com/premium_photo-1670426501140-b21450719ef1?q=80&w=709&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'Various',
                'product' => 'Premium Australian Shiraz Mixed Case',
                'price' => 149.99,
                'salePrice' => 79.99,
                'voucherId' => 'SHIRAZ70',
                'excerpt' => 'Save £70 on 12 bold and rich Australian Shiraz bottles from top regions like Barossa and McLaren Vale. An ideal winter warmer.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1549419149-165c71a39649?w=2340',
                            'alt' => 'Wine glasses and bottles of Shiraz',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🔥 Value Mixed Case',
                            'productName' => 'Australian Shiraz Mixed Case (12 bottles)',
                            'brand' => 'Various',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1549419149-165c71a39649?w=800'],
                            'price' => 149.99,
                            'salePrice' => 79.99,
                            'currency' => '£',
                            'description' => 'A selection of 12 robust, dark-fruit-driven Shiraz wines from six different Australian producers. Includes award-winners from Barossa Valley and Coonawarra. Great everyday drinking red wine.',
                            'link' => 'https://example.com/shiraz-mixed-case',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'SHIRAZ70',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Deep Dive into Australian Shiraz', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'This mixed case offers incredible value, bringing the per-bottle price down to just £6.67. It’s an easy way to explore the diversity of Australia’s signature grape.',
                                'Expect rich notes of black cherry, pepper, and vanilla from oak aging. These wines are perfect with BBQ, steaks, and strong cheeses. Free delivery is included with this offer.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Bordeaux Haut-Médoc Cru Bourgeois - 40% Off Case of 3',
                'slug' => 'bordeaux-haut-medoc-deal',
                'category' => 'Bordeaux',
                'tag' => 'france',
                'image' => 'https://images.unsplash.com/photo-1642102903921-48cf4c58ec63?q=80&w=627&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'Château Haut-Bages Libéral',
                'product' => 'Haut-Médoc 2018 (Case of 3)',
                'price' => 150.00,
                'salePrice' => 90.00,
                'voucherId' => 'BORDEAUX40',
                'excerpt' => 'A fantastic 40% discount on a ready-to-drink Bordeaux from the highly-regarded 2018 vintage. Classic claret for immediate enjoyment.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1594917573679-5e7e1f4d9320?w=2340',
                            'alt' => 'Bordeaux bottles in a cellar',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🇫🇷 Classic Claret Deal',
                            'productName' => 'Haut-Médoc 2018 Cru Bourgeois (Case of 3)',
                            'brand' => 'Château Haut-Bages Libéral',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1594917573679-5e7e1f4d9320?w=800'],
                            'price' => 150.00,
                            'salePrice' => 90.00,
                            'currency' => '£',
                            'description' => 'A complex, Cabernet Sauvignon-dominant blend with blackcurrant, cedar, and tobacco notes. The 2018 vintage is drinking beautifully now. Save £60 on three bottles.',
                            'link' => 'https://example.com/haut-medoc-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'BORDEAUX40',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Investment-Grade Drinking', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Cru Bourgeois classification guarantees quality, and the 2018 vintage is an excellent, cellar-worthy year. This 40% saving is perfect for Bordeaux enthusiasts seeking a mature wine for a weeknight.',
                                'We recommend decanting for at least one hour to allow the tertiary notes to fully open up. Offer valid until the end of the month.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Zalto Denk`Art Universal Glasses - Buy 5, Get 1 Free',
                'slug' => 'zalto-glasses-deal',
                'category' => 'Glassware',
                'tag' => 'luxury',
                'image' => 'https://images.unsplash.com/photo-1678554884723-9c084ffd10cf?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'Zalto',
                'product' => 'Zalto Denk`Art Universal Glass',
                'price' => 270.00,
                'salePrice' => 225.00,
                'voucherId' => 'ZALTOFREE',
                'excerpt' => 'A rare offer on the industry\'s favourite luxury mouth-blown glassware. Get 6 glasses for the price of 5 – a £45 saving.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1548682121-50e56e077a79?w=2340',
                            'alt' => 'Zalto wine glasses',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🥂 Luxury Glassware Deal',
                            'productName' => 'Zalto Universal Glass (Set of 6)',
                            'brand' => 'Zalto',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1548682121-50e56e077a79?w=800'],
                            'price' => 270.00,
                            'salePrice' => 225.00,
                            'currency' => '£',
                            'description' => 'The ultimate wine glass, celebrated for its feather-light weight and perfect balance. Designed to enhance both red and white wines. Purchase 5 glasses and receive the 6th free.',
                            'link' => 'https://example.com/zalto-glasses',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'ZALTOFREE',
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'The Glass Matters', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Zalto glasses are universally acknowledged by sommeliers as among the best for their ability to perfectly present the aroma and structure of the wine. They are mouth-blown and dishwasher safe.',
                                'This is an extremely rare promotion on a premium product. Enhance your wine-drinking experience for the cost of one less glass.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Mixed Dozen Case: Summer Whites - £5/Bottle Sale',
                'slug' => 'summer-whites-case-deal',
                'category' => 'Mixed Cases',
                'tag' => 'white-wine',
                'image' => 'https://images.unsplash.com/photo-1724120505644-c75d304cc915?q=80&w=1170&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'Various',
                'product' => 'Mixed Case of 12 Summer Whites',
                'price' => 120.00,
                'salePrice' => 60.00,
                'voucherId' => 'SUMMER60',
                'excerpt' => 'Unbelievable 50% off on a mixed case of 12 refreshing Sauvignon Blanc, Pinot Grigio, and dry Riesling. Stock up for the warm weather.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1565492211910-14c1c736551b?w=2340',
                            'alt' => 'Chilled white wine bottles',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '☀️ Summer Essentials Deal',
                            'productName' => 'Mixed Case of 12 Summer Whites',
                            'brand' => 'Various',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1565492211910-14c1c736551b?w=800'],
                            'price' => 120.00,
                            'salePrice' => 60.00,
                            'currency' => '£',
                            'description' => 'A hand-selected case of 12 light, crisp, and aromatic white wines perfect for summer sipping, including bottles from Marlborough, Veneto, and Mosel. Only £5 per bottle.',
                            'link' => 'https://example.com/summer-whites-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'SUMMER60',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Perfect for Picnics and Parties', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'You won’t find quality at this price point anywhere else. This is a clearance sale on fantastic new-world and old-world whites that are ready to chill and enjoy.',
                                'The case includes tasting notes for each bottle, making it a great way to discover new favourites without breaking the bank.'
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
                'meta_title' => $dealData['title'] . ' - Decanter',
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

        echo "Adding deals to Decanter homepage.\n";

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
                    'readTime' => 'Offer ends soon'
                ]
            ];
        }

        $dealBlock = [
            'type' => 'page_grid',
            'data' => [
                'title' => '🍷 Decanter\'s Top Wine Deals',
                'subtitle' => 'Limited time offers on fine wine and accessories',
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