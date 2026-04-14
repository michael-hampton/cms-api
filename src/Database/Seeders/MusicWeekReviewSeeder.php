<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\Category;
use App\Models\Page;
use App\Models\PageCategory;
use App\Models\PageTag;
use App\Models\Site;
use App\Models\Tag;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class MusicWeekReviewSeeder extends Seeder
{
    private $pageRepository;
    private $blockRepository;
    private $tagRepository;
    private $categoryRepository;
    private $blockParserService;
    private \App\Models\Model $site;

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
        $this->site = Site::find(21);

        if (!$this->site) {
            echo "Music Week site not found.\n";
            return;
        }

        $reviewPages = $this->createReviewPages();
        $this->addReviewSectionToHomepage($reviewPages);
    }

    private function createReviewPages(): array
    {
        $reviews = [
            [
                'title' => 'Taylor Swift - Midnights (3am Edition) Review',
                'slug' => 'taylor-swift-midnights-3am-review',
                'category' => 'Track Reviews',
                'image' => 'https://images.unsplash.com/photo-1514320291840-2e0a9bf2a9ae?w=800',
                'artist' => 'Taylor Swift',
                'album' => 'Midnights (3am Edition)',
                'rating' => 4.9,
                'genre' => 'Pop',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Taylor Swift\'s "Midnights (3am Edition)" showcases her evolution as a songwriter and producer, delivering introspective lyrics wrapped in shimmering synth-pop production. The expanded edition adds seven bonus tracks that deepen the album\'s narrative exploration of sleepless nights and self-reflection.',
                            'The production throughout is meticulous and layered, with Jack Antonoff\'s signature touches complementing Swift\'s vocal delivery perfectly. Songs range from vulnerable ballads to infectious pop anthems, each track revealing new dimensions with repeated listens. The 3am tracks feel like intimate diary entries, offering fans deeper insight into the creative process.',
                            'Lyrically, Swift demonstrates her masterful storytelling ability, painting vivid pictures of midnight anxieties, past relationships, and personal growth. The metaphors are clever without being overwrought, and the emotional honesty resonates throughout. Her vocal performance shows remarkable range and control, adapting effortlessly to each song\'s unique mood.',
                            'Overall, "Midnights (3am Edition)" stands as one of Swift\'s most cohesive and mature works, balancing commercial appeal with artistic depth. It\'s an essential addition to her discography and a testament to her continuing relevance in contemporary pop music.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'The Weeknd - Dawn FM Album Review',
                'slug' => 'the-weeknd-dawn-fm-review',
                'category' => 'Album Reviews',
                'artist' => 'The Weeknd',
                'image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800',
                'album' => 'Dawn FM',
                'rating' => 4.7,
                'genre' => 'R&B/Synthwave',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            '"Dawn FM" is The Weeknd\'s ambitious journey through a conceptual radio station experience, blending retro 80s synthwave aesthetics with modern R&B sensibilities. The album plays like a continuous broadcast, complete with radio host interludes that create a cohesive narrative arc from beginning to end.',
                            'Production quality is exceptional, with crisp synths, punchy drums, and atmospheric textures that evoke both nostalgia and futurism simultaneously. Collaborations with producers like Swedish House Mafia and Oneohtrix Point Never bring diverse sonic perspectives while maintaining the album\'s unified vision. The Weeknd\'s vocals glide effortlessly over the instrumental landscapes, showcasing his signature falsetto and emotional depth.',
                            'Thematically, the album explores mortality, redemption, and acceptance through the metaphor of a journey through purgatory. The lyrics are introspective and often philosophical, marking a maturation in The Weeknd\'s songwriting. Guest appearances from Tyler, The Creator, Lil Wayne, and Quincy Jones add depth without overshadowing the central vision.',
                            'In conclusion, "Dawn FM" is a bold artistic statement that successfully merges concept with accessibility. It demonstrates The Weeknd\'s willingness to take creative risks while delivering an album that rewards both casual listening and deep analysis.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'SZA - SOS Album Review',
                'slug' => 'sza-sos-album-review',
                'category' => 'Album Reviews',
                'image' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=800',
                'artist' => 'SZA',
                'album' => 'SOS',
                'rating' => 4.8,
                'genre' => 'R&B/Alternative',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'SZA\'s "SOS" is a sprawling, emotionally raw exploration of heartbreak, self-discovery, and empowerment. Spanning 23 tracks, the album showcases SZA\'s versatility as she effortlessly transitions between R&B, pop, rap, and alternative influences, creating a sonic tapestry that feels both cohesive and adventurous.',
                            'The production is diverse and inventive, featuring contributions from industry heavyweights and up-and-coming producers alike. From the acoustic guitar-driven intimacy of certain tracks to the hard-hitting beats of others, each song occupies its own sonic space while contributing to the album\'s overarching narrative. SZA\'s vocal performance is stunning throughout, displaying vulnerability, anger, confidence, and everything in between.',
                            'Lyrically, SZA holds nothing back, offering brutally honest reflections on toxic relationships, personal growth, and the complexities of modern romance. Her wordplay is clever and conversational, making listeners feel like confidants in her journey. The album doesn\'t shy away from contradictions, embracing the messy reality of human emotions and relationships.',
                            'Overall, "SOS" is a triumphant return that solidifies SZA\'s position as one of R&B\'s most compelling voices. It\'s an album that demands to be heard in full, rewarding patient listeners with a rich, multifaceted artistic statement that will resonate for years to come.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Bad Bunny - Un Verano Sin Ti Review',
                'slug' => 'bad-bunny-verano-sin-ti-review',
                'category' => 'Track Reviews',
                'artist' => 'Bad Bunny',
                'image' => 'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?w=800',
                'album' => 'Un Verano Sin Ti',
                'rating' => 4.6,
                'genre' => 'Reggaeton/Latin',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Bad Bunny\'s "Un Verano Sin Ti" is a sun-soaked celebration of Caribbean culture and Latin music innovation. The album effortlessly blends reggaeton, dembow, mambo, and electronic influences, creating a sound that feels both timeless and thoroughly modern. It\'s an album designed for summer nights and beach parties, yet contains enough substance to transcend seasonal listening.',
                            'Production is vibrant and layered, with live instrumentation complementing electronic elements to create rich, textured soundscapes. The album\'s pacing is masterful, knowing when to turn up the energy and when to pull back for more introspective moments. Collaborations with artists like Chencho Corleone, Jhay Cortez, and Tony Dize enhance the album without disrupting its flow.',
                            'Bad Bunny\'s performance throughout is charismatic and confident, his distinctive voice serving as the perfect vehicle for both playful party anthems and more emotionally vulnerable tracks. Lyrically, he explores themes of love, loss, desire, and Puerto Rican pride with equal passion. The album feels like a love letter to his homeland and its musical traditions.',
                            'In summary, "Un Verano Sin Ti" is a genre-defining work that showcases Bad Bunny\'s artistic range and cultural impact. It\'s an essential listen for anyone interested in contemporary Latin music and demonstrates why Bad Bunny has become a global phenomenon.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Arctic Monkeys - The Car Album Review',
                'slug' => 'arctic-monkeys-the-car-review',
                'artist' => 'Arctic Monkeys',
                'category' => 'Album Reviews',
                'image' => 'https://images.unsplash.com/photo-1501594907352-04cda38ebc29?w=800',
                'album' => 'The Car',
                'rating' => 4.5,
                'genre' => 'Indie Rock',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Arctic Monkeys\' "The Car" continues the band\'s evolution toward more sophisticated, lounge-inspired rock. The album eschews the raw energy of their early work in favor of lush orchestration, jazz influences, and cinematic arrangements. It\'s a bold artistic choice that won\'t please every fan but rewards those willing to embrace the band\'s maturation.',
                            'Production is immaculate, with James Ford helping craft a sound that feels warm, analog, and meticulously detailed. Strings, keyboards, and unconventional instrumentation create atmospheric textures that transport listeners to dimly lit cocktail bars and midnight drives. Alex Turner\'s guitar work is more restrained and melodic, serving the songs rather than dominating them.',
                            'Turner\'s lyrics remain as evocative and enigmatic as ever, painting surreal scenes and exploring themes of nostalgia, disillusionment, and observation. His vocal delivery has grown more theatrical and crooner-like, perfectly suited to the album\'s aesthetic. The rhythm section provides a steady, sophisticated foundation that allows the melodies and arrangements to breathe.',
                            'Overall, "The Car" is a challenging but rewarding listen that showcases Arctic Monkeys\' willingness to experiment and evolve. It may not be their most immediate album, but it reveals new layers with each listen and stands as a testament to the band\'s artistic ambition.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Rosalía - MOTOMAMI Album Review',
                'slug' => 'rosalia-motomami-review',
                'category' => 'Album Reviews',
                'image' => 'https://images.unsplash.com/photo-1487180144351-b8472da7d491?w=800',
                'artist' => 'Rosalía',
                'album' => 'MOTOMAMI',
                'rating' => 4.9,
                'genre' => 'Experimental Pop/Flamenco',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Rosalía\'s "MOTOMAMI" is a fearless, genre-defying masterpiece that cements her status as one of the most innovative artists in contemporary music. The album fuses flamenco traditions with experimental pop, reggaeton, electronic music, and avant-garde production techniques, creating something entirely unique and thrilling.',
                            'The production is bold and unpredictable, featuring abrupt transitions, unconventional song structures, and a willingness to embrace both minimalism and maximalism within the same album. Collaborations with producers like El Guincho, Noah Goldstein, and others push boundaries while maintaining Rosalía\'s distinctive artistic vision. Her vocals are stunning throughout, demonstrating technical virtuosity and emotional range.',
                            'Conceptually, the album explores duality and empowerment through the metaphor of the motorcycle and the woman, embracing contradictions and complexity. The lyrics blend Spanish and English, traditional and modern references, vulnerability and strength. Each track feels like a complete artistic statement while contributing to the album\'s cohesive narrative.',
                            'In conclusion, "MOTOMAMI" is a landmark album that pushes the boundaries of what pop music can be. It\'s challenging, accessible, experimental, and deeply rooted in tradition all at once. Rosalía has created a work that will be studied and celebrated for years to come.'
                        ]
                    ]
                ]
            ]
        ];

        $pages = [];
        foreach ($reviews as $reviewData) {
            $page = Page::create([
                'title' => $reviewData['title'],
                'slug' => $reviewData['slug'],
                'status' => 'published',
                'page_type' => 'content',
                'meta_title' => $reviewData['title'] . ' - TechWeekly',
                'site_id' => 2,
            ]);

            $category = Category::where('slug', strtolower($reviewData['category']))->where('site_id', 2)->first();
            if ($category) {
                PageCategory::create(['page_id' => $page->id, 'category_id' => $category->id]);
            }

            $tag = Tag::where('slug', $reviewData['artist'])->where('site_id', 2)->first();
            if ($tag) {
                PageTag::create(['page_id' => $page->id, 'tag_id' => $tag->id]);
            }

            $blocks = [
                [
                    'type' => 'image',
                    'data' => [
                        'src' => $reviewData['image'],
                        'alt' => $reviewData['album'],
                        'layout' => 'full',
                        'alignment' => 'fullscreen'
                    ]
                ],
                [
                    'type' => 'heading',
                    'data' => ['text' => $reviewData['title'], 'level' => 1]
                ],
                [
                    'type' => 'award',
                    'data' => [
                        'subcategory' => 'Expert Review',
                        'productName' => $reviewData['album'] . ' by ' . $reviewData['artist'],
                        'winner' => true,
                        'rating' => $reviewData['rating'],
                    ]
                ],
                [
                    'type' => 'text',
                    'data' => $reviewData['review']['data']
                ]
            ];

            $this->createBlocksForPage($page->id, $blocks);
            $pages[] = ['page' => $page, 'data' => $reviewData];
        }

        return $pages;
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
        $homepage = Page::where('slug', 'home')->where('site_id', 38)->first();
        if (!$homepage) return;

        echo "Adding reviews to homepage.\n";

        $reviewItems = [];
        foreach ($reviewPages as $item) {
            $page = $item['page'];
            $data = $item['data'];

            $reviewItems[] = [
                'title' => $page->title,
                'slug' => $page->slug,
                'image' => ['src' => $data['image'], 'alt' => $data['album'] ?? $data['artist'] ?? ''],
                'badge' => ['text' => '⭐ ' . $data['rating'] . '/5', 'color' => 'success'],
                'meta' => [
                    'album' => $data['album'] ?? '',
                    'artist' => $data['artist'] ?? '',
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