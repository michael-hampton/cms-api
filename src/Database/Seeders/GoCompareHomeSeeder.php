<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\Page;
use App\Models\Site;
use App\Repositories\Cms\BlockRepository;
use App\Services\Cms\BlockParserService;

class GoCompareHomeSeeder extends Seeder
{
    private $blockRepository;
    private $blockParserService;
    private $site;

    public function __construct()
    {
        $this->blockRepository = new BlockRepository();
        $this->blockParserService = (new Container())->resolve(BlockParserService::class);
        parent::__construct();
    }

    public function run(): void
    {
        $this->site = Site::where('slug', 'gocompare')->first();

        if (!$this->site) {
            echo "GoCompare site not found. Please create it first.\n";
            return;
        }

        $this->createHomepage();
    }

    private function createHomepage(): void
    {
        $page = Page::where('slug', 'home')
            ->where('site_id', $this->site->id)
            ->first();

        $blocks = [
            // Promo Header Banner
            [
                'type' => 'banner',
                'data' => [
                    'bannerType' => 'promo-header',
                    'title' => 'Save up to £300 on Car Insurance',
                    'subtitle' => 'Compare quotes from over 100 providers in minutes',
                    'ctaText' => 'Get Quotes',
                    'ctaUrl' => '/car-insurance',
                    'backgroundColor' => '#00a8e1',
                    'textColor' => '#ffffff',
                    'dismissible' => true,
                    'context' => 'default'
                ],
                'order' => 1
            ],

            // Hero Section
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Compare. Switch. Save.',
                    'subtitle' => 'Find better deals on insurance, energy, broadband and more',
                    'ctaText' => 'Start Comparing',
                    'ctaUrl' => '#services',
                    'showSearch' => true,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?ixlib=rb-4.0.3'
                ],
                'order' => 2
            ],

            // Services Grid
            [
                'type' => 'services',
                'data' => [
                    'title' => 'What would you like to compare?',
                    'layout' => 'grid',
                    'services' => [
                        [
                            'title' => 'Car Insurance',
                            'description' => 'Compare quotes from over 100 insurers',
                            'icon' => '🚗',
                            'link' => '/car-insurance'
                        ],
                        [
                            'title' => 'Home Insurance',
                            'description' => 'Protect your home and contents',
                            'icon' => '🏠',
                            'link' => '/home-insurance'
                        ],
                        [
                            'title' => 'Energy',
                            'description' => 'Switch and save on gas & electric',
                            'icon' => '⚡',
                            'link' => '/energy'
                        ],
                        [
                            'title' => 'Broadband',
                            'description' => 'Find faster, cheaper internet',
                            'icon' => '📡',
                            'link' => '/broadband'
                        ],
                        [
                            'title' => 'Travel Insurance',
                            'description' => 'Cover for your next trip',
                            'icon' => '✈️',
                            'link' => '/travel-insurance'
                        ],
                        [
                            'title' => 'Credit Cards',
                            'description' => 'Compare cards and apply online',
                            'icon' => '💳',
                            'link' => '/credit-cards'
                        ]
                    ]
                ],
                'order' => 3
            ],

            [
                'type' => 'accordion',
                'data' => [
                    'title' => 'Frequently Asked Questions',
                    'items' => [
                        [
                            'question' => 'How does GoCompare work?',
                            'answer' => 'Simply enter your details once and we\'ll search hundreds of providers to find you the best deals. It\'s quick, free, and there\'s no obligation to switch.',
                            'isOpen' => true
                        ],
                        [
                            'question' => 'Is GoCompare really free?',
                            'answer' => 'Yes, GoCompare is completely free to use. We make money through commission from providers when you switch, but this never affects the price you pay.',
                            'isOpen' => false
                        ],
                        [
                            'question' => 'How long does it take to compare?',
                            'answer' => 'Most comparisons take just 2-3 minutes. You\'ll need basic information like your address and current provider details.',
                            'isOpen' => false
                        ],
                        [
                            'question' => 'Can I switch immediately?',
                            'answer' => 'Yes! Once you\'ve found a better deal, you can often switch online in minutes. We\'ll guide you through the entire process.',
                            'isOpen' => false
                        ],
                        [
                            'question' => 'What if I have a question after switching?',
                            'answer' => 'Our customer service team is here to help Monday-Friday 8am-8pm and weekends 9am-5pm. You can also manage everything through your online account.',
                            'isOpen' => false
                        ]
                    ],
                    'allowMultipleOpen' => false,
                    'openFirstByDefault' => true,
                    'context' => 'default'
                ],
                'order' => 4
            ],

