<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Category;
use App\Models\Page;
use App\Models\PageCategory;
use App\Models\PageGrid;
use App\Models\PageTag;
use App\Models\Site;
use App\Models\Tag;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\TagRepository;

class TechWeeklySeeder extends Seeder
{
    private $pageRepository;
    private $blockRepository;
    private $tagRepository;
    private $categoryRepository;
    private $site;

    public function __construct()
    {
        $this->pageRepository = new PageRepository();
        $this->blockRepository = new BlockRepository();
        $this->tagRepository = new TagRepository();
        $this->categoryRepository = new CategoryRepository();

        parent::__construct();
    }

    public function run(): void
    {
        $this->site = Site::find(2);

        if (!$this->site) {
            echo "TechWeekly site not found.\n";
            return;
        }

        $this->createTaxonomies();
        $pages = $this->createPages();
        $this->createArticleGrid($pages);
    }

    private function createTaxonomies(): void
    {
        $categories = ['TVs', 'Smartphones', 'Laptops', 'Audio', 'Gaming'];
        $tags = ['tvs' => ['Sony', 'LG', 'Panasonic', 'TCL'], 'smartphones' => ['Nokia', 'Apple', 'Samsung', 'Google'], 'laptops' => ['Microsoft']];


        foreach ($categories as $categoryName) {
            $category = Category::create([
                'name' => $categoryName,
                'slug' => strtolower(str_replace(' ', '-', $categoryName)),
                'site_id' => 2
            ]);

            $currentTags = $tags[strtolower($categoryName)];

            if (empty($currentTags)) {
                continue;
            }

            foreach ($currentTags as $currentTag) {
                Tag::create([
                    'name' => $currentTag,
                    'category_id' => $category->id,
                    'slug' => strtolower($currentTag),
                    'site_id' => 2
                ]);
            }
        }


    }

    private function createPages(): array
    {
        $pages = [];

        $pages[] = $this->createBestTVsPage();
        $pages[] = $this->createSmartphoneGuide();
        $pages[] = $this->createGamingLaptopsPage();
        $pages[] = $this->createWirelessEarbudsPage();
        $pages[] = $this->createSmartHomeGuide();

        $this->createTvBrand();
        $this->createGamingLaptopsBrandPage();
        $this->createSmartphoneBrandGuide();

        return $pages;
    }

