<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Page;
use App\Repositories\BlockRepository;

class GoCompareZoneSeeder extends Seeder
{
    private $blockRepository;

    public function __construct()
    {
        $this->blockRepository = new BlockRepository();

        parent::__construct();
    }

    public function run(): void
    {
        $this->createHomepage();
    }

    private function createHomepage(): void
    {
        $page = Page::where('slug', 'home')
            ->where('site_id', 28)
            ->first();

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Compare & Save Money',
                    'subtitle' => 'Get quotes from leading providers and switch in minutes.',
                    'ctaText' => 'Start Comparing',
                    'ctaUrl' => '#products',
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
            // Zone A - Insurance heading
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Compare Insurance',
                    'subtitle' => 'Save hundreds on your insurance',
                    'level' => 2
                ],
                'order' => 3
            ],
            // Zone B - Two column insurance cards
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'layout' => 'grid',
                    'columns' => 2,
                    'pages' => [
                        [
                            'title' => 'Car Insurance',
                            'slug' => 'car-insurance',
                            'excerpt' => 'Compare quotes from over 100 providers.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=800', 'alt' => 'Car Insurance']
                        ]
                    ]
                ],
                'order' => 4
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'layout' => 'grid',
                    'columns' => 2,
                    'pages' => [
                        [
                            'title' => 'Home Insurance',
                            'slug' => 'home-insurance',
                            'excerpt' => 'Protect your home and contents.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1570129477492-45c003edd2be?w=800', 'alt' => 'Home Insurance']
                        ]
                    ]
                ],
                'order' => 5
            ],
            // Zone C - Three column money products
            [
                'type' => 'heading',
                'data' => ['text' => 'Money Products', 'level' => 2],
                'order' => 6
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'layout' => 'grid',
                    'columns' => 1,
                    'pages' => [['title' => 'Credit Cards', 'slug' => 'credit-cards', 'excerpt' => 'Find the best deals']]
                ],
                'order' => 7
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'layout' => 'grid',
                    'columns' => 1,
                    'pages' => [['title' => 'Personal Loans', 'slug' => 'loans', 'excerpt' => 'Compare rates']]
                ],
                'order' => 8
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'layout' => 'grid',
                    'columns' => 1,
                    'pages' => [['title' => 'Bank Accounts', 'slug' => 'bank-accounts', 'excerpt' => 'Find your account']]
                ],
                'order' => 9
            ],
            // Zone D - Four column utilities
            [
                'type' => 'heading',
                'data' => ['text' => 'Utilities', 'level' => 2],
                'order' => 10
            ],
            [
                'type' => 'info',
                'data' => ['infoType' => 'tip', 'description' => 'Energy Saving'],
                'order' => 11
            ],
            [
                'type' => 'info',
                'data' => ['infoType' => 'tip', 'description' => 'Broadband Deals'],
                'order' => 12
            ],
            [
                'type' => 'info',
                'data' => ['infoType' => 'tip', 'description' => 'Mobile Contracts'],
                'order' => 13
            ],
            [
                'type' => 'info',
                'data' => ['infoType' => 'tip', 'description' => 'TV Packages'],
                'order' => 14
            ],
            // Zone E - Four columns, 2 blocks each
            [
                'type' => 'text',
                'data' => ['paragraphs' => ['Feature 1 top']],
                'order' => 15
            ],
            [
                'type' => 'text',
                'data' => ['paragraphs' => ['Feature 1 bottom']],
                'order' => 16
            ],
            [
                'type' => 'text',
                'data' => ['paragraphs' => ['Feature 2 top']],
                'order' => 17
            ],
            [
                'type' => 'text',
                'data' => ['paragraphs' => ['Feature 2 bottom']],
                'order' => 18
            ],
            [
                'type' => 'text',
                'data' => ['paragraphs' => ['Feature 3 top']],
                'order' => 19
            ],
            [
                'type' => 'text',
                'data' => ['paragraphs' => ['Feature 3 bottom']],
                'order' => 20
            ],
            [
                'type' => 'text',
                'data' => ['paragraphs' => ['Feature 4 top']],
                'order' => 21
            ],
            [
                'type' => 'text',
                'data' => ['paragraphs' => ['Feature 4 bottom']],
                'order' => 22
            ],
        ];

        // Create blocks first to get their IDs
        $createdBlocks = [];
        foreach ($blocks as $blockData) {
            $block = $this->blockRepository->create([
                'page_id' => $page->id,
                'type' => $blockData['type'],
                'data' => json_encode($blockData['data']),
                'order' => $blockData['order']
            ]);
            $createdBlocks[] = $block;
        }

        // Now create zones using the block IDs
        $zones = [
            // Zone A - 1 column, 1 block (heading)
            [
                'id' => 'zone-a',
                'name' => 'Insurance Header',
                'columns' => 1,
                'blocks' => [
                    [$createdBlocks[2]->id] // Insurance heading
                ],
                'options' => [
                    'background' => 'default',
                    'padding' => 'medium',
                    'width' => 'contained'
                ],
                'sortOrder' => 1
            ],
            // Zone B - 2 columns, 2 blocks (insurance cards)
            [
                'id' => 'zone-b',
                'name' => 'Insurance Products',
                'columns' => 2,
                'blocks' => [
                    [$createdBlocks[3]->id], // Car insurance
                    [$createdBlocks[4]->id]  // Home insurance
                ],
                'options' => [
                    'background' => 'muted',
                    'padding' => 'large',
                    'width' => 'contained'
                ],
                'sortOrder' => 2
            ],
            // Zone C - 3 columns, 3 blocks (money products)
            [
                'id' => 'zone-c',
                'name' => 'Money Products',
                'columns' => 3,
                'blocks' => [
                    [$createdBlocks[6]->id], // Credit cards
                    [$createdBlocks[7]->id], // Loans
                    [$createdBlocks[8]->id]  // Bank accounts
                ],
                'options' => [
                    'background' => 'default',
                    'padding' => 'medium',
                    'width' => 'contained'
                ],
                'sortOrder' => 3
            ],
            // Zone D - 4 columns, 4 blocks (utilities)
            [
                'id' => 'zone-d',
                'name' => 'Utilities',
                'columns' => 4,
                'blocks' => [
                    [$createdBlocks[10]->id], // Energy
                    [$createdBlocks[11]->id], // Broadband
                    [$createdBlocks[12]->id], // Mobile
                    [$createdBlocks[13]->id]  // TV
                ],
                'options' => [
                    'background' => 'brand',
                    'padding' => 'small',
                    'width' => 'full'
                ],
                'sortOrder' => 4
            ],
            // Zone E - 4 columns, 2 blocks per column
            [
                'id' => 'zone-e',
                'name' => 'Features Grid',
                'columns' => 4,
                'blocks' => [
                    [$createdBlocks[14]->id, $createdBlocks[15]->id], // Column 1
                    [$createdBlocks[16]->id, $createdBlocks[17]->id], // Column 2
                    [$createdBlocks[18]->id, $createdBlocks[19]->id], // Column 3
                    [$createdBlocks[20]->id, $createdBlocks[21]->id]  // Column 4
                ],
                'options' => [
                    'background' => 'muted',
                    'padding' => 'large',
                    'width' => 'contained'
                ],
                'sortOrder' => 5
            ]
        ];

        // Save zones to page
        $page->zones = json_encode($zones);
        $page->save();
    }
}