            // Review Banner (Sidebar)
            [
                'type' => 'banner',
                'data' => [
                    'bannerType' => 'review-banner',
                    'title' => 'Trusted by Millions',
                    'rating' => 4.5,
                    'reviewCount' => 25000,
                    'ctaText' => 'Read Reviews',
                    'ctaUrl' => '/reviews',
                    'backgroundColor' => '#f8f9fa',
                    'textColor' => '#212529',
                    'context' => 'sidebar'
                ],
                'order' => 5
            ],

            // Stats
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Why Choose GoCompare?',
                    'stats' => [
                        ['number' => '10M+', 'label' => 'Users helped annually', 'icon' => '👥'],
                        ['number' => '£300', 'label' => 'Average savings on car insurance', 'icon' => '💰'],
                        ['number' => '100+', 'label' => 'Providers compared', 'icon' => '🏢'],
                        ['number' => '2 mins', 'label' => 'Average comparison time', 'icon' => '⏱️']
                    ]
                ],
                'order' => 6
            ],

            // Providers Banner (Sidebar)
            [
                'type' => 'banner',
                'data' => [
                    'bannerType' => 'providers-banner',
                    'title' => 'Featured Providers',
                    'subtitle' => 'We compare top UK brands',
                    'providers' => [
                        ['name' => 'Aviva', 'logo' => '/images/providers/aviva.png'],
                        ['name' => 'Direct Line', 'logo' => '/images/providers/directline.png'],
                        ['name' => 'Admiral', 'logo' => '/images/providers/admiral.png'],
                        ['name' => 'Churchill', 'logo' => '/images/providers/churchill.png']
                    ],
                    'backgroundColor' => '#ffffff',
                    'textColor' => '#212529',
                    'context' => 'sidebar'
                ],
                'order' => 7
            ],

            // Testimonials
            [
                'type' => 'testimonial',
                'data' => [
                    'title' => 'What Our Customers Say',
                    'testimonials' => [
                        [
                            'quote' => 'Saved £200 on my car insurance in just minutes. The process was so simple!',
                            'author' => 'Sarah M.',
                            'location' => 'Manchester',
                            'rating' => 5
                        ],
                        [
                            'quote' => 'Great service, found me the perfect broadband deal for my family.',
                            'author' => 'James T.',
                            'location' => 'London',
                            'rating' => 5
                        ],
                        [
                            'quote' => 'The comparison was quick and the savings were real. Highly recommend!',
                            'author' => 'Emma R.',
                            'location' => 'Birmingham',
                            'rating' => 4
                        ]
                    ]
                ],
                'order' => 8
            ],
            [
                'type' => 'cta',
                'data' => [
                    'text' => 'Get Your Free Quote',
                    'url' => '/get-quote',
                    'noFollow' => false,
                    'sponsored' => false,
                    'openInNewTab' => false,
                    'style' => 'primary',
                    'size' => 'large',
                    'alignment' => 'center',
                    'context' => 'sidebar'
                ],
                'order' => 5
            ],
            [
                'type' => 'cta',
                'data' => [
                    'text' => 'Start Your Comparison Now',
                    'url' => '/get-started',
                    'noFollow' => false,
                    'sponsored' => false,
                    'openInNewTab' => false,
                    'style' => 'primary',
                    'size' => 'large',
                    'alignment' => 'center',
                    'context' => 'default'
                ],
                'order' => 11
            ],
            [
                'type' => 'cta',
                'data' => [
                    'text' => 'Special Offer: Get 20% Off',
                    'url' => 'https://partner-site.com/offer',
                    'noFollow' => true,
                    'sponsored' => true,
                    'openInNewTab' => true,
                    'style' => 'secondary',
                    'size' => 'medium',
                    'alignment' => 'center',
                    'context' => 'sidebar'
                ],
                'order' => 13
            ],
            // News Feed Block
            [
                'type' => 'news-feed',
                'data' => [
                    'title' => 'Latest Insurance News',
                    'subtitle' => 'Stay informed with the latest updates',
                    'layout' => 'grid',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showDate' => true,
                    'showAuthor' => true,
                    'showCategory' => true,
                    'showReadTime' => true,
                    'limit' => 6,
                    'context' => 'default',
                    'items' => [
                        [
                            'title' => 'How to Save on Car Insurance in 2025',
                            'excerpt' => 'Discover the latest tips and tricks to reduce your car insurance premiums without compromising on coverage.',
                            'image' => [
                                'id' => 1,
                                'src' => 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?ixlib=rb-4.0.3',
                                'alt' => 'Car insurance savings',
                                'name' => 'car-insurance.jpg'
                            ],
                            'pageUrl' => '/guides/save-car-insurance-2025',
                            'pageId' => null,
                            'author' => 'Sarah Johnson',
                            'date' => '2025-01-15',
                            'category' => 'Car Insurance',
                            'readTime' => '5 min read',
                            'featured' => true
                        ],
                        [
                            'title' => 'Energy Price Cap Changes Explained',
                            'excerpt' => 'Understanding the latest energy price cap adjustments and how they affect your bills.',
                            'image' => [
                                'id' => 2,
                                'src' => 'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?ixlib=rb-4.0.3',
                                'alt' => 'Energy savings',
                                'name' => 'energy-prices.jpg'
                            ],
                            'pageUrl' => '/guides/energy-price-cap-changes',
                            'pageId' => null,
                            'author' => 'Michael Brown',
                            'date' => '2025-01-10',
                            'category' => 'Energy',
                            'readTime' => '4 min read',
                            'featured' => false
                        ],
                        [
                            'title' => 'Best Broadband Deals This Month',
                            'excerpt' => 'Compare the top broadband offers and find the perfect package for your home.',
                            'image' => [
                                'id' => 3,
                                'src' => 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?ixlib=rb-4.0.3',
                                'alt' => 'Broadband deals',
                                'name' => 'broadband.jpg'
                            ],
                            'pageUrl' => '/guides/best-broadband-deals',
                            'pageId' => null,
                            'author' => 'Emma Wilson',
                            'date' => '2025-01-08',
                            'category' => 'Broadband',
                            'readTime' => '6 min read',
                            'featured' => false
                        ]
                    ]
                ],
                'order' => 9
            ],

