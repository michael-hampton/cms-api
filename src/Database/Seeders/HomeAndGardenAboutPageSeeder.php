<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\Page;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class HomeAndGardenAboutPageSeeder extends Seeder
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
        $this->createAboutPage();
    }

    private function createAboutPage(): void
    {
        $page = Page::create([
            'title' => 'About Haven & Hearth',
            'slug' => 'about',
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => 'About Us - Haven & Hearth',
            'meta_description' => 'Learn about Haven & Hearth - your trusted source for home design inspiration, gardening tips, and lifestyle content since 2010.',
            'site_id' => 9,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'About Haven & Hearth',
                    'subtitle' => 'Creating beautiful homes and inspiring gardens since 2010',
                    'backgroundImage' => 'https://images.unsplash.com/photo-1556912173-3bb406ef7e77?w=2000'
                ]
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Haven & Hearth was founded with a simple mission: to help people create homes they love and gardens that thrive. What started as a passion project has grown into a trusted resource for millions of readers seeking inspiration, practical advice, and expert guidance.',
                        'Our team of experienced designers, gardeners, and home enthusiasts brings you carefully curated content that combines aesthetic inspiration with actionable advice. Whether you\'re planning a complete renovation, starting your first garden, or simply looking for seasonal decor ideas, we\'re here to help every step of the way.'
                    ]
                ]
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Our Community',
                    'stats' => [
                        ['number' => '15+', 'label' => 'Years of Experience', 'icon' => '🏡'],
                        ['number' => '5M+', 'label' => 'Monthly Readers', 'icon' => '👥'],
                        ['number' => '2,000+', 'label' => 'Articles Published', 'icon' => '📝'],
                        ['number' => '50+', 'label' => 'Expert Contributors', 'icon' => '✨']
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Meet Our Team',
                    'subtitle' => 'The experts behind Haven & Hearth',
                    'level' => 2
                ]
            ],
            [
                'type' => 'team',
                'data' => [
                    'layout' => 'grid',
                    'members' => [
                        [
                            'name' => 'Sarah Green',
                            'role' => 'Lead Gardening Editor',
                            'bio' => 'Master gardener with 20 years of experience in organic gardening and landscape design.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400'],
                            'email' => 'sarah.green@havenhearth.com'
                        ],
                        [
                            'name' => 'Emma Nordström',
                            'role' => 'Interior Design Expert',
                            'bio' => 'Award-winning interior designer specializing in Scandinavian and modern farmhouse styles.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=400'],
                            'email' => 'emma.nordstrom@havenhearth.com'
                        ],
                        [
                            'name' => 'Marcus Chen',
                            'role' => 'DIY & Projects Editor',
                            'bio' => 'Former contractor turned writer, passionate about making home improvement accessible to everyone.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400'],
                            'email' => 'marcus.chen@havenhearth.com'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'heading',
                'data' => ['text' => 'Our Values', 'level' => 2]
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Authenticity: Real homes, real gardens, real advice from real experts',
                        'Accessibility: Beautiful design and successful gardening should be available to everyone',
                        'Sustainability: Promoting eco-friendly practices and responsible consumption',
                        'Quality: Thoroughly researched, carefully tested, honestly reviewed',
                        'Community: Building connections between home and garden enthusiasts worldwide'
                    ]
                ]
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'A house is made of bricks and beams. A home is made of hopes and dreams. We\'re here to help you build both.',
                    'attribution' => 'Haven & Hearth Editorial Mission'
                ]
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
                'order' => $blockData['order'] ?? 1
            ]);
        }
    }
}