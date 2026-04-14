<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\PageGrid;
use App\Models\Site;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class SoundwaveSeeder extends Seeder
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
        $this->createGenreArticles();
    }

    // Add to SoundwaveSeeder.php

    private function createGenreArticles(): void
    {
        // Article 1: Genre Deep Dive
        $article1 = [
            'title' => 'The Shoegaze Revival: How Gen-Z Rediscovered Dream Pop',
            'slug' => 'shoegaze-revival-gen-z',
            'artist' => null,
            'genre' => 'Alternative/Shoegaze',
            'author' => 'Sarah Chen',
            'tags' => ['featured', 'genre-deep-dive', 'shoegaze', 'indie', 'trending'],
            'categories' => ['Features', 'Genres'],
            'excerpt' => 'TikTok teens are obsessed with 90s shoegaze. We explore why bands like Slowdive are suddenly cool again.',
            'hero_image' => 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
            'content' => [
                'Twenty years ago, shoegaze was indie music\'s best-kept secret. Today, teenagers on TikTok are making Slowdive\'s "Alison" go viral. What happened?',
                'The answer lies in a perfect storm of nostalgia, accessibility, and pure sonic beauty. Shoegaze\'s wall-of-sound aesthetic translates perfectly to headphones and earbuds—the primary way Gen-Z consumes music.'
            ]
        ];

        // Article 2: Album Anniversary
        $article2 = [
            'title' => 'Radiohead\'s OK Computer at 28: An Oral History',
            'slug' => 'radiohead-ok-computer-oral-history',
            'artist' => 'Radiohead',
            'genre' => 'Alternative Rock',
            'author' => 'Marcus Webb',
            'rating' => 5,
            'tags' => ['featured', 'anniversary', 'classic', 'radiohead', 'rock'],
            'categories' => ['Features', 'Classic Albums'],
            'excerpt' => 'Band members, producers, and collaborators tell the story of the album that changed everything.',
            'hero_image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
            'content' => [
                'In the spring of 1997, Radiohead released an album that would define a generation. OK Computer wasn\'t just prescient—it was prophetic.',
                'We spoke with Thom Yorke, Jonny Greenwood, producer Nigel Godrich, and others about the making of this masterpiece.'
            ]
        ];

        // Article 3: Festival Guide
        $article3 = [
            'title' => 'Summer Festival Season 2025: Your Complete Guide to UK Festivals',
            'slug' => 'uk-summer-festivals-2025-guide',
            'author' => 'Jake Morrison',
            'genre' => 'Multi-Genre',
            'tags' => ['featured', 'festivals', 'summer', 'guide', 'live-music'],
            'categories' => ['News', 'Festivals'],
            'excerpt' => 'From Glastonbury to Reading: tickets, lineups, survival tips, and what to expect.',
            'hero_image' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
            'content' => [
                'Festival season is upon us, and 2025\'s lineup of UK festivals might be the strongest in years. Here\'s everything you need to know.',
                'We\'ve compiled the essential information: who\'s playing, how to get tickets, what to pack, and insider tips from festival veterans.'
            ]
        ];

        // Article 4: Emerging Artist Profile
        $article4 = [
            'title' => 'Meet Yaya Bey: Brooklyn\'s Neo-Soul Revelation',
            'slug' => 'yaya-bey-neo-soul-profile',
            'artist' => 'Yaya Bey',
            'genre' => 'Neo-Soul/R&B',
            'author' => 'Keisha Williams',
            'tags' => ['breakthrough-artist', 'neo-soul', 'r&b', 'interview', 'brooklyn'],
            'categories' => ['Features', 'Emerging Artists'],
            'excerpt' => 'The Brooklyn singer-songwriter blending 90s R&B with contemporary poetry and raw emotion.',
            'hero_image' => 'https://images.unsplash.com/photo-1598387993441-a364f854c3e1?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
            'content' => [
                'In a cramped Brooklyn studio, Yaya Bey is making the kind of R&B your soul didn\'t know it was missing. Her voice—warm, weathered, wise beyond her years—channels Erykah Badu\'s introspection and Lauryn Hill\'s raw honesty.',
                '"I grew up on my mother\'s record collection," she tells me. "Sade, Anita Baker, Minnie Riperton. Women who sang about real life, real pain, real love."'
            ]
        ];

        // Article 5: Music Technology Feature
        $article5 = [
            'title' => 'The AI Debate: Can Algorithms Create Authentic Music?',
            'slug' => 'ai-music-authenticity-debate',
            'author' => 'Tom Richardson',
            'genre' => 'Industry/Technology',
            'tags' => ['featured', 'ai-music', 'technology', 'industry', 'debate'],
            'categories' => ['Culture', 'Technology'],
            'excerpt' => 'As AI-generated music floods streaming platforms, musicians and listeners grapple with what makes music "real."',
            'hero_image' => 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
            'content' => [
                'Last month, an AI-generated song in the style of Drake and The Weeknd went viral, racking up millions of streams before being taken down. It sounded... real.',
                'We spoke with musicians, producers, and AI researchers about the future of music creation. The consensus? We\'re entering uncharted territory.'
            ]
        ];

        $articles = [$article1, $article2, $article3, $article4, $article5];
        $pages = [];
        foreach ($articles as $articleData) {
            $page = $this->createArticlePage($articleData);
            $pages[] = $page;
        }

        $this->createArticleGrid($pages);
    }

    private function createArticlePage(array $data): Page
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'meta_title' => $data['title'] . ' - SOUNDWAVE',
            'meta_description' => $data['excerpt'],
            'page_type' => 'content',
            'site_id' => 20
        ]);

        // Add tags
        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, 20);
            $page->tags(true)->attach($tag->id);
        }

        // Add categories
        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, 20);
            $page->categories(true)->attach($category->id);
        }

        // Add custom fields
        $customFields = [];

        if (!empty($data['artist'])) {
            $customFields['artist_name'] = $data['artist'];
        }
        if (!empty($data['genre'])) {
            $customFields['genre'] = $data['genre'];
        }
        if (!empty($data['author'])) {
            $customFields['author'] = $data['author'];
        }
        if (!empty($data['rating'])) {
            $customFields['rating'] = $data['rating'];
        }
        if (!empty($data['spotify_link'])) {
            $customFields['spotify_link'] = $data['spotify_link'];
        }
        if (!empty($data['apple_music_link'])) {
            $customFields['apple_music_link'] = $data['apple_music_link'];
        }
        if (!empty($data['venue'])) {
            $customFields['venue'] = $data['venue'];
        }
        if (!empty($data['event_date'])) {
            $customFields['event_date'] = $data['event_date'];
        }

        foreach ($customFields as $key => $value) {
            $fieldDef = CustomFieldDefinition::where('key', $key)->first();
            if ($fieldDef) {
                PageCustomField::create([
                    'custom_field_definition_id' => $fieldDef->id,
                    'field_value' => (string)$value,
                    'page_id' => $page->id
                ]);
            }
        }

        // Create article blocks
        $blocks = [
            [
                'type' => 'image',
                'data' => [
                    'src' => $data['hero_image'],
                    'alt' => $data['title'],
                    'caption' => '',
                    'layout' => 'full',
                    'alignment' => 'fullscreen'
                ],
                'order' => 1
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => $data['title'],
                    'level' => 1
                ],
                'order' => 2
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'By ' . $data['author'] . ' | ' . ($data['genre'] ?? 'Music') .
                        ($data['rating'] ? ' | Rating: ' . str_repeat('★', $data['rating']) . str_repeat('☆', 5 - $data['rating']) : '')
                    ]
                ],
                'order' => 3
            ]
        ];

        // Add content paragraphs
        $order = 4;
        foreach ($data['content'] as $paragraph) {
            $blocks[] = [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [$paragraph]
                ],
                'order' => $order++
            ];
        }

        // Add rating if it's a review
        if (!empty($data['rating'])) {
            $blocks[] = [
                'type' => 'note',
                'data' => [
                    'title' => 'Our Verdict',
                    'paragraphs' => [
                        'Rating: ' . $data['rating'] . '/5',
                        $data['rating'] >= 4 ? 'Highly Recommended' : ($data['rating'] >= 3 ? 'Worth Your Time' : 'Mixed')
                    ],
                    'alignment' => 'fullscreen'
                ],
                'order' => $order++
            ];
        }

        // Add streaming links if available
        if (!empty($data['spotify_link']) || !empty($data['apple_music_link'])) {
            $streamingLinks = [];
            if (!empty($data['spotify_link'])) {
                $streamingLinks[] = '🎧 [Listen on Spotify](' . $data['spotify_link'] . ')';
            }
            if (!empty($data['apple_music_link'])) {
                $streamingLinks[] = '🍎 [Listen on Apple Music](' . $data['apple_music_link'] . ')';
            }

            $blocks[] = [
                'type' => 'note',
                'data' => [
                    'title' => 'Listen Now',
                    'paragraphs' => $streamingLinks,
                    'alignment' => 'fullscreen'
                ],
                'order' => $order++
            ];
        }

        // Add author bio
        $blocks[] = [
            'type' => 'person',
            'data' => [
                'name' => $data['author'],
                'role' => 'Music Journalist',
                'bio' => 'Writer for SOUNDWAVE Magazine',
                'email' => strtolower(str_replace(' ', '.', $data['author'])) . '@soundwavemag.com',
                'displayType' => 'profile'
            ],
            'order' => $order++
        ];

        // Add related articles section
        $blocks[] = [
            'type' => 'heading',
            'data' => [
                'text' => 'You Might Also Like',
                'level' => 2
            ],
            'order' => $order++
        ];

        $this->createBlocksForPage($page->id, $blocks);

        return $page;
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

    private function createArticleGrid($pages): void
    {
        $site = Site::find(20);

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
            'site_id' => 20
        ]);
    }
}