<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\Page;
use App\Models\Site;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\Cms\Pages\BlockParserService;

class SpotlightLayoutTestSeeder extends Seeder
{
    private $pageRepository;
    private $blockRepository;
    private $blockParserService;
    private \App\Models\Model $site;

    public function __construct()
    {
        $this->pageRepository = new PageRepository();
        $this->blockRepository = new BlockRepository();
        $this->blockParserService = (new Container())->resolve(BlockParserService::class);

        parent::__construct();
    }

    public function run(): void
    {
        $this->site = Site::find(52);

        if (!$this->site) {
            echo "Site ID 52 not found.\n";
            return;
        }

        echo "Creating Spotlight Layout test page for site ID 52...\n";

        // Create test page with spotlight layout
        $page = $this->createSpotlightTestPage();

        echo "✅ Created test page: '{$page->title}' (ID: {$page->id})\n";
        echo "🔗 URL: /{$page->slug}\n";
        echo "📱 Test both desktop and mobile views\n";
    }

    private function createSpotlightTestPage(): Page
    {
        // Create the main test page
        $page = Page::create([
            'title' => 'Spotlight Layout Test - Fashion Editorial',
            'slug' => 'spotlight-layout-test',
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => 'Spotlight Layout Test - Premium Fashion',
            'meta_description' => 'Testing the new spotlight layout feature with fashion products.',
            'site_id' => $this->site->id,
        ]);

        // Introduction content
        $blocks = [
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Winter Fashion Essentials',
                    'level' => 1
                ]
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'The NFL player shared that, when he feels sad, he listens to his girlfriend\'s music. "She has something for everything," he explained. Kelce continued, "The only way you can find yourself in the light is to find the dark first...I listen to music that is very telling of my mood, yes."',
                        'This spotlight layout showcases our featured products in an elegant, scrollable format perfect for editorial content.'
                    ]
                ]
            ]
        ];

        // Spotlight Group Block #1 - Buckled Heel Slingbacks
        $spotlightGroup1 = [
            'type' => 'group',
            'data' => [
                'name' => 'Slingbacks Spotlight',
                'layout' => 'spotlight',
                'blocks' => [
                    // Hero image (will be sticky on desktop)
                    [
                        'id' => 'img-spotlight-hero-1',
                        'type' => 'image',
                        'src' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80',
                        'alt' => 'Woman wearing white sunglasses and blue sequined jacket',
                        'caption' => '(Image credit: Liana Hakobyan)'
                    ],
                    // Product 1
                    [
                        'id' => 'prod-slingback-1',
                        'type' => 'product',
                        'name' => 'Buckled Heel Slingbacks',
                        'description' => 'In lieu of being able to hug in person, send them a microwavable heating pad.',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=400&q=80',
                            'alt' => 'Black buckled heel slingbacks'
                        ],
                        'price' => '£89.99',
                        'brand' => '',
                        'link' => 'https://example.com/slingbacks',
                        'buttonText' => 'Shop Now'
                    ],
                    // Product 2
                    [
                        'id' => 'prod-slingback-2',
                        'type' => 'product',
                        'name' => 'Buckled Heel Slingbacks',
                        'description' => 'In lieu of being able to hug in person, send them a microwavable heating pad.',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=400&q=80',
                            'alt' => 'Black buckled heel slingbacks'
                        ],
                        'price' => '£89.99',
                        'brand' => '',
                        'link' => 'https://example.com/slingbacks',
                        'buttonText' => 'Shop Now'
                    ],
                    // Product 3 with label
                    [
                        'id' => 'prod-slingback-3',
                        'type' => 'product',
                        'name' => 'Buckled Heel Slingbacks',
                        'label' => 'PRODUCT LABEL',
                        'description' => 'In lieu of being able to hug in person, send them a microwavable heating pad.',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=400&q=80',
                            'alt' => 'Black buckled heel slingbacks'
                        ],
                        'price' => '£89.99',
                        'brand' => '',
                        'link' => 'https://example.com/slingbacks',
                        'buttonText' => 'Shop Now'
                    ],
                    // Product 4 with brand
                    [
                        'id' => 'prod-slingback-4',
                        'type' => 'product',
                        'name' => 'Buckled Heel Slingbacks',
                        'brand' => 'Zara',
                        'description' => 'In lieu of being able to hug in person, send them a microwavable heating pad.',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=400&q=80',
                            'alt' => 'Black buckled heel slingbacks'
                        ],
                        'price' => '£89.99',
                        'link' => 'https://example.com/slingbacks',
                        'buttonText' => 'Shop Now'
                    ]
                ]
            ]
        ];

        // Text separator
        $textBlock = [
            'type' => 'text',
            'data' => [
                'paragraphs' => [
                    'The design features a sleek silhouette with comfortable heel height, making it perfect for both professional and casual settings. The buckle detail adds a touch of sophistication to any outfit.'
                ]
            ]
        ];

        // Spotlight Group Block #2 - Portrait Image Test
        $spotlightGroup2 = [
            'type' => 'group',
            'data' => [
                'name' => 'Fashion Portrait Spotlight',
                'layout' => 'spotlight',
                'blocks' => [
                    // Portrait hero image
                    [
                        'id' => 'img-spotlight-hero-2',
                        'type' => 'image',
                        'src' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=800&q=80',
                        'alt' => 'Fashion model in elegant pose',
                        'caption' => 'Editorial fashion photography'
                    ],
                    // Product 1
                    [
                        'id' => 'prod-fashion-1',
                        'type' => 'product',
                        'name' => 'Designer Handbag',
                        'brand' => 'Luxury Brand',
                        'description' => 'Crafted from premium leather with gold hardware. A timeless piece for your collection.',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=400&q=80',
                            'alt' => 'Designer handbag'
                        ],
                        'price' => '£450.00',
                        'salePrice' => '£350.00',
                        'link' => 'https://example.com/designer-handbag',
                        'buttonText' => 'Shop Now'
                    ],
                    // Product 2
                    [
                        'id' => 'prod-fashion-2',
                        'type' => 'product',
                        'name' => 'Silk Scarf',
                        'brand' => 'Premium Silk Co.',
                        'label' => 'NEW ARRIVAL',
                        'description' => 'Hand-printed silk scarf featuring an exclusive design. Perfect for any season.',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1601924994987-69e26d50dc26?w=400&q=80',
                            'alt' => 'Silk scarf'
                        ],
                        'price' => '£120.00',
                        'link' => 'https://example.com/silk-scarf',
                        'buttonText' => 'Shop Now'
                    ],
                    // Product 3
                    [
                        'id' => 'prod-fashion-3',
                        'type' => 'product',
                        'name' => 'Statement Earrings',
                        'description' => 'Bold geometric design in brushed gold finish. Make a statement with these eye-catching earrings.',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=400&q=80',
                            'alt' => 'Statement earrings'
                        ],
                        'price' => '£75.00',
                        'link' => 'https://example.com/statement-earrings',
                        'buttonText' => 'Shop Now'
                    ]
                ]
            ]
        ];

        // Additional test: Spotlight with maximum products (5)
        $spotlightGroup3 = [
            'type' => 'group',
            'data' => [
                'name' => 'Maximum Products Test',
                'layout' => 'spotlight',
                'blocks' => [
                    // Square image
                    [
                        'id' => 'img-spotlight-hero-3',
                        'type' => 'image',
                        'src' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=800&q=80',
                        'alt' => 'Fashion flatlay with accessories',
                        'caption' => 'Curated collection'
                    ],
                    // 5 products
                    [
                        'id' => 'prod-max-1',
                        'type' => 'product',
                        'name' => 'Leather Wallet',
                        'brand' => 'Artisan Leather',
                        'description' => 'Handcrafted from full-grain leather. Designed to age beautifully.',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1627123424574-724758594e93?w=400&q=80',
                            'alt' => 'Leather wallet'
                        ],
                        'price' => '£85.00',
                        'link' => '#',
                        'buttonText' => 'Shop Now'
                    ],
                    [
                        'id' => 'prod-max-2',
                        'type' => 'product',
                        'name' => 'Sunglasses',
                        'brand' => 'Designer Eyewear',
                        'description' => 'UV protection with style. Classic frame that never goes out of fashion.',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=400&q=80',
                            'alt' => 'Sunglasses'
                        ],
                        'price' => '£150.00',
                        'link' => '#',
                        'buttonText' => 'Shop Now'
                    ],
                    [
                        'id' => 'prod-max-3',
                        'type' => 'product',
                        'name' => 'Watch',
                        'label' => 'BESTSELLER',
                        'description' => 'Minimalist design meets Swiss precision. A timeless accessory.',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400&q=80',
                            'alt' => 'Minimalist watch'
                        ],
                        'price' => '£280.00',
                        'brand' => 'Swiss Time',
                        'link' => '#',
                        'buttonText' => 'Shop Now'
                    ],
                    [
                        'id' => 'prod-max-4',
                        'type' => 'product',
                        'name' => 'Belt',
                        'description' => 'Italian leather belt with brass buckle. Essential wardrobe staple.',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1624222247344-550fb60583bb?w=400&q=80',
                            'alt' => 'Leather belt'
                        ],
                        'price' => '£65.00',
                        'link' => '#',
                        'buttonText' => 'Shop Now'
                    ],
                    [
                        'id' => 'prod-max-5',
                        'type' => 'product',
                        'name' => 'Card Holder',
                        'brand' => 'Minimalist Co.',
                        'description' => 'Slim design holds 6-8 cards. Made from vegetable-tanned leather.',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1627123424574-724758594e93?w=400&q=80',
                            'alt' => 'Card holder'
                        ],
                        'price' => '£45.00',
                        'link' => '#',
                        'buttonText' => 'Shop Now'
                    ]
                ]
            ]
        ];

        // Edge case test: Spotlight without image (should fallback to default)
        $spotlightGroupNoImage = [
            'type' => 'group',
            'data' => [
                'name' => 'No Image Test (Fallback)',
                'layout' => 'spotlight',
                'blocks' => [
                    [
                        'id' => 'prod-noimg-1',
                        'type' => 'product',
                        'name' => 'Product Without Hero Image',
                        'description' => 'This should fallback to default layout since there is no image block.',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400&q=80',
                            'alt' => 'Product'
                        ],
                        'price' => '£50.00',
                        'link' => '#',
                        'buttonText' => 'Shop Now'
                    ]
                ]
            ]
        ];

        // Comparison: Regular carousel group
        $carouselGroup = [
            'type' => 'group',
            'data' => [
                'name' => 'Carousel Comparison',
                'layout' => 'carousel',
                'carouselTitle' => 'More Items You Might Like',
                'blocks' => [
                    [
                        'id' => 'prod-carousel-1',
                        'type' => 'product',
                        'name' => 'Carousel Product 1',
                        'description' => 'This is in a carousel layout for comparison.',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1608749907764-3be0ac5ce675?w=400&q=80',
                            'alt' => 'Carousel product'
                        ],
                        'price' => '£60.00',
                        'link' => '#',
                        'buttonText' => 'Shop Now'
                    ],
                    [
                        'id' => 'prod-carousel-2',
                        'type' => 'product',
                        'name' => 'Carousel Product 2',
                        'description' => 'Another carousel item.',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=400&q=80',
                            'alt' => 'Carousel product'
                        ],
                        'price' => '£70.00',
                        'link' => '#',
                        'buttonText' => 'Shop Now'
                    ]
                ]
            ]
        ];

        // Conclusion
        $conclusionBlock = [
            'type' => 'text',
            'data' => [
                'paragraphs' => [
                    'This test page demonstrates the spotlight layout in various configurations:',
                    '✓ Desktop: Image should be sticky on the left while products scroll on the right',
                    '✓ Mobile: Image appears first, then products stack vertically below',
                    '✓ Supports both square and portrait images',
                    '✓ Prevents ad injection between spotlight blocks',
                    '✓ Validates that at least one image and 2-5 products are optimal'
                ]
            ]
        ];

        // Combine all blocks
        $allBlocks = array_merge(
            $blocks,
            [$spotlightGroup1],
            [$textBlock],
            [$spotlightGroup2],
            [$spotlightGroup3],
            [$spotlightGroupNoImage],
            [$carouselGroup],
            [$conclusionBlock]
        );

        $this->createBlocksForPage($page->id, $allBlocks);

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
}