<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Framework\Support\Str;
use App\Models\Author;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageAuthor;
use App\Models\PageCustomField;
use App\Repositories\BlockRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;
use App\Services\BlockParserService;

class BuyingGuideSeeder extends Seeder
{
    private $pageRepository;
    private $blockRepository;
    private $tagRepository;
    private $categoryRepository;
    private $blockParserService;

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
        $this->createTechWeeklyGuides();
        $this->createVogueNoirGuides();
        $this->createGoCompareGuides();
        $this->createGamesRadarGuides();
    }

    private function createTechWeeklyGuides(): void
    {
        $siteId = 2;

        $guides = [
            [
                'title' => 'Best Gaming Laptops 2025: Ultimate Buying Guide',
                'slug' => 'best-gaming-laptops-2025',
                'tags' => ['buying-guide', 'gaming', 'laptops', 'hardware'],
                'categories' => ['Technology', 'Hardware'],
                'author' => [
                    'name' => 'Marcus Chen',
                    'bio' => 'Hardware reviewer specializing in gaming tech',
                ],
                'custom_fields' => [
                    'author_name' => 'Marcus Chen',
                    'read_time' => 18,
                    'excerpt' => 'Find the perfect gaming laptop for your needs and budget with our comprehensive 2025 buyer\'s guide.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Gaming Laptops 2025',
                            'subtitle' => 'Your complete guide to choosing the perfect portable gaming rig',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Gaming laptops have evolved dramatically. Today\'s portable gaming rigs rival desktop performance while maintaining surprising portability. But with prices ranging from £800 to £4,000+, choosing the right one requires careful consideration.',
                                'This guide breaks down everything you need to know: from GPUs and CPUs to display refresh rates and cooling systems. We\'ve tested dozens of gaming laptops to bring you definitive recommendations for every budget and use case.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What to Look For',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'GPU Performance: The single most important factor for gaming',
                                'Display: 144Hz minimum for competitive gaming, 1440p or 4K for visuals',
                                'CPU: Modern 6-core minimum, 8-core recommended',
                                'RAM: 16GB minimum, 32GB for future-proofing',
                                'Storage: 512GB SSD minimum, preferably NVMe',
                                'Cooling: Adequate thermal management prevents throttling',
                                'Build Quality: Premium materials justify higher prices',
                                'Battery Life: Expect 3-5 hours for gaming, 8+ for productivity'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Our Top Picks',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Overall: ASUS ROG Zephyrus G16',
                            'subtitle' => 'The perfect balance of performance and portability',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'ASUS ROG Zephyrus G16'
                            ],
                            'url' => 'https://example.com/asus-zephyrus-g16',
                            'specs' => [
                                ['text' => 'GPU', 'value' => 'NVIDIA RTX 4070'],
                                ['text' => 'CPU', 'value' => 'Intel Core i9-14900H'],
                                ['text' => 'Display', 'value' => '16" 240Hz QHD+ OLED'],
                                ['text' => 'RAM', 'value' => '32GB DDR5'],
                                ['text' => 'Storage', 'value' => '1TB PCIe 4.0 SSD'],
                                ['text' => 'Weight', 'value' => '1.9kg'],
                                ['text' => 'Price', 'value' => '£2,499']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Stunning OLED display with 240Hz refresh',
                                'Excellent build quality and premium materials',
                                'Surprisingly good battery life (7+ hours)',
                                'Powerful performance in a slim chassis',
                                'Effective cooling system'
                            ],
                            'cons' => [
                                'Premium price point',
                                'No webcam (uses detachable module)',
                                'Limited port selection'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Budget: Lenovo Legion 5 Pro',
                            'subtitle' => 'Outstanding performance without breaking the bank',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Lenovo Legion 5 Pro'
                            ],
                            'url' => 'https://example.com/lenovo-legion-5-pro',
                            'specs' => [
                                ['text' => 'GPU', 'value' => 'NVIDIA RTX 4060'],
                                ['text' => 'CPU', 'value' => 'AMD Ryzen 7 7745HX'],
                                ['text' => 'Display', 'value' => '16" 165Hz WQXGA'],
                                ['text' => 'RAM', 'value' => '16GB DDR5'],
                                ['text' => 'Storage', 'value' => '512GB PCIe 4.0 SSD'],
                                ['text' => 'Weight', 'value' => '2.5kg'],
                                ['text' => 'Price', 'value' => '£1,299']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Excellent value for money',
                                'Solid 1440p gaming performance',
                                'Good port selection',
                                'Upgradeable RAM and storage',
                                'Competitive pricing'
                            ],
                            'cons' => [
                                'Bulkier and heavier design',
                                'Plastic construction feels less premium',
                                'Average battery life (4 hours)'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Premium: Razer Blade 16',
                            'subtitle' => 'No-compromise gaming in a MacBook-style chassis',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1625978963928-5c8a6aede5a9?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Razer Blade 16'
                            ],
                            'url' => 'https://example.com/razer-blade-16',
                            'specs' => [
                                ['text' => 'GPU', 'value' => 'NVIDIA RTX 4090'],
                                ['text' => 'CPU', 'value' => 'Intel Core i9-14900HX'],
                                ['text' => 'Display', 'value' => '16" 240Hz QHD+ Mini-LED'],
                                ['text' => 'RAM', 'value' => '32GB DDR5'],
                                ['text' => 'Storage', 'value' => '2TB PCIe 4.0 SSD'],
                                ['text' => 'Weight', 'value' => '2.4kg'],
                                ['text' => 'Price', 'value' => '£4,299']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Desktop-class RTX 4090 performance',
                                'Beautiful Mini-LED display',
                                'CNC aluminum unibody construction',
                                'Per-key RGB lighting',
                                'Thunderbolt 4 connectivity'
                            ],
                            'cons' => [
                                'Very expensive',
                                'Gets hot under sustained load',
                                'Limited battery life when gaming'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Performance Comparison',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Model', 'GPU', '1080p FPS', '1440p FPS', '4K FPS', 'Price'],
                                ['ASUS ROG Zephyrus G16', 'RTX 4070', '165', '120', '65', '£2,499'],
                                ['Lenovo Legion 5 Pro', 'RTX 4060', '144', '95', '45', '£1,299'],
                                ['Razer Blade 16', 'RTX 4090', '240+', '180', '110', '£4,299'],
                                ['MSI Stealth 17', 'RTX 4080', '200', '145', '85', '£3,199']
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Tip: Always check for student discounts. Many manufacturers offer 10-15% off for students and educators.'
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The best gaming laptop is the one that fits your specific needs and budget. Don\'t overspend on features you won\'t use.',
                            'attribution' => 'Marcus Chen, Hardware Reviewer'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Best Wireless Earbuds 2025: Complete Buyer\'s Guide',
                'slug' => 'best-wireless-earbuds-2025',
                'tags' => ['buying-guide', 'audio', 'earbuds', 'reviews'],
                'categories' => ['Technology', 'Audio'],
                'author' => [
                    'name' => 'Sarah Johnson',
                    'bio' => 'Audio specialist and tech journalist',
                ],
                'custom_fields' => [
                    'author_name' => 'Sarah Johnson',
                    'read_time' => 15,
                    'excerpt' => 'Cut the cord with confidence. Our expert guide to the best wireless earbuds for every use case and budget.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Wireless Earbuds 2025',
                            'subtitle' => 'Expert-tested recommendations for superior sound on the go',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The wireless earbud market has exploded, with options ranging from £50 budget picks to £350 audiophile-grade models. Sound quality, ANC performance, battery life, and fit all matter—but which features justify higher prices?',
                                'We\'ve tested over 50 pairs of wireless earbuds to identify the best options across every price point and use case.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Overall: Sony WF-1000XM5',
                            'subtitle' => 'Industry-leading ANC meets exceptional sound quality',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1606841837239-c5a1a4a07af7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Sony WF-1000XM5'
                            ],
                            'url' => 'https://example.com/sony-wf1000xm5',
                            'specs' => [
                                ['text' => 'Driver', 'value' => '8.4mm Dynamic'],
                                ['text' => 'ANC', 'value' => 'Industry-leading'],
                                ['text' => 'Battery', 'value' => '8hrs (24hrs w/ case)'],
                                ['text' => 'Codec', 'value' => 'LDAC, AAC, SBC'],
                                ['text' => 'Water Resistance', 'value' => 'IPX4'],
                                ['text' => 'Price', 'value' => '£259']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Best-in-class noise cancellation',
                                'Exceptional sound quality with LDAC',
                                'Comfortable fit for extended wear',
                                'Excellent call quality',
                                'Wireless charging included'
                            ],
                            'cons' => [
                                'No multipoint Bluetooth',
                                'Case is slightly bulky',
                                'Premium pricing'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Value: Nothing Ear (2)',
                            'subtitle' => 'Flagship features at mid-range prices',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1649859394614-dc4bb0996d3b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Nothing Ear 2'
                            ],
                            'url' => 'https://example.com/nothing-ear-2',
                            'specs' => [
                                ['text' => 'Driver', 'value' => '11.6mm Dynamic'],
                                ['text' => 'ANC', 'value' => 'Up to 40dB'],
                                ['text' => 'Battery', 'value' => '6hrs (30hrs w/ case)'],
                                ['text' => 'Codec', 'value' => 'AAC, SBC'],
                                ['text' => 'Water Resistance', 'value' => 'IP54'],
                                ['text' => 'Price', 'value' => '£129']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Outstanding value for money',
                                'Unique transparent design',
                                'Solid ANC performance',
                                'Good sound quality',
                                'Long battery life'
                            ],
                            'cons' => [
                                'No LDAC support',
                                'Fit may not suit everyone',
                                'Basic app features'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'ANC Performance Comparison',
                            'productA' => 'Sony WF-1000XM5',
                            'productB' => 'Apple AirPods Pro 2',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Low Frequency (Rumble)',
                                    'items' => [
                                        ['value' => 'Excellent (95% reduction)'],
                                        ['value' => 'Very Good (90% reduction)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Mid Frequency (Voices)',
                                    'items' => [
                                        ['value' => 'Excellent (85% reduction)'],
                                        ['value' => 'Good (75% reduction)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'High Frequency (Treble)',
                                    'items' => [
                                        ['value' => 'Good (60% reduction)'],
                                        ['value' => 'Good (65% reduction)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Transparency Mode',
                                    'items' => [
                                        ['value' => 'Natural but slightly processed'],
                                        ['value' => 'Most natural sounding']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Important: Always test fit before buying. Even the best earbuds won\'t perform well if they don\'t fit your ears properly.'
                        ]
                    ]
                ]
            ]
        ];

        foreach ($guides as $guideData) {
            $this->createGuide($guideData, $siteId);
        }
    }

    private function createGuide(array $data, int $siteId): void
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'page_type' => 'buying-guide',
            'meta_title' => $data['title'],
            'meta_description' => $data['custom_fields']['excerpt'] ?? '',
            'site_id' => $siteId,
        ]);

        if (!empty($data['author'])) {
            $data['author']['slug'] = Str::slug($data['author']['name']);
            $author = Author::create($data['author']);
            PageAuthor::create([
                'page_id' => $page->id,
                'author_id' => $author->id
            ]);
        }

        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, $siteId);
            $page->tags(true)->attach($tag->id);
        }

        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, $siteId);
            $page->categories(true)->attach($category->id);
        }

        foreach ($data['custom_fields'] as $key => $value) {
            $fieldDef = CustomFieldDefinition::where('key', $key)
                ->where('site_id', $siteId)
                ->first();

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

    private function createVogueNoirGuides(): void
    {
        $siteId = 6;

        $guides = [
            [
                'title' => 'Best Designer Handbags 2025: Investment Pieces Worth Buying',
                'slug' => 'best-designer-handbags-investment-guide-2025',
                'tags' => ['buying-guide', 'luxury', 'handbags', 'investment'],
                'categories' => ['Fashion', 'Accessories', 'Bags'],
                'author' => [
                    'name' => 'Isabella Rossi',
                    'bio' => 'Luxury fashion expert with 20 years experience',
                ],
                'custom_fields' => [
                    'author_name' => 'Isabella Rossi',
                    'read_time' => 16,
                    'excerpt' => 'Invest wisely in timeless designer handbags that appreciate in value. Our expert guide to bags worth every penny.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Designer Handbags 2025',
                            'subtitle' => 'Investment-grade luxury bags that stand the test of time',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'A designer handbag is more than an accessory—it\'s an investment. The right bag can appreciate 10-15% annually, outperforming many traditional investments. But which bags hold value, and which lose it the moment you leave the boutique?',
                                'We\'ve analyzed resale data, auction results, and collector trends to identify the handbags truly worth your investment.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Investment: Hermès Birkin 30cm',
                            'subtitle' => 'The gold standard of handbag investments',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Hermès Birkin'
                            ],
                            'url' => 'https://example.com/hermes-birkin',
                            'specs' => [
                                ['text' => 'Size', 'value' => '30cm'],
                                ['text' => 'Material', 'value' => 'Togo Leather'],
                                ['text' => 'Retail Price', 'value' => '£10,000-12,000'],
                                ['text' => '5-Year Appreciation', 'value' => '+70%'],
                                ['text' => 'Resale Value', 'value' => '£17,000+'],
                                ['text' => 'Availability', 'value' => '2-6 year waitlist']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Unmatched resale value and appreciation',
                                'Handcrafted quality and durability',
                                'Ultimate status symbol',
                                'Limited supply ensures demand',
                                'Available in endless color combinations'
                            ],
                            'cons' => [
                                'Extremely difficult to purchase at retail',
                                'Very high initial investment',
                                'Requires careful authentication when buying secondhand',
                                'Heavy when fully loaded'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Classic: Chanel Classic Flap Medium',
                            'subtitle' => 'Timeless elegance with strong appreciation',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Chanel Classic Flap'
                            ],
                            'url' => 'https://example.com/chanel-classic-flap',
                            'specs' => [
                                ['text' => 'Size', 'value' => 'Medium (25cm)'],
                                ['text' => 'Material', 'value' => 'Caviar or Lambskin'],
                                ['text' => 'Retail Price', 'value' => '£7,800'],
                                ['text' => '5-Year Appreciation', 'value' => '+55%'],
                                ['text' => 'Resale Value', 'value' => '£12,000+'],
                                ['text' => 'Availability', 'value' => 'In-store purchase']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Iconic design by Coco Chanel',
                                'Strong value retention',
                                'Versatile for day and evening',
                                'Regular price increases protect investment',
                                'Available for immediate purchase'
                            ],
                            'cons' => [
                                'Significant annual price increases',
                                'Lambskin scratches easily',
                                'Limited capacity',
                                'Chain strap can be heavy'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Bag', '2020 Price', '2025 Price', 'Appreciation', 'Investment Grade'],
                                ['Hermès Birkin 30', '£7,000', '£12,000', '+71%', 'A+'],
                                ['Chanel Classic Flap', '£5,000', '£7,800', '+56%', 'A'],
                                ['Louis Vuitton Neverfull', '£1,000', '£1,450', '+45%', 'B+'],
                                ['Dior Lady Dior', '£3,800', '£5,200', '+37%', 'B'],
                                ['Gucci Dionysus', '£1,600', '£1,750', '+9%', 'C']
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Investment Tip: Black, navy, and neutral colors appreciate faster than seasonal shades. Classic hardware (gold or silver) outperforms trendy finishes.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Best Sustainable Fashion Brands 2025: Ethical Shopping Guide',
                'slug' => 'best-sustainable-fashion-brands-2025',
                'tags' => ['buying-guide', 'sustainable-fashion', 'ethical', 'eco-friendly'],
                'categories' => ['Fashion', 'Sustainable'],
                'author' => [
                    'name' => 'Emma Green',
                    'bio' => 'Sustainable fashion specialist',
                ],
                'custom_fields' => [
                    'author_name' => 'Emma Green',
                    'read_time' => 14,
                    'excerpt' => 'Shop sustainably without sacrificing style. Our guide to the best eco-conscious fashion brands.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Sustainable Fashion Brands 2025',
                            'subtitle' => 'Style with substance: eco-friendly brands leading the change',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Luxury Sustainable: Stella McCartney',
                            'subtitle' => 'Pioneering luxury fashion without animal products',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1558769132-cb1aea3c5f0c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Stella McCartney'
                            ],
                            'url' => 'https://example.com/stella-mccartney',
                            'specs' => [
                                ['text' => 'Founded', 'value' => '2001'],
                                ['text' => 'Materials', 'value' => '100% vegetarian'],
                                ['text' => 'Certifications', 'value' => 'B Corp, PETA Approved'],
                                ['text' => 'Price Range', 'value' => '£££-££££'],
                                ['text' => 'Sustainability Score', 'value' => '95/100']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Never uses leather, fur, or animal skins',
                                'Bio-fabricated materials innovation',
                                'Transparent supply chain',
                                'Carbon neutral operations',
                                'Circular fashion initiatives'
                            ],
                            'cons' => [
                                'Premium pricing',
                                'Limited retail locations',
                                'Some pieces require special care'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Sustainability Standards Comparison',
                            'productA' => 'Stella McCartney',
                            'productB' => 'Patagonia',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Materials',
                                    'items' => [
                                        ['value' => 'Innovative bio-fabrics, recycled materials'],
                                        ['value' => 'Organic cotton, recycled polyester']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Transparency',
                                    'items' => [
                                        ['value' => 'Full supply chain disclosure'],
                                        ['value' => 'Complete transparency + activism']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Price Point',
                                    'items' => [
                                        ['value' => 'Luxury (£500-3000)'],
                                        ['value' => 'Mid-range (£50-400)']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        foreach ($guides as $guideData) {
            $this->createGuide($guideData, $siteId);
        }
    }

    private function createGoCompareGuides(): void
    {
        $siteId = 28;

        $guides = [
            [
                'title' => 'Best Credit Cards 2025: Complete Comparison Guide',
                'slug' => 'best-credit-cards-2025-guide',
                'tags' => ['buying-guide', 'credit-cards', 'money', 'finance'],
                'categories' => ['Money', 'Credit Cards'],
                'author' => [
                    'name' => 'Andrew Foster',
                    'bio' => '15+ years in personal finance and credit expertise',
                ],
                'custom_fields' => [
                    'author_name' => 'Andrew Foster',
                    'read_time' => 12,
                    'excerpt' => 'Find the perfect credit card for your needs. Compare rates, rewards, and benefits across top UK providers.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Credit Cards 2025',
                            'subtitle' => 'Expert comparisons to help you choose the right card',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Choosing the right credit card can save you thousands in interest or earn valuable rewards. But with hundreds of options, how do you find the perfect match?',
                                'We\'ve compared rates, fees, rewards programs, and benefits to bring you definitive recommendations for every financial situation.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Balance Transfer: Virgin Money',
                            'subtitle' => 'Longest 0% period for existing debt',
                            'url' => 'https://example.com/virgin-balance-transfer',
                            'specs' => [
                                ['text' => '0% Period', 'value' => '29 months'],
                                ['text' => 'Transfer Fee', 'value' => '2.9%'],
                                ['text' => 'APR After 0%', 'value' => '24.9%'],
                                ['text' => 'Credit Score', 'value' => 'Good to Excellent'],
                                ['text' => 'Annual Fee', 'value' => '£0']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Market-leading 29-month 0% period',
                                'Low transfer fee of 2.9%',
                                'No annual fee',
                                'Online account management',
                                'Purchase protection included'
                            ],
                            'cons' => [
                                'High APR after promotional period',
                                'No rewards or cashback',
                                'Requires good credit score'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Rewards: American Express Platinum Cashback',
                            'subtitle' => 'Maximum cashback on everyday spending',
                            'url' => 'https://example.com/amex-platinum-cashback',
                            'specs' => [
                                ['text' => 'Cashback Rate', 'value' => '5% first 3 months, 1% ongoing'],
                                ['text' => 'Annual Fee', 'value' => '£25'],
                                ['text' => 'APR', 'value' => '22.9%'],
                                ['text' => 'Acceptance', 'value' => '95% of UK retailers']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                '5% cashback for first 3 months',
                                'No limit on cashback earnings',
                                'Excellent fraud protection',
                                'Purchase protection and insurance',
                                'Great customer service'
                            ],
                            'cons' => [
                                'Annual fee of £25',
                                'Not accepted everywhere',
                                'High APR if you carry balance'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Rewards vs Balance Transfer',
                            'productA' => 'Rewards Card',
                            'productB' => 'Balance Transfer Card',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Earning on spending'],
                                        ['value' => 'Paying off debt']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Typical APR',
                                    'items' => [
                                        ['value' => '20-30%'],
                                        ['value' => '0% then 22-27%']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Annual Fee',
                                    'items' => [
                                        ['value' => '£0-150'],
                                        ['value' => '£0']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Best Energy Deals 2025: How to Switch and Save',
                'slug' => 'best-energy-deals-2025',
                'tags' => ['buying-guide', 'energy', 'utilities', 'savings'],
                'categories' => ['Utilities', 'Energy'],
                'author' => [
                    'name' => 'Robert Davies',
                    'bio' => 'Energy market analyst',
                ],
                'custom_fields' => [
                    'author_name' => 'Robert Davies',
                    'read_time' => 10,
                    'excerpt' => 'Save hundreds on energy bills. Our guide to finding and switching to cheaper tariffs.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Energy Deals 2025',
                            'subtitle' => 'Cut your bills with our switching guide',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Fixed Rate: Octopus Energy Fixed 12M',
                            'subtitle' => 'Price certainty with green energy',
                            'url' => 'https://example.com/octopus-fixed',
                            'specs' => [
                                ['text' => 'Contract Length', 'value' => '12 months'],
                                ['text' => 'Average Annual Cost', 'value' => '£1,456'],
                                ['text' => 'Exit Fee', 'value' => '£0'],
                                ['text' => 'Green Energy', 'value' => '100%'],
                                ['text' => 'Customer Rating', 'value' => '4.7/5']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Price locked for 12 months',
                                '100% renewable electricity',
                                'No exit fees',
                                'Award-winning app and service',
                                'Smart tariff options'
                            ],
                            'cons' => [
                                'Slightly higher than variable',
                                'Limited time offer',
                                'Requires smart meter for best deals'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        foreach ($guides as $guideData) {
            $this->createGuide($guideData, $siteId);
        }
    }

    private function createGamesRadarGuides(): void
    {
        $siteId = 38;

        $guides = [
            [
                'title' => 'Best Gaming Monitors 2025: Ultimate Display Buying Guide',
                'slug' => 'best-gaming-monitors-2025',
                'tags' => ['buying-guide', 'gaming', 'monitors', 'hardware'],
                'categories' => ['Gaming', 'Hardware'],
                'author' => [
                    'name' => 'Jake Morrison',
                    'bio' => 'Gaming hardware specialist and esports enthusiast',
                ],
                'custom_fields' => [
                    'author_name' => 'Jake Morrison',
                    'read_time' => 14,
                    'excerpt' => 'Level up your gaming with the perfect monitor. Expert recommendations for every budget and gaming style.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Gaming Monitors 2025',
                            'subtitle' => 'Find your competitive edge with the perfect display',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Your monitor is the window to every gaming experience. Refresh rate, response time, panel type, and resolution all impact performance and immersion.',
                                'We\'ve tested dozens of gaming monitors to identify the best options for competitive FPS, immersive RPGs, and everything in between.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Overall: ASUS ROG Swift OLED PG27AQDM',
                            'subtitle' => 'OLED perfection for gaming',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'ASUS ROG Swift OLED'
                            ],
                            'url' => 'https://example.com/asus-oled',
                            'specs' => [
                                ['text' => 'Size', 'value' => '27"'],
                                ['text' => 'Resolution', 'value' => '2560x1440 (1440p)'],
                                ['text' => 'Refresh Rate', 'value' => '240Hz'],
                                ['text' => 'Response Time', 'value' => '0.03ms'],
                                ['text' => 'Panel', 'value' => 'OLED'],
                                ['text' => 'Price', 'value' => '£899']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Stunning OLED picture quality',
                                'Blazing fast 240Hz refresh rate',
                                'Near-instantaneous response time',
                                'Perfect blacks and infinite contrast',
                                'G-Sync compatible'
                            ],
                            'cons' => [
                                'OLED burn-in risk with static elements',
                                'Premium price',
                                'No HDR1000 brightness'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Budget: AOC 24G2',
                            'subtitle' => 'Competitive gaming on a budget',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1585792180666-f7347c490ee2?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'AOC Gaming Monitor'
                            ],
                            'url' => 'https://example.com/aoc-24g2',
                            'specs' => [
                                ['text' => 'Size', 'value' => '24"'],
                                ['text' => 'Resolution', 'value' => '1920x1080 (1080p)'],
                                ['text' => 'Refresh Rate', 'value' => '144Hz'],
                                ['text' => 'Response Time', 'value' => '1ms'],
                                ['text' => 'Panel', 'value' => 'IPS'],
                                ['text' => 'Price', 'value' => '£159']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Outstanding value for money',
                                'Solid 144Hz performance',
                                'IPS panel with good colors',
                                'FreeSync and G-Sync compatible',
                                'Height adjustable stand'
                            ],
                            'cons' => [
                                '1080p resolution shows age',
                                'Basic HDR implementation',
                                'Limited brightness'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Model', 'Size', 'Resolution', 'Refresh', 'Panel', 'Price'],
                                ['ASUS ROG Swift OLED', '27"', '1440p', '240Hz', 'OLED', '£899'],
                                ['LG 27GR95QE', '27"', '1440p', '240Hz', 'OLED', '£949'],
                                ['AOC 24G2', '24"', '1080p', '144Hz', 'IPS', '£159'],
                                ['Samsung Odyssey G7', '32"', '1440p', '165Hz', 'VA', '£449']
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Pro Tip: Match your monitor refresh rate to your GPU capability. A 360Hz monitor is wasted if your PC can\'t push those frame rates.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Best Gaming Headsets 2025: Audio Buying Guide',
                'slug' => 'best-gaming-headsets-2025',
                'tags' => ['buying-guide', 'gaming', 'audio', 'headsets'],
                'categories' => ['Gaming', 'Accessories'],
                'author' => [
                    'name' => 'Sarah Chen',
                    'bio' => 'Gaming peripherals expert',
                ],
                'custom_fields' => [
                    'author_name' => 'Sarah Chen',
                    'read_time' => 12,
                    'excerpt' => 'Hear every footstep with our gaming headset guide. Top picks for competitive and immersive gaming.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Gaming Headsets 2025',
                            'subtitle' => 'Dominate with crystal-clear audio and comms',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1599669454699-248893623440?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Overall: SteelSeries Arctis Nova Pro',
                            'subtitle' => 'Premium wireless with swappable batteries',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1612208695882-02f2322b7fee?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'SteelSeries Arctis Nova Pro'
                            ],
                            'url' => 'https://example.com/arctis-nova-pro',
                            'specs' => [
                                ['text' => 'Connection', 'value' => 'Wireless 2.4GHz + Bluetooth'],
                                ['text' => 'Battery Life', 'value' => '44 hours (swappable)'],
                                ['text' => 'Driver', 'value' => '40mm neodymium'],
                                ['text' => 'Microphone', 'value' => 'Retractable boom'],
                                ['text' => 'Weight', 'value' => '338g'],
                                ['text' => 'Price', 'value' => '£349']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Hot-swappable battery system',
                                'Excellent sound quality',
                                'Premium build quality',
                                'Multi-platform compatibility',
                                'Active noise cancellation'
                            ],
                            'cons' => [
                                'Very expensive',
                                'Heavy for extended sessions',
                                'Base station required'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Wireless vs Wired',
                            'productA' => 'Wireless Headsets',
                            'productB' => 'Wired Headsets',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Latency',
                                    'items' => [
                                        ['value' => '5-15ms (acceptable)'],
                                        ['value' => '0ms (instant)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Battery Concerns',
                                    'items' => [
                                        ['value' => 'Yes (15-40 hours typical)'],
                                        ['value' => 'No']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Freedom of Movement',
                                    'items' => [
                                        ['value' => 'Excellent'],
                                        ['value' => 'Limited by cable']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        foreach ($guides as $guideData) {
            $this->createGuide($guideData, $siteId);
        }
    }
}