// List Block with Links
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'schemaType' => 'none',
                    'context' => 'default',
                    'items' => [
                        '<a href="/car-insurance">Compare Car Insurance</a> - Get quotes from over 100 providers',
                        '<a href="/home-insurance">Home Insurance Comparison</a> - Protect your property and contents',
                        '<a href="/energy">Switch Energy Supplier</a> - Save on your gas and electricity bills',
                        '<a href="/broadband">Find Better Broadband</a> - Faster speeds at lower prices',
                        '<a href="/travel-insurance">Travel Insurance Quotes</a> - Cover for your holidays',
                        '<a href="/credit-cards">Compare Credit Cards</a> - Find the right card for you'
                    ]
                ],
                'order' => 10
            ],

// Ordered List Block (Steps/How-to)
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ol',
                    'startIndex' => 1,
                    'schemaType' => 'steps',
                    'context' => 'sidebar',
                    'items' => [
                        'Enter your details in our <a href="/get-quote">quick quote form</a>',
                        'Compare quotes from top <a href="/providers">UK providers</a>',
                        'Choose the best deal for your needs',
                        'Switch online in minutes',
                        'Start saving money immediately'
                    ]
                ],
                'order' => 11
            ],
            [
                'type' => 'page-links',
                'data' => [
                    'columns' => 4,
                    'layout' => 'grid',
                    'showImages' => true,
                    'showDescriptions' => true,
                    'links' => [
                        [
                            'title' => 'More Resources',
                            'description' => 'Find out more about insurance, energy, broadband and more',
                            'imageUrl' => 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?ixlib=rb-4.0.3',
                            'pageUrl' => '/about',
                            'icon' => ''
                        ],
                        [
                            'title' => 'Link 2',
                            'description' => 'Find out more about insurance, energy, broadband and more',
                            'imageUrl' => 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?ixlib=rb-4.0.3',
                            'pageUrl' => '/about',
                            'icon' => ''
                        ],
                        [
                            'title' => 'Link 3',
                            'description' => 'Find out more about insurance, energy, broadband and more',
                            'imageUrl' => 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?ixlib=rb-4.0.3',
                            'pageUrl' => '/about',
                            'icon' => ''
                        ],
                        [
                            'title' => 'Link 4',
                            'description' => 'Find out more about insurance, energy, broadband and more',
                            'imageUrl' => 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?ixlib=rb-4.0.3',
                            'pageUrl' => '/about',
                            'icon' => ''
                        ]
                    ]
                ],
                'order' => 5
            ],

        ];

        /**
         * $validatedLinks = [];
         * foreach ($links as $link) {
         * if (empty($link['title'])) {
         * continue;
         * }
         *
         * $validatedLinks[] = [
         * 'title' => trim($link['title']),
         * 'description' => trim($link['description'] ?? ''),
         * 'imageUrl' => $link['imageUrl'] ?? '',
         * 'imageId' => $link['imageId'] ?? null,
         * 'pageUrl' => $link['pageUrl'] ?? '',
         * 'pageId' => $link['pageId'] ?? null,
         * 'icon' => $link['icon'] ?? ''
         * ];
         * }
         */

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
}