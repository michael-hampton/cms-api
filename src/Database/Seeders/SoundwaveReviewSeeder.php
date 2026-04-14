<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class SoundwaveReviewSeeder extends Seeder
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
        $this->site = Site::find(20);

        if (!$this->site) {
            echo "Soundwave site not found.\n";
            return;
        }

        $reviewPages = $this->createReviewPages();
        $this->addReviewSectionToHomepage($reviewPages);
    }

    private function createReviewPages(): array
    {
        $reviews = [
            [
                'title' => 'Kendrick Lamar - GNX Album Review',
                'slug' => 'kendrick-lamar-gnx-review',
                'artist' => 'Kendrick Lamar',
                'album' => 'GNX',
                'rating' => 4.5,
                'genre' => 'Hip-Hop',
                'image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Kendrick Lamar\'s "GNX" marks another bold chapter in his illustrious career. The album blends hard-hitting lyrical mastery with innovative production choices, demonstrating Kendrick\'s ability to evolve while staying true to his roots.',
                            'Tracks range from introspective storytelling to aggressive, politically charged anthems, showing his versatility and willingness to tackle complex themes. Collaborations are well-chosen and complement Kendrick\'s distinct style.',
                            'The production is crisp and layered, balancing contemporary trap beats with jazzy undertones that keep each track fresh and dynamic. "GNX" solidifies Kendrick Lamar as one of the most influential voices in modern hip-hop.',
                            'Overall, this album is a must-listen for fans of thought-provoking, high-caliber hip-hop that challenges and entertains in equal measure.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Charli XCX - BRAT Album Review',
                'slug' => 'charli-xcx-brat-review',
                'artist' => 'Charli XCX',
                'album' => 'BRAT',
                'rating' => 4.8,
                'genre' => 'Pop/Hyperpop',
                'image' => 'https://images.unsplash.com/photo-1514320291840-2e0a9bf2a9ae?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            '"BRAT" sees Charli XCX at her experimental best, pushing hyperpop boundaries with bold synths, unconventional structures, and infectious hooks.',
                            'Vocals are layered and dynamic, blending playfulness with emotional intensity, while the production is futuristic and meticulously crafted. The album challenges pop conventions without alienating listeners.',
                            'Standout tracks demonstrate Charli\'s knack for catchy melodies and adventurous sound design, making it a landmark record in her discography. Lyrical themes explore identity, empowerment, and digital-age culture with wit and honesty.',
                            'In summary, "BRAT" is a high-energy, boundary-pushing pop album that cements Charli XCX\'s position as a forward-thinking, genre-defying artist.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'The 1975 - Live at Madison Square Garden Review',
                'slug' => 'the-1975-msg-review',
                'artist' => 'The 1975',
                'album' => 'Live Performance',
                'rating' => 5.0,
                'genre' => 'Alternative Rock',
                'image' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The 1975\'s performance at Madison Square Garden is a testament to their status as one of the most compelling live acts today. Energy, precision, and charisma are evident throughout the concert.',
                            'Song arrangements are elevated in the live setting, with extended instrumental sections and audience interaction enhancing the experience. Visual production and stage design perfectly complement the music.',
                            'The band\'s ability to balance pop sensibilities with experimental rock textures is on full display, giving fans both nostalgia and fresh excitement. Vocals are polished yet emotionally raw, creating a captivating connection with the audience.',
                            'Overall, this live recording is an essential experience for fans and a showcase of The 1975\'s musical evolution and stage mastery.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Billie Eilish - Horizons Album Review',
                'slug' => 'billie-eilish-horizons-review',
                'artist' => 'Billie Eilish',
                'album' => 'Horizons',
                'rating' => 4.7,
                'genre' => 'Electropop',
                'image' => 'https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            '"Horizons" showcases Billie Eilish\'s continued growth as an artist, blending moody electropop textures with intimate songwriting. Each track is meticulously produced, with layered vocals and subtle sound design creating a lush listening experience.',
                            'The album explores themes of self-discovery, vulnerability, and personal reflection, with Billie\'s signature whispery vocals providing emotional depth. Standout tracks demonstrate her willingness to experiment while remaining accessible.',
                            'Instrumentation is varied and innovative, balancing minimalistic beats with rich harmonic arrangements. The album demonstrates both lyrical maturity and sonic sophistication.',
                            'In conclusion, "Horizons" is a compelling record that strengthens Billie Eilish\'s reputation as a boundary-pushing, emotionally resonant pop artist.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Dua Lipa - Future Nostalgia 2 Review',
                'slug' => 'dua-lipa-future-nostalgia-2-review',
                'artist' => 'Dua Lipa',
                'album' => 'Future Nostalgia 2',
                'rating' => 4.6,
                'genre' => 'Pop/Dance',
                'image' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            '"Future Nostalgia 2" is an exhilarating dance-pop record, with Dua Lipa delivering infectious grooves, retro-inspired beats, and precise vocal performances.',
                            'Production is polished and dynamic, with catchy hooks and layered instrumentation keeping listeners engaged from start to finish. The album maintains a strong sense of cohesion while exploring diverse sonic palettes.',
                            'Lyrically, Dua balances empowerment, romance, and self-reflection, giving each track personality and relatability. The result is a sophisticated pop album that honors her earlier work while pushing forward.',
                            'Overall, "Future Nostalgia 2" is a must-listen for fans of contemporary pop, combining danceable rhythms with smart songwriting and impeccable production.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Tyler, The Creator - CALL ME IF YOU GET LOST 2 Review',
                'slug' => 'tyler-creator-cmigl-2-review',
                'artist' => 'Tyler, The Creator',
                'album' => 'CALL ME IF YOU GET LOST 2',
                'rating' => 4.9,
                'genre' => 'Hip-Hop/Rap',
                'image' => 'https://images.unsplash.com/photo-1529655683826-aba9b3e77383?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            '"CALL ME IF YOU GET LOST 2" is a masterclass in contemporary hip-hop. Tyler seamlessly blends rap, funk, jazz, and soul influences into a cohesive album with narrative depth and musical complexity.',
                            'Lyrically, the album showcases introspection, humor, and social commentary, revealing Tyler\'s unique perspective and artistic growth. Production is innovative, with intricate beats and rich instrumentation.',
                            'Guest appearances are well-integrated, enhancing rather than overshadowing Tyler\'s distinctive style. The flow and sequencing of tracks maintain listener engagement throughout.',
                            'Overall, this sequel is a bold, ambitious, and highly enjoyable record that further cements Tyler, The Creator\'s place as a leading innovator in hip-hop.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Kamasi Washington - The Epic Continues Album Review',
                'slug' => 'kamasi-washington-epic-continues-review',
                'artist' => 'Kamasi Washington',
                'album' => 'The Epic Continues',
                'rating' => 4.8,
                'genre' => 'Jazz',
                'image' => 'https://images.unsplash.com/photo-1521335629791-ce4aec67dd47?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Kamasi Washington\'s "The Epic Continues" builds on the grandeur and complexity of his previous work. The album combines ambitious arrangements with virtuosic solos, creating an immersive jazz experience.',
                            'Tracks move seamlessly between intense improvisation and structured compositions, highlighting both ensemble cohesion and individual brilliance. Each piece evokes emotional depth and narrative storytelling without words.',
                            'The album also experiments with orchestral elements, fusing traditional jazz with modern textures for a refreshing listening experience. Attention to detail and production quality is impeccable.',
                            'Overall, "The Epic Continues" is a masterful jazz record that will appeal to longtime fans and newcomers alike, cementing Kamasi Washington\'s status as a leading figure in contemporary jazz.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Gojira - Fortitude Album Review',
                'slug' => 'gojira-fortitude-review',
                'artist' => 'Gojira',
                'album' => 'Fortitude',
                'rating' => 4.7,
                'genre' => 'Metal',
                'image' => 'https://images.unsplash.com/photo-1532635246-22e0e6a887d1?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Gojira\'s "Fortitude" demonstrates the band\'s mastery of progressive metal, combining ferocious riffs with socially conscious lyrics. The album balances aggression with melody, keeping listeners engaged throughout.',
                            'Drumming is precise and powerful, complementing the tight guitar work, while vocals range from haunting clean passages to intense growls. Themes of environmental awareness and human resilience run through the record.',
                            'Production quality is top-notch, allowing each instrument to shine without overwhelming the listener. The album feels both expansive and immediate, delivering the emotional impact that fans expect.',
                            'Overall, "Fortitude" is a compelling metal release that solidifies Gojira\'s reputation as one of the genre\'s most innovative and thoughtful acts.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Arctic Monkeys - Reverie Album Review',
                'slug' => 'arctic-monkeys-reverie-review',
                'artist' => 'Arctic Monkeys',
                'album' => 'Reverie',
                'rating' => 4.6,
                'genre' => 'Indie Rock',
                'image' => 'https://images.unsplash.com/photo-1507874457470-272b3c8d8ee2?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            '"Reverie" finds Arctic Monkeys blending dreamy melodies with clever, introspective lyrics. The album explores new sonic textures while staying grounded in their signature indie rock sound.',
                            'Guitar work is layered and expressive, complemented by tight rhythm sections and Alex Turner\'s distinctive vocals. Each track tells a story, balancing wit, emotion, and narrative depth.',
                            'Production is polished yet organic, capturing the band\'s energy and intimacy effectively. Standout tracks reveal experimental arrangements without straying too far from their core identity.',
                            'Overall, "Reverie" is a rewarding listen for both longtime fans and newcomers, offering a sophisticated, well-crafted indie rock experience.'
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
        $homepage = Page::where('slug', 'home')->where('site_id', 7)->first();
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
                'image' => ['src' => $data['image'], 'alt' => $data['artist']],
                'badge' => ['text' => '⭐ ' . $data['rating'] . '/5', 'color' => 'success'],
                'meta' => [
                    'category' => $data['genre'],
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