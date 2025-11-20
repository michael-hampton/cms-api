<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\CustomFieldDefinition;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\Site;
use App\Repositories\BlockRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;
use App\Services\BlockParserService;

class GoCompareSeeder extends Seeder
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
        $this->createSite();
        $this->createMenu();
        $this->createTags();
        $this->createCategories();
        $this->createCustomFields();
        $this->createHomepage();
        $this->createArticles();
        $this->createAboutPage();
        $this->createContactPage();
        $this->createArticleMenuItems();
    }

    private function createSite(): void
    {
        $this->site = Site::create([
            'name' => 'GoCompare - Compare & Save',
            'slug' => 'gocompare',
            'is_active' => true,
        ]);
    }

    private function createMenu(): void
    {
        $this->menu = Menu::create([
            'name' => 'Main Menu',
            'slug' => 'main-menu',
            'site_id' => $this->site->id,
            'is_active' => true,
        ]);
    }

    private function createTags(): void
    {
        $tags = [
            'featured', 'trending', 'best-buys', 'money-saving',
            'car-insurance', 'home-insurance', 'travel-insurance', 'pet-insurance',
            'life-insurance', 'health-insurance', 'business-insurance',
            'energy', 'broadband', 'mobile', 'credit-cards', 'loans',
            'mortgages', 'bank-accounts', 'savings', 'investments',
            'guides', 'reviews', 'comparison', 'deals',
            'award-winning', 'customer-choice', 'best-value',
            'new-customers', 'switching', 'renewal'
        ];

        foreach ($tags as $tagName) {
            $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
        }
    }

    private function createCategories(): void
    {
        $categories = [
            'Insurance' => [
                'Car Insurance' => ['Comprehensive', 'Third Party', 'Temporary', 'Young Drivers'],
                'Home Insurance' => ['Buildings', 'Contents', 'Combined', 'Landlord'],
                'Travel Insurance' => ['Single Trip', 'Annual Multi-Trip', 'Family', 'Winter Sports'],
                'Pet Insurance' => ['Dog Insurance', 'Cat Insurance', 'Multi-Pet'],
                'Life Insurance' => ['Term Life', 'Whole Life', 'Over 50s'],
                'Health Insurance' => ['Private Medical', 'Dental', 'Income Protection']
            ],
            'Money' => [
                'Credit Cards' => ['Balance Transfer', 'Purchase', 'Rewards', 'Bad Credit'],
                'Loans' => ['Personal Loans', 'Secured Loans', 'Car Loans'],
                'Mortgages' => ['First Time Buyer', 'Remortgage', 'Buy to Let'],
                'Bank Accounts' => ['Current Accounts', 'Savings Accounts', 'ISAs'],
                'Investing' => ['Stocks & Shares ISA', 'Pensions', 'Investment Platforms']
            ],
            'Utilities' => [
                'Energy' => ['Gas & Electric', 'Smart Meters', 'Green Energy'],
                'Broadband' => ['Fibre', 'Standard', 'Mobile Broadband'],
                'Mobile Phones' => ['Contract Phones', 'SIM Only', 'Pay As You Go'],
                'TV & Streaming' => ['TV Packages', 'Streaming Services']
            ],
            'Guides & Advice' => ['How to Guides', 'Money Saving Tips', 'Product Reviews', 'Industry News']
        ];

        $this->createCategoriesRecursively($categories);
    }

    private function createCategoriesRecursively(array $categories, ?int $parentId = null): void
    {
        foreach ($categories as $name => $children) {
            $category = $this->categoryRepository->findOrCreateByName($name, $this->site->id);
            if ($parentId) {
                $category->parent_id = $parentId;
                $category->save();
            }

            if (is_array($children)) {
                $this->createCategoriesRecursively($children, $category->id);
            }
        }
    }

    private function createCustomFields(): void
    {
        $fields = [
            ['key' => 'author_name', 'name' => 'Author Name', 'type' => 'text'],
            ['key' => 'author_bio', 'name' => 'Author Bio', 'type' => 'textarea'],
            ['key' => 'read_time', 'name' => 'Read Time (minutes)', 'type' => 'number'],
            ['key' => 'excerpt', 'name' => 'Article Excerpt', 'type' => 'textarea'],
            ['key' => 'product_type', 'name' => 'Product Type', 'type' => 'select', 'options' => '{"insurance":"Insurance","money":"Money","utilities":"Utilities"}'],
            ['key' => 'comparison_data', 'name' => 'Comparison Data', 'type' => 'textarea'],
            ['key' => 'partner_links', 'name' => 'Partner Links', 'type' => 'textarea'],
            ['key' => 'review_rating', 'name' => 'Review Rating', 'type' => 'number'],
            ['key' => 'price_from', 'name' => 'Price From', 'type' => 'text'],
            ['key' => 'last_updated', 'name' => 'Last Updated', 'type' => 'text']
        ];

        foreach ($fields as $field) {
            CustomFieldDefinition::create([
                'key' => $field['key'],
                'name' => $field['name'],
                'type' => $field['type'],
                'is_active' => true,
                'sort_order' => 10,
                'options' => $field['options'] ?? null,
                'site_id' => $this->site->id
            ]);
        }
    }

    private function createHomepage(): void
    {
        $page = Page::create([
            'title' => 'GoCompare - Compare Prices & Save Money',
            'page_type' => 'content',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'Compare Insurance, Money & Utilities | GoCompare',
            'meta_description' => 'Compare prices and save money on insurance, utilities, money and more. Get quotes from leading UK providers and switch in minutes.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Home',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Compare & Save Money',
                    'subtitle' => 'Get quotes from leading providers and switch in minutes. We compare insurance, utilities, money products and more.',
                    'ctaText' => 'Start Comparing',
                    'ctaUrl' => '#products',
                    'secondaryCtaText' => 'See How It Works',
                    'secondaryCtaUrl' => '#how-it-works',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Why Choose GoCompare?',
                    'stats' => [
                        ['number' => '20M+', 'label' => 'Quotes Provided', 'icon' => '🔍'],
                        ['number' => '500+', 'label' => 'Partners', 'icon' => '🤝'],
                        ['number' => '£450', 'label' => 'Average Saving', 'icon' => '💰'],
                        ['number' => '4.5★', 'label' => 'Customer Rating', 'icon' => '⭐']
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Compare Insurance',
                    'subtitle' => 'Save hundreds on your insurance',
                    'level' => 2
                ],
                'order' => 3
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'layout' => 'grid',
                    'columns' => 4,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'showFeatures' => true,
                    'pages' => [
                        [
                            'title' => 'Car Insurance',
                            'slug' => 'car-insurance',
                            'excerpt' => 'Compare quotes from over 100 providers. Save up to £350 on your car insurance.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Car Insurance'
                            ],
                            'badge' => [
                                'text' => 'Most Popular',
                                'color' => 'primary'
                            ],
                            'features' => [
                                'Compare 100+ providers',
                                'Get quotes in minutes',
                                'Save up to £350'
                            ]
                        ],
                        [
                            'title' => 'Home Insurance',
                            'slug' => 'home-insurance',
                            'excerpt' => 'Protect your home and contents with comprehensive cover from leading insurers.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1570129477492-45c003edd2be?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Home Insurance'
                            ],
                            'badge' => [
                                'text' => 'Save Money',
                                'color' => 'success'
                            ],
                            'features' => [
                                'Buildings & contents',
                                'Flexible excess options',
                                'Quick claims process'
                            ]
                        ],
                        [
                            'title' => 'Travel Insurance',
                            'slug' => 'travel-insurance',
                            'excerpt' => 'Stay protected on holiday with comprehensive travel insurance cover.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Travel Insurance'
                            ],
                            'features' => [
                                'Single & annual trips',
                                'Medical cover included',
                                'Instant cover'
                            ]
                        ],
                        [
                            'title' => 'Pet Insurance',
                            'slug' => 'pet-insurance',
                            'excerpt' => 'Keep your furry friends healthy with comprehensive pet insurance.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1450778869180-41d0601e046e?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Pet Insurance'
                            ],
                            'features' => [
                                'Dogs & cats covered',
                                'Vet fee cover',
                                'Lifetime policies'
                            ]
                        ]
                    ]
                ],
                'order' => 4
            ],
            [
                'type' => 'divider',
                'data' => [
                    'style' => 'solid'
                ],
                'order' => 5
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Compare Money Products',
                    'subtitle' => 'Find better deals on credit cards, loans and more',
                    'level' => 2
                ],
                'order' => 6
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'layout' => 'grid',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'pages' => [
                        [
                            'title' => 'Credit Cards',
                            'slug' => 'credit-cards',
                            'excerpt' => 'Compare credit cards and find the best deals for balance transfers, purchases and rewards.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Credit Cards'
                            ],
                            'badge' => [
                                'text' => '0% Deals',
                                'color' => 'warning'
                            ]
                        ],
                        [
                            'title' => 'Personal Loans',
                            'slug' => 'loans',
                            'excerpt' => 'Compare personal loans from leading lenders. Get the best rates for your circumstances.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1579621970563-ebec7560ff3e?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Personal Loans'
                            ]
                        ],
                        [
                            'title' => 'Bank Accounts',
                            'slug' => 'bank-accounts',
                            'excerpt' => 'Find the right current or savings account for you with our comparison tool.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1601597111158-2fceff292cdc?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Bank Accounts'
                            ]
                        ]
                    ]
                ],
                'order' => 7
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Compare Utilities',
                    'subtitle' => 'Save on energy, broadband and mobile',
                    'level' => 2
                ],
                'order' => 8
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'layout' => 'grid',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'pages' => [
                        [
                            'title' => 'Energy',
                            'slug' => 'energy',
                            'excerpt' => 'Compare gas and electricity prices. Switch to a cheaper tariff and save money.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Energy'
                            ],
                            'badge' => [
                                'text' => 'Save £300',
                                'color' => 'success'
                            ]
                        ],
                        [
                            'title' => 'Broadband',
                            'slug' => 'broadband',
                            'excerpt' => 'Find the fastest, cheapest broadband deals. Compare packages from all major providers.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Broadband'
                            ]
                        ],
                        [
                            'title' => 'Mobile Phones',
                            'slug' => 'mobile',
                            'excerpt' => 'Compare mobile phone contracts and SIM only deals. Get the best data allowance.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Mobile Phones'
                            ]
                        ]
                    ]
                ],
                'order' => 9
            ],
            [
                'type' => 'info',
                'data' => [
                    'infoType' => 'info',
                    'description' => '💡 Top Tip: Switching is easy and you could save hundreds. Most switches take just a few minutes online.'
                ],
                'order' => 10
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'GoCompare saved me £425 on my car insurance. The comparison took 5 minutes and switching was seamless.',
                    'attribution' => 'Sarah M, Manchester'
                ],
                'order' => 11
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Get Money Saving Tips',
                    'subtitle' => 'Sign up for our newsletter and receive exclusive deals and money-saving advice',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => false,
                    'showSubject' => false,
                    'showMessage' => false,
                    'submitButtonText' => 'Subscribe Free',
                    'requireName' => true,
                    'requireEmail' => true
                ],
                'order' => 12
            ]
        ];

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

    private function createArticles(): void
    {
        $articles = [
            // Article 1: Car Insurance
            [
                'title' => 'Car Insurance Guide: Everything You Need to Know',
                'slug' => 'car-insurance',
                'tags' => ['featured', 'car-insurance', 'guides', 'insurance'],
                'categories' => ['Insurance', 'Car Insurance'],
                'custom_fields' => [
                    'author_name' => 'James Thompson',
                    'author_bio' => 'Insurance expert with 15 years experience in the UK market.',
                    'read_time' => 10,
                    'excerpt' => 'Everything you need to know about car insurance in the UK. Compare quotes, understand cover types, and save money.',
                    'product_type' => 'insurance'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Car Insurance',
                            'caption' => 'Find the right car insurance for your needs',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Car insurance is a legal requirement in the UK, but finding the right policy at the best price can be confusing. This comprehensive guide explains everything you need to know about car insurance, from understanding different types of cover to finding ways to reduce your premium.',
                                'We\'ll walk you through the key factors that affect your car insurance cost, explain what\'s included in different policies, and share insider tips on how to save money without compromising on cover.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Types of Car Insurance',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Comparing Cover Levels',
                            'productA' => 'Third Party Only',
                            'productB' => 'Comprehensive',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Cost',
                                    'items' => [
                                        ['value' => 'Cheapest option'],
                                        ['value' => 'More expensive but better value']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Your Car Damage',
                                    'items' => [
                                        ['value' => 'Not covered'],
                                        ['value' => 'Fully covered']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Other Vehicles',
                                    'items' => [
                                        ['value' => 'Covered'],
                                        ['value' => 'Covered']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Theft & Fire',
                                    'items' => [
                                        ['value' => 'Not included'],
                                        ['value' => 'Included']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Low-value cars'],
                                        ['value' => 'Most drivers']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Third Party Only is the legal minimum in the UK. It covers damage to other vehicles and property, but not your own car. Despite being the most basic cover, it\'s often not much cheaper than comprehensive insurance.',
                                'Comprehensive insurance covers damage to your own vehicle as well as third parties. It typically includes theft, fire damage, and vandalism. Most drivers find comprehensive insurance offers the best value and peace of mind.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'How to Save Money on Car Insurance',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Compare quotes from multiple providers - you could save hundreds',
                                'Increase your voluntary excess - but only to an amount you can afford',
                                'Add an experienced driver as a named driver',
                                'Pay annually instead of monthly - avoid interest charges',
                                'Build your no-claims bonus - don\'t make small claims',
                                'Install a black box - prove you\'re a safe driver',
                                'Limit your mileage - only pay for the miles you need',
                                'Improve security - fit an approved alarm or immobiliser'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'I saved £425 by comparing car insurance quotes on GoCompare. The process was quick and easy, and my new insurer provided better cover for less money.',
                            'attribution' => 'Michael R, Leeds'
                        ]
                    ]
                ]
            ],

            // Article 2: Home Insurance
            [
                'title' => 'Home Insurance Explained: Buildings vs Contents Cover',
                'slug' => 'home-insurance',
                'tags' => ['featured', 'home-insurance', 'guides', 'insurance'],
                'categories' => ['Insurance', 'Home Insurance'],
                'custom_fields' => [
                    'author_name' => 'Emma Wilson',
                    'author_bio' => 'Property insurance specialist with expertise in home and landlord insurance.',
                    'read_time' => 8,
                    'excerpt' => 'Understand the difference between buildings and contents insurance. Learn what\'s covered and how to save money.',
                    'product_type' => 'insurance'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1570129477492-45c003edd2be?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Home Insurance',
                            'caption' => 'Protect your home and belongings',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Home insurance protects one of your biggest investments - your property. But with buildings insurance, contents insurance, and combined policies available, it can be confusing to know what you need.',
                                'This guide breaks down everything you need to know about home insurance, helping you choose the right cover at the best price.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Buildings vs Contents Insurance',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'What\'s the Difference?',
                            'productA' => 'Buildings Insurance',
                            'productB' => 'Contents Insurance',
                            'comparisons' => [
                                [
                                    'subtitle' => 'What It Covers',
                                    'items' => [
                                        ['value' => 'Structure of your home'],
                                        ['value' => 'Your belongings']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Examples',
                                    'items' => [
                                        ['value' => 'Walls, roof, floors, windows'],
                                        ['value' => 'Furniture, electronics, clothes']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Who Needs It',
                                    'items' => [
                                        ['value' => 'Homeowners (usually required)'],
                                        ['value' => 'Everyone (optional but recommended)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Average Cost',
                                    'items' => [
                                        ['value' => '£150-300 per year'],
                                        ['value' => '£100-200 per year']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Includes',
                                    'items' => [
                                        ['value' => 'Outbuildings, garages, driveways'],
                                        ['value' => 'Valuables, personal possessions']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What Buildings Insurance Covers',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Fire, flood, and storm damage to the structure',
                                'Burst pipes and water damage',
                                'Subsidence and ground movement',
                                'Theft or vandalism damage to the building',
                                'Falling trees or branches',
                                'Impact from vehicles',
                                'Permanent fixtures like kitchens and bathrooms'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What Contents Insurance Covers',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Furniture and soft furnishings',
                                'Electronics and appliances',
                                'Clothing and personal items',
                                'Jewellery and valuables',
                                'Sports equipment',
                                'Garden furniture',
                                'Money and credit cards (limited amount)'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Money Saving Tip',
                            'paragraphs' => [
                                'Buying buildings and contents insurance together as a combined policy typically saves 10-15% compared to buying them separately. Many insurers offer this discount to encourage customers to consolidate their cover.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'How Much Cover Do You Need?',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'For buildings insurance, you need to cover the rebuild cost of your home - not its market value. Rebuild costs are often lower than market value. Use the RICS rebuild calculator to get an accurate estimate.',
                                'For contents insurance, add up the replacement cost of everything in your home. Don\'t forget items in lofts, garages, and sheds. Most people underestimate by 20-30%.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'After a flood damaged our home, our buildings insurance covered all repairs. We were so grateful we had adequate cover in place.',
                            'attribution' => 'David P, York'
                        ]
                    ]
                ]
            ],

            // Article 3: Travel Insurance
            [
                'title' => 'Travel Insurance Guide: Single Trip vs Annual Multi-Trip',
                'slug' => 'travel-insurance',
                'tags' => ['travel-insurance', 'guides', 'insurance', 'holiday'],
                'categories' => ['Insurance', 'Travel Insurance'],
                'custom_fields' => [
                    'author_name' => 'Sarah Mitchell',
                    'author_bio' => 'Travel insurance expert and frequent traveller.',
                    'read_time' => 7,
                    'excerpt' => 'Everything you need to know about travel insurance. Compare single trip and annual policies.',
                    'product_type' => 'insurance'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Travel Insurance',
                            'caption' => 'Stay protected on your travels',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Travel insurance is essential for protecting yourself against unexpected costs while abroad. From medical emergencies to cancelled flights, the right policy gives you peace of mind.',
                                'This guide explains the different types of travel insurance and helps you choose the right cover for your needs.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Single Trip vs Annual Multi-Trip',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Which Policy is Right for You?',
                            'productA' => 'Single Trip',
                            'productB' => 'Annual Multi-Trip',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'One holiday per year'],
                                        ['value' => '2+ trips per year']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Typical Cost',
                                    'items' => [
                                        ['value' => '£15-50 per trip'],
                                        ['value' => '£40-150 per year']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Trip Duration',
                                    'items' => [
                                        ['value' => 'Any length'],
                                        ['value' => 'Usually max 31 days per trip']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Flexibility',
                                    'items' => [
                                        ['value' => 'Tailor to specific trip'],
                                        ['value' => 'Spontaneous trips covered']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Value',
                                    'items' => [
                                        ['value' => 'Good for infrequent travel'],
                                        ['value' => 'Better value for 2+ trips']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What Travel Insurance Covers',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Emergency medical treatment abroad',
                                'Repatriation to the UK if seriously ill',
                                'Cancelled or curtailed trips',
                                'Lost, stolen or damaged baggage',
                                'Delayed departure compensation',
                                'Personal liability cover',
                                'Legal expenses',
                                'Missed departure cover'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Important: Always declare pre-existing medical conditions when buying travel insurance. Failure to disclose conditions could invalidate your entire policy.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Do I Need Winter Sports Cover?',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Standard travel insurance usually excludes winter sports like skiing and snowboarding. If you\'re hitting the slopes, you need to add winter sports cover to your policy.',
                                'Winter sports cover typically costs an extra £20-40 per trip and includes ski rescue, piste closure, and equipment damage.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'When I broke my leg skiing in France, my travel insurance covered the £15,000 medical bill and repatriation flight. Best £45 I ever spent.',
                            'attribution' => 'Tom H, Manchester'
                        ]
                    ]
                ]
            ],

            // Article 4: Energy Comparison
            [
                'title' => 'How to Compare Energy Prices and Switch Suppliers',
                'slug' => 'energy',
                'tags' => ['featured', 'energy', 'utilities', 'money-saving'],
                'categories' => ['Utilities', 'Energy'],
                'custom_fields' => [
                    'author_name' => 'Robert Davies',
                    'author_bio' => 'Energy market analyst and consumer rights advocate.',
                    'read_time' => 9,
                    'excerpt' => 'Save hundreds on your energy bills by comparing gas and electricity prices. Learn how to switch suppliers easily.',
                    'product_type' => 'utilities'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Energy Bills',
                            'caption' => 'Cut your energy costs with the right tariff',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Energy bills are one of the biggest household expenses, but many people pay more than they need to. Comparing energy prices and switching to a cheaper tariff could save you hundreds of pounds per year.',
                                'This guide explains how to compare energy prices, what to look for in a tariff, and how to switch suppliers without any hassle.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Energy Switching Stats',
                            'stats' => [
                                ['number' => '£300+', 'label' => 'Average Annual Saving', 'icon' => '💰'],
                                ['number' => '17', 'label' => 'Days to Complete Switch', 'icon' => '📅'],
                                ['number' => '70%', 'label' => 'Never Switch Supplier', 'icon' => '😮'],
                                ['number' => '50+', 'label' => 'Energy Suppliers', 'icon' => '⚡']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Fixed vs Variable Tariffs',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Choosing the Right Tariff Type',
                            'productA' => 'Fixed Rate',
                            'productB' => 'Variable Rate',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Price Guarantee',
                                    'items' => [
                                        ['value' => 'Fixed for contract term'],
                                        ['value' => 'Can change anytime']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Contract Length',
                                    'items' => [
                                        ['value' => '12-24 months typical'],
                                        ['value' => 'Rolling 30 days']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Exit Fees',
                                    'items' => [
                                        ['value' => 'Often applies (£30-50)'],
                                        ['value' => 'None']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Price certainty'],
                                        ['value' => 'Maximum flexibility']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Risk',
                                    'items' => [
                                        ['value' => 'Locked in if prices fall'],
                                        ['value' => 'Exposed to price rises']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'How to Compare Energy Prices',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Find your latest energy bill - you\'ll need your annual usage',
                                'Enter your postcode and usage into our comparison tool',
                                'Review the cheapest tariffs available in your area',
                                'Check for exit fees on your current contract',
                                'Consider supplier reputation and customer service ratings',
                                'Choose your new tariff and complete the switch online',
                                'Your new supplier handles everything - no interruption to supply'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Top Money Saving Tips',
                            'paragraphs' => [
                                'Switch every year: Loyalty doesn\'t pay in energy. The best deals are always for new customers.',
                                'Pay by direct debit: Save £50-100 per year compared to payment on receipt.',
                                'Get a smart meter: See your energy usage in real-time and identify where you can save.',
                                'Consider green energy: Renewable tariffs are often competitively priced and better for the environment.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Understanding Your Energy Bill',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Charge Type', 'What It Is', 'Typical %'],
                                ['Unit Rate', 'Cost per kWh of energy used', '75-80%'],
                                ['Standing Charge', 'Daily fixed charge', '15-20%'],
                                ['VAT', 'Government tax at 5%', '5%']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'I was with the same supplier for 8 years. After comparing prices on GoCompare, I switched and now save £340 per year. It took 10 minutes online.',
                            'attribution' => 'Jennifer L, Bristol'
                        ]
                    ]
                ]
            ],

            // Article 5: Credit Cards
            [
                'title' => 'Credit Cards Explained: Balance Transfer vs Purchase Cards',
                'slug' => 'credit-cards',
                'tags' => ['featured', 'credit-cards', 'money', 'guides'],
                'categories' => ['Money', 'Credit Cards'],
                'custom_fields' => [
                    'author_name' => 'Andrew Foster',
                    'author_bio' => 'Personal finance expert specializing in credit and lending.',
                    'read_time' => 11,
                    'excerpt' => 'Find the right credit card for your needs. Compare balance transfer, purchase, and rewards cards.',
                    'product_type' => 'money'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Credit Cards',
                            'caption' => 'Choose the right credit card for your needs',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Credit cards can be a useful financial tool when used responsibly. Whether you want to spread the cost of purchases, transfer existing debt, or earn rewards on spending, there\'s a card designed for your needs.',
                                'This guide explains the different types of credit cards available and helps you choose the right one for your circumstances.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Types of Credit Cards',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Balance Transfer vs Purchase Cards',
                            'productA' => 'Balance Transfer',
                            'productB' => 'Purchase Card',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Clearing existing debt'],
                                        ['value' => 'New purchases']
                                    ]
                                ],
                                [
                                    'subtitle' => '0% Period',
                                    'items' => [
                                        ['value' => 'Up to 29 months'],
                                        ['value' => 'Up to 21 months']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Transfer Fee',
                                    'items' => [
                                        ['value' => '2-3% of transfer amount'],
                                        ['value' => 'No fee']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Purchase Rate',
                                    'items' => [
                                        ['value' => 'Usually high (20%+)'],
                                        ['value' => '0% during offer period']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Ideal User',
                                    'items' => [
                                        ['value' => 'Has existing card debt'],
                                        ['value' => 'Making large purchase']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'How Balance Transfer Cards Work',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Balance transfer cards allow you to move existing credit card debt from one or more cards to a new card with a 0% interest period. This means all your payments go towards clearing the debt, not paying interest.',
                                'For example, if you owe £3,000 on a card charging 20% APR, you pay £600 per year in interest. Transfer to a 0% card and you save that £600 while paying off the balance.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Check your credit score - better scores get better offers',
                                'Compare balance transfer cards with the longest 0% periods',
                                'Calculate if the transfer fee is worth it vs current interest',
                                'Apply for the card that saves you the most money',
                                'Transfer your balances within the specified time frame',
                                'Set up a direct debit to clear the balance before 0% ends',
                                'Avoid new purchases - they usually don\'t get 0% rate'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Important Warning',
                            'paragraphs' => [
                                'Balance transfer cards only save money if you clear the debt before the 0% period ends. After that, interest rates jump to 20%+ APR.',
                                'Calculate how much you need to pay each month to clear the balance in time. If you can\'t afford it, look for a card with a longer 0% period, even if it has a higher transfer fee.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'How to Use Credit Cards Responsibly',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Only spend what you can afford to repay',
                                'Pay off your balance in full each month if possible',
                                'Never withdraw cash - fees and interest rates are very high',
                                'Set up a direct debit to avoid missed payments',
                                'Keep your credit utilization below 30% of your limit',
                                'Don\'t apply for multiple cards in a short period',
                                'Check your credit report regularly for errors'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Rewards Credit Cards',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'If you pay off your balance in full each month, rewards cards let you earn cashback, points, or airmiles on your spending. Typical cashback rates are 0.5-1% on purchases.',
                                'However, rewards cards usually charge higher interest rates if you carry a balance. Only use rewards cards if you\'re disciplined enough to pay in full monthly.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'I transferred £5,000 of debt to a 0% balance transfer card. By paying £220 per month, I cleared it all in 23 months and saved over £2,000 in interest.',
                            'attribution' => 'Rebecca S, Birmingham'
                        ]
                    ]
                ]
            ],
            // Article 6: Broadband
            [
                'title' => 'Broadband Comparison Guide: Fibre vs Standard Broadband',
                'slug' => 'broadband',
                'tags' => ['featured', 'broadband', 'utilities', 'guides'],
                'categories' => ['Utilities', 'Broadband'],
                'custom_fields' => [
                    'author_name' => 'Rachel Green',
                    'author_bio' => 'Technology and utilities expert with 10 years in the telecoms industry.',
                    'read_time' => 8,
                    'excerpt' => 'Compare broadband deals and find the fastest, cheapest internet for your home. Understand the difference between fibre and standard broadband.',
                    'product_type' => 'utilities'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Broadband Internet',
                            'caption' => 'Find the perfect broadband deal for your needs',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Choosing the right broadband package can be confusing with so many providers and speed options available. Whether you\'re streaming Netflix, working from home, or just browsing the web, getting the right speed at the right price is essential.',
                                'This guide explains the different types of broadband, what speeds you actually need, and how to find the best deal for your household.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Types of Broadband',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Standard vs Fibre Broadband',
                            'productA' => 'Standard Broadband',
                            'productB' => 'Fibre Broadband',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Speed',
                                    'items' => [
                                        ['value' => 'Up to 17 Mbps'],
                                        ['value' => 'Up to 900 Mbps']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Technology',
                                    'items' => [
                                        ['value' => 'Copper phone lines'],
                                        ['value' => 'Fibre optic cables']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Typical Cost',
                                    'items' => [
                                        ['value' => '£20-25 per month'],
                                        ['value' => '£25-50 per month']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Light browsing, email'],
                                        ['value' => 'Streaming, gaming, work from home']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Availability',
                                    'items' => [
                                        ['value' => 'Nationwide'],
                                        ['value' => '97% of UK homes']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What Speed Do You Need?',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Usage Type', 'Recommended Speed', 'Best Connection'],
                                ['Browsing & email', '10 Mbps', 'Standard broadband'],
                                ['HD streaming (1 device)', '5-10 Mbps', 'Standard broadband'],
                                ['HD streaming (multiple devices)', '25-35 Mbps', 'Fibre broadband'],
                                ['4K streaming', '25 Mbps per device', 'Fibre broadband'],
                                ['Online gaming', '15-25 Mbps', 'Fibre broadband'],
                                ['Working from home', '20-40 Mbps', 'Fibre broadband'],
                                ['Large household (5+ people)', '50-100 Mbps', 'Fast fibre broadband']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'How to Save Money on Broadband',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Compare providers in your area - prices vary significantly',
                                'Check for new customer deals - discounts of 50% are common',
                                'Consider contract length - longer contracts often have better rates',
                                'Bundle with TV or phone - packages can save £10-20 per month',
                                'Negotiate at renewal - threaten to switch for loyalty discounts',
                                'Check you\'re not paying for speed you don\'t need',
                                'Look for cashback offers - some sites offer £50-100 cashback'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Broadband in the UK',
                            'stats' => [
                                ['number' => '67 Mbps', 'label' => 'Average Speed', 'icon' => '⚡'],
                                ['number' => '97%', 'label' => 'Fibre Coverage', 'icon' => '📶'],
                                ['number' => '£28', 'label' => 'Average Monthly Cost', 'icon' => '💷'],
                                ['number' => '12', 'label' => 'Major Providers', 'icon' => '🏢']
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Switching is Easy',
                            'paragraphs' => [
                                'Worried about switching broadband? Don\'t be! Your new provider handles the entire process for you. There\'s no interruption to your service, and your switch will complete within 2 weeks.',
                                'If you\'re moving to a new provider using the Openreach network (BT, Sky, TalkTalk, Plusnet, etc.), they\'ll handle everything through an automatic process. You won\'t lose internet access during the switch.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'I was paying £45 for standard broadband. After comparing on GoCompare, I switched to fibre for £27 per month. Five times faster and £18 cheaper!',
                            'attribution' => 'Paul T, Southampton'
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => '💡 Pro Tip: Set a reminder for 2 months before your contract ends. This gives you time to compare deals and avoid expensive out-of-contract rates.'
                        ]
                    ]
                ]
            ],

// Article 7: Pet Insurance
            [
                'title' => 'Pet Insurance Guide: Protect Your Furry Friends',
                'slug' => 'pet-insurance',
                'tags' => ['pet-insurance', 'insurance', 'guides', 'best-buys'],
                'categories' => ['Insurance', 'Pet Insurance'],
                'custom_fields' => [
                    'author_name' => 'Laura Watson',
                    'author_bio' => 'Veterinary insurance specialist and pet owner.',
                    'read_time' => 9,
                    'excerpt' => 'Everything you need to know about pet insurance. Compare policies, understand cover levels, and find the best protection for your pet.',
                    'product_type' => 'insurance'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1450778869180-41d0601e046e?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Pet Insurance',
                            'caption' => 'Keep your pets healthy and protected',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Vet bills can be eye-wateringly expensive. A single operation can cost thousands of pounds, and ongoing treatment for chronic conditions adds up quickly. Pet insurance gives you peace of mind that you can afford the best care for your beloved companion.',
                                'This guide explains the different types of pet insurance, what to look for in a policy, and how to find cover that offers the best value for your pet\'s needs.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'The Cost of Pet Care',
                            'stats' => [
                                ['number' => '£3,000', 'label' => 'Average Cruciate Ligament Surgery', 'icon' => '🏥'],
                                ['number' => '£2,000+', 'label' => 'Cancer Treatment', 'icon' => '💊'],
                                ['number' => '£800', 'label' => 'Broken Bone Treatment', 'icon' => '🦴'],
                                ['number' => '£65', 'label' => 'Average Monthly Premium (Dog)', 'icon' => '💰']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Types of Pet Insurance',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Lifetime vs Time-Limited Cover',
                            'productA' => 'Lifetime Cover',
                            'productB' => 'Time-Limited Cover',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Coverage Duration',
                                    'items' => [
                                        ['value' => 'Entire life of pet'],
                                        ['value' => '12 months per condition']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Annual Limit',
                                    'items' => [
                                        ['value' => 'Resets each year'],
                                        ['value' => 'One-time limit per condition']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Chronic Conditions',
                                    'items' => [
                                        ['value' => 'Covered every year'],
                                        ['value' => 'Only covered for 12 months']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Typical Cost',
                                    'items' => [
                                        ['value' => '£40-80 per month'],
                                        ['value' => '£20-40 per month']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Complete peace of mind'],
                                        ['value' => 'Budget-conscious owners']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What Does Pet Insurance Cover?',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Vet fees for illness and injury',
                                'Emergency treatment and hospitalization',
                                'Surgery and specialist care',
                                'Prescription medications',
                                'Diagnostic tests (X-rays, blood tests, scans)',
                                'Physiotherapy and alternative treatments',
                                'Dental illness (not routine care)',
                                'Death from illness or injury',
                                'Third party liability (dogs only)',
                                'Overseas travel cover (up to 90 days)'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What\'s NOT Covered?',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Pre-existing conditions',
                                'Routine vaccinations and check-ups',
                                'Neutering or spaying',
                                'Pregnancy and breeding costs',
                                'Cosmetic procedures',
                                'Behavioral issues',
                                'Dental hygiene and cleaning',
                                'Conditions during exclusion period (first 14 days)'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Important: Always declare your pet\'s full medical history when applying. Failure to disclose pre-existing conditions will invalidate your policy and any claims will be rejected.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Choosing the Right Excess',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Pet insurance excess comes in two types: a fixed amount per condition (e.g., £99) and a percentage of the claim (typically 10-20%). Some policies have both.',
                                'Higher excesses mean lower premiums, but you\'ll pay more when you claim. Choose an excess you can comfortably afford if your pet needs treatment. For older pets, be aware that age-related excesses may increase the amount you pay.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Money Saving Tips',
                            'paragraphs' => [
                                'Insure your pet young: Premiums are much lower for young, healthy pets. Wait until they\'re older or have health issues and you\'ll pay significantly more.',
                                'Multi-pet discount: Many insurers offer 10-15% discount when you insure multiple pets.',
                                'Pay annually: Save around 5% by paying your premium upfront rather than monthly.',
                                'Microchip your pet: Some insurers offer discounts for microchipped pets.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Dog vs Cat Insurance Costs',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Pet Type', 'Average Annual Cost', 'Cover Level'],
                                ['Cat - Accident Only', '£60-100', '£1,000-2,000'],
                                ['Cat - Time Limited', '£120-180', '£2,000-4,000'],
                                ['Cat - Lifetime', '£180-300', '£5,000-10,000+'],
                                ['Dog - Accident Only', '£120-200', '£1,000-2,000'],
                                ['Dog - Time Limited', '£200-400', '£2,000-4,000'],
                                ['Dog - Lifetime', '£400-900', '£5,000-15,000+']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'My dog needed emergency surgery for a twisted stomach. The bill was £4,200. Thank goodness for our pet insurance - we only paid the £99 excess.',
                            'attribution' => 'Mark D, Edinburgh'
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => '💡 Did you know? Lifetime cover is recommended by most vets as it provides the most comprehensive protection for long-term conditions like diabetes, arthritis, and allergies.'
                        ]
                    ]
                ]
            ],

// Article 8: Life Insurance
            [
                'title' => 'Life Insurance Explained: Term Life vs Whole Life Cover',
                'slug' => 'life-insurance',
                'tags' => ['life-insurance', 'insurance', 'guides', 'money'],
                'categories' => ['Insurance', 'Life Insurance'],
                'custom_fields' => [
                    'author_name' => 'Daniel Matthews',
                    'author_bio' => 'Life insurance specialist with 20 years experience in financial protection.',
                    'read_time' => 10,
                    'excerpt' => 'Understand life insurance and find the right cover for your family. Compare term life, whole life, and over 50s policies.',
                    'product_type' => 'insurance'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1511632765486-a01980e01a18?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Life Insurance',
                            'caption' => 'Protect your family\'s financial future',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Life insurance provides financial protection for your loved ones if you die. It ensures your family can maintain their lifestyle, pay the mortgage, and cover everyday expenses without your income.',
                                'Choosing the right type and amount of life insurance is crucial. This guide explains the different types of policies available and helps you determine how much cover you need.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Life Insurance in the UK',
                            'stats' => [
                                ['number' => '31M', 'label' => 'People With Life Insurance', 'icon' => '👥'],
                                ['number' => '£12', 'label' => 'Average Monthly Cost', 'icon' => '💷'],
                                ['number' => '£200K', 'label' => 'Average Payout', 'icon' => '💰'],
                                ['number' => '13%', 'label' => 'Decline in Premiums (Last 10 Years)', 'icon' => '📉']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Types of Life Insurance',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Term Life vs Whole Life Insurance',
                            'productA' => 'Term Life Insurance',
                            'productB' => 'Whole of Life Insurance',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Coverage Period',
                                    'items' => [
                                        ['value' => 'Fixed term (10-40 years)'],
                                        ['value' => 'Entire lifetime']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Payout',
                                    'items' => [
                                        ['value' => 'Only if you die during term'],
                                        ['value' => 'Guaranteed payout eventually']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Cost',
                                    'items' => [
                                        ['value' => 'Much cheaper'],
                                        ['value' => 'Significantly more expensive']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Protecting mortgage & income'],
                                        ['value' => 'Inheritance planning']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Typical Example',
                                    'items' => [
                                        ['value' => '£15/month for £200k cover'],
                                        ['value' => '£150/month for £50k cover']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'How Much Life Insurance Do You Need?',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'A good rule of thumb is to have cover worth 10 times your annual income. This ensures your family can maintain their lifestyle without your earnings.',
                                'However, you should also consider specific financial obligations like your mortgage, other debts, children\'s education costs, and funeral expenses. Many people choose cover that pays off the mortgage plus an additional lump sum for living expenses.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Calculate your annual income and multiply by 10',
                                'Add your outstanding mortgage balance',
                                'Add other debts (loans, credit cards, car finance)',
                                'Consider children\'s future costs (university, weddings)',
                                'Add funeral costs (typically £4,000-5,000)',
                                'Subtract existing savings and investments',
                                'The result is your recommended cover amount'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Level vs Decreasing Term Insurance',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Feature', 'Level Term', 'Decreasing Term'],
                                ['Payout Amount', 'Stays the same', 'Reduces over time'],
                                ['Cost', 'More expensive', 'Cheaper'],
                                ['Best For', 'Income protection', 'Mortgage protection'],
                                ['Example Premium', '£25/month', '£15/month'],
                                ['Example Payout (Year 1)', '£250,000', '£250,000'],
                                ['Example Payout (Year 15)', '£250,000', '£125,000']
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Level term insurance provides the same payout throughout the policy term. It\'s ideal if you want to replace your income or leave a fixed sum for your family.',
                                'Decreasing term insurance reduces in line with your mortgage balance. It\'s cheaper because the payout decreases over time, making it perfect for mortgage protection.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Critical Illness Cover',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Critical illness cover pays out a lump sum if you\'re diagnosed with a serious illness like cancer, heart attack, or stroke. You can add it to life insurance for comprehensive protection.',
                                'This type of cover is valuable because it pays out while you\'re alive, helping you cover medical costs, adapt your home, or simply maintain your lifestyle while unable to work.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Cancer (all malignant types)',
                                'Heart attack',
                                'Stroke',
                                'Multiple sclerosis',
                                'Kidney failure',
                                'Major organ transplant',
                                'Parkinson\'s disease',
                                'Paralysis',
                                'Coronary artery bypass'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Important Considerations',
                            'paragraphs' => [
                                'Medical History: Be completely honest about your medical history and lifestyle. Non-disclosure can invalidate your policy.',
                                'Smoker Status: Smokers pay 50-120% more for life insurance. If you quit smoking for 12 months, you can reapply for non-smoker rates.',
                                'Review Regularly: Your life insurance needs change as your circumstances change. Review your cover every 5 years or after major life events.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Life Insurance in Trust',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Writing your life insurance policy in trust means the payout goes directly to your beneficiaries without forming part of your estate. This has two major benefits: it avoids inheritance tax and speeds up the payout process.',
                                'Most insurers offer this service free of charge. It\'s a simple process that can save your family thousands in tax and weeks of waiting for probate.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'After my husband passed away unexpectedly, our life insurance payout cleared the mortgage and provided enough for me and the children to maintain our home. It was devastating, but at least we had financial security.',
                            'attribution' => 'Anonymous, London'
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => '💡 Top Tip: Life insurance is cheapest when you\'re young and healthy. The average 30-year-old pays £12/month for £200,000 cover. Wait until you\'re 40 and the same cover costs £20/month.'
                        ]
                    ]
                ]
            ]
        ];

        foreach ($articles as $articleData) {
            $this->createArticle($articleData);
        }
    }

    private function createArticle(array $data): void
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => $data['title'] . ' - GoCompare',
            'meta_description' => $data['custom_fields']['excerpt'] ?? '',
            'site_id' => $this->site->id,
        ]);

        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
            $page->tags(true)->attach($tag->id);
        }

        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, $this->site->id);
            $page->categories(true)->attach($category->id);
        }

        foreach ($data['custom_fields'] as $key => $value) {
            $fieldDef = CustomFieldDefinition::where('key', $key)->first();
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

    private function createAboutPage(): void
    {
        $page = Page::create([
            'title' => 'About GoCompare',
            'page_type' => 'content',
            'slug' => 'about',
            'status' => 'published',
            'meta_title' => 'About Us - GoCompare',
            'meta_description' => 'Learn about GoCompare - the UK\'s leading comparison service helping millions save money every year.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'About',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'About GoCompare',
                    'subtitle' => 'Helping millions of people compare and save money since 2006',
                    'ctaText' => 'Our Story',
                    'ctaUrl' => '#story',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'GoCompare is one of the UK\'s leading comparison websites, helping people compare prices on insurance, utilities, financial products and more. Since launching in 2006, we\'ve provided over 20 million quotes and helped millions of people save money.',
                        'We believe everyone deserves access to the best deals. Our free comparison service searches hundreds of providers to find you the right product at the right price. Whether you\'re looking for car insurance, energy deals, or a credit card, we make it quick and easy to compare.',
                        'Based in Newport, Wales, we\'re proud to be part of the UK\'s comparison market, employing hundreds of people and working with over 500 partners to bring you the widest choice and best value.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'GoCompare by Numbers',
                    'stats' => [
                        ['number' => '20M+', 'label' => 'Quotes Provided', 'icon' => '📊'],
                        ['number' => '500+', 'label' => 'Partner Providers', 'icon' => '🤝'],
                        ['number' => '18', 'label' => 'Years Experience', 'icon' => '⏰'],
                        ['number' => '4.5★', 'label' => 'Trustpilot Rating', 'icon' => '⭐']
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Mission',
                    'level' => 2
                ],
                'order' => 4
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Our mission is simple: to help people make smarter financial decisions by giving them the tools to compare products and find better deals. We believe price comparison should be free, fast, and transparent.',
                        'We work hard to ensure our comparisons are comprehensive and unbiased. We don\'t favor any particular provider - we simply show you what\'s available and let you decide what\'s best for your circumstances.'
                    ]
                ],
                'order' => 5
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Values',
                    'level' => 2
                ],
                'order' => 6
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Transparency - We\'re open about how we work and how we make money',
                        'Customer First - Your needs come before everything else',
                        'Independence - We don\'t favor any provider over another',
                        'Innovation - We constantly improve our service to help you save more',
                        'Trust - We handle your data responsibly and securely'
                    ]
                ],
                'order' => 7
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'We started GoCompare because we believed people deserved better access to competitive prices. Nearly 20 years later, that mission drives everything we do.',
                    'attribution' => 'Hayley Parsons, Founder'
                ],
                'order' => 8
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createContactPage(): void
    {
        $page = Page::create([
            'title' => 'Contact GoCompare',
            'page_type' => 'content',
            'slug' => 'contact',
            'status' => 'published',
            'meta_title' => 'Contact Us - GoCompare',
            'meta_description' => 'Get in touch with the GoCompare team. We\'re here to help with any questions.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Contact',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Contact Us',
                    'subtitle' => 'We\'re here to help with any questions about our comparison services',
                    'showSearch' => false
                ],
                'order' => 1
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'GoCompare Customer Services',
                    'role' => 'Contact Information',
                    'email' => 'hello@gocompare.com',
                    'phone' => '0800 197 6962',
                    'address' => 'Imperial House, Imperial Way, Newport, NP10 8UH, Wales, UK',
                    'displayType' => 'contact'
                ],
                'order' => 2
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Our customer service team is available Monday to Friday, 8am-8pm, and Saturday 9am-5pm to help with any questions about using our comparison service.',
                        'For existing policy enquiries, please contact your provider directly using the details on your policy documents.',
                        'Media enquiries: press@gocompare.com'
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Send Us a Message',
                    'subtitle' => 'Fill out the form below and we\'ll get back to you within 2 business days',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => false,
                    'showSubject' => true,
                    'showMessage' => true,
                    'submitButtonText' => 'Send Message',
                    'requireName' => true,
                    'requireEmail' => true,
                    'requireMessage' => true
                ],
                'order' => 4
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createArticleMenuItems(): void
    {
        // Get all article pages
        $articles = Page::where('site_id', $this->site->id)
            ->whereIn('slug', [
                'car-insurance',
                'home-insurance',
                'travel-insurance',
                'energy',
                'credit-cards',
                'broadband',
                'pet-insurance',
                'life-insurance'
            ])
            ->get();

        // Create Insurance submenu items
        $insuranceMenuItems = ['car-insurance', 'home-insurance', 'travel-insurance', 'pet-insurance', 'life-insurance'];
        foreach ($insuranceMenuItems as $slug) {
            $page = $articles->firstWhere('slug', $slug);
            if ($page) {
                MenuItem::create([
                    'label' => $page->title,
                    'menu_id' => $this->menu->id,
                    'target_type' => 'page',
                    'target_id' => $page->id,
                    'is_active' => true,
                    'sort_order' => 20
                ]);
            }
        }

        // Create Money submenu items
        $moneyMenuItems = ['credit-cards'];
        foreach ($moneyMenuItems as $slug) {
            $page = $articles->firstWhere('slug', $slug);
            if ($page) {
                MenuItem::create([
                    'label' => $page->title,
                    'menu_id' => $this->menu->id,
                    'target_type' => 'page',
                    'target_id' => $page->id,
                    'is_active' => true,
                    'sort_order' => 30
                ]);
            }
        }

        // Create Utilities submenu items
        $utilitiesMenuItems = ['energy', 'broadband'];
        foreach ($utilitiesMenuItems as $slug) {
            $page = $articles->firstWhere('slug', $slug);
            if ($page) {
                MenuItem::create([
                    'label' => $page->title,
                    'menu_id' => $this->menu->id,
                    'target_type' => 'page',
                    'target_id' => $page->id,
                    'is_active' => true,
                    'sort_order' => 40
                ]);
            }
        }
    }
}