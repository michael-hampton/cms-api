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
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\BlockParserService;

class HorseAndHoundDealSeeder extends Seeder
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
        $this->site = Site::find(29); // Assuming Horse & Hound is site ID 6

        if (!$this->site) {
            echo "Horse & Hound site not found.\n";
            return;
        }

        $dealPages = $this->createDealPages();
        $this->addDealSectionToHomepage($dealPages);
    }

    private function createDealPages(): array
    {
        $deals = [
            [
                'title' => 'Ariat Heritage Contour II Field Boots - £80 Off All Sizes',
                'slug' => 'ariat-field-boots-deal',
                'category' => 'Rider Wear',
                'tag' => 'boots',
                'image' => 'https://images.unsplash.com/photo-1521866385838-ee60c10aba09?q=80&w=1170&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', // Reusing a boot image
                'brand' => 'Ariat',
                'product' => 'Heritage Contour II Field Boot',
                'price' => 280.00,
                'salePrice' => 200.00,
                'voucherId' => 'ARIAT80',
                'excerpt' => 'Premium full-grain leather boots with ATS technology for comfort. Save £80 on this classic riding boot in all colours and calf sizes.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1594917637841-8f5f8b9e67d2?w=2340',
                            'alt' => 'Ariat Heritage Field Boots',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '👢 Essential Riding Boots Deal',
                            'productName' => 'Ariat Heritage Contour II Field Boot',
                            'brand' => 'Ariat',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1594917637841-8f5f8b9e67d2?w=800'],
                            'price' => 280.00,
                            'salePrice' => 200.00,
                            'currency' => '£',
                            'description' => 'A beautifully fitted field boot featuring full-length elasticated panels, a full-length zipper, and Ariat\'s moisture-wicking technology. Perfect for competition or daily riding.',
                            'link' => 'https://example.com/ariat-boots-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'ARIAT80',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Comfort and Performance', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Heritage Contour II is one of the most popular boots for its comfort straight out of the box, minimal break-in time, and flattering fit. The ATS sole technology provides all-day support.',
                                'A rare £80 saving on a premium Ariat boot. Ensure you check the size guide for height and calf measurements before purchasing.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Full-grain leather upper',
                                'Full-length elasticized panel',
                                'ATS® Technology for support',
                                '£80 Saving'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'WeatherBeeta ComFiTec Plus Dynamic Turnout Rug - 40% Off',
                'slug' => 'weatherbeeta-turnout-rug-deal',
                'category' => 'Horse Wear',
                'tag' => 'rugs',
                'image' => 'https://images.unsplash.com/photo-1591771874509-77ab8308077b?q=80&w=1167&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'WeatherBeeta',
                'product' => 'ComFiTec Plus Dynamic Turnout Rug (Medium/Heavy)',
                'price' => 149.99,
                'salePrice' => 89.99,
                'voucherId' => 'RUG40',
                'excerpt' => 'Massive 40% discount on the best-selling 1200D waterproof and breathable turnout rug. Features a 220g fill and full wrap tail flap.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1579709849206-382f7c0a9e70?w=2340',
                            'alt' => 'Turnout rug on a horse',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🐴 Essential Rug Bargain',
                            'productName' => 'WeatherBeeta ComFiTec Plus Dynamic Turnout Rug',
                            'brand' => 'WeatherBeeta',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1579709849206-382f7c0a9e70?w=800'],
                            'price' => 149.99,
                            'salePrice' => 89.99,
                            'currency' => '£',
                            'description' => 'A superior medium/heavyweight rug perfect for winter. Features a 1200 denier triple weave outer shell, memory foam wither relief, and quick clip front closures. Highly durable and fully waterproof.',
                            'link' => 'https://example.com/weatherbeeta-rug-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'RUG40',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Keep Your Horse Warm and Dry', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'This is an excellent investment for all-weather protection. The 40% discount makes a premium 1200D rug affordable for everyday use. The strong outer layer is built to withstand field wear and tear.',
                                'Available in sizes 5’6” to 7’0”. Check the size chart carefully to ensure the perfect fit for your horse.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Equilibrium Therapy Massage Pad - 25% Off RRP',
                'slug' => 'equilibrium-massage-pad-deal',
                'category' => 'Therapy',
                'tag' => 'equipment',
                'image' => 'https://plus.unsplash.com/premium_photo-1732738372524-00ffdf2c1268?q=80&w=1170&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', // Reusing a general product image
                'brand' => 'Equilibrium Products',
                'product' => 'Therapy Massage Pad',
                'price' => 450.00,
                'salePrice' => 337.50,
                'voucherId' => 'EQUILIBRIUM25',
                'excerpt' => 'Save over £112 on the highly-rated, battery-operated massage pad. Proven to help with back flexibility and relaxation. Essential for performance horses.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1549419149-165c71a39649?w=2340',
                            'alt' => 'Equilibrium Therapy Massage Pad',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '✨ Horse Welfare Deal',
                            'productName' => 'Equilibrium Therapy Massage Pad',
                            'brand' => 'Equilibrium Products',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1549419149-165c71a39649?w=800'],
                            'price' => 450.00,
                            'salePrice' => 337.50,
                            'currency' => '£',
                            'description' => 'Three intensity settings target the horse\'s back muscles to help with warm-up, cool-down, and injury recovery. Battery-operated and easy to use in the stable or on the go. Used by top international riders.',
                            'link' => 'https://example.com/equilibrium-pad-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'EQUILIBRIUM25',
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Improve Flexibility and Back Health', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The massage pad is scientifically proven to increase back movement and promote relaxation, which is crucial for ridden horses. This 25% saving is a great opportunity to invest in your horse\'s well-being.',
                                'Perfect for horses in hard work, or those with known back stiffness. The deal includes the pad, battery, charger, and carry bag.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Dengie Hi-Fi Lite (20kg) - Buy 2, Get 1 FREE',
                'slug' => 'dengie-hi-fi-lite-deal',
                'category' => 'Feed',
                'tag' => 'feed',
                'image' => 'https://images.unsplash.com/photo-1650656518719-58f4ef75d2cd?q=80&w=1074&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'Dengie',
                'product' => 'Hi-Fi Lite Horse Feed (20kg)',
                'price' => 45.00,
                'salePrice' => 30.00,
                'voucherId' => 'DENGIE3FOR2',
                'excerpt' => 'Save £15 with this 3-for-2 deal on Dengie\'s low-calorie, high-fibre feed. Great value for maintaining condition without excess weight.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1558961730-e41c42f0a149?w=2340',
                            'alt' => 'Horse Feed in a bucket',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🌾 Feed Bulk Buy Deal',
                            'productName' => 'Dengie Hi-Fi Lite (3 x 20kg bags)',
                            'brand' => 'Dengie',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1558961730-e41c42f0a149?w=800'],
                            'price' => 45.00,
                            'salePrice' => 30.00,
                            'currency' => '£',
                            'description' => 'A mix of alfalfa and quality soft straw with a low-sugar, low-starch formulation. Ideal for leisure horses and ponies. Purchase two bags and get a third absolutely free.',
                            'link' => 'https://example.com/dengie-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'DENGIE3FOR2',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Best Value for Everyday Feeding', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Bulk buying is the most economical way to manage feed costs, and a 3-for-2 deal is the best discount you\'ll find on a core feed product. This covers your feed needs for weeks.',
                                'Hi-Fi Lite is a highly palatable and dust-free option, perfect for horses prone to respiratory issues. Offer valid for one week only.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Charles Owen Ayr8 Plus Riding Helmet - 20% Off',
                'slug' => 'charles-owen-ayr8-deal',
                'category' => 'Rider Wear',
                'tag' => 'safety',
                'image' => 'https://images.unsplash.com/photo-1763130063371-67f62fd8ea8a?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', // Reusing a general image
                'brand' => 'Charles Owen',
                'product' => 'Ayr8 Plus Helmet',
                'price' => 350.00,
                'salePrice' => 280.00,
                'voucherId' => 'AYR820',
                'excerpt' => 'Save £70 on the prestigious Ayr8 Plus helmet. Approved to three international safety standards. Available in black and navy velvet finishes.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1594917573679-5e7e1f4d9320?w=2340',
                            'alt' => 'Riding Helmet',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🛡️ Premium Safety Deal',
                            'productName' => 'Charles Owen Ayr8 Plus Helmet',
                            'brand' => 'Charles Owen',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1594917573679-5e7e1f4d9320?w=800'],
                            'price' => 350.00,
                            'salePrice' => 280.00,
                            'currency' => '£',
                            'description' => 'The perfect balance of safety and style. Features a removable and washable headband, a slim profile, and a sophisticated ventilation system. Meets PAS015:2011, VG1 01.040 2014-12, and ASTM F1163-15 standards.',
                            'link' => 'https://example.com/charles-owen-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'AYR820',
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Safety at an Unbeatable Price', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Charles Owen is the benchmark for equestrian safety. A 20% discount on the Ayr8 Plus is extremely rare and an opportunity to upgrade your safety equipment without compromising on style or certification.',
                                'The Ayr8 Plus is approved for all UK competition disciplines. Deal available while stocks last on specific colours.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'LeMieux ProKit Lite Grooming Bag - Half Price Sale',
                'slug' => 'lemieux-grooming-bag-deal',
                'category' => 'Stable Supplies',
                'tag' => 'accessories',
                'image' => 'https://images.unsplash.com/photo-1699514886447-559ba61217ff?q=80&w=668&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', // Reusing a general product image
                'brand' => 'LeMieux',
                'product' => 'ProKit Lite Grooming Bag',
                'price' => 40.00,
                'salePrice' => 20.00,
                'voucherId' => 'LEMIEUX50',
                'excerpt' => '50% off the perfect lightweight and durable storage solution for all your grooming essentials. Available in seasonal colours.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1579709849206-382f7c0a9e70?w=2340',
                            'alt' => 'LeMieux Grooming Bag',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => '🧼 Half Price Stable Organiser',
                            'productName' => 'LeMieux ProKit Lite Grooming Bag',
                            'brand' => 'LeMieux',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1579709849206-382f7c0a9e70?w=800'],
                            'price' => 40.00,
                            'salePrice' => 20.00,
                            'currency' => '£',
                            'description' => 'A versatile, lightweight, and durable bag with multiple compartments and a padded handle. Perfect for organizing brushes, sprays, and boots. Fully wipe-clean and weather-resistant.',
                            'link' => 'https://example.com/lemieux-bag-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'voucherId' => 'LEMIEUX50',
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Organise Your Yard', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'LeMieux is known for quality, and this grooming bag is a must-have for the organized yard. At half price, you can afford to buy one for the stable and one for your lorry or tack room.',
                                'The bag features a reinforced base for stability and numerous external pockets for quick access. This is a clearance sale on previous season\'s colours.'
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
                'meta_title' => $dealData['title'] . ' - Horse & Hound',
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

        echo "Adding deals to Horse & Hound homepage.\n";

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
                'title' => '🐴 Horse & Hound\'s Top Deals',
                'subtitle' => 'The best offers on rider wear, horse supplies, and yard equipment',
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