    private function createBestTVsPage(): Page
    {
        $page = Page::create([
            'title' => 'Best TVs of 2025: Ultimate Buying Guide',
            'slug' => 'best-tvs-2025',
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => 'Best TVs of 2025 - Expert Reviews & Buying Guide',
            'meta_description' => 'Find the perfect TV with our comprehensive 2025 buying guide. Expert reviews of Sony, Samsung, LG and more.',
            'site_id' => 2,
        ]);

        // Assign taxonomies
        $tvTerm = Category::where('slug', 'tvs')->where('site_id', 2)->first();
        if ($tvTerm) {
            PageCategory::create([
                'page_id' => $page->id,
                'category_id' => $tvTerm->id
            ]);
        }

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Best TVs of 2025',
                    'subtitle' => 'Expert reviews and buying advice for the top television models',
                    'ctaText' => 'See Top Picks',
                    'ctaUrl' => '#top-picks',
                    'backgroundImage' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=2000'
                ]
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The TV market in 2025 offers incredible options across all price ranges. Whether you\'re looking for cutting-edge OLED technology, budget-friendly 4K, or the latest gaming features, there\'s never been a better time to upgrade.',
                        'We\'ve tested dozens of models to bring you this comprehensive guide covering picture quality, smart features, gaming performance, and value for money.'
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Top Pick: Sony A95L OLED', 'level' => 2]
            ],
            [
                'type' => 'product',
                'data' => [
                    'name' => 'Sony A95L 65" QD-OLED TV',
                    'brand' => 'Sony',
                    'productName' => 'A95L 65" QD-OLED',
                    'price' => 2799.00,
                    'currency' => '£',
                    'image' => ['src' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=800'],
                    'description' => 'The Sony A95L combines QD-OLED technology with Sony\'s excellent processing for stunning picture quality. Perfect for movies and gaming.',
                    'link' => 'https://example.com/sony-a95l',
                    'linkText' => 'Check Price',
                    'showReviewPanel' => true,
                    'review' => [
                        'rating' => 4.9,
                        'pros' => [
                            'Exceptional picture quality with QD-OLED panel',
                            'Excellent motion handling for sports and gaming',
                            'Google TV with comprehensive app support',
                            'HDMI 2.1 with 4K 120Hz support'
                        ],
                        'cons' => [
                            'Premium price point',
                            'Slight risk of burn-in with static content'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Best Value: Samsung QN90D', 'level' => 2]
            ],
            [
                'type' => 'product',
                'data' => [
                    'name' => 'Samsung QN90D 55" Neo QLED',
                    'brand' => 'Samsung',
                    'productName' => 'QN90D 55" Neo QLED',
                    'price' => 1299.00,
                    'currency' => '£',
                    'image' => ['src' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=800'],
                    'description' => 'Samsung\'s QN90D offers mini-LED backlighting and quantum dots for excellent brightness and color at a competitive price.',
                    'link' => 'https://example.com/samsung-qn90d',
                    'linkText' => 'Check Price',
                    'showReviewPanel' => true,
                    'review' => [
                        'rating' => 4.7,
                        'pros' => [
                            'Excellent brightness for bright rooms',
                            'Great gaming features with low input lag',
                            'Sleek design with slim bezels',
                            'Good smart TV platform'
                        ],
                        'cons' => [
                            'Viewing angles not as wide as OLED',
                            'Black levels not quite OLED quality'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'product-comparison',
                'data' => [
                    'title' => 'OLED vs QLED: Which is Right for You?',
                    'productA' => 'OLED (Sony A95L)',
                    'productB' => 'QLED (Samsung QN90D)',
                    'comparisons' => [
                        [
                            'subtitle' => 'Picture Quality',
                            'items' => [
                                ['value' => 'Perfect blacks, infinite contrast'],
                                ['value' => 'Excellent brightness, great contrast']
                            ]
                        ],
                        [
                            'subtitle' => 'Price',
                            'items' => [
                                ['value' => '£2,799'],
                                ['value' => '£1,299']
                            ]
                        ],
                        [
                            'subtitle' => 'Best For',
                            'items' => [
                                ['value' => 'Dark rooms, movie watching'],
                                ['value' => 'Bright rooms, gaming, sports']
                            ]
                        ],
                        [
                            'subtitle' => 'Burn-in Risk',
                            'items' => [
                                ['value' => 'Low but present'],
                                ['value' => 'None']
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'More Top TV Picks by Brand', 'level' => 2]
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'layout' => 'grid',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'pages' => [
                        [
                            'title' => 'LG C4 OLED Review',
                            'slug' => 'lg-c4-oled-review',
                            'excerpt' => 'LG\'s C4 OLED delivers excellent picture quality at a more accessible price point.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=800', 'alt' => 'LG C4 OLED'],
                            'badge' => ['text' => 'Editor\'s Choice', 'color' => 'success']
                        ],
                        [
                            'title' => 'TCL QM8 Mini-LED Review',
                            'slug' => 'tcl-qm8-review',
                            'excerpt' => 'Incredible value with mini-LED technology and great gaming features.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=800', 'alt' => 'TCL QM8'],
                            'badge' => ['text' => 'Best Budget', 'color' => 'warning']
                        ],
                        [
                            'title' => 'Panasonic MZ2000 OLED Review',
                            'slug' => 'panasonic-mz2000-review',
                            'excerpt' => 'Master OLED panel with professional calibration for videophiles.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=800', 'alt' => 'Panasonic MZ2000'],
                            'badge' => ['text' => 'Premium', 'color' => 'primary']
                        ]
                    ]
                ]
            ],
            [
                'type' => 'table',
                'data' => [
                    'hasHeader' => true,
                    'rows' => [
                        ['Model', 'Technology', 'Size Options', 'Price', 'Rating'],
                        ['Sony A95L', 'QD-OLED', '55", 65", 77"', '£2,799+', '4.9/5'],
                        ['Samsung QN90D', 'Neo QLED', '55", 65", 75", 85"', '£1,299+', '4.7/5'],
                        ['LG C4', 'OLED', '42", 48", 55", 65", 77", 83"', '£1,499+', '4.8/5'],
                        ['TCL QM8', 'Mini-LED', '65", 75", 85"', '£999+', '4.5/5'],
                        ['Panasonic MZ2000', 'Master OLED', '55", 65", 77"', '£3,499+', '4.9/5']
                    ]
                ]
            ],
            [
                'type' => 'info',
                'data' => [
                    'infoType' => 'tip',
                    'description' => 'When shopping for a TV, consider your room\'s lighting conditions. OLED TVs excel in dark rooms, while QLED/Mini-LED TVs perform better in bright spaces.'
                ]
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'The 2025 TV market offers something for everyone. OLED technology has matured beautifully, while mini-LED has brought excellent performance to lower price points.',
                    'attribution' => 'TechWeekly Editorial Team'
                ]
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);

        // Assign Sony brand taxonomy
        $sonyTerm = Tag::where('slug', 'sony')->where('site_id', 2)->first();
        if ($sonyTerm) {
            PageTag::create([
                'tag_id' => $sonyTerm->id,
                'page_id' => $page->id,
                'category_id' => $tvTerm?->id ?? null
            ]);
        }

        return $page;
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

    private function createSmartphoneGuide(): Page
    {
        $page = Page::create([
            'title' => 'Best Smartphones 2025: Complete Buying Guide',
            'slug' => 'best-smartphones-2025',
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => 'Best Smartphones 2025 - Expert Reviews',
            'meta_description' => 'Find your perfect smartphone with our comprehensive 2025 buying guide covering iPhone, Samsung, Google Pixel and more.',
            'site_id' => 2,
        ]);

        $tvTerm = Category::where('slug', 'smartphones')->where('site_id', 2)->first();
        if ($tvTerm) {
            PageCategory::create([
                'page_id' => $page->id,
                'category_id' => $tvTerm->id
            ]);
        }

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Best Smartphones 2025',
                    'subtitle' => 'Expert reviews of the latest iPhone, Android, and flagship devices',
                    'ctaText' => 'See Top Picks',
                    'ctaUrl' => '#recommendations',
                    'backgroundImage' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=2000'
                ]
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The smartphone market in 2025 is more competitive than ever. From Apple\'s latest iPhone to innovative Android flagships, there\'s an incredible device for every need and budget.',
                        'We\'ve tested all the major releases to help you find the perfect smartphone, whether you prioritize camera quality, battery life, performance, or value.'
                    ]
                ]
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Smartphone Market 2025',
                    'stats' => [
                        ['number' => '50+', 'label' => 'Devices Tested', 'icon' => '📱'],
                        ['number' => '1,500+', 'label' => 'Photos Taken', 'icon' => '📸'],
                        ['number' => '200hrs', 'label' => 'Battery Testing', 'icon' => '🔋'],
                        ['number' => '100+', 'label' => 'Benchmark Tests', 'icon' => '⚡']
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Best Overall: iPhone 16 Pro', 'level' => 2]
            ],
            [
                'type' => 'image',
                'data' => [
                    'src' => 'https://images.unsplash.com/photo-1592286927505-b7e90a87c39e?w=1200',
                    'alt' => 'iPhone 16 Pro',
                    'caption' => 'The iPhone 16 Pro sets new standards for smartphone photography and performance',
                    'layout' => 'full'
                ]
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'A18 Pro chip delivers exceptional performance',
                        'New 5x telephoto camera captures stunning detail',
                        'Titanium design feels premium and durable',
                        'iOS 18 brings powerful AI features',
                        'Best-in-class video recording capabilities'
                    ]
                ]
            ],
            [
                'type' => 'buying-guide',
                'data' => [
                    'title' => 'iPhone 16 Pro',
                    'subtitle' => 'Apple\'s flagship delivers on all fronts',
                    'image' => 'https://images.unsplash.com/photo-1592286927505-b7e90a87c39e?w=800',
                    'url' => 'https://example.com/iphone-16-pro',
                    'specs' => [
                        ['text' => 'Display', 'value' => '6.3" OLED ProMotion 120Hz'],
                        ['text' => 'Processor', 'value' => 'A18 Pro'],
                        ['text' => 'Cameras', 'value' => '48MP main, 48MP ultra, 12MP 5x tele'],
                        ['text' => 'Battery', 'value' => 'All-day (29 hours video)'],
                        ['text' => 'Price', 'value' => 'From £999']
                    ],
                    'showReviewPanel' => true,
                    'pros' => [
                        'Outstanding camera system',
                        'Blazing fast performance',
                        'Premium build quality',
                        'Long software support'
                    ],
                    'cons' => [
                        'Expensive',
                        'Limited customization vs Android',
                        'USB-C still slower than competitors'
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Best Android: Samsung Galaxy S25 Ultra', 'level' => 2]
            ],
            [
                'type' => 'product',
                'data' => [
                    'name' => 'Samsung Galaxy S25 Ultra',
                    'brand' => 'Samsung',
                    'productName' => 'Galaxy S25 Ultra',
                    'price' => 1199.00,
                    'currency' => '£',
                    'image' => ['src' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=800'],
                    'description' => 'Samsung\'s ultimate flagship with S Pen, 200MP camera, and AI-powered features.',
                    'link' => 'https://example.com/galaxy-s25-ultra',
                    'linkText' => 'Check Price',
                    'showReviewPanel' => true,
                    'review' => [
                        'rating' => 4.8,
                        'pros' => [
                            'Incredible 200MP camera with AI enhancements',
                            'Built-in S Pen for productivity',
                            'Gorgeous 6.9" display',
                            'Exceptional battery life'
                        ],
                        'cons' => [
                            'Very large and heavy',
                            'Expensive',
                            'Some AI features require internet'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'gallery',
                'data' => [
                    'layout' => 'grid',
                    'slides' => [
                        [
                            'title' => 'Camera Samples',
                            'description' => '200MP sensor captures incredible detail',
                            'image' => 'https://images.unsplash.com/photo-1506710507565-203b9f24669b?w=800',
                            'alt' => 'Galaxy S25 Ultra camera sample'
                        ],
                        [
                            'title' => 'Night Mode',
                            'description' => 'Low-light photography is exceptional',
                            'image' => 'https://images.unsplash.com/photo-1514539079130-25950c84af65?w=800',
                            'alt' => 'Night mode photo'
                        ],
                        [
                            'title' => 'Portrait Mode',
                            'description' => 'Beautiful bokeh and edge detection',
                            'image' => 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=800',
                            'alt' => 'Portrait mode example'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Best Value: Google Pixel 9', 'level' => 2]
            ],
            [
                'type' => 'deal',
                'data' => [
                    'title' => 'Limited Time Offer',
                    'productName' => 'Google Pixel 9',
                    'brand' => 'Google',
                    'price' => 799.00,
                    'salePrice' => 649.00,
                    'currency' => '£',
                    'image' => ['src' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=800'],
                    'description' => 'Google\'s Pixel 9 brings flagship AI features and camera quality at a great price. Save £150 this week only!',
                    'link' => 'https://example.com/pixel-9-deal',
                    'showDealButton' => true,
                    'voucherId' => 'PIXEL150'
                ]
            ],
            [
                'type' => 'testimonial',
                'data' => [
                    'layout' => 'grid',
                    'testimonials' => [
                        [
                            'text' => 'The Pixel 9\'s camera is absolutely incredible. The AI features make every photo look professional.',
                            'author' => 'Sarah Johnson',
                            'role' => 'Tech Enthusiast',
                            'rating' => 5
                        ],
                        [
                            'text' => 'Best Android phone I\'ve ever used. Clean software, great battery life, and the price is unbeatable.',
                            'author' => 'Michael Chen',
                            'role' => 'Software Developer',
                            'rating' => 5
                        ]
                    ]
                ]
            ],
            [
                'type' => 'note',
                'data' => [
                    'title' => 'Buying Advice',
                    'paragraphs' => [
                        'Consider your ecosystem: If you use a Mac, iPad, or Apple Watch, the iPhone integrates seamlessly. For customization and value, Android offers more options.',
                        'Don\'t overlook mid-range phones: The Pixel 9 and Galaxy S24 FE offer flagship features at significantly lower prices.'
                    ],
                    'alignment' => 'fullscreen'
                ]
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);

        return $page;
    }

    private function createGamingLaptopsPage(): Page
    {
        $page = Page::create([
            'title' => 'Best Gaming Laptops 2025: Power Meets Portability',
            'slug' => 'best-gaming-laptops-2025',
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => 'Best Gaming Laptops 2025 - Expert Reviews',
            'meta_description' => 'Find the perfect gaming laptop with our comprehensive guide covering performance, display quality, and value.',
            'site_id' => 2,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Best Gaming Laptops 2025',
                    'subtitle' => 'High-performance machines for serious gamers',
                    'ctaText' => 'Explore Reviews',
                    'ctaUrl' => '#reviews',
                    'backgroundImage' => 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?w=2000'
                ]
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Gaming laptops have evolved tremendously. Modern machines deliver desktop-class performance with RTX 40-series GPUs and high-refresh displays while maintaining reasonable battery life.',
                        'Whether you need a portable powerhouse for esports or a desktop replacement for AAA titles, we\'ve tested the best options at every price point.'
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Top Performance: ASUS ROG Strix Scar 18', 'level' => 2]
            ],
            [
                'type' => 'buying-guide',
                'data' => [
                    'title' => 'ASUS ROG Strix Scar 18',
                    'subtitle' => 'Absolute gaming performance champion',
                    'image' => 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?w=800',
                    'url' => 'https://example.com/rog-scar-18',
                    'specs' => [
                        ['text' => 'CPU', 'value' => 'Intel Core i9-14900HX'],
                        ['text' => 'GPU', 'value' => 'NVIDIA RTX 4090 (175W)'],
                        ['text' => 'Display', 'value' => '18" QHD+ 240Hz'],
                        ['text' => 'RAM', 'value' => '32GB DDR5'],
                        ['text' => 'Storage', 'value' => '2TB PCIe 4.0 SSD']
                    ],
                    'showReviewPanel' => true,
                    'pros' => [
                        'Unmatched gaming performance',
                        'Beautiful 240Hz display',
                        'Excellent cooling system',
                        'Per-key RGB keyboard'
                    ],
                    'cons' => [
                        'Very expensive (£3,999)',
                        'Heavy at 3.1kg',
                        'Battery life under 2 hours gaming'
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Best Value: Lenovo Legion Pro 5', 'level' => 2]
            ],
            [
                'type' => 'product',
                'data' => [
                    'name' => 'Lenovo Legion Pro 5',
                    'brand' => 'Lenovo',
                    'productName' => 'Legion Pro 5 RTX 4070',
                    'price' => 1599.00,
                    'currency' => '£',
                    'image' => ['src' => 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?w=800'],
                    'description' => 'Incredible value with RTX 4070, excellent display, and solid build quality.',
                    'link' => 'https://example.com/legion-pro-5',
                    'linkText' => 'Check Price',
                    'showReviewPanel' => true,
                    'review' => [
                        'rating' => 4.7,
                        'pros' => [
                            'Excellent price-to-performance ratio',
                            '165Hz QHD display',
                            'Good battery life for gaming laptop',
                            'Understated professional design'
                        ],
                        'cons' => [
                            'Webcam is only 720p',
                            'Trackpad could be larger',
                            'No per-key RGB lighting'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'table',
                'data' => [
                    'hasHeader' => true,
                    'rows' => [
                        ['Model', 'GPU', 'Display', 'Weight', 'Price'],
                        ['ASUS ROG Scar 18', 'RTX 4090', '18" QHD+ 240Hz', '3.1kg', '£3,999'],
                        ['Razer Blade 16', 'RTX 4080', '16" QHD+ 240Hz', '2.4kg', '£3,299'],
                        ['Lenovo Legion Pro 5', 'RTX 4070', '16" QHD 165Hz', '2.5kg', '£1,599'],
                        ['ASUS TUF A16', 'RTX 4060', '16" FHD 165Hz', '2.2kg', '£1,199'],
                        ['MSI Cyborg 15', 'RTX 4050', '15.6" FHD 144Hz', '1.9kg', '£899']
                    ]
                ]
            ],
            [
                'type' => 'info',
                'data' => [
                    'infoType' => 'tip',
                    'description' => 'For competitive gaming, prioritize high refresh rates (240Hz+). For AAA single-player games, focus on GPU power and a QHD or 4K display.'
                ]
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'The gap between gaming laptops and desktops has never been smaller. Today\'s laptops can handle any game at high settings.',
                    'attribution' => 'TechWeekly Gaming Editor'
                ]
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);

        $laptopTerm = Category::where('slug', 'laptops')->where('site_id', 2)->first();

        if ($laptopTerm) {
            PageCategory::create([
                'page_id' => $page->id,
                'category_id' => $laptopTerm->id
            ]);
        }

        return $page;
    }

    private function createWirelessEarbudsPage(): Page
    {
        $page = Page::create([
            'title' => 'Best Wireless Earbuds 2025: Sound Quality & ANC Guide',
            'slug' => 'best-wireless-earbuds-2025',
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => 'Best Wireless Earbuds 2025 - Expert Audio Reviews',
            'meta_description' => 'Find perfect wireless earbuds with our comprehensive guide covering AirPods, Sony, Bose and more.',
            'site_id' => 2,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Best Wireless Earbuds 2025',
                    'subtitle' => 'Expert reviews of the top earbuds for sound quality, ANC, and value',
                    'ctaText' => 'See Top Picks',
                    'ctaUrl' => '#recommendations',
                    'backgroundImage' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=2000'
                ]
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The wireless earbuds market has exploded with incredible options. From Apple\'s AirPods to Sony\'s noise-cancelling champions, there\'s never been more choice.',
                        'We\'ve tested over 50 pairs to bring you this comprehensive guide covering sound quality, active noise cancellation, battery life, and fit comfort.'
                    ]
                ]
            ],
            [
                'type' => 'award',
                'data' => [
                    'subcategory' => 'Best Overall',
                    'productName' => 'Sony WF-1000XM5',
                    'image' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=800',
                    'winner' => true,
                    'rating' => 4.9,
                    'strapline' => 'Class-leading ANC and exceptional sound quality',
                    'caption' => 'Sony\'s WF-1000XM5 sets the standard for premium wireless earbuds'
                ]
            ],
            [
                'type' => 'product',
                'data' => [
                    'name' => 'Sony WF-1000XM5',
                    'brand' => 'Sony',
                    'productName' => 'WF-1000XM5',
                    'price' => 259.00,
                    'currency' => '£',
                    'image' => ['src' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=800'],
                    'description' => 'Sony\'s flagship earbuds deliver best-in-class ANC, incredible sound quality, and all-day comfort.',
                    'link' => 'https://example.com/sony-wf1000xm5',
                    'linkText' => 'Check Price',
                    'showReviewPanel' => true,
                    'review' => [
                        'rating' => 4.9,
                        'pros' => [
                            'Industry-leading noise cancellation',
                            'Exceptional sound quality with LDAC support',
                            'Comfortable fit for extended wear',
                            '8 hours battery (24 with case)'
                        ],
                        'cons' => [
                            'Expensive',
                            'No wireless charging on base model',
                            'Case is slightly bulky'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Best for Apple Users: AirPods Pro 2', 'level' => 2]
            ],
            [
                'type' => 'image',
                'data' => [
                    'src' => 'https://images.unsplash.com/photo-1606841837239-c5a1a4a07af7?w=1200',
                    'alt' => 'AirPods Pro 2',
                    'caption' => 'Perfect integration with Apple devices and excellent spatial audio',
                    'layout' => 'full'
                ]
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Seamless Apple ecosystem integration',
                        'Excellent spatial audio with head tracking',
                        'Improved ANC over original Pro',
                        'Adaptive Transparency mode is brilliant',
                        'Find My integration with speaker in case'
                    ]
                ]
            ],
            [
                'type' => 'product-comparison',
                'data' => [
                    'title' => 'Sony WF-1000XM5 vs AirPods Pro 2',
                    'productA' => 'Sony WF-1000XM5',
                    'productB' => 'AirPods Pro 2',
                    'comparisons' => [
                        [
                            'subtitle' => 'Sound Quality',
                            'items' => [
                                ['value' => 'Audiophile-grade with LDAC'],
                                ['value' => 'Excellent, optimized for Apple']
                            ]
                        ],
                        [
                            'subtitle' => 'ANC Performance',
                            'items' => [
                                ['value' => 'Best in class'],
                                ['value' => 'Excellent']
                            ]
                        ],
                        [
                            'subtitle' => 'Battery Life',
                            'items' => [
                                ['value' => '8hrs (24 with case)'],
                                ['value' => '6hrs (30 with case)']
                            ]
                        ],
                        [
                            'subtitle' => 'Best For',
                            'items' => [
                                ['value' => 'Audiophiles, Android users'],
                                ['value' => 'Apple ecosystem users']
                            ]
                        ],
                        [
                            'subtitle' => 'Price',
                            'items' => [
                                ['value' => '£259'],
                                ['value' => '£229']
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Best Budget: Nothing Ear (2)', 'level' => 2]
            ],
            [
                'type' => 'deal',
                'data' => [
                    'title' => 'Amazing Value',
                    'productName' => 'Nothing Ear (2)',
                    'brand' => 'Nothing',
                    'price' => 149.00,
                    'salePrice' => 99.00,
                    'currency' => '£',
                    'image' => ['src' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=800'],
                    'description' => 'Transparent design, great sound, and solid ANC at an unbeatable price. Limited time offer!',
                    'link' => 'https://example.com/nothing-ear-2',
                    'showDealButton' => true
                ]
            ],
            [
                'type' => 'gallery',
                'data' => [
                    'layout' => 'carousel',
                    'slides' => [
                        [
                            'title' => 'Transparent Design',
                            'description' => 'Unique see-through aesthetic',
                            'image' => 'https://images.unsplash.com/photo-1606841837239-c5a1a4a07af7?w=800',
                            'alt' => 'Nothing Ear 2 design'
                        ],
                        [
                            'title' => 'Comfortable Fit',
                            'description' => 'Three sizes of silicone tips included',
                            'image' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=800',
                            'alt' => 'Earbuds fit'
                        ],
                        [
                            'title' => 'Compact Case',
                            'description' => 'Pocket-friendly charging case',
                            'image' => 'https://images.unsplash.com/photo-1606841837239-c5a1a4a07af7?w=800',
                            'alt' => 'Charging case'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'table',
                'data' => [
                    'hasHeader' => true,
                    'rows' => [
                        ['Model', 'ANC', 'Battery', 'Codec', 'Price'],
                        ['Sony WF-1000XM5', 'Excellent', '8hrs', 'LDAC/AAC', '£259'],
                        ['AirPods Pro 2', 'Excellent', '6hrs', 'AAC', '£229'],
                        ['Bose QuietComfort Ultra', 'Excellent', '6hrs', 'aptX Adaptive', '£299'],
                        ['Nothing Ear (2)', 'Good', '6.3hrs', 'LDAC/AAC', '£99'],
                        ['Samsung Galaxy Buds2 Pro', 'Very Good', '5hrs', 'AAC', '£179']
                    ]
                ]
            ],
            [
                'type' => 'note',
                'data' => [
                    'title' => 'Fit Testing is Critical',
                    'paragraphs' => [
                        'The best earbuds in the world won\'t sound good if they don\'t fit properly. Always test different ear tip sizes.',
                        'Many retailers offer generous return policies - take advantage to ensure perfect fit and comfort for your ears.'
                    ],
                    'alignment' => 'fullscreen'
                ]
            ],
            [
                'type' => 'testimonial',
                'data' => [
                    'layout' => 'carousel',
                    'testimonials' => [
                        [
                            'text' => 'The Sony WF-1000XM5 completely changed my commute. The noise cancellation is incredible and they\'re so comfortable I forget I\'m wearing them.',
                            'author' => 'James Wilson',
                            'role' => 'Daily Commuter',
                            'rating' => 5
                        ],
                        [
                            'text' => 'As an iPhone user, AirPods Pro 2 are perfect. The integration is seamless and spatial audio is mind-blowing for movies.',
                            'author' => 'Emma Thompson',
                            'role' => 'Content Creator',
                            'rating' => 5
                        ],
                        [
                            'text' => 'Nothing Ear (2) punch way above their price. For £99, you get features that compete with £200+ earbuds.',
                            'author' => 'David Chen',
                            'role' => 'Budget Shopper',
                            'rating' => 5
                        ]
                    ]
                ]
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);

        $audioTerm = Category::where('slug', 'audio')->where('site_id', 2)->first();
        if ($audioTerm) {
            PageCategory::create([
                'page_id' => $page->id,
                'category_id' => $audioTerm->id
            ]);
        }

        return $page;
    }

    private function createSmartHomeGuide(): Page
    {
        $page = Page::create([
            'title' => 'Smart Home Guide 2025: Complete Setup & Device Reviews',
            'slug' => 'smart-home-guide-2025',
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => 'Smart Home Guide 2025 - Setup & Device Reviews',
            'meta_description' => 'Build your perfect smart home with our comprehensive guide covering hubs, lights, cameras, thermostats and more.',
            'site_id' => 2,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Complete Smart Home Guide 2025',
                    'subtitle' => 'Transform your house into an intelligent home',
                    'ctaText' => 'Get Started',
                    'ctaUrl' => '#getting-started',
                    'backgroundImage' => 'https://images.unsplash.com/photo-1558002038-1055907df827?w=2000'
                ]
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Smart home technology has matured into reliable, affordable systems that genuinely improve daily life. From voice-controlled lighting to intelligent security, the possibilities are endless.',
                        'This guide walks you through building a smart home from scratch, covering essential devices, platform choices, and integration tips.'
                    ]
                ]
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Smart Home Market 2025',
                    'stats' => [
                        ['number' => '75%', 'label' => 'Homes with Smart Devices', 'icon' => '🏠'],
                        ['number' => '£2.4B', 'label' => 'UK Market Value', 'icon' => '💷'],
                        ['number' => '8.2', 'label' => 'Avg Devices per Home', 'icon' => '📱'],
                        ['number' => '30%', 'label' => 'Energy Savings', 'icon' => '⚡']
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Step 1: Choose Your Ecosystem', 'level' => 2]
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Before buying devices, decide on your primary ecosystem. The three major players are Amazon Alexa, Google Home, and Apple HomeKit. Each has strengths and device compatibility varies.',
                        'Most people choose based on their existing tech: iPhone users lean toward HomeKit, Android users prefer Google Home, and Alexa works well for everyone with the widest device support.'
                    ]
                ]
            ],
            [
                'type' => 'product-comparison',
                'data' => [
                    'title' => 'Smart Home Ecosystems Compared',
                    'productA' => 'Amazon Alexa',
                    'productB' => 'Google Home',
                    'comparisons' => [
                        [
                            'subtitle' => 'Device Support',
                            'items' => [
                                ['value' => 'Widest compatibility'],
                                ['value' => 'Very wide, growing fast']
                            ]
                        ],
                        [
                            'subtitle' => 'Voice Assistant',
                            'items' => [
                                ['value' => 'Good, improving'],
                                ['value' => 'Excellent, natural']
                            ]
                        ],
                        [
                            'subtitle' => 'Privacy',
                            'items' => [
                                ['value' => 'Concerns exist'],
                                ['value' => 'Better than Alexa']
                            ]
                        ],
                        [
                            'subtitle' => 'Price',
                            'items' => [
                                ['value' => 'Hubs from £24.99'],
                                ['value' => 'Hubs from £29.99']
                            ]
                        ],
                        [
                            'subtitle' => 'Best For',
                            'items' => [
                                ['value' => 'Maximum device choice'],
                                ['value' => 'Android users, AI quality']
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Step 2: Essential Smart Home Devices', 'level' => 2]
            ],
            [
                'type' => 'section',
                'data' => [
                    'title' => 'Smart Lighting',
                    'headingType' => 'h3',
                    'navigationText' => 'Smart Lighting'
                ]
            ],
            [
                'type' => 'product',
                'data' => [
                    'name' => 'Philips Hue White & Color Ambiance Starter Kit',
                    'brand' => 'Philips',
                    'productName' => 'Hue Starter Kit (4 Bulbs + Bridge)',
                    'price' => 179.99,
                    'currency' => '£',
                    'image' => ['src' => 'https://images.unsplash.com/photo-1558002038-1055907df827?w=800'],
                    'description' => 'The gold standard in smart lighting. 16 million colors, reliable performance, and works with all major ecosystems.',
                    'link' => 'https://example.com/hue-starter',
                    'linkText' => 'Check Price',
                    'showReviewPanel' => true,
                    'review' => [
                        'rating' => 4.8,
                        'pros' => [
                            'Excellent color accuracy',
                            'Rock-solid reliability',
                            'Works with Alexa, Google, HomeKit',
                            'Extensive product range'
                        ],
                        'cons' => [
                            'Requires Hue Bridge',
                            'More expensive than alternatives',
                            'Bridge limited to 50 lights'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'section',
                'data' => [
                    'title' => 'Smart Security',
                    'headingType' => 'h3',
                    'navigationText' => 'Smart Security'
                ]
            ],
            [
                'type' => 'image',
                'data' => [
                    'src' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=1200',
                    'alt' => 'Smart security camera',
                    'caption' => 'Modern smart cameras offer 4K video, AI detection, and cloud storage',
                    'layout' => 'full'
                ]
            ],
            [
                'type' => 'buying-guide',
                'data' => [
                    'title' => 'Ring Video Doorbell Pro 2',
                    'subtitle' => 'Best smart doorbell with head-to-toe HD+ video',
                    'image' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800',
                    'url' => 'https://example.com/ring-doorbell-pro2',
                    'specs' => [
                        ['text' => 'Video', 'value' => '1536p HD+'],
                        ['text' => 'Field of View', 'value' => '150° horizontal'],
                        ['text' => 'Power', 'value' => 'Hardwired (existing doorbell)'],
                        ['text' => 'Storage', 'value' => 'Ring Protect subscription'],
                        ['text' => 'Features', 'value' => '3D Motion Detection, Pre-Roll']
                    ],
                    'showReviewPanel' => true,
                    'pros' => [
                        'Head-to-toe HD+ video quality',
                        '3D motion detection is accurate',
                        'Pre-roll shows 4 seconds before motion',
                        'Works with Alexa devices'
                    ],
                    'cons' => [
                        'Requires subscription for recordings',
                        'Hardwired installation needed',
                        'No HomeKit support'
                    ]
                ]
            ],
            [
                'type' => 'section',
                'data' => [
                    'title' => 'Smart Heating',
                    'headingType' => 'h3',
                    'navigationText' => 'Smart Heating'
                ]
            ],
            [
                'type' => 'product',
                'data' => [
                    'name' => 'Google Nest Learning Thermostat',
                    'brand' => 'Google',
                    'productName' => 'Nest Learning Thermostat (3rd Gen)',
                    'price' => 219.00,
                    'currency' => '£',
                    'image' => ['src' => 'https://images.unsplash.com/photo-1545259742-24e8470d0e94?w=800'],
                    'description' => 'Learns your schedule and preferences to optimize comfort and energy savings automatically.',
                    'link' => 'https://example.com/nest-thermostat',
                    'linkText' => 'Check Price',
                    'showReviewPanel' => true,
                    'review' => [
                        'rating' => 4.7,
                        'pros' => [
                            'Learns your schedule over time',
                            'Beautiful design',
                            'Excellent energy reports',
                            'Works with most heating systems'
                        ],
                        'cons' => [
                            'Professional installation recommended',
                            'Expensive',
                            'Learning period takes 1-2 weeks'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Complete Smart Home Starter Package', 'level' => 2]
            ],
            [
                'type' => 'table',
                'data' => [
                    'hasHeader' => true,
                    'rows' => [
                        ['Device Type', 'Recommended Product', 'Price', 'Priority'],
                        ['Smart Hub', 'Amazon Echo (4th Gen)', '£89.99', 'Essential'],
                        ['Smart Lights', 'Philips Hue Starter Kit', '£179.99', 'High'],
                        ['Smart Doorbell', 'Ring Video Doorbell Pro 2', '£229.99', 'High'],
                        ['Smart Thermostat', 'Nest Learning Thermostat', '£219.00', 'Medium'],
                        ['Smart Plug', 'TP-Link Kasa 4-Pack', '£39.99', 'High'],
                        ['Smart Camera', 'Arlo Pro 4', '£179.99', 'Medium'],
                        ['Smart Lock', 'August Wi-Fi Smart Lock', '£229.99', 'Medium']
                    ]
                ]
            ],
            [
                'type' => 'info',
                'data' => [
                    'infoType' => 'tip',
                    'description' => 'Start small! Begin with smart lighting and a hub, then expand gradually. This lets you learn the system and identify what works best for your lifestyle before investing heavily.'
                ]
            ],
            [
                'type' => 'note',
                'data' => [
                    'title' => 'Security & Privacy Considerations',
                    'paragraphs' => [
                        'Smart home devices collect data. Always review privacy policies and disable features you don\'t need.',
                        'Use strong, unique passwords for each account. Enable two-factor authentication wherever available.',
                        'Keep firmware updated - manufacturers regularly patch security vulnerabilities.',
                        'Consider a separate network for IoT devices to isolate them from your main computers and phones.'
                    ],
                    'alignment' => 'fullscreen'
                ]
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'A well-designed smart home should fade into the background, working so seamlessly you forget it\'s there. The best automation is the kind you never have to think about.',
                    'attribution' => 'Smart Home Expert, TechWeekly'
                ]
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);

        return $page;
    }

    private function createTvBrand(): Page
    {
        $page = Page::create([
            'title' => 'TV Sony',
            'slug' => 'tv-sony',
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => 'Best TVs of 2025 - Expert Reviews & Buying Guide',
            'meta_description' => 'Find the perfect TV with our comprehensive 2025 buying guide. Expert reviews of Sony, Samsung, LG and more.',
            'site_id' => 2,
        ]);

        // Assign taxonomies
        $tvTerm = Tag::where('slug', 'sony')->where('site_id', 2)->first();
        if ($tvTerm) {
            PageTag::create([
                'page_id' => $page->id,
                'tag_id' => $tvTerm->id
            ]);
        }

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Best TVs of 2025',
                    'subtitle' => 'Expert reviews and buying advice for the top television models',
                    'ctaText' => 'See Top Picks',
                    'ctaUrl' => '#top-picks',
                    'backgroundImage' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=2000'
                ]
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The TV market in 2025 offers incredible options across all price ranges. Whether you\'re looking for cutting-edge OLED technology, budget-friendly 4K, or the latest gaming features, there\'s never been a better time to upgrade.',
                        'We\'ve tested dozens of models to bring you this comprehensive guide covering picture quality, smart features, gaming performance, and value for money.'
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Top Pick: Sony A95L OLED', 'level' => 2]
            ],
            [
                'type' => 'product',
                'data' => [
                    'name' => 'Sony A95L 65" QD-OLED TV',
                    'brand' => 'Sony',
                    'productName' => 'A95L 65" QD-OLED',
                    'price' => 2799.00,
                    'currency' => '£',
                    'image' => ['src' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=800'],
                    'description' => 'The Sony A95L combines QD-OLED technology with Sony\'s excellent processing for stunning picture quality. Perfect for movies and gaming.',
                    'link' => 'https://example.com/sony-a95l',
                    'linkText' => 'Check Price',
                    'showReviewPanel' => true,
                    'review' => [
                        'rating' => 4.9,
                        'pros' => [
                            'Exceptional picture quality with QD-OLED panel',
                            'Excellent motion handling for sports and gaming',
                            'Google TV with comprehensive app support',
                            'HDMI 2.1 with 4K 120Hz support'
                        ],
                        'cons' => [
                            'Premium price point',
                            'Slight risk of burn-in with static content'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Best Value: Samsung QN90D', 'level' => 2]
            ],
            [
                'type' => 'product',
                'data' => [
                    'name' => 'Samsung QN90D 55" Neo QLED',
                    'brand' => 'Samsung',
                    'productName' => 'QN90D 55" Neo QLED',
                    'price' => 1299.00,
                    'currency' => '£',
                    'image' => ['src' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=800'],
                    'description' => 'Samsung\'s QN90D offers mini-LED backlighting and quantum dots for excellent brightness and color at a competitive price.',
                    'link' => 'https://example.com/samsung-qn90d',
                    'linkText' => 'Check Price',
                    'showReviewPanel' => true,
                    'review' => [
                        'rating' => 4.7,
                        'pros' => [
                            'Excellent brightness for bright rooms',
                            'Great gaming features with low input lag',
                            'Sleek design with slim bezels',
                            'Good smart TV platform'
                        ],
                        'cons' => [
                            'Viewing angles not as wide as OLED',
                            'Black levels not quite OLED quality'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'product-comparison',
                'data' => [
                    'title' => 'OLED vs QLED: Which is Right for You?',
                    'productA' => 'OLED (Sony A95L)',
                    'productB' => 'QLED (Samsung QN90D)',
                    'comparisons' => [
                        [
                            'subtitle' => 'Picture Quality',
                            'items' => [
                                ['value' => 'Perfect blacks, infinite contrast'],
                                ['value' => 'Excellent brightness, great contrast']
                            ]
                        ],
                        [
                            'subtitle' => 'Price',
                            'items' => [
                                ['value' => '£2,799'],
                                ['value' => '£1,299']
                            ]
                        ],
                        [
                            'subtitle' => 'Best For',
                            'items' => [
                                ['value' => 'Dark rooms, movie watching'],
                                ['value' => 'Bright rooms, gaming, sports']
                            ]
                        ],
                        [
                            'subtitle' => 'Burn-in Risk',
                            'items' => [
                                ['value' => 'Low but present'],
                                ['value' => 'None']
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'More Top TV Picks by Brand', 'level' => 2]
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'layout' => 'grid',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'pages' => [
                        [
                            'title' => 'LG C4 OLED Review',
                            'slug' => 'lg-c4-oled-review',
                            'excerpt' => 'LG\'s C4 OLED delivers excellent picture quality at a more accessible price point.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=800', 'alt' => 'LG C4 OLED'],
                            'badge' => ['text' => 'Editor\'s Choice', 'color' => 'success']
                        ],
                        [
                            'title' => 'TCL QM8 Mini-LED Review',
                            'slug' => 'tcl-qm8-review',
                            'excerpt' => 'Incredible value with mini-LED technology and great gaming features.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=800', 'alt' => 'TCL QM8'],
                            'badge' => ['text' => 'Best Budget', 'color' => 'warning']
                        ],
                        [
                            'title' => 'Panasonic MZ2000 OLED Review',
                            'slug' => 'panasonic-mz2000-review',
                            'excerpt' => 'Master OLED panel with professional calibration for videophiles.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=800', 'alt' => 'Panasonic MZ2000'],
                            'badge' => ['text' => 'Premium', 'color' => 'primary']
                        ]
                    ]
                ]
            ],
            [
                'type' => 'table',
                'data' => [
                    'hasHeader' => true,
                    'rows' => [
                        ['Model', 'Technology', 'Size Options', 'Price', 'Rating'],
                        ['Sony A95L', 'QD-OLED', '55", 65", 77"', '£2,799+', '4.9/5'],
                        ['Samsung QN90D', 'Neo QLED', '55", 65", 75", 85"', '£1,299+', '4.7/5'],
                        ['LG C4', 'OLED', '42", 48", 55", 65", 77", 83"', '£1,499+', '4.8/5'],
                        ['TCL QM8', 'Mini-LED', '65", 75", 85"', '£999+', '4.5/5'],
                        ['Panasonic MZ2000', 'Master OLED', '55", 65", 77"', '£3,499+', '4.9/5']
                    ]
                ]
            ],
            [
                'type' => 'info',
                'data' => [
                    'infoType' => 'tip',
                    'description' => 'When shopping for a TV, consider your room\'s lighting conditions. OLED TVs excel in dark rooms, while QLED/Mini-LED TVs perform better in bright spaces.'
                ]
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'The 2025 TV market offers something for everyone. OLED technology has matured beautifully, while mini-LED has brought excellent performance to lower price points.',
                    'attribution' => 'TechWeekly Editorial Team'
                ]
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);

        // Assign Sony brand taxonomy
        $sonyTerm = Tag::where('slug', 'sony')->where('site_id', 2)->first();
        if ($sonyTerm) {
            PageTag::create([
                'tag_id' => $sonyTerm->id,
                'page_id' => $page->id,
                'category_id' => $tvTerm?->id ?? null
            ]);
        }

        return $page;
    }

    private function createGamingLaptopsBrandPage(): Page
    {
        $page = Page::create([
            'title' => 'Laptop Microsoft',
            'slug' => 'laptop-microsoft',
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => 'Best Gaming Laptops 2025 - Expert Reviews',
            'meta_description' => 'Find the perfect gaming laptop with our comprehensive guide covering performance, display quality, and value.',
            'site_id' => 2,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Best Gaming Laptops 2025',
                    'subtitle' => 'High-performance machines for serious gamers',
                    'ctaText' => 'Explore Reviews',
                    'ctaUrl' => '#reviews',
                    'backgroundImage' => 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?w=2000'
                ]
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Gaming laptops have evolved tremendously. Modern machines deliver desktop-class performance with RTX 40-series GPUs and high-refresh displays while maintaining reasonable battery life.',
                        'Whether you need a portable powerhouse for esports or a desktop replacement for AAA titles, we\'ve tested the best options at every price point.'
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Top Performance: ASUS ROG Strix Scar 18', 'level' => 2]
            ],
            [
                'type' => 'buying-guide',
                'data' => [
                    'title' => 'ASUS ROG Strix Scar 18',
                    'subtitle' => 'Absolute gaming performance champion',
                    'image' => 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?w=800',
                    'url' => 'https://example.com/rog-scar-18',
                    'specs' => [
                        ['text' => 'CPU', 'value' => 'Intel Core i9-14900HX'],
                        ['text' => 'GPU', 'value' => 'NVIDIA RTX 4090 (175W)'],
                        ['text' => 'Display', 'value' => '18" QHD+ 240Hz'],
                        ['text' => 'RAM', 'value' => '32GB DDR5'],
                        ['text' => 'Storage', 'value' => '2TB PCIe 4.0 SSD']
                    ],
                    'showReviewPanel' => true,
                    'pros' => [
                        'Unmatched gaming performance',
                        'Beautiful 240Hz display',
                        'Excellent cooling system',
                        'Per-key RGB keyboard'
                    ],
                    'cons' => [
                        'Very expensive (£3,999)',
                        'Heavy at 3.1kg',
                        'Battery life under 2 hours gaming'
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Best Value: Lenovo Legion Pro 5', 'level' => 2]
            ],
            [
                'type' => 'product',
                'data' => [
                    'name' => 'Lenovo Legion Pro 5',
                    'brand' => 'Lenovo',
                    'productName' => 'Legion Pro 5 RTX 4070',
                    'price' => 1599.00,
                    'currency' => '£',
                    'image' => ['src' => 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?w=800'],
                    'description' => 'Incredible value with RTX 4070, excellent display, and solid build quality.',
                    'link' => 'https://example.com/legion-pro-5',
                    'linkText' => 'Check Price',
                    'showReviewPanel' => true,
                    'review' => [
                        'rating' => 4.7,
                        'pros' => [
                            'Excellent price-to-performance ratio',
                            '165Hz QHD display',
                            'Good battery life for gaming laptop',
                            'Understated professional design'
                        ],
                        'cons' => [
                            'Webcam is only 720p',
                            'Trackpad could be larger',
                            'No per-key RGB lighting'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'table',
                'data' => [
                    'hasHeader' => true,
                    'rows' => [
                        ['Model', 'GPU', 'Display', 'Weight', 'Price'],
                        ['ASUS ROG Scar 18', 'RTX 4090', '18" QHD+ 240Hz', '3.1kg', '£3,999'],
                        ['Razer Blade 16', 'RTX 4080', '16" QHD+ 240Hz', '2.4kg', '£3,299'],
                        ['Lenovo Legion Pro 5', 'RTX 4070', '16" QHD 165Hz', '2.5kg', '£1,599'],
                        ['ASUS TUF A16', 'RTX 4060', '16" FHD 165Hz', '2.2kg', '£1,199'],
                        ['MSI Cyborg 15', 'RTX 4050', '15.6" FHD 144Hz', '1.9kg', '£899']
                    ]
                ]
            ],
            [
                'type' => 'info',
                'data' => [
                    'infoType' => 'tip',
                    'description' => 'For competitive gaming, prioritize high refresh rates (240Hz+). For AAA single-player games, focus on GPU power and a QHD or 4K display.'
                ]
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'The gap between gaming laptops and desktops has never been smaller. Today\'s laptops can handle any game at high settings.',
                    'attribution' => 'TechWeekly Gaming Editor'
                ]
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);

        $laptopTerm = Tag::where('slug', 'microsoft')->where('site_id', 2)->first();

        if ($laptopTerm) {
            PageTag::create([
                'page_id' => $page->id,
                'tag_id' => $laptopTerm->id
            ]);
        }

        return $page;
    }

    private function createSmartphoneBrandGuide(): Page
    {
        $page = Page::create([
            'title' => 'Smartphone Apple',
            'slug' => 'smartphone-apple',
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => 'Best Smartphones 2025 - Expert Reviews',
            'meta_description' => 'Find your perfect smartphone with our comprehensive 2025 buying guide covering iPhone, Samsung, Google Pixel and more.',
            'site_id' => 2,
        ]);

        $tvTerm = Tag::where('slug', 'apple')->where('site_id', 2)->first();
        if ($tvTerm) {
            PageTag::create([
                'tag_id' => $tvTerm->id,
                'page_id' => $page->id,
            ]);
        }

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Best Smartphones 2025',
                    'subtitle' => 'Expert reviews of the latest iPhone, Android, and flagship devices',
                    'ctaText' => 'See Top Picks',
                    'ctaUrl' => '#recommendations',
                    'backgroundImage' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=2000'
                ]
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The smartphone market in 2025 is more competitive than ever. From Apple\'s latest iPhone to innovative Android flagships, there\'s an incredible device for every need and budget.',
                        'We\'ve tested all the major releases to help you find the perfect smartphone, whether you prioritize camera quality, battery life, performance, or value.'
                    ]
                ]
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Smartphone Market 2025',
                    'stats' => [
                        ['number' => '50+', 'label' => 'Devices Tested', 'icon' => '📱'],
                        ['number' => '1,500+', 'label' => 'Photos Taken', 'icon' => '📸'],
                        ['number' => '200hrs', 'label' => 'Battery Testing', 'icon' => '🔋'],
                        ['number' => '100+', 'label' => 'Benchmark Tests', 'icon' => '⚡']
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Best Overall: iPhone 16 Pro', 'level' => 2]
            ],
            [
                'type' => 'image',
                'data' => [
                    'src' => 'https://images.unsplash.com/photo-1592286927505-b7e90a87c39e?w=1200',
                    'alt' => 'iPhone 16 Pro',
                    'caption' => 'The iPhone 16 Pro sets new standards for smartphone photography and performance',
                    'layout' => 'full'
                ]
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'A18 Pro chip delivers exceptional performance',
                        'New 5x telephoto camera captures stunning detail',
                        'Titanium design feels premium and durable',
                        'iOS 18 brings powerful AI features',
                        'Best-in-class video recording capabilities'
                    ]
                ]
            ],
            [
                'type' => 'buying-guide',
                'data' => [
                    'title' => 'iPhone 16 Pro',
                    'subtitle' => 'Apple\'s flagship delivers on all fronts',
                    'image' => 'https://images.unsplash.com/photo-1592286927505-b7e90a87c39e?w=800',
                    'url' => 'https://example.com/iphone-16-pro',
                    'specs' => [
                        ['text' => 'Display', 'value' => '6.3" OLED ProMotion 120Hz'],
                        ['text' => 'Processor', 'value' => 'A18 Pro'],
                        ['text' => 'Cameras', 'value' => '48MP main, 48MP ultra, 12MP 5x tele'],
                        ['text' => 'Battery', 'value' => 'All-day (29 hours video)'],
                        ['text' => 'Price', 'value' => 'From £999']
                    ],
                    'showReviewPanel' => true,
                    'pros' => [
                        'Outstanding camera system',
                        'Blazing fast performance',
                        'Premium build quality',
                        'Long software support'
                    ],
                    'cons' => [
                        'Expensive',
                        'Limited customization vs Android',
                        'USB-C still slower than competitors'
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Best Android: Samsung Galaxy S25 Ultra', 'level' => 2]
            ],
            [
                'type' => 'product',
                'data' => [
                    'name' => 'Samsung Galaxy S25 Ultra',
                    'brand' => 'Samsung',
                    'productName' => 'Galaxy S25 Ultra',
                    'price' => 1199.00,
                    'currency' => '£',
                    'image' => ['src' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=800'],
                    'description' => 'Samsung\'s ultimate flagship with S Pen, 200MP camera, and AI-powered features.',
                    'link' => 'https://example.com/galaxy-s25-ultra',
                    'linkText' => 'Check Price',
                    'showReviewPanel' => true,
                    'review' => [
                        'rating' => 4.8,
                        'pros' => [
                            'Incredible 200MP camera with AI enhancements',
                            'Built-in S Pen for productivity',
                            'Gorgeous 6.9" display',
                            'Exceptional battery life'
                        ],
                        'cons' => [
                            'Very large and heavy',
                            'Expensive',
                            'Some AI features require internet'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'gallery',
                'data' => [
                    'layout' => 'grid',
                    'slides' => [
                        [
                            'title' => 'Camera Samples',
                            'description' => '200MP sensor captures incredible detail',
                            'image' => 'https://images.unsplash.com/photo-1506710507565-203b9f24669b?w=800',
                            'alt' => 'Galaxy S25 Ultra camera sample'
                        ],
                        [
                            'title' => 'Night Mode',
                            'description' => 'Low-light photography is exceptional',
                            'image' => 'https://images.unsplash.com/photo-1514539079130-25950c84af65?w=800',
                            'alt' => 'Night mode photo'
                        ],
                        [
                            'title' => 'Portrait Mode',
                            'description' => 'Beautiful bokeh and edge detection',
                            'image' => 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=800',
                            'alt' => 'Portrait mode example'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Best Value: Google Pixel 9', 'level' => 2]
            ],
            [
                'type' => 'deal',
                'data' => [
                    'title' => 'Limited Time Offer',
                    'productName' => 'Google Pixel 9',
                    'brand' => 'Google',
                    'price' => 799.00,
                    'salePrice' => 649.00,
                    'currency' => '£',
                    'image' => ['src' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=800'],
                    'description' => 'Google\'s Pixel 9 brings flagship AI features and camera quality at a great price. Save £150 this week only!',
                    'link' => 'https://example.com/pixel-9-deal',
                    'showDealButton' => true,
                    'voucherId' => 'PIXEL150'
                ]
            ],
            [
                'type' => 'testimonial',
                'data' => [
                    'layout' => 'grid',
                    'testimonials' => [
                        [
                            'text' => 'The Pixel 9\'s camera is absolutely incredible. The AI features make every photo look professional.',
                            'author' => 'Sarah Johnson',
                            'role' => 'Tech Enthusiast',
                            'rating' => 5
                        ],
                        [
                            'text' => 'Best Android phone I\'ve ever used. Clean software, great battery life, and the price is unbeatable.',
                            'author' => 'Michael Chen',
                            'role' => 'Software Developer',
                            'rating' => 5
                        ]
                    ]
                ]
            ],
            [
                'type' => 'note',
                'data' => [
                    'title' => 'Buying Advice',
                    'paragraphs' => [
                        'Consider your ecosystem: If you use a Mac, iPad, or Apple Watch, the iPhone integrates seamlessly. For customization and value, Android offers more options.',
                        'Don\'t overlook mid-range phones: The Pixel 9 and Galaxy S24 FE offer flagship features at significantly lower prices.'
                    ],
                    'alignment' => 'fullscreen'
                ]
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);

        return $page;
    }

    private function createArticleGrid($pages): void
    {
        $site = Site::find(6);

        $items = [];

        foreach ($pages as $page) {
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
                        'url' => "/{$site->slug}/{$page->slug}",
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
            'items' => $items,
            'site_id' => 2
        ]);
    }
}