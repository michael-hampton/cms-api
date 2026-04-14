<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\Page;
use App\Models\Site;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class GamesRadarReviewSeeder extends Seeder
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
        $this->site = Site::find(6);

        if (!$this->site) {
            echo "TechWeekly site not found.\n";
            return;
        }

        $reviewPages = $this->createReviewPages();
        //$this->addReviewSectionToHomepage($reviewPages);
    }

    private function createReviewPages(): array
    {
        $reviews = [
            [
                'title' => 'The Legend of Zelda: Tears of the Kingdom Review',
                'slug' => 'zelda-tears-kingdom-full-review',
                'game' => 'Tears of the Kingdom',
                'rating' => 5.0,
                'platform' => 'Nintendo Switch',
                'genre' => 'Action-Adventure',
                'image' => 'https://images.unsplash.com/photo-1578374173705-0a5dc6100c7d?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Nintendo\'s "Tears of the Kingdom" is a breathtaking evolution of the open-world Action-Adventure genre. The game builds on the beloved mechanics of "Breath of the Wild," adding layers of complexity, exploration, and story depth that elevate the experience.',
                            'The world design is expansive and detailed, rewarding curiosity and experimentation. Puzzles and dungeons are cleverly designed, offering satisfying challenges without feeling repetitive. Combat is fluid, with a wide range of weapons and abilities that encourage strategic play.',
                            'Visuals are stunning on the Switch hardware, with dynamic lighting, environmental effects, and vibrant character design. Sound design and music complement the immersive atmosphere, enhancing both tension and wonder.',
                            'Overall, "Tears of the Kingdom" sets a new benchmark for open-world gaming and solidifies Nintendo\'s reputation for creating magical, unforgettable adventures.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Elden Ring: Shadow of the Erdtree DLC Review',
                'slug' => 'elden-ring-dlc-full-review',
                'game' => 'Elden Ring DLC',
                'rating' => 4.8,
                'platform' => 'Multi-platform',
                'genre' => 'Action RPG',
                'image' => 'https://images.unsplash.com/photo-1552820728-8b83bb6b773f?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'FromSoftware\'s "Shadow of the Erdtree" DLC expands the vast, haunting world of Elden Ring with new regions, enemies, and epic encounters. It stays true to the series\' signature difficulty while introducing innovative gameplay elements.',
                            'The DLC delivers intricate level design, with hidden paths, secrets, and vertical exploration that rewards curiosity. Boss battles are challenging yet fair, demanding mastery of combat mechanics and tactical planning.',
                            'Visuals and environmental storytelling continue to impress, with hauntingly beautiful landscapes and intricate architectural details. Audio cues and music intensify the tension and emotional impact of encounters.',
                            'In summary, the "Shadow of the Erdtree" DLC enhances the Elden Ring experience, offering both veteran players and newcomers a rewarding, challenging, and visually stunning expansion.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Baldur\'s Gate 3 - One Year Later Review',
                'slug' => 'baldurs-gate-3-one-year-review',
                'game' => 'Baldur\'s Gate 3',
                'rating' => 5.0,
                'platform' => 'PC, PS5, Xbox',
                'genre' => 'RPG',
                'image' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            '"Baldur\'s Gate 3" continues to shine a year after release, with additional content, bug fixes, and polish enhancing the already rich RPG experience. The game offers deep tactical combat and engaging narrative choices.',
                            'Player decisions feel meaningful, with branching storylines and multiple endings that respond dynamically to actions. Character creation and customization are detailed, allowing for a wide variety of playstyles and strategies.',
                            'Visuals are top-notch, from detailed environments to expressive character animations. Audio and voice acting further immerse players in the world, complementing the epic storytelling and strategic gameplay.',
                            'Overall, "Baldur\'s Gate 3" remains a pinnacle of modern RPG design, providing hours of meaningful, challenging, and highly enjoyable gameplay.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Hogwarts Legacy Review: Magic at Your Fingertips',
                'slug' => 'hogwarts-legacy-full-review',
                'game' => 'Hogwarts Legacy',
                'rating' => 4.7,
                'platform' => 'PC, PS5, Xbox',
                'genre' => 'Action RPG',
                'image' => 'https://images.unsplash.com/photo-1606813902469-bca2b6225bfa?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            '"Hogwarts Legacy" delivers a magical Action RPG experience that immerses players in the wizarding world. The game excels at capturing the spirit of Hogwarts while providing a fully interactive and expansive open world.',
                            'Storytelling is compelling, with quests and side missions that offer meaningful choices. Spellcasting and combat are satisfying, with a wide variety of magical abilities that encourage experimentation.',
                            'The attention to environmental detail and atmospheric design is exceptional, creating a sense of wonder and discovery. Performance is smooth across platforms, ensuring a seamless and engaging experience.',
                            'In conclusion, "Hogwarts Legacy" is a must-play for fans of the franchise and RPG enthusiasts seeking an immersive magical adventure.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'God of War: Ragnarok Review',
                'slug' => 'god-of-war-ragnarok-review',
                'game' => 'God of War: Ragnarok',
                'rating' => 4.9,
                'platform' => 'PS5',
                'genre' => 'Action-Adventure',
                'image' => 'https://images.unsplash.com/photo-1605902711622-cfb43c4439b5?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            '"God of War: Ragnarok" continues the epic saga with masterful storytelling, stunning visuals, and engaging combat. Kratos and Atreus\' journey is both emotionally resonant and action-packed.',
                            'The game introduces refined combat mechanics, diverse enemy encounters, and clever puzzle-solving that keeps players engaged throughout. Boss battles are intense and memorable, showcasing the game\'s cinematic flair.',
                            'Environmental design is breathtaking, with detailed landscapes and dynamic weather systems that enhance immersion. The soundtrack and sound effects elevate the narrative tension and emotional depth.',
                            'Overall, "Ragnarok" is a triumphant continuation of the franchise, offering an unforgettable Action-Adventure experience that balances heart, strategy, and spectacle.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Resident Evil 4 Remake Review',
                'slug' => 'resident-evil-4-remake-review',
                'game' => 'Resident Evil 4 Remake',
                'rating' => 4.6,
                'platform' => 'PC, PS5, Xbox',
                'genre' => 'Survival Horror',
                'image' => 'https://images.unsplash.com/photo-1611605699462-6d9f176c05d8?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Capcom\'s "Resident Evil 4 Remake" reimagines the classic survival horror experience with modern graphics, controls, and gameplay enhancements. It maintains the tension and fear that made the original iconic.',
                            'Enemy AI is intelligent and terrifying, while the reworked environments heighten suspense and exploration. Combat is smoother, offering more strategy and variety in encounters.',
                            'Visuals are striking, from detailed character models to atmospheric lighting and effects. Sound design plays a critical role in immersion, making every creak and whisper matter.',
                            'In summary, the remake respects the legacy of the original while modernizing the experience, making it essential for both returning fans and newcomers to survival horror.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Hollow Knight: Silksong Review',
                'slug' => 'hollow-knight-silksong-review',
                'game' => 'Hollow Knight: Silksong',
                'rating' => 4.8,
                'platform' => 'PC, Switch',
                'genre' => 'Indie/Platformer',
                'image' => 'https://images.unsplash.com/photo-1601972580283-7b2d1e8c5f30?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            '"Hollow Knight: Silksong" builds on the success of its predecessor with refined platforming, new abilities, and a hauntingly beautiful world to explore. The game offers precise controls and challenging, rewarding combat.',
                            'Level design is intricate and interconnected, encouraging exploration and rewarding curiosity with secrets, upgrades, and lore. Boss fights are diverse, testing skill and strategy.',
                            'Art direction and soundtrack are mesmerizing, creating an atmospheric indie experience that is both challenging and emotionally engaging.',
                            'Overall, "Silksong" is a standout indie platformer, combining tight gameplay, creative world-building, and immersive storytelling into a must-play adventure.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Among Us: The Reunion Update Review',
                'slug' => 'among-us-reunion-update-review',
                'game' => 'Among Us',
                'rating' => 4.5,
                'platform' => 'PC, Mobile, Switch',
                'genre' => 'Multiplayer/Party',
                'image' => 'https://images.unsplash.com/photo-1587736340568-35669aa5e7f2?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The "Reunion Update" for "Among Us" reinvigorates the social deduction party game with new maps, tasks, and mechanics that enhance player engagement. The update keeps gameplay fresh and encourages strategic teamwork and deception.',
                            'New features and improved UI make navigation smoother, while additional customization options allow players to express themselves creatively. The core gameplay remains addictive and entertaining.',
                            'The social aspect is stronger than ever, supporting group dynamics, alliances, and rivalries. Performance across platforms is stable, ensuring smooth multiplayer sessions.',
                            'Overall, the "Reunion Update" successfully revitalizes "Among Us," making it more enjoyable for both longtime players and newcomers seeking fun, interactive multiplayer experiences.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Stardew Valley: Expanded Horizons Review',
                'slug' => 'stardew-valley-expanded-horizons-review',
                'game' => 'Stardew Valley',
                'rating' => 4.9,
                'platform' => 'PC, Switch, Mobile',
                'genre' => 'Indie/Simulation',
                'image' => 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=800',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            '"Expanded Horizons" adds a wealth of content to "Stardew Valley," including new crops, locations, events, and gameplay mechanics. It enhances the already deep and rewarding farming simulation experience.',
                            'Players can explore new regions, interact with additional villagers, and take on new quests that expand storytelling opportunities. Seasonal events and crafting improvements enrich the daily farming routine.',
                            'Graphics retain the charming pixel-art style, complemented by relaxing music and immersive sound effects. The game maintains its addictive yet soothing gameplay loop.',
                            'In conclusion, "Expanded Horizons" revitalizes "Stardew Valley," offering fresh challenges and experiences while preserving the beloved charm that made the game a fan favorite.'
                        ]
                    ]
                ]
            ]
        ];

        foreach ($reviews as $review) {

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
                'excerpt' => $data['excerpt'],
                'image' => ['src' => $data['image'], 'alt' => $data['game']],
                'badge' => ['text' => '⭐ ' . $data['rating'] . '/5', 'color' => 'success'],
                'meta' => [
                    'category' => $data['platform'],
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