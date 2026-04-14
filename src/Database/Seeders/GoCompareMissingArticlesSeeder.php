<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\Site;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class GoCompareMissingArticlesSeeder extends Seeder
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
        $this->site = Site::where('id', 10)->first();
        $this->createArticles();
    }

    private function createArticles(): void
    {
        $articles = [
            [
                'title' => 'Health Insurance Guide: Private Medical Cover Explained',
                'slug' => 'health-insurance',
                'tags' => ['health-insurance', 'insurance', 'guides', 'private-medical'],
                'categories' => ['Insurance', 'Health Insurance'],
                'custom_fields' => [
                    'author_name' => 'Dr. Sarah Collins',
                    'author_bio' => 'Healthcare insurance specialist with medical background.',
                    'read_time' => 9,
                    'excerpt' => 'Everything you need to know about private health insurance in the UK. Compare plans, understand benefits, and find the right cover.',
                    'product_type' => 'insurance'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Health Insurance',
                            'caption' => 'Protect your health with the right insurance',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Private health insurance provides faster access to medical treatment and specialist care beyond what the NHS offers. With waiting times for NHS treatment at record highs, more people are considering private health insurance.',
                                'This guide explains how private health insurance works, what it covers, and how to find the best policy for your needs and budget.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'NHS vs Private Health Insurance',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Understanding Your Options',
                            'productA' => 'NHS',
                            'productB' => 'Private Insurance',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Cost',
                                    'items' => [
                                        ['value' => 'Free at point of use'],
                                        ['value' => '£50-150 per month']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Waiting Times',
                                    'items' => [
                                        ['value' => 'Can be several months'],
                                        ['value' => 'Days to weeks']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Choice of Hospital',
                                    'items' => [
                                        ['value' => 'Limited choice'],
                                        ['value' => 'Wide choice of facilities']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Specialist Access',
                                    'items' => [
                                        ['value' => 'GP referral required'],
                                        ['value' => 'Direct specialist access']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Emergency Care',
                                    'items' => [
                                        ['value' => 'Excellent emergency care'],
                                        ['value' => 'Uses NHS for emergencies']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What Does Health Insurance Cover?',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'In-patient treatment - hospital stays and surgery',
                                'Out-patient appointments - consultations and diagnostics',
                                'Day-patient treatment - procedures not requiring overnight stay',
                                'Diagnostic tests - MRI, CT scans, X-rays, blood tests',
                                'Cancer care - treatment and support',
                                'Mental health treatment - therapy and counselling',
                                'Specialist consultations',
                                'Physiotherapy and rehabilitation'
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
                                'Pre-existing conditions (usually)',
                                'Routine GP appointments',
                                'Emergency treatment',
                                'Cosmetic procedures',
                                'Pregnancy and childbirth',
                                'Chronic conditions requiring ongoing care',
                                'Dental and optical care (unless added)',
                                'Experimental treatments'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Important: Always declare pre-existing medical conditions when applying. Non-disclosure can invalidate your entire policy.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Types of Health Insurance Policies',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Policy Type', 'Best For', 'Average Cost'],
                                ['Comprehensive', 'Full private healthcare', '£100-150/month'],
                                ['Budget/Guided', 'Essential cover, limited choice', '£50-80/month'],
                                ['Company Scheme', 'Employer-provided cover', 'Varies (often free)'],
                                ['Cash Plans', 'Routine healthcare costs', '£10-30/month'],
                                ['Health Trust', 'Affordable alternative', '£20-50/month']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'How to Save Money on Health Insurance',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Choose a higher excess - reduce premiums by 20-30%',
                                'Use NHS for some treatments - hybrid approach saves money',
                                'Opt for guided consultant choice - accept insurer\'s specialist',
                                'Choose a six-week wait option - mirrors NHS wait times',
                                'Remove optional extras - therapies you won\'t use',
                                'Pay annually - save around 10% vs monthly payments',
                                'Stay healthy - many insurers reward healthy lifestyles',
                                'Group policies - employer or affinity group discounts'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Pre-Existing Conditions',
                            'paragraphs' => [
                                'Most health insurance policies don\'t cover pre-existing conditions. However, some insurers offer moratorium underwriting, which may cover pre-existing conditions if you\'ve been symptom-free for 2 years.',
                                'Alternatively, full medical underwriting provides lifetime cover for declared conditions but may exclude specific conditions or charge higher premiums.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Age and Health Insurance Costs',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Health insurance premiums increase with age as the risk of needing treatment rises. Taking out cover while young locks in lower rates, though premiums still increase annually.',
                                'Many insurers offer age-banded pricing, where premiums remain level until you move into the next age bracket (typically every 5 years).'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Private health insurance gave me peace of mind during cancer treatment. I had immediate access to specialists and the latest treatments without waiting.',
                            'attribution' => 'John T, Surrey'
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => '💡 Top Tip: Many employers offer health insurance as a benefit. If yours doesn\'t, ask if they\'d consider it - group policies are much cheaper than individual cover.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Savings Accounts Guide: Find the Best Interest Rates',
                'slug' => 'savings',
                'tags' => ['savings', 'money', 'guides', 'interest-rates'],
                'categories' => ['Money', 'Bank Accounts', 'Savings'],
                'custom_fields' => [
                    'author_name' => 'Thomas Price',
                    'author_bio' => 'Personal finance journalist specializing in savings and investments.',
                    'read_time' => 11,
                    'excerpt' => 'Compare savings accounts and find the best interest rates. Understand ISAs, fixed-term bonds, and instant access accounts.',
                    'product_type' => 'money'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1579621970563-ebec7560ff3e?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Savings Account',
                            'caption' => 'Make your money work harder with the right savings account',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'With interest rates at their highest in years, now is an excellent time to review your savings. The right savings account can earn you hundreds or even thousands in interest, but with dozens of accounts available, finding the best deal can be overwhelming.',
                                'This comprehensive guide explains the different types of savings accounts, helps you understand interest rates, and shows you how to maximize your savings returns.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Savings in the UK',
                            'stats' => [
                                ['number' => '5.2%', 'label' => 'Best Instant Access Rate', 'icon' => '💰'],
                                ['number' => '£2,000', 'label' => 'Average UK Savings', 'icon' => '📊'],
                                ['number' => '£20,000', 'label' => 'ISA Annual Allowance', 'icon' => '🎯'],
                                ['number' => '£85K', 'label' => 'FSCS Protection Per Bank', 'icon' => '🛡️']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Types of Savings Accounts',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Instant Access vs Fixed Rate',
                            'productA' => 'Instant Access',
                            'productB' => 'Fixed Rate Bond',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Interest Rate',
                                    'items' => [
                                        ['value' => '4.5-5.2% (variable)'],
                                        ['value' => '5.0-5.5% (fixed)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Access to Money',
                                    'items' => [
                                        ['value' => 'Withdraw anytime'],
                                        ['value' => 'Locked in (1-5 years)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Rate Guarantee',
                                    'items' => [
                                        ['value' => 'Can change anytime'],
                                        ['value' => 'Guaranteed for term']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Emergency fund'],
                                        ['value' => 'Long-term goals']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Early Withdrawal',
                                    'items' => [
                                        ['value' => 'No penalty'],
                                        ['value' => 'Heavy penalties or not allowed']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Cash ISAs Explained',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Individual Savings Accounts (ISAs) allow you to save up to £20,000 per year completely tax-free. Any interest earned is yours to keep without paying income tax.',
                                'For basic rate taxpayers, the £1,000 personal savings allowance means most won\'t benefit from an ISA for small amounts. However, higher rate taxpayers (£500 allowance) and additional rate taxpayers (£0 allowance) should maximize ISA contributions.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'You can save up to £20,000 per tax year',
                                'All interest is completely tax-free',
                                'Can be instant access or fixed rate',
                                'ISA allowance doesn\'t roll over - use it or lose it',
                                'Can transfer previous years\' ISAs to better rates',
                                'Only one cash ISA per tax year (but can have stocks ISA too)',
                                'No age limit - available to all UK residents'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Regular Savings Accounts',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Regular savings accounts offer the highest interest rates (often 7-8%) but require monthly deposits and restrict withdrawals. They\'re perfect for building savings habits.',
                                'Typical restrictions include: minimum monthly deposit (£25-50), maximum monthly deposit (£200-300), 12-month term, and limited or no withdrawals allowed.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Understanding Interest Rates',
                            'paragraphs' => [
                                'AER (Annual Equivalent Rate) shows the interest rate if you left money in for a year, including compounding. This is the rate to compare between accounts.',
                                'Gross rate shows interest before tax. Net rate shows interest after basic rate (20%) tax is deducted. Remember, ISAs pay gross and you keep it all tax-free.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'How to Maximize Your Savings',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Use your ISA allowance first - keep interest tax-free',
                                'Split between instant access and fixed - balance access and returns',
                                'Compare rates monthly - switch to better rates regularly',
                                'Consider regular savers - highest rates for monthly deposits',
                                'Use FSCS protection wisely - spread over £85k across different banks',
                                'Look beyond the high street - challenger banks offer better rates',
                                'Set up automatic transfers - pay yourself first',
                                'Review annually - don\'t let savings languish in low-rate accounts'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Notice Accounts',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Notice Period', 'Typical Rate Boost', 'Best For'],
                                ['30 days', '+0.2% vs instant access', 'Short-term savings goals'],
                                ['60 days', '+0.3% vs instant access', 'House deposit funds'],
                                ['90 days', '+0.4% vs instant access', 'Medium-term savings'],
                                ['120 days', '+0.5% vs instant access', 'Maximum flexibility with better rates']
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Notice accounts sit between instant access and fixed-rate bonds. You get a better interest rate than instant access but must give notice (30-120 days) before withdrawing.',
                                'They\'re ideal if you want better rates but might need access with some warning, such as for a house deposit or large purchase.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Important: FSCS protection covers up to £85,000 per person, per banking group. Check which banks share a banking license to ensure your savings are fully protected.'
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'I was earning just 0.5% on my savings for years. After comparing rates on GoCompare, I switched to accounts paying 5.2%. That\'s an extra £470 per year on £10,000.',
                            'attribution' => 'Lisa M, Birmingham'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Current Accounts Guide: Find the Best Bank Account',
                'slug' => 'bank-accounts',
                'tags' => ['bank-accounts', 'money', 'guides', 'current-accounts'],
                'categories' => ['Money', 'Bank Accounts'],
                'custom_fields' => [
                    'author_name' => 'Helen Baker',
                    'author_bio' => 'Banking expert with 15 years experience in retail banking.',
                    'read_time' => 10,
                    'excerpt' => 'Compare current accounts and find the best bank for your needs. Understand fees, overdrafts, and account benefits.',
                    'product_type' => 'money'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1601597111158-2fceff292cdc?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Bank Account',
                            'caption' => 'Find the perfect current account for your banking needs',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Your current account is the foundation of your finances, handling your salary, bills, and daily spending. Yet millions of people stick with the same bank for decades, missing out on better deals, cashback, and perks.',
                                'This guide helps you compare current accounts, understand what different banks offer, and find the account that best suits your needs and saves you money.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Types of Current Accounts',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Standard vs Packaged Accounts',
                            'productA' => 'Standard Account',
                            'productB' => 'Packaged Account',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Monthly Fee',
                                    'items' => [
                                        ['value' => 'Free'],
                                        ['value' => '£10-25 per month']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Benefits Included',
                                    'items' => [
                                        ['value' => 'Basic banking only'],
                                        ['value' => 'Insurance, breakdown, cashback']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Overdraft Rate',
                                    'items' => [
                                        ['value' => '35-40% typical'],
                                        ['value' => 'Often preferential rates']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Credit Interest',
                                    'items' => [
                                        ['value' => 'Usually 0%'],
                                        ['value' => 'Some pay up to 3%']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Most people'],
                                        ['value' => 'Those needing bundled benefits']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What to Look for in a Current Account',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Monthly fees - avoid unless benefits justify the cost',
                                'Overdraft facilities and rates - some charge 40%+ APR',
                                'Credit interest on balances - rare but valuable if available',
                                'Switch incentives - bonuses of £100-200 for new customers',
                                'Mobile app quality - essential for modern banking',
                                'Branch and ATM access - still important for some',
                                'Additional benefits - travel insurance, breakdown cover',
                                'Foreign transaction fees - important for travelers'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Understanding Overdrafts',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Overdrafts let you borrow money through your current account. An arranged overdraft has a pre-agreed limit and charges interest (typically 35-40% APR). An unarranged overdraft occurs when you go beyond your limit and carries even higher charges.',
                                'Since April 2020, banks must charge a simple annual interest rate with no daily or monthly fees. However, rates remain expensive, so overdrafts should only be used for short-term emergencies.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Overdraft Amount', 'Cost at 40% APR', 'Monthly Cost'],
                                ['£100', '£40 per year', '£3.33'],
                                ['£500', '£200 per year', '£16.67'],
                                ['£1,000', '£400 per year', '£33.33'],
                                ['£2,000', '£800 per year', '£66.67']
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => '💡 Smart Tip: If you regularly use your overdraft, consider a 0% money transfer credit card instead. Transfer the debt to the card and clear it interest-free over 12-24 months.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Switching Your Bank Account',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Current Account Switch Service (CASS) makes switching banks simple and guaranteed. Your new bank handles everything, moving all your direct debits, standing orders, and payments automatically.',
                                'The switch completes in 7 working days, and both banks guarantee to redirect any payments for 3 years. If anything goes wrong, you\'re protected and compensated.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Choose your new bank and apply for an account',
                                'Request to use the Current Account Switch Service',
                                'Pick a switch date (minimum 7 working days away)',
                                'Your new bank contacts your old bank',
                                'All payments and direct debits are moved automatically',
                                'Your old account closes and balance transfers over',
                                'Payments to old account redirect for 3 years'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Switch Bonuses and Cashback',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Many banks offer cash bonuses (£100-200) to attract new customers. However, these usually require you to switch using CASS and meet minimum deposit requirements.',
                                'Some accounts also offer ongoing cashback on bills, direct debits, or debit card spending. Calculate whether these benefits outweigh any monthly fees.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Packaged Accounts - Worth It?',
                            'paragraphs' => [
                                'Packaged accounts charge £10-25 monthly but include insurance and benefits. They\'re only worthwhile if you\'d buy the insurance anyway and the cover meets your needs.',
                                'Common inclusions: travel insurance, mobile phone insurance, AA/RAC breakdown cover, card protection. Check the terms carefully - cover is often basic with significant exclusions.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Digital and Challenger Banks',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Digital banks like Monzo, Starling, and Revolut offer app-only banking with innovative features: instant spending notifications, budgeting tools, savings pots, and no foreign transaction fees.',
                                'They lack physical branches but excel at customer service through in-app chat. Perfect for tech-savvy users who never visit branches.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'I switched to get the £175 bonus and discovered my new bank\'s app is far better than my old one. The budgeting tools have helped me save an extra £200 per month.',
                            'attribution' => 'David K, Manchester'
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Important: Your money is protected up to £85,000 per banking group by the FSCS. Check that your chosen bank is covered before switching.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Mobile Phone Deals: Contracts vs SIM Only Plans',
                'slug' => 'mobile',
                'tags' => ['featured', 'mobile', 'utilities', 'guides', 'smartphones'],
                'categories' => ['Utilities', 'Mobile Phones'],
                'custom_fields' => [
                    'author_name' => 'Alex Turner',
                    'author_bio' => 'Technology journalist specializing in mobile and telecommunications.',
                    'read_time' => 9,
                    'excerpt' => 'Compare mobile phone contracts and SIM only deals. Find the best data allowance and save money on your monthly bill.',
                    'product_type' => 'utilities'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Mobile Phones',
                            'caption' => 'Find the perfect mobile phone deal for your needs',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Mobile phone costs are a significant monthly expense, but many people overpay for data they don\'t use or pay too much for handsets. With hundreds of deals across multiple networks, finding the right plan can save you hundreds per year.',
                                'This guide explains the difference between phone contracts and SIM only plans, helps you calculate how much data you need, and shows you how to get the best mobile deal.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'UK Mobile Market',
                            'stats' => [
                                ['number' => '£30', 'label' => 'Average Monthly Bill', 'icon' => '💷'],
                                ['number' => '5GB', 'label' => 'Average Data Usage', 'icon' => '📊'],
                                ['number' => '£360', 'label' => 'Annual Saving (SIM Only)', 'icon' => '💰'],
                                ['number' => '99%', 'label' => '4G Coverage', 'icon' => '📶']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Phone Contract vs SIM Only',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Which Option is Right for You?',
                            'productA' => 'Phone Contract',
                            'productB' => 'SIM Only',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Upfront Cost',
                                    'items' => [
                                        ['value' => '£0-100 typically'],
                                        ['value' => 'Buy phone separately']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Monthly Cost',
                                    'items' => [
                                        ['value' => '£30-80 per month'],
                                        ['value' => '£5-20 per month']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Contract Length',
                                    'items' => [
                                        ['value' => '24-36 months'],
                                        ['value' => '1-12 months']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Total Cost',
                                    'items' => [
                                        ['value' => '£720-2,880 over 2 years'],
                                        ['value' => '£120-480 + phone cost']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Latest phones, spread cost'],
                                        ['value' => 'Maximum savings, flexibility']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'How Much Data Do You Need?',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Usage Type', 'Recommended Data', 'Monthly Cost'],
                                ['Light (calls, texts, WhatsApp)', '1-2GB', '£5-8'],
                                ['Moderate (social media, browsing)', '5-10GB', '£8-12'],
                                ['Heavy (streaming music)', '20-30GB', '£12-18'],
                                ['Very Heavy (video streaming)', '50GB+', '£18-25'],
                                ['Unlimited', 'No restrictions', '£20-30']
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Most people overestimate their data needs. Check your current usage in your phone settings or provider app. If you use WiFi at home and work, 5-10GB is usually sufficient.',
                                'Video streaming uses the most data (about 1GB per hour). If you watch Netflix on your commute, you\'ll need a much larger allowance or download content on WiFi first.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Network Coverage',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'EE - Best overall 4G coverage (99% UK), fastest speeds',
                                'Vodafone - Good coverage, strong international roaming',
                                'O2 - Priority ticketing scheme, free WiFi at venues',
                                'Three - Includes roaming in 71 countries, good value',
                                'MVNOs - Use main network infrastructure, often cheaper'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => '💡 Check coverage at your home and workplace before switching. All networks have coverage checkers on their websites showing signal strength by postcode.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'How to Save Money on Mobile Phones',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Consider SIM only - save £20-40 per month vs contracts',
                                'Buy handsets outright - cheaper long-term than contracts',
                                'Don\'t over-buy data - most people use under 5GB monthly',
                                'Look at MVNOs - same networks, lower prices (Giffgaff, Smarty)',
                                'Time your purchase - Black Friday and January sales offer best deals',
                                'Negotiate retention deals - call to cancel for better offers',
                                'Consider refurbished phones - save 30-50% on like-new devices',
                                'Review annually - switch when contract ends to avoid price rises'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '5G - Is It Worth It?',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                '5G offers faster speeds and lower latency than 4G, but coverage is still limited to major cities and towns. Unless you live in a well-covered area and have a 5G phone, the premium isn\'t worth paying.',
                                '5G plans typically cost £3-5 more per month. Check your network\'s 5G coverage map before upgrading. For most users, 4G speeds (20-30Mbps average) are sufficient for streaming, browsing, and social media.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'The True Cost of Phone Contracts',
                            'paragraphs' => [
                                'A £50/month contract over 24 months costs £1,200. The handset might retail for £600, meaning you\'re paying £600 for £10/month worth of calls and data.',
                                'Instead: Buy the phone for £600 upfront, get a £10/month SIM only plan = £840 total. Save £360 over 2 years, plus you own the phone outright and can switch plans anytime.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Roaming Charges',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Post-Brexit, EU roaming is no longer guaranteed free. Most networks reintroduced roaming charges: EE (£2/day), Vodafone (£2/day), O2 (£3.50/day). Three still offers free roaming in many countries.',
                                'For frequent travelers, consider an MVNO like Smarty (free EU roaming) or buy local SIMs abroad.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'I was paying £45/month for a contract I didn\'t need anymore. Switched to a £8/month SIM only deal with the same allowance. Saving £444 per year!',
                            'attribution' => 'Sophie L, Bristol'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Mortgage Guide: First Time Buyers & Remortgaging',
                'slug' => 'mortgages',
                'tags' => ['featured', 'mortgages', 'money', 'guides', 'property'],
                'categories' => ['Money', 'Mortgages'],
                'custom_fields' => [
                    'author_name' => 'Katherine Wright',
                    'author_bio' => 'Mortgage advisor with 20 years experience helping buyers find the right home loan.',
                    'read_time' => 12,
                    'excerpt' => 'Everything you need to know about mortgages. From first time buyers to remortgaging, understand rates, deposits, and how to get approved.',
                    'product_type' => 'money'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Mortgage and Home Buying',
                            'caption' => 'Find the right mortgage for your dream home',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Getting a mortgage is one of the biggest financial decisions you\'ll ever make. With hundreds of mortgage products available and interest rates that can vary by 2% or more, choosing the right mortgage can save you tens of thousands of pounds over the loan term.',
                                'This comprehensive guide covers everything from getting your first mortgage to remortgaging for a better deal. We\'ll help you understand mortgage types, calculate what you can afford, and navigate the application process.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'UK Mortgage Market',
                            'stats' => [
                                ['number' => '£296K', 'label' => 'Average House Price', 'icon' => '🏠'],
                                ['number' => '4.5x', 'label' => 'Typical Income Multiple', 'icon' => '📊'],
                                ['number' => '5.2%', 'label' => 'Average 2-Year Fixed Rate', 'icon' => '💷'],
                                ['number' => '15%', 'label' => 'Recommended Deposit', 'icon' => '🎯']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Fixed Rate vs Variable Rate Mortgages',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Understanding Your Options',
                            'productA' => 'Fixed Rate',
                            'productB' => 'Variable Rate',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Interest Rate',
                                    'items' => [
                                        ['value' => 'Fixed for 2, 3, 5, or 10 years'],
                                        ['value' => 'Changes with market rates']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Monthly Payment',
                                    'items' => [
                                        ['value' => 'Same every month'],
                                        ['value' => 'Can go up or down']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Typical Rate',
                                    'items' => [
                                        ['value' => '5.0-5.5% (2-year)'],
                                        ['value' => '7.0-8.0% (Standard Variable)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Early Repayment',
                                    'items' => [
                                        ['value' => 'Charges apply (1-5% of loan)'],
                                        ['value' => 'Usually no charges']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Budget certainty'],
                                        ['value' => 'Flexibility to overpay']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'How Much Can You Borrow?',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Most lenders will lend 4-4.5 times your annual salary (or joint income for couples). Some specialist lenders offer up to 5.5x income, but this is less common and requires excellent credit.',
                                'However, how much you can borrow also depends on your monthly outgoings, existing debts, and deposit size. Lenders use affordability calculators to ensure you can afford payments even if rates rise.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Calculate your gross annual income (before tax)',
                                'Multiply by 4.5 to get maximum borrowing estimate',
                                'Add your deposit to this amount',
                                'Subtract any existing debts',
                                'Check your credit score - it affects rates offered',
                                'Use online mortgage calculators for accurate figures',
                                'Get a decision in principle before house hunting'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Deposit Requirements',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Deposit %', 'Loan-to-Value', 'Typical Rate', 'Products Available'],
                                ['5%', '95% LTV', '5.8-6.2%', 'Limited choice'],
                                ['10%', '90% LTV', '5.3-5.7%', 'Good choice'],
                                ['15%', '85% LTV', '4.9-5.3%', 'Wide choice'],
                                ['20%', '80% LTV', '4.7-5.1%', 'Best rates start'],
                                ['25%', '75% LTV', '4.5-4.9%', 'Excellent rates'],
                                ['40%', '60% LTV', '4.0-4.5%', 'Best rates available']
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The larger your deposit, the lower your interest rate. A 5% deposit qualifies you for a 95% LTV mortgage with rates around 6%, while a 25% deposit gets 75% LTV rates around 4.5%.',
                                'This rate difference is significant: on a £200,000 mortgage over 25 years, the difference between 6% and 4.5% is £170 per month, or £51,000 over the full term.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'First Time Buyer Schemes',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Lifetime ISA - Government adds 25% bonus (up to £1,000/year) toward first home',
                                'Shared Ownership - Buy 25-75% of property, pay rent on remainder',
                                'Help to Buy Equity Loan - Government lends up to 20% (40% in London)',
                                'First Homes Scheme - Buy new build at 30-50% discount',
                                '95% Mortgages - Special products for 5% deposits',
                                'Guarantor Mortgages - Family member guarantees some of loan'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => '💡 First Time Buyer Tip: Don\'t pay stamp duty on properties up to £425,000 (£625,000 in London). This saves up to £11,250 compared to existing homeowners.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Mortgage Application Process',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Check your credit score (3 months before applying)',
                                'Save your deposit (minimum 5% of property value)',
                                'Gather documents (3 months bank statements, 3 months payslips, ID)',
                                'Get Decision in Principle (AIP) - soft credit check',
                                'Find a property and make an offer',
                                'Submit full mortgage application',
                                'Lender values property and underwrites application',
                                'Mortgage offer issued (usually 2-6 weeks)',
                                'Instruct solicitor to complete legal work',
                                'Exchange contracts (legally committed)',
                                'Complete on property (receive keys!)'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Should You Use a Mortgage Broker?',
                            'paragraphs' => [
                                'Mortgage brokers have access to products you can\'t get directly, including exclusive rates. They can also help with complex situations like self-employment, adverse credit, or unusual properties.',
                                'Most brokers are fee-free (they earn commission from lenders) or charge £300-500. For first-time buyers or anyone time-poor, a broker often finds better deals and handles the paperwork.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Remortgaging to Save Money',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'When your fixed-rate deal ends, you\'ll automatically move to your lender\'s Standard Variable Rate (SVR), typically 7-8%. This can add £300-500 to your monthly payments.',
                                'Remortgaging to a new deal 3-6 months before your current deal ends prevents this. Most people save by remortgaging every 2-5 years, constantly moving to new competitive rates.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Start looking 3-6 months before current deal ends',
                                'Consider product fees vs interest rate (£999 fee vs 0.2% higher rate)',
                                'Overpay during fixed period if allowed (typically 10% per year)',
                                'Port your mortgage if moving home',
                                'Release equity if property value has increased',
                                'Switch lenders for better rates - you\'re not tied to current lender'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Additional Costs to Consider',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Cost', 'Typical Amount', 'When Payable'],
                                ['Mortgage Arrangement Fee', '£0-2,000', 'Can add to loan'],
                                ['Valuation Fee', '£250-1,500', 'Upfront'],
                                ['Legal Fees', '£850-1,500', 'On completion'],
                                ['Stamp Duty', '£0-£27,000+', 'On completion'],
                                ['Survey', '£400-1,000', 'Upfront (optional)'],
                                ['Removal Costs', '£500-2,000', 'Moving day'],
                                ['Buildings Insurance', '£150-300/year', 'From completion']
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Important: Budget at least 3-5% of property value for all purchase costs (deposit excluded). On a £250,000 house, you need £7,500-12,500 plus your deposit.'
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'We thought buying was impossible on our salaries, but the Lifetime ISA bonus and first-time buyer stamp duty relief made it affordable. We moved in 6 months ago!',
                            'attribution' => 'James & Lucy, London'
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
}