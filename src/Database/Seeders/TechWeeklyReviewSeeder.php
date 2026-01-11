<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\BlockParserService;

class TechWeeklyReviewSeeder extends Seeder
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
        $this->site = Site::find(2);

        if (!$this->site) {
            echo "TechWeekly site not found.\n";
            return;
        }

        $reviewPages = $this->createReviewPages();
        //$this->addReviewSectionToHomepage($reviewPages);
    }

    private function createReviewPages(): array
    {
        $reviews = [
            [
                'title' => 'Sony A95L QD-OLED Review: The Best TV Money Can Buy',
                'slug' => 'sony-a95l-review',
                'product' => 'Sony A95L 65" QD-OLED',
                'rating' => 5.0,
                'category' => 'TVs',
                'tag' => 'sony',
                'image' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The Sony A95L QD-OLED is an absolute marvel in television technology. Combining OLED depth with Quantum Dot brightness, this TV delivers unparalleled contrast and vivid, lifelike colors that truly set a new benchmark for home entertainment.',
                            'During our testing, the TV handled dark scenes with exceptional precision while producing punchy highlights in brighter sequences. Motion handling is smooth and fluid, making fast-action sports and gaming a pure joy.',
                            'Sony\'s interface is intuitive and responsive, and the integration with streaming apps is seamless. While the price is at the premium end, the overall performance, picture quality, and build make it a worthwhile investment for anyone seeking the best viewing experience.',
                            'In conclusion, the A95L is not just another OLED TV — it is a flagship masterpiece that will satisfy both cinephiles and gamers alike.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'iPhone 16 Pro Review: Apple\'s Most Impressive Phone Yet',
                'slug' => 'iphone-16-pro-review',
                'product' => 'iPhone 16 Pro',
                'rating' => 4.8,
                'category' => 'Smartphones',
                'tag' => 'apple',
                'image' => 'https://images.unsplash.com/photo-1592286927505-b7e90a87c39e?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The iPhone 16 Pro sets a new standard for flagship smartphones with the A18 Pro chip delivering remarkable speed and efficiency. App launches, multitasking, and gaming are faster than ever, making it an incredibly responsive device.',
                            'Camera improvements are substantial; low-light shots are cleaner, and the new telephoto lens provides impressive zoom clarity. Video capabilities are also outstanding, with stabilization and color accuracy that rival professional setups.',
                            'The design feels familiar but refined, with durability improvements and subtle aesthetic tweaks. Battery life comfortably handles a full day of heavy usage, and the ecosystem integration with macOS and iPadOS continues to be seamless.',
                            'Overall, the iPhone 16 Pro is a must-have for users who prioritize performance, photography, and ecosystem convenience. It remains expensive, but for those invested in Apple\'s ecosystem, it delivers excellent value.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'ASUS ROG Strix Scar 18 Review: Desktop Power in a Laptop',
                'slug' => 'asus-rog-scar-18-review',
                'product' => 'ASUS ROG Strix Scar 18',
                'rating' => 4.7,
                'category' => 'Laptops',
                'tag' => 'microsoft',
                'image' => 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The ASUS ROG Strix Scar 18 brings desktop-class performance to a portable form factor. Equipped with an RTX 4090 GPU and high-end CPU options, it handles AAA gaming and creative workloads effortlessly.',
                            'The 18-inch display is large, bright, and fast, offering a high refresh rate that makes gaming smooth and immersive. Cooling performance is impressive for a laptop of this power, though it does get warm under extreme load.',
                            'Keyboard and build quality feel premium, and the extensive connectivity options satisfy both gamers and creators who need multiple ports for peripherals. Battery life is understandably limited given the high-end specs, so this machine is best suited for plugged-in sessions.',
                            'In conclusion, the Strix Scar 18 is perfect for those who want a desktop replacement laptop without compromise, offering top-tier performance in a portable package.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Samsung Galaxy Z Fold 6 Review: Redefining Foldables',
                'slug' => 'samsung-galaxy-z-fold-6-review',
                'product' => 'Samsung Galaxy Z Fold 6',
                'rating' => 4.6,
                'category' => 'Smartphones',
                'tag' => 'samsung',
                'image' => 'https://images.unsplash.com/photo-1587829741301-dc798b84b5b0?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The Samsung Galaxy Z Fold 6 continues to push the boundaries of foldable smartphones. Its hinge is more durable, the screen feels seamless when unfolded, and the overall design is more refined than previous generations.',
                            'Multitasking is where this device shines — apps run side by side effortlessly, and the larger unfolded screen makes productivity tasks genuinely practical. The cameras perform admirably, offering crisp photos and versatile shooting modes.',
                            'Battery life has improved slightly but remains average for a device of this size and complexity. Overall, the Galaxy Z Fold 6 is perfect for tech enthusiasts who want a cutting-edge, futuristic smartphone experience without compromise.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Dell XPS 17 Review: Power Meets Elegance',
                'slug' => 'dell-xps-17-review',
                'product' => 'Dell XPS 17',
                'rating' => 4.5,
                'category' => 'Laptops',
                'tag' => 'dell',
                'image' => 'https://images.unsplash.com/photo-1593642532973-d31b6557fa68?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The Dell XPS 17 blends power and elegance into a sleek, professional laptop. Its large 17-inch 4K display is stunning for creative work, offering excellent color accuracy and sharpness.',
                            'Performance is strong across creative and productivity applications, and the thermal design keeps the laptop cool under heavy loads. The keyboard and touchpad are comfortable and precise, suitable for extended typing sessions.',
                            'Connectivity options are abundant, and the build quality feels solid and premium. Battery life is respectable given the size and power, making it a great choice for professionals who need desktop-level performance in a portable form factor.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'LG C3 OLED Review: Cinema in Your Living Room',
                'slug' => 'lg-c3-oled-review',
                'product' => 'LG C3 OLED 55"',
                'rating' => 4.7,
                'category' => 'TVs',
                'tag' => 'lg',
                'image' => 'https://images.unsplash.com/photo-1611095564980-61d499f99569?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The LG C3 OLED delivers a cinematic experience in your living room. Its OLED panel produces perfect blacks and vibrant colors, while the HDR performance adds depth and realism to movies and shows.',
                            'Motion handling is excellent, ensuring fast-paced content like sports and action films remains fluid and clear. Gaming performance is also strong with low input lag and high refresh rate support.',
                            'The interface is intuitive, offering access to popular streaming platforms, and smart home integration is smooth. Overall, the LG C3 OLED is a top-tier option for viewers seeking premium picture quality without compromise.'
                        ]
                    ]
                ]
            ]
        ];


        foreach ($reviews as $review) {
            // 1. Find the page by slug
            $page = Page::where('slug', $review['slug'])->first();

            if (!$page) {
                // Skip if page not found
                continue;
            }

            // 2. Find the first text block for this page
            $block = Block::where('type', 'text')
                ->where('page_id', $page->id)
                ->first();

            if (!$block) {
                // Skip if no text block exists
                continue;
            }

            // 3. Update the block's data array
            if (isset($review['review']['data']['paragraphs'])) {
                $block->data = [
                    'paragraphs' => $review['review']['data']['paragraphs']
                ];
                $block->save();
                echo "Updated block for page: {$review['slug']}\n";
            }
        }

        return [];

    }

    private function createBlocksForPage(int $pageId, array $blocks): void
    {
        foreach ($blocks as $blockData) {
            $this->blockRepository->create([
                'page_id' => $pageId,
                'type' => $blockData['type'],
                'data' => json_encode($blockData['data']),
                'order' => $blockData['order'] ?? 1
            ]);
        }
    }

    private function addReviewSectionToHomepage(array $reviewPages): void
    {
        $homepage = Page::where('slug', 'home')->where('site_id', 2)->first();
        if (!$homepage) return;

        echo "Adding reviews to homepage.\n";

        $reviewItems = [];
        foreach ($reviewPages as $item) {
            $page = $item['page'];
            $data = $item['data'];

            $reviewItems[] = [
                'title' => $page->title,
                'slug' => $page->slug,
                'excerpt' => $data['excerpt'],
                'image' => ['src' => $data['image'], 'alt' => $data['product']],
                'badge' => ['text' => '⭐ ' . $data['rating'] . '/5', 'color' => 'success'],
                'meta' => [
                    'category' => $data['category'],
                    'readTime' => '10 min read'
                ]
            ];
        }

        $reviewBlock = [
            'type' => 'page_grid',
            'data' => [
                'title' => 'Latest Reviews',
                'subtitle' => 'In-depth expert reviews of the latest tech products',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'pages' => $reviewItems
            ]
        ];

        // Get current max order
        $maxOrder = $homepage->blocks()->max('order') ?? 0;

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => $reviewBlock['type'],
            'data' => json_encode($reviewBlock['data']),
            'order' => $maxOrder + 1
        ]);
    }
}