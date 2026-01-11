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
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\BlockParserService;

class GuitarWorldSeeder extends Seeder
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
    }

    private function createSite(): void
    {
        $this->site = Site::create([
            'name' => 'Guitar World',
            'slug' => 'guitar-world',
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
            'featured', 'editors-pick', 'trending', 'breaking',
            'electric-guitar', 'acoustic-guitar', 'bass-guitar',
            'gibson', 'fender', 'prs', 'ibanez', 'martin',
            'rock', 'metal', 'blues', 'jazz', 'country',
            'technique', 'gear-review', 'interview', 'lesson',
            'pedals', 'amps', 'strings', 'pickups',
            'beginner', 'intermediate', 'advanced'
        ];

        foreach ($tags as $tagName) {
            $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
        }
    }

    private function createCategories(): void
    {
        $categories = [
            'News' => ['Breaking News', 'Industry Updates', 'Artist News'],
            'Reviews' => [
                'Guitars' => ['Electric Guitars', 'Acoustic Guitars', 'Bass Guitars'],
                'Gear' => ['Amplifiers', 'Effects Pedals', 'Accessories']
            ],
            'Features' => ['Interviews', 'Lessons', 'Technique', 'Gear Guides'],
            'Artists' => ['Rock', 'Metal', 'Blues', 'Jazz', 'Country']
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
            ['key' => 'author_image', 'name' => 'Author Image', 'type' => 'text'],
            ['key' => 'read_time', 'name' => 'Read Time (minutes)', 'type' => 'number'],
            ['key' => 'excerpt', 'name' => 'Article Excerpt', 'type' => 'textarea'],
            ['key' => 'rating', 'name' => 'Product Rating', 'type' => 'number'],
            ['key' => 'product_price', 'name' => 'Product Price', 'type' => 'text'],
            ['key' => 'manufacturer', 'name' => 'Manufacturer', 'type' => 'text'],
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
            'title' => 'Guitar World - The Ultimate Guitar Magazine',
            'page_type' => 'content',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'Guitar World - Guitar News, Reviews, Lessons & Features',
            'meta_description' => 'The world\'s leading guitar publication featuring gear reviews, artist interviews, lessons, and breaking news.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Home',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
            'sort_order' => 10
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'The World\'s Leading Guitar Magazine',
                    'subtitle' => 'Breaking news, gear reviews, artist interviews, and lessons',
                    'ctaText' => 'Latest News',
                    'ctaUrl' => '#featured',
                    'secondaryCtaText' => 'Gear Reviews',
                    'secondaryCtaUrl' => '#reviews',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Featured Stories',
                    'subtitle' => 'The latest and greatest from Guitar World',
                    'level' => 2
                ],
                'order' => 2
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'layout' => 'masonry',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'showMeta' => true,
                    'pages' => [
                        [
                            'title' => 'Eric Clapton\'s Lost 1954 Fender Stratocaster Discovered',
                            'slug' => 'clapton-lost-strat-discovered',
                            'excerpt' => 'The guitar legend\'s long-lost Stratocaster from his early blues days has been authenticated and will be auctioned.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1564186763535-ebb21ef5277f?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Vintage Fender Stratocaster'
                            ],
                            'badge' => [
                                'text' => 'Breaking News',
                                'color' => 'primary'
                            ],
                            'meta' => [
                                'author' => 'Michael Astley-Brown',
                                'date' => 'December 6, 2025',
                                'readTime' => '6 min read'
                            ]
                        ],
                        [
                            'title' => 'Gibson Les Paul Standard 2025 Review',
                            'slug' => 'gibson-les-paul-standard-2025-review',
                            'excerpt' => 'Gibson\'s latest iteration of the iconic Les Paul Standard delivers timeless tone with modern refinements.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1516924962500-2b4b3b99ea02?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Gibson Les Paul'
                            ],
                            'badge' => [
                                'text' => 'Gear Review',
                                'color' => 'success'
                            ],
                            'meta' => [
                                'author' => 'Jonathan Horsley',
                                'date' => 'December 5, 2025',
                                'readTime' => '12 min read'
                            ]
                        ],
                        [
                            'title' => 'Joe Bonamassa: "These Are My Top 5 Blues Licks',
                            'slug' => 'joe-bonamassa-top-blues-licks',
                            'excerpt' => 'The blues-rock virtuoso shares his essential vocabulary for authentic blues guitar playing.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1511735111819-9a3f7709049c?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Blues guitarist'],

                            'badge' => [
                                'text' => 'Lesson',
                                'color' => 'warning'
                            ],
                            'meta' => [
                                'author' => 'Richard Barrett',
                                'date' => 'December 4, 2025',
                                'readTime' => '8 min read'
                            ]
                        ],
                        [
                            'title' => 'Fender American Pro II Telecaster Review',
                            'slug' => 'fender-am-pro-ii-tele-review',
                            'excerpt' => 'The blues-rock virtuoso shares his essential vocabulary for authentic blues guitar playing.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Fender Telecaster'
                            ],
                            'badge' => [
                                'text' => 'Lesson',
                                'color' => 'warning'
                            ],
                            'meta' => [
                                'author' => 'Richard Barrett',
                                'date' => 'December 4, 2025',
                                'readTime' => '8 min read'
                            ]
                        ],
                        [
                            'title' => 'Strymon BigSky MX Reverb Review',
                            'slug' => 'strymon-bigsky-mx-review',
                            'excerpt' => 'The blues-rock virtuoso shares his essential vocabulary for authentic blues guitar playing.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1614963366795-aad6e05143e3?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Effects pedal'
                            ],

                            'badge' => [
                                'text' => 'Lesson',
                                'color' => 'warning'
                            ],
                            'meta' => [
                                'author' => 'Richard Barrett',
                                'date' => 'December 4, 2025',
                                'readTime' => '8 min read'
                            ]
                        ],
                        [
                            'title' => 'Marshall JVM410H Review',
                            'slug' => 'marshall-jvm410h-review',
                            'excerpt' => 'The blues-rock virtuoso shares his essential vocabulary for authentic blues guitar playing.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1563330232-57114bb0823c?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Marshall amp'
                            ],

                            'badge' => [
                                'text' => 'Lesson',
                                'color' => 'warning'
                            ],
                            'meta' => [
                                'author' => 'Richard Barrett',
                                'date' => 'December 4, 2025',
                                'readTime' => '8 min read'
                            ]
                        ]
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'divider',
                'data' => ['style' => 'solid'],
                'order' => 4
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Latest Gear Reviews',
                    'subtitle' => 'Expert reviews of the newest guitars, amps, and pedals',
                    'level' => 2
                ],
                'order' => 5
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'layout' => 'grid',
                    'columns' => 4,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'showMeta' => true,
                    'pages' => [
                        [
                            'title' => 'PRS SE Custom 24 Review',
                            'slug' => 'prs-se-custom-24-review',
                            'excerpt' => 'Fender\'s workhorse Tele gets refined upgrades that honor its heritage.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1550985543-49bee3167284?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'PRS Guitar'],

                            'meta' => [
                                'author' => 'Chris Gill',
                                'date' => 'December 3, 2025',
                                'readTime' => '10 min read'
                            ]
                        ],
                        [
                            'title' => 'Boss Katana 100 MkII Review',
                            'slug' => 'boss-katana-100-mkii-review',
                            'excerpt' => 'The next generation of Strymon\'s legendary reverb delivers stunning sonic landscapes.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1563330232-57114bb0823c?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Guitar Amplifier'
                            ],

                            'meta' => [
                                'author' => 'Jon Wiederhorn',
                                'date' => 'December 2, 2025',
                                'readTime' => '9 min read'
                            ]
                        ],
                        [
                            'title' => 'Ernie Ball Paradigm Strings Review',
                            'slug' => 'ernie-ball-paradigm-strings-review',
                            'excerpt' => 'Marshall\'s versatile four-channel monster delivers everything from pristine cleans to brutal high-gain.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1525201548942-d8732f6617a0?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Guitar Strings'
                            ],

                            'meta' => [
                                'author' => 'Phil Weller',
                                'date' => 'December 1, 2025',
                                'readTime' => '11 min read'
                            ]
                        ],
                        [
                            'title' => 'Ibanez RG550 Genesis Review',
                            'slug' => 'ibanez-rg550-genesis-review',
                            'excerpt' => 'The ultimate shred machine with cutting-edge features and playability.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1516924962500-2b4b3b99ea02?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Ibanez Guitar'
                            ],

                            'meta' => [
                                'author' => 'Jonathan Horsley',
                                'date' => 'November 29, 2025',
                                'readTime' => '12 min read'
                            ]
                        ],
                        [
                            'title' => 'MXR Carbon Copy Deluxe Review',
                            'slug' => 'mxr-carbon-copy-deluxe-review',
                            'excerpt' => 'The ultimate shred machine with cutting-edge features and playability.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1614963366795-aad6e05143e3?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Delay Pedal'
                            ],

                            'meta' => [
                                'author' => 'Jonathan Horsley',
                                'date' => 'November 29, 2025',
                                'readTime' => '12 min read'
                            ]
                        ],
                        [
                            'title' => 'Mesa Boogie Mark VII Review',
                            'slug' => 'mesa-boogie-mark-vii-review',
                            'excerpt' => 'The ultimate shred machine with cutting-edge features and playability.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1563330232-57114bb0823c?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Mesa Boogie Amp'
                            ],

                            'meta' => [
                                'author' => 'Jonathan Horsley',
                                'date' => 'November 29, 2025',
                                'readTime' => '12 min read'
                            ]
                        ]
                    ]
                ],
                'order' => 6
            ],
            [
                'type' => 'divider',
                'data' => ['style' => 'solid'],
                'order' => 7
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Featured Products',
                    'subtitle' => 'The best gear we\'ve tested this month',
                    'level' => 2
                ],
                'order' => 8
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'showMeta' => true,
                    'pages' => [
                        [
                            'title' => 'Martin D-28 Modern Deluxe Review',
                            'slug' => 'martin-d28-modern-deluxe-review',
                            'excerpt' => 'PRS quality at an accessible price point. This SE model punches well above its weight class.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Martin Acoustic'
                            ],

                            'meta' => [
                                'author' => 'Jonathan Horsley',
                                'date' => 'November 30, 2025',
                                'readTime' => '10 min read'
                            ]
                        ],
                        [
                            'title' => 'Fender Player Plus Stratocaster Review',
                            'slug' => 'fender-player-plus-strat-review',
                            'excerpt' => 'Digital modeling meets tube-like response in this incredible value amplifier.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1564186763535-ebb21ef5277f?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Fender Strat'
                            ],
                            'meta' => [
                                'author' => 'Phil Weller',
                                'date' => 'November 28, 2025',
                                'readTime' => '8 min read'
                            ]
                        ],
                        [
                            'title' => 'Electro-Harmonix Big Muff Pi Review',
                            'slug' => 'ehx-big-muff-pi-review',
                            'excerpt' => 'These ultra-durable strings promise to last 3x longer. We put them to the test.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1614963366795-aad6e05143e3?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Big Muff'
                            ],
                            'meta' => [
                                'author' => 'Chris Gill',
                                'date' => 'November 25, 2025',
                                'readTime' => '6 min read'
                            ]
                        ],
                        [
                            'title' => 'Taylor 814ce Builder\'s Edition Review',
                            'slug' => 'taylor-814ce-builders-edition-review',
                            'excerpt' => 'These ultra-durable strings promise to last 3x longer. We put them to the test.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Taylor Acoustic'
                            ],
                            'meta' => [
                                'author' => 'Chris Gill',
                                'date' => 'November 25, 2025',
                                'readTime' => '6 min read'
                            ]
                        ],
                        [
                            'title' => 'Orange Rockerverb 50 MKIII Review',
                            'slug' => 'orange-rockerverb-50-mkiii-review',
                            'excerpt' => 'These ultra-durable strings promise to last 3x longer. We put them to the test.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1563330232-57114bb0823c?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Orange Amp'
                            ],
                            'meta' => [
                                'author' => 'Chris Gill',
                                'date' => 'November 25, 2025',
                                'readTime' => '6 min read'
                            ]
                        ],
                        [
                            'title' => 'D\'Addario NYXL Strings Review',
                            'slug' => 'daddario-nyxl-strings-review',
                            'excerpt' => 'These ultra-durable strings promise to last 3x longer. We put them to the test.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1525201548942-d8732f6617a0?auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Guitar Strings'
                            ],
                            'meta' => [
                                'author' => 'Chris Gill',
                                'date' => 'November 25, 2025',
                                'readTime' => '6 min read'
                            ]
                        ]
                    ],
                    'layout' => 'grid',
                ],
                'order' => 7
            ],
            [
                'type' => 'team',
                'data' => [
                    'title' => 'Our Expert Team',
                    'subtitle' => 'Meet the guitarists and gear experts behind Guitar World',
                    'layout' => 'grid',
                    'members' => [
                        [
                            'name' => 'Michael Astley-Brown',
                            'role' => 'Editor-in-Chief',
                            'bio' => 'Former touring musician with 20+ years covering the guitar industry.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'michael@guitarworld.com'
                        ],
                        [
                            'name' => 'Jonathan Horsley',
                            'role' => 'Senior Gear Editor',
                            'bio' => 'Gear obsessive specializing in vintage guitars and boutique effects.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'jonathan@guitarworld.com'
                        ],
                        [
                            'name' => 'Richard Barrett',
                            'role' => 'Lessons Editor',
                            'bio' => 'Professional guitar instructor and touring session player.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'richard@guitarworld.com'
                        ]
                    ]
                ],
                'order' => 8
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Subscribe to Guitar World',
                    'subtitle' => 'Get the latest guitar news, reviews, and lessons delivered weekly',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => false,
                    'showSubject' => false,
                    'showMessage' => false,
                    'submitButtonText' => 'Subscribe',
                    'requireName' => true,
                    'requireEmail' => true
                ],
                'order' => 8
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
            [
                'title' => 'Eric Clapton\'s Lost 1954 Fender Stratocaster Discovered in London Basement',
                'slug' => 'clapton-lost-strat-discovered',
                'tags' => ['featured', 'breaking', 'fender', 'blues'],
                'categories' => ['News', 'Breaking News'],
                'custom_fields' => [
                    'author_name' => 'Michael Astley-Brown',
                    'author_bio' => 'Former touring musician with 20+ years covering the guitar industry.',
                    'read_time' => 6,
                    'excerpt' => 'The guitar legend\'s long-lost Stratocaster from his early blues days has been authenticated and will be auctioned.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1564186763535-ebb21ef5277f?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Vintage Fender Stratocaster',
                            'caption' => 'The newly discovered 1954 Stratocaster shows remarkable preservation',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'In a discovery that has sent shockwaves through the vintage guitar community, Eric Clapton\'s long-lost 1954 Fender Stratocaster has been authenticated after being found in a London basement.',
                                'The guitar, which Clapton played during his early days with The Yardbirds, was thought to have been lost in the 1960s. Its rediscovery by a family clearing out their late father\'s estate has been confirmed by guitar historian and authentication expert Walter Carter.',
                                'The sunburst Stratocaster shows the wear patterns consistent with Clapton\'s playing style from that era, including distinctive thumb wear on the back of the neck and pick scratches around the bridge pickup.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'This is one of the most significant vintage guitar discoveries in decades. The provenance is rock-solid, and the instrument tells the story of a pivotal moment in rock history.',
                            'attribution' => 'Walter Carter, Guitar Historian'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Guitar\'s Journey',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'According to family records, the guitar was sold to a music shop owner in 1966 when Clapton upgraded to a newer model. The shop owner, who passed away in 2020, had kept it in storage, unaware of its significance.',
                                'Clapton himself has confirmed the guitar\'s authenticity after viewing detailed photographs. "I remember that guitar well," he said in a statement. "It was my first serious Stratocaster, and I played it on dozens of early recordings."'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'note',
                            'description' => 'The guitar will be auctioned by Christie\'s in March 2026, with estimates placing its value between $500,000 and $800,000.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Gibson Les Paul Standard 2025 Review: Classic Tone Meets Modern Performance',
                'slug' => 'gibson-les-paul-standard-2025-review',
                'tags' => ['featured', 'gear-review', 'gibson', 'editors-pick'],
                'categories' => ['Reviews', 'Guitars', 'Electric Guitars'],
                'custom_fields' => [
                    'author_name' => 'Jonathan Horsley',
                    'author_bio' => 'Gear obsessive specializing in vintage guitars and boutique effects.',
                    'read_time' => 12,
                    'rating' => 5,
                    'product_price' => '$2,799',
                    'manufacturer' => 'Gibson',
                    'excerpt' => 'Gibson\'s latest iteration of the iconic Les Paul Standard delivers timeless tone with modern refinements.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1516924962500-2b4b3b99ea02?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Gibson Les Paul Standard 2025',
                            'caption' => 'The 2025 Les Paul Standard in Heritage Cherry Sunburst',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Gibson Les Paul Standard 2025',
                            'brand' => 'Gibson',
                            'productName' => 'Les Paul Standard',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1516924962500-2b4b3b99ea02?auto=format&fit=crop&w=800&q=80'],
                            'price' => 2799.00,
                            'currency' => '$',
                            'description' => 'The legendary Les Paul Standard with hand-wired electronics and weight-relieved mahogany body.',
                            'link' => 'https://example.com/gibson-les-paul',
                            'linkText' => 'Check Price',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 5.0,
                                'pros' => [
                                    'Exceptional build quality and playability',
                                    'Classic PAF-style humbucker tones',
                                    'Weight-relieved body reduces fatigue',
                                    'Hand-wired electronics for superior clarity',
                                    'Nitrocellulose lacquer finish ages beautifully'
                                ],
                                'cons' => [
                                    'Premium price point',
                                    'Still heavier than many modern guitars',
                                    'Limited tonal versatility compared to HSS guitars'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Gibson\'s Les Paul Standard has been the backbone of rock and blues for over 70 years, and the 2025 model proves the design is more relevant than ever.',
                                'This latest iteration refines the classic formula with modern manufacturing techniques while maintaining the hand-built quality and tonal characteristics that made the original legendary.',
                                'From the moment you pick it up, the Les Paul Standard 2025 exudes quality. The weight-relieved mahogany body provides the classic Les Paul sustain while reducing shoulder fatigue during long sessions.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Tone and Performance',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Burstbucker pickups deliver the perfect balance of vintage PAF warmth and modern output. The neck pickup provides smooth, creamy jazz tones, while the bridge pickup snarls with aggressive rock bite.',
                                'Split-coil tapping via the push-pull tone controls opens up a world of single-coil-like tones, making this Les Paul more versatile than traditionalists might expect.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Specification', 'Details'],
                                ['Body', 'Weight-relieved mahogany'],
                                ['Top', 'AAA flame maple'],
                                ['Neck', 'Mahogany, rounded profile'],
                                ['Fretboard', 'Rosewood, 22 frets'],
                                ['Pickups', 'Burstbucker 61R/61T'],
                                ['Electronics', 'Hand-wired with Orange Drop caps'],
                                ['Hardware', 'Chrome-plated Tune-O-Matic'],
                                ['Finish', 'Nitrocellulose lacquer'],
                                ['Weight', '8.5 lbs']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'This is simply the best Les Paul Standard Gibson has made in years. It honors the past while embracing modern playability.',
                            'attribution' => 'Jonathan Horsley, Guitar World'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Verdict',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Gibson Les Paul Standard 2025 is a masterclass in guitar building. Whether you\'re a blues player seeking vocal sustain or a rock player needing aggressive crunch, this guitar delivers.',
                                'At $2,799, it\'s a significant investment, but you\'re getting an instrument that will serve you for a lifetime and likely appreciate in value.',
                                'If you\'re in the market for a premium Les Paul, this 2025 Standard should be at the top of your list.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'award',
                        'data' => [
                            'subcategory' => 'Editors\' Choice Award',
                            'productName' => 'Gibson Les Paul Standard 2025',
                            'winner' => true,
                            'rating' => 5.0,
                            'strapline' => 'The quintessential Les Paul for modern players',
                            'caption' => 'Outstanding build quality, tone, and playability make this our top pick'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Joe Bonamassa: "These Are My Top 5 Blues Licks That Every Guitarist Should Know"',
                'slug' => 'joe-bonamassa-top-blues-licks',
                'tags' => ['featured', 'lesson', 'blues', 'technique', 'intermediate'],
                'categories' => ['Features', 'Lessons'],
                'custom_fields' => [
                    'author_name' => 'Richard Barrett',
                    'author_bio' => 'Professional guitar instructor and touring session player.',
                    'read_time' => 8,
                    'excerpt' => 'The blues-rock virtuoso shares his essential vocabulary for authentic blues guitar playing.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1511735111819-9a3f7709049c?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Blues guitarist performing',
                            'caption' => 'Joe Bonamassa shares his essential blues vocabulary',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Joe Bonamassa is widely regarded as one of the finest blues guitarists of his generation. With over 40 albums and countless performances under his belt, he\'s mastered the art of blues guitar.',
                                'We sat down with Joe to discuss the essential blues licks that form the foundation of his playing. These five phrases represent the core vocabulary every blues guitarist should have in their arsenal.',
                                '"Blues is a language," Bonamassa explains. "These licks are like words in that language. Once you know them, you can start putting together your own sentences."'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Lick #1: The Classic Blues Box Turnaround',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'This lick uses the minor pentatonic scale with added chromatic passing tones. It\'s the foundation of countless blues solos from B.B. King to Eric Clapton.',
                                '"Start on the root note, bend up a whole step, then work your way down the box pattern," Joe advises. "The key is in the phrasing - let each note breathe."'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Practice this lick slowly with a metronome, focusing on timing and vibrato before increasing speed.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Lick #2: The Shuffle Rhythm Riff',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Every blues player needs a solid shuffle in their vocabulary. This Texas-style shuffle uses open strings and hammer-ons to create that authentic boogie feel.',
                                '"This is straight out of Stevie Ray Vaughan\'s playbook," Bonamassa notes. "The key is keeping that triplet feel locked in tight."'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Start with your index finger on the 5th fret low E string',
                                'Add your ring finger on the 7th fret for the shuffle interval',
                                'Use a palm-muted attack for the classic Texas tone',
                                'Alternate between the root and the fifth with swing timing',
                                'Add open string pull-offs for extra rhythmic interest'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Lick #3: The Albert King Bend',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Albert King\'s string bending approach was revolutionary. Playing left-handed on a right-handed guitar strung upside-down, he developed a unique bending style that\'s instantly recognizable.',
                                '"You want to bend from the minor third up to the major third, then back down," Joe explains. "It\'s that cry that makes it so vocal and emotional."'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Lick #4: The T-Bone Walker Turnaround',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'T-Bone Walker pioneered electric blues guitar, and this turnaround is essential for any 12-bar blues progression. It uses chord fragments and single-note lines to create movement.',
                                'This lick works perfectly at the end of a 12-bar progression, setting up the return to the I chord with style and sophistication.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Lick #5: The Chicago-Style Slide Phrase',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Even if you\'re not a slide player, understanding this lick will improve your phrasing. It uses wide interval jumps and expressive vibrato to create that haunting Chicago blues sound.',
                                '"Muddy Waters and Elmore James made this approach famous," Bonamassa says. "Try to make your guitar cry like the human voice."'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'These five licks are your foundation. Master them, then make them your own. That\'s how the blues tradition continues.',
                            'attribution' => 'Joe Bonamassa'
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Practice Tips',
                            'paragraphs' => [
                                'Work on each lick separately before combining them. Use a backing track in various keys to develop your ear.',
                                'Focus on tone, vibrato, and phrasing rather than speed. Blues is about feel, not flash.',
                                'Record yourself playing these licks and listen back critically. Are you capturing the emotion?'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Fender American Professional II Telecaster Review: The Workhorse Gets Better',
                'slug' => 'fender-am-pro-ii-tele-review',
                'tags' => ['gear-review', 'fender', 'editors-pick'],
                'categories' => ['Reviews', 'Guitars', 'Electric Guitars'],
                'custom_fields' => [
                    'author_name' => 'Chris Gill',
                    'author_bio' => 'Gear reviewer specializing in Fender guitars and American-made instruments.',
                    'read_time' => 10,
                    'rating' => 4.5,
                    'product_price' => '$1,799',
                    'manufacturer' => 'Fender',
                    'excerpt' => 'Fender\'s workhorse Tele gets refined upgrades that honor its heritage.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Fender Telecaster',
                            'caption' => 'The American Professional II Telecaster in Butterscotch Blonde',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Fender Telecaster is arguably the most versatile electric guitar ever made. From country to punk, jazz to metal, the Tele has proven itself across every genre.',
                                'The American Professional II series represents Fender\'s modern take on their classic designs, incorporating player feedback and manufacturing improvements while maintaining the essence that made these guitars legendary.',
                                'This Telecaster proves you can refine perfection without losing what made it perfect in the first place.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Fender American Professional II Telecaster',
                            'subtitle' => 'Modern refinements meet timeless Tele tone',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?auto=format&fit=crop&w=800&q=80'],
                            'specs' => [
                                ['text' => 'Body', 'value' => 'Alder'],
                                ['text' => 'Neck', 'value' => 'Maple, Deep C profile'],
                                ['text' => 'Fretboard', 'value' => 'Maple or Rosewood'],
                                ['text' => 'Pickups', 'value' => 'V-Mod II Telecaster'],
                                ['text' => 'Bridge', 'value' => 'Top-load or string-through'],
                                ['text' => 'Hardware', 'value' => 'Upgraded bent-steel saddles']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Comfortable Deep C neck profile',
                                'V-Mod II pickups offer classic tone with more clarity',
                                'Top-notch build quality and finish',
                                'Rolled fretboard edges enhance playability',
                                'Versatile enough for any genre'
                            ],
                            'cons' => [
                                'Premium pricing may deter some players',
                                'Traditional Tele bridge can still have sharp edges',
                                'Some purists prefer vintage-spec pickups'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What\'s New in Series II',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'V-Mod II pickups deliver enhanced clarity and note definition',
                                'Deep C neck shape provides modern comfort',
                                'Rolled fingerboard edges for a played-in feel',
                                'Upgraded bent-steel saddles improve intonation',
                                'Super-Natural satin finish on neck back',
                                'Top-load string option reduces string tension'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'This is the Telecaster for players who want classic tone with modern playability. It\'s everything a Tele should be.',
                            'attribution' => 'Chris Gill, Guitar World'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Strymon BigSky MX Reverb Pedal Review: Endless Sonic Possibilities',
                'slug' => 'strymon-bigsky-mx-review',
                'tags' => ['gear-review', 'pedals', 'effects'],
                'categories' => ['Reviews', 'Gear', 'Effects Pedals'],
                'custom_fields' => [
                    'author_name' => 'Jon Wiederhorn',
                    'author_bio' => 'Effects pedal specialist and recording engineer.',
                    'read_time' => 9,
                    'rating' => 5,
                    'product_price' => '$679',
                    'manufacturer' => 'Strymon',
                    'excerpt' => 'The next generation of Strymon\'s legendary reverb delivers stunning sonic landscapes.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1614963366795-aad6e05143e3?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Strymon BigSky MX',
                            'caption' => 'The BigSky MX offers studio-quality reverb in a pedalboard format',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Strymon\'s BigSky revolutionized what guitarists could expect from a reverb pedal. The new BigSky MX takes that foundation and builds upon it with more power, more presets, and more sonic possibilities.',
                                'This isn\'t just a reverb pedal—it\'s a complete ambient sound design studio that happens to fit on your pedalboard.',
                                'From subtle room ambience to otherworldly soundscapes, the BigSky MX delivers reverb tones that rival high-end studio processors.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Strymon BigSky MX',
                            'brand' => 'Strymon',
                            'productName' => 'Multi-Algorithm Reverb Pedal',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1614963366795-aad6e05143e3?auto=format&fit=crop&w=800&q=80'],
                            'price' => 679.00,
                            'currency' => 'GBP',
                            'description' => '12 reverb algorithms with dual processing, MIDI, extensive I/O, and 300+ presets.',
                            'link' => 'https://example.com/bigsky-mx',
                            'linkText' => 'Check Price',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 5.0,
                                'pros' => [
                                    'Stunning reverb quality across all algorithms',
                                    '300+ preset slots for extensive sound library',
                                    'Dual processing allows complex layering',
                                    'Comprehensive MIDI implementation',
                                    'Professional studio-quality sound'
                                ],
                                'cons' => [
                                    'Premium price point',
                                    'Large footprint on pedalboard',
                                    'Deep menu system has learning curve'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Key Features',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Feature', 'Specification'],
                                ['Algorithms', '12 reverb types'],
                                ['Processing', 'Dual stereo reverb engines'],
                                ['Presets', '300+ slots'],
                                ['I/O', 'Stereo in/out, MIDI, expression'],
                                ['Controls', '8 knobs, 3 footswitches, color display'],
                                ['Bypass', 'Buffered or true bypass'],
                                ['Power', '9V DC, 500mA']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The BigSky MX is the most powerful and versatile reverb pedal on the market. It\'s an investment that will serve you for years.',
                            'attribution' => 'Jon Wiederhorn, Guitar World'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Marshall JVM410H Review: The Ultimate British Stack',
                'slug' => 'marshall-jvm410h-review',
                'tags' => ['gear-review', 'amps', 'marshall'],
                'categories' => ['Reviews', 'Gear', 'Amplifiers'],
                'custom_fields' => [
                    'author_name' => 'Phil Weller',
                    'author_bio' => 'Amplifier specialist with 30 years of studio and live experience.',
                    'read_time' => 11,
                    'rating' => 4.5,
                    'product_price' => '$2,199',
                    'manufacturer' => 'Marshall',
                    'excerpt' => 'Marshall\'s versatile four-channel monster delivers everything from pristine cleans to brutal high-gain.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1563330232-57114bb0823c?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Marshall JVM410H',
                            'caption' => 'The JVM410H delivers 100 watts of pure British tone',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Marshall\'s JVM series represents the company\'s most versatile amplifier line. The JVM410H takes this versatility to the extreme with four channels, three modes per channel, and a massive tonal palette.',
                                'This 100-watt all-tube head can cover everything from sparkling cleans to crushing metal distortion, making it a genuine one-amp-does-all solution for working guitarists.',
                                'While it maintains Marshall\'s classic voicing, the JVM410H offers unprecedented flexibility for players who need multiple sounds in one package.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'JVM410H vs JCM800',
                            'productA' => 'JVM410H (Modern)',
                            'productB' => 'JCM800 (Classic)',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Channels',
                                    'items' => [
                                        ['value' => '4 channels (12 modes total)'],
                                        ['value' => '2 channels']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Versatility',
                                    'items' => [
                                        ['value' => 'Extreme (clean to brutal)'],
                                        ['value' => 'Limited (crunch to lead)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Effects Loop',
                                    'items' => [
                                        ['value' => 'Series/Parallel with mix control'],
                                        ['value' => 'Series only']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Footswitching',
                                    'items' => [
                                        ['value' => 'Full MIDI control'],
                                        ['value' => 'Basic channel switching']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'award',
                        'data' => [
                            'subcategory' => 'Best Versatile Amp Head 2025',
                            'productName' => 'Marshall JVM410H',
                            'winner' => true,
                            'rating' => 4.5,
                            'strapline' => 'Unmatched versatility in a classic British package'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'PRS SE Custom 24 Review',
                'slug' => 'prs-se-custom-24-review',
                'tags' => ['gear-review', 'prs'],
                'categories' => ['Reviews', 'Guitars', 'Electric Guitars'],
                'custom_fields' => ['author_name' => 'Jonathan Horsley', 'read_time' => 10, 'rating' => 4.5, 'excerpt' => 'PRS quality at accessible price.'],
                'image' => ['src' => 'https://images.unsplash.com/photo-1550985543-49bee3167284?auto=format&fit=crop&w=800&q=80', 'alt' => 'PRS Guitar'],
                'badge' => ['text' => 'Review', 'color' => 'success'],
                'content' => [
                    ['type' => 'image', 'data' => ['src' => 'https://images.unsplash.com/photo-1550985543-49bee3167284?auto=format&fit=crop&w=800&q=80', 'alt' => 'PRS SE Custom 24', 'caption' => 'PRS SE Custom 24 in Vintage Sunburst', 'layout' => 'full', 'alignment' => 'fullscreen'], 'order' => 1],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The PRS SE Custom 24 brings the iconic PRS design and build philosophy to a more accessible price point without significant compromise. This is not a budget guitar - it\'s a professional instrument manufactured overseas.', 'Featuring a mahogany body with maple top, 85/15 "S" humbuckers, and PRS\'s Wide Thin neck profile, the SE Custom 24 delivers surprising value. The attention to detail rivals guitars costing twice as much.']], 'order' => 2],
                    ['type' => 'heading', 'data' => ['text' => 'Core Features', 'subtitle' => 'PRS quality at an accessible price', 'level' => 3], 'order' => 3],
                    ['type' => 'note', 'data' => ['title' => 'Build Specifications', 'paragraphs' => ['Body: Mahogany with beveled maple top', 'Neck: Maple, Wide Thin profile, 25" scale length', 'Fingerboard: Rosewood, 24 frets, bird inlays', 'Pickups: 85/15 "S" humbuckers', 'Hardware: PRS tremolo, locking tuners'], 'alignment' => 'fullscreen'], 'order' => 4],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The 85/15 "S" pickups are surprisingly versatile. The coil-tap function accessed via the push-pull tone control adds genuine single-coil tones, making this guitar suitable for country, blues, rock, and metal.', 'Playability is outstanding. The Wide Thin neck feels fast without being too thin, and the satin finish allows smooth position changes. The fretwork is excellent with no sharp edges and perfectly level frets. The PRS tremolo stays in tune remarkably well even with aggressive use.']], 'order' => 5],
                    ['type' => 'list', 'data' => ['listType' => 'ul', 'schemaType' => 'none', 'items' => ['Exceptional build quality for the price point', 'Versatile 85/15 "S" pickups with coil-tap', 'Comfortable Wide Thin neck profile', 'Stable PRS tremolo system', 'Includes quality gig bag']], 'order' => 6]
                ]
            ],
            [
                'title' => 'Boss Katana 100 MkII Review',
                'slug' => 'boss-katana-100-mkii-review',
                'tags' => ['gear-review', 'amps'],
                'categories' => ['Reviews', 'Gear', 'Amplifiers'],
                'custom_fields' => ['author_name' => 'Phil Weller', 'read_time' => 8, 'rating' => 4.5, 'excerpt' => 'Best practice amp on the market.'],
                'image' => ['src' => 'https://images.unsplash.com/photo-1563330232-57114bb0823c?auto=format&fit=crop&w=800&q=80', 'alt' => 'Guitar Amplifier'],
                'badge' => ['text' => 'Review', 'color' => 'success'],
                'content' => [
                    ['type' => 'image', 'data' => ['src' => 'https://images.unsplash.com/photo-1563330232-57114bb0823c?auto=format&fit=crop&w=800&q=80', 'alt' => 'Boss Katana 100 MkII', 'caption' => 'Boss Katana 100 MkII combo amplifier', 'layout' => 'full', 'alignment' => 'fullscreen'], 'order' => 1],
                    ['type' => 'heading', 'data' => ['text' => 'The Practice Amp Redefined', 'subtitle' => 'Studio-quality tones at bedroom volumes', 'level' => 2], 'order' => 2],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The Boss Katana 100 MkII has become the go-to practice amplifier for guitarists worldwide, and for good reason. It delivers authentic tube-like response and studio-quality effects at any volume level, all at an incredibly affordable price.', 'With 100 watts of power through a single 12-inch speaker, five amp characters, and 60+ effects accessible via the Boss Tone Studio software, the Katana 100 MkII offers more flexibility than most players will ever need.']], 'order' => 3],
                    ['type' => 'info', 'data' => ['infoType' => 'tip', 'description' => 'Download the free Boss Tone Studio software to unlock the full potential of the Katana. You can access all 60+ effects and save custom patches.'], 'order' => 4],
                    ['type' => 'note', 'data' => ['title' => 'Amp Characters', 'paragraphs' => ['Acoustic: Optimized for acoustic-electric guitars', 'Clean: Sparkling cleans with excellent headroom', 'Crunch: Classic British-style overdrive', 'Lead: High-gain tones for rock and metal', 'Brown: Modern high-gain with tight low end'], 'alignment' => 'fullscreen'], 'order' => 5],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The sound quality is exceptional, especially considering the price point. The Clean and Crunch channels nail classic tones, while the Lead and Brown channels provide crushing high-gain with excellent articulation.', 'The effects are surprisingly usable. The reverbs, delays, and modulation effects are studio-quality, and you can run up to three effects simultaneously plus reverb and delay. The USB recording output makes home recording simple and sounds professional.', 'At bedroom volumes, the Katana sounds great thanks to the built-in power control. The 0.5-watt setting maintains tone and feel at whisper-quiet volumes. The aux input and headphone output make silent practice convenient.']], 'order' => 6]
                ]
            ],
            [
                'title' => 'Ernie Ball Paradigm Strings Review',
                'slug' => 'ernie-ball-paradigm-strings-review',
                'tags' => ['gear-review', 'strings'],
                'categories' => ['Reviews', 'Gear', 'Accessories'],
                'custom_fields' => ['author_name' => 'Chris Gill', 'read_time' => 6, 'rating' => 4, 'excerpt' => 'Ultra-durable strings tested.'],
                'image' => ['src' => 'https://images.unsplash.com/photo-1525201548942-d8732f6617a0?auto=format&fit=crop&w=800&q=80', 'alt' => 'Guitar Strings'],
                'badge' => ['text' => 'Review', 'color' => 'success'],
                'content' => [
                    ['type' => 'image', 'data' => ['src' => 'https://images.unsplash.com/photo-1525201548942-d8732f6617a0?auto=format&fit=crop&w=800&q=80', 'alt' => 'Ernie Ball Paradigm Strings', 'caption' => 'Paradigm strings promise 37% more break resistance', 'layout' => 'full', 'alignment' => 'fullscreen'], 'order' => 1],
                    ['type' => 'heading', 'data' => ['text' => 'Unbreakable Strings?', 'subtitle' => 'Ernie Ball\'s engineering breakthrough tested', 'level' => 2], 'order' => 2],
                    ['type' => 'text', 'data' => ['paragraphs' => ['Ernie Ball\'s Paradigm strings make bold claims about durability and longevity. Using proprietary RPS (Reinforced Plain String) technology and Everlast nanotechnology, these strings promise to last significantly longer than traditional sets.', 'We subjected multiple sets to rigorous testing over three months, including aggressive bending, heavy pick attack, and exposure to various environmental conditions to verify these claims.']], 'order' => 3],
                    ['type' => 'note', 'data' => ['title' => 'Technology Breakdown', 'paragraphs' => ['RPS Technology: Patented reinforcement at ball end prevents breakage at the most common failure point', 'Everlast Coating: Plasma-enhanced nano-treatment resists corrosion without affecting tone', 'Wound String Core: Upgraded wire increases fatigue resistance by 35%'], 'alignment' => 'fullscreen'], 'order' => 4],
                    ['type' => 'text', 'data' => ['paragraphs' => ['After three months of heavy use, our test sets showed minimal corrosion and maintained tension remarkably well. The high E string, typically the first casualty, survived aggressive bending that would normally break standard strings within weeks.', 'Tonally, these strings sound nearly identical to regular Slinkys. The coating is so thin it doesn\'t deaden the tone like some coated strings. Sustain and brightness remain consistent throughout their extended lifespan.']], 'order' => 5],
                    ['type' => 'list', 'data' => ['listType' => 'ul', 'schemaType' => 'none', 'items' => ['<strong>Pros:</strong> Exceptional durability, maintains tone longer, no break resistance claims verified', '<strong>Cons:</strong> 2x the cost of regular strings, slightly stiffer feel initially', '<strong>Best For:</strong> Heavy players, touring musicians, humid climates']], 'order' => 6]
                ]
            ],
            [
                'title' => 'Ibanez RG550 Genesis Review',
                'slug' => 'ibanez-rg550-genesis-review',
                'tags' => ['gear-review', 'ibanez', 'metal'],
                'categories' => ['Reviews', 'Guitars', 'Electric Guitars'],
                'custom_fields' => ['author_name' => 'Jonathan Horsley', 'read_time' => 11, 'rating' => 4.5, 'excerpt' => 'Classic shredder returns.'],
                'image' => ['src' => 'https://images.unsplash.com/photo-1516924962500-2b4b3b99ea02?auto=format&fit=crop&w=800&q=80', 'alt' => 'Ibanez Guitar'],
                'badge' => ['text' => 'Review', 'color' => 'success'],
                'content' => [
                    ['type' => 'image', 'data' => ['src' => 'https://images.unsplash.com/photo-1516924962500-2b4b3b99ea02?auto=format&fit=crop&w=800&q=80', 'alt' => 'Ibanez RG550 Genesis', 'caption' => 'The RG550 Genesis returns to shred', 'layout' => 'full', 'alignment' => 'fullscreen'], 'order' => 1],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The Ibanez RG550 Genesis is a faithful reissue of the legendary shred machine that defined an era. First released in 1987, the RG550 became the weapon of choice for virtuoso players worldwide.', 'This modern recreation maintains the original\'s specifications while incorporating subtle manufacturing improvements. It\'s a love letter to the golden age of instrumental rock guitar.']], 'order' => 2],
                    ['type' => 'heading', 'data' => ['text' => 'Built for Speed', 'subtitle' => 'The ultimate shredder returns', 'level' => 3], 'order' => 3],
                    ['type' => 'note', 'data' => ['title' => 'Classic RG Specs', 'paragraphs' => ['Body: Basswood with Laser Blue finish', 'Neck: Maple Super Wizard profile, 25.5" scale', 'Fingerboard: Maple with 24 jumbo frets, sharktooth inlays', 'Pickups: V7/S1/V8 passive pickups', 'Hardware: Edge tremolo, Gotoh locking tuners'], 'alignment' => 'fullscreen'], 'order' => 4],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The Super Wizard neck is impossibly thin and fast. Combined with the jumbo frets and flat 15.75" radius, this guitar is optimized for technical playing. The satin finish feels broken-in from day one.', 'The Edge tremolo is a marvel of engineering. It stays in tune through dive bombs and extreme vibrato while returning perfectly to pitch. The knife-edge pivot design has been refined over decades and it shows.', 'Tonally, the RG550 delivers classic hot-rodded humbucker sounds. The V7 neck pickup is smooth for leads, while the V8 bridge offers tight, aggressive tones perfect for metal riffing. The middle S1 single-coil adds versatility.']], 'order' => 5],
                    ['type' => 'quote', 'data' => ['text' => 'The RG550 Genesis proves that sometimes the classics don\'t need improvement. This is the shred guitar perfected, and it\'s every bit as relevant today as it was in 1987.', 'attribution' => 'Jonathan Horsley, Guitar World'], 'order' => 6]
                ]
            ],
            [
                'title' => 'MXR Carbon Copy Deluxe Review',
                'slug' => 'mxr-carbon-copy-deluxe-review',
                'tags' => ['gear-review', 'pedals'],
                'categories' => ['Reviews', 'Gear', 'Effects Pedals'],
                'custom_fields' => ['author_name' => 'Jon Wiederhorn', 'read_time' => 7, 'rating' => 4.5, 'excerpt' => 'Analog delay perfection.'],
                'image' => ['src' => 'https://images.unsplash.com/photo-1614963366795-aad6e05143e3?auto=format&fit=crop&w=800&q=80', 'alt' => 'Delay Pedal'],
                'badge' => ['text' => 'Review', 'color' => 'success'],
                'content' => [
                    ['type' => 'image', 'data' => ['src' => 'https://images.unsplash.com/photo-1614963366795-aad6e05143e3?auto=format&fit=crop&w=800&q=80', 'alt' => 'MXR Carbon Copy Deluxe', 'caption' => 'MXR\'s expanded analog delay masterpiece', 'layout' => 'full', 'alignment' => 'fullscreen'], 'order' => 1],
                    ['type' => 'heading', 'data' => ['text' => 'Analog Delay Perfection', 'subtitle' => 'The Carbon Copy grows up', 'level' => 2], 'order' => 2],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The MXR Carbon Copy Deluxe expands on the beloved original with tap tempo, expression pedal control, and extended delay time. It maintains the warm, organic character of bucket-brigade analog delay while adding modern functionality.', 'With up to 900ms of delay time (double the original), modulation controls, and true bypass switching, the Deluxe version addresses every limitation of the standard Carbon Copy without losing its soul.']], 'order' => 3],
                    ['type' => 'info', 'data' => ['infoType' => 'tip', 'description' => 'Use an expression pedal with the exp input to control delay time in real-time for pitch-shifting effects and creative soundscapes.'], 'order' => 4],
                    ['type' => 'note', 'data' => ['title' => 'Control Layout', 'paragraphs' => ['Delay Time: 30ms to 900ms of warm analog delay', 'Repeats: Feedback control from single repeat to infinite oscillation', 'Mix: Blend between dry and delayed signal', 'Modulation: Rate and Width controls for chorus-like movement', 'Tap Tempo: Set delay time on the fly with subdivisions'], 'alignment' => 'fullscreen'], 'order' => 5],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The sound is quintessential analog delay - warm, slightly dark, with natural degradation on the repeats. The modulation adds subtle movement that\'s perfect for ambient textures.', 'Build quality is tank-like, as expected from MXR. The enclosure is rugged enough for years of gigging. The bright LED makes tap tempo easy to see on dark stages.', 'At this price point, the Carbon Copy Deluxe competes with boutique analog delays costing twice as much. It\'s the sweet spot between affordability and professional performance.']], 'order' => 6]
                ]
            ],
            [
                'title' => 'Mesa Boogie Mark VII Review',
                'slug' => 'mesa-boogie-mark-vii-review',
                'tags' => ['gear-review', 'amps'],
                'categories' => ['Reviews', 'Gear', 'Amplifiers'],
                'custom_fields' => ['author_name' => 'Phil Weller', 'read_time' => 12, 'rating' => 5, 'excerpt' => 'Mesa\'s flagship amp refined.'],
                'image' => ['src' => 'https://images.unsplash.com/photo-1563330232-57114bb0823c?auto=format&fit=crop&w=800&q=80', 'alt' => 'Mesa Boogie Amp'],
                'badge' => ['text' => 'Review', 'color' => 'success'],
                'content' => [
                    ['type' => 'image', 'data' => ['src' => 'https://images.unsplash.com/photo-1563330232-57114bb0823c?auto=format&fit=crop&w=800&q=80', 'alt' => 'Mesa Boogie Mark VII', 'caption' => 'The Mark VII: Mesa\'s most advanced amplifier', 'layout' => 'full', 'alignment' => 'fullscreen'], 'order' => 1],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The Mesa Boogie Mark VII represents the apex of the legendary Mark series lineage. Combining the best features from Mark IIC+, Mark IV, and Mark V into one amplifier, it offers unprecedented tonal flexibility and modern features.', 'This is Mesa\'s most ambitious amplifier, featuring three channels with multiple modes, CabClone IR, MIDI control, and the coveted Mark series lead tone that has defined rock and metal for four decades.']], 'order' => 2],
                    ['type' => 'heading', 'data' => ['text' => 'Three Channels of Mesa Magic', 'subtitle' => 'From sparkling cleans to crushing leads', 'level' => 3], 'order' => 3],
                    ['type' => 'list', 'data' => ['listType' => 'ul', 'schemaType' => 'none', 'items' => ['Channel 1: Clean, Fat, Crunch - Vintage Fender to British overdrive', 'Channel 2: Crunch, Mark VII, Mark IV - Modern rhythm tones', 'Channel 3: Mark IIC+, Mark IV, Mark VII - Legendary lead voices']], 'order' => 4],
                    ['type' => 'text', 'data' => ['paragraphs' => ['Channel 1 delivers pristine cleans with massive headroom. The Fat mode adds body perfect for jazz, while Crunch provides classic rock overdrive. The independent graphic EQ on each channel allows surgical tone shaping.', 'Channel 3\'s Mark IIC+ mode is the holy grail. This is the tone heard on thousands of metal records - singing sustain, harmonic richness, and that signature mid-range punch. The Mark VII mode adds modern aggression with tighter low end.', 'The CabClone IR section is revolutionary. With 50+ speaker cab simulations and room ambiences, you can record or play silently with convincing amp-in-the-room feel. The headphone output sounds incredible.', 'MIDI implementation is comprehensive. Store 128 presets, control every parameter, and integrate seamlessly with modern rigs. The amp footswitch makes channel switching and effect loop control effortless.']], 'order' => 5],
                    ['type' => 'quote', 'data' => ['text' => 'The Mark VII is Mesa\'s masterpiece. It captures every great tone from the Mark series history while adding features that make it the most practical Mark amplifier ever made.', 'attribution' => 'Phil Weller, Guitar World'], 'order' => 6]
                ]
            ],
            // Grid 3: Featured Products (6 articles)
            [
                'title' => 'Martin D-28 Modern Deluxe Review',
                'slug' => 'martin-d28-modern-deluxe-review',
                'tags' => ['gear-review', 'martin', 'acoustic-guitar'],
                'categories' => ['Reviews', 'Guitars', 'Acoustic Guitars'],
                'custom_fields' => ['author_name' => 'Chris Gill', 'read_time' => 10, 'rating' => 5, 'excerpt' => 'Legendary acoustic updated.'],
                'image' => ['src' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?auto=format&fit=crop&w=800&q=80', 'alt' => 'Martin Acoustic'],
                'badge' => ['text' => 'Review', 'color' => 'success'],
                'content' => [
                    ['type' => 'image', 'data' => ['src' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?auto=format&fit=crop&w=800&q=80', 'alt' => 'Martin D-28 Modern Deluxe', 'caption' => 'The reimagined D-28 Modern Deluxe', 'layout' => 'full', 'alignment' => 'fullscreen'], 'order' => 1],
                    ['type' => 'heading', 'data' => ['text' => 'A Legend Reimagined', 'subtitle' => 'Martin updates an icon for modern players', 'level' => 2], 'order' => 2],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The Martin D-28 Modern Deluxe takes the legendary dreadnought blueprint and refines it with modern appointments. Forward-shifted scalloped X-bracing, a titanium truss rod, and LR Baggs VTC electronics create a contemporary instrument that honors tradition.', 'This isn\'t a vintage reissue - it\'s a forward-thinking evolution of the most recorded acoustic guitar in history. Martin has addressed every player request while maintaining the thunderous low end and sparkling highs that define the D-28 sound.']], 'order' => 3],
                    ['type' => 'note', 'data' => ['title' => 'Premium Specifications', 'paragraphs' => ['Top: Sitka spruce with forward-shifted X-bracing', 'Back/Sides: East Indian rosewood', 'Neck: Select hardwood with high-performance taper', 'Fingerboard: Ebony with modern high-performance taper', 'Electronics: LR Baggs VTC with soundhole control', 'Case: Deluxe hardshell included'], 'alignment' => 'fullscreen'], 'order' => 4],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The forward-shifted bracing is the game-changer. It opens up the top end while maintaining bass response, creating more balanced projection across all frequencies. Single notes ring with bell-like clarity while chords bloom with depth.', 'The neck profile is thinner than vintage D-28s, making it more comfortable for electric players transitioning to acoustic. The ebony fingerboard feels smooth and fast. Action is perfectly set for easy playability without sacrificing tone.', 'LR Baggs VTC electronics are transparent and feedback-resistant. The discrete soundhole control doesn\'t require cutting into the guitar. Plugged in, this guitar sounds like a mic\'d D-28 - natural and powerful.']], 'order' => 5],
                    ['type' => 'list', 'data' => ['listType' => 'ul', 'schemaType' => 'none', 'items' => ['<strong>Sound:</strong> Classic D-28 power with improved balance and clarity', '<strong>Playability:</strong> Modern neck profile perfect for all playing styles', '<strong>Electronics:</strong> Professional-grade LR Baggs system', '<strong>Build:</strong> Impeccable craftsmanship with premium materials', '<strong>Value:</strong> Heirloom quality that will appreciate over time']], 'order' => 6]
                ]
            ],
            [
                'title' => 'Fender Player Plus Stratocaster Review',
                'slug' => 'fender-player-plus-strat-review',
                'tags' => ['gear-review', 'fender'],
                'categories' => ['Reviews', 'Guitars', 'Electric Guitars'],
                'custom_fields' => ['author_name' => 'Jonathan Horsley', 'read_time' => 9, 'rating' => 4.5, 'excerpt' => 'Player series gets upgrades.'],
                'image' => ['src' => 'https://images.unsplash.com/photo-1564186763535-ebb21ef5277f?auto=format&fit=crop&w=800&q=80', 'alt' => 'Fender Strat'],
                'badge' => ['text' => 'Review', 'color' => 'success'],
                'content' => [
                    ['type' => 'image', 'data' => ['src' => 'https://images.unsplash.com/photo-1564186763535-ebb21ef5277f?auto=format&fit=crop&w=800&q=80', 'alt' => 'Fender Player Plus Stratocaster', 'caption' => 'Player Plus Strat in Opal Spark finish', 'layout' => 'full', 'alignment' => 'fullscreen'], 'order' => 1],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The Fender Player Plus Stratocaster elevates the Player series with upgraded features that significantly enhance performance. Noiseless pickups, push-pull pot controls, and locking tuners bring this Mexican-made Strat closer to American Professional territory.', 'Fender has strategically positioned the Player Plus series to offer premium features at mid-level pricing. This Strat delivers 90% of American Pro performance at 60% of the cost.']], 'order' => 2],
                    ['type' => 'heading', 'data' => ['text' => 'Upgraded Player Series', 'subtitle' => 'Premium features at an accessible price', 'level' => 3], 'order' => 3],
                    ['type' => 'note', 'data' => ['title' => 'Key Upgrades', 'paragraphs' => ['Noiseless Pickups: Hum-free single-coil tone with vintage character', 'Push-Pull Tone Pot: Adds neck+bridge pickup combination', 'Locking Tuners: Stay in tune through aggressive vibrato', 'Modern "C" Neck: Comfortable profile with rolled edges', '2-Point Tremolo: Smooth operation and stable tuning'], 'alignment' => 'fullscreen'], 'order' => 4],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The Noiseless pickups are impressive. They capture authentic Strat spank and sparkle without hum or noise. The push-pull tone control adds the neck+bridge combination, expanding tonal options significantly.', 'Build quality punches above its weight class. The glossy finish is flawless, frets are well-dressed with no sharp edges, and the setup from the factory is excellent. The Modern "C" neck feels comfortable for both rhythm and lead playing.', 'The 2-point tremolo operates smoothly and returns to pitch accurately. Combined with locking tuners, this Strat stays in tune better than vintage-spec models that cost more.']], 'order' => 5],
                    ['type' => 'list', 'data' => ['listType' => 'ul', 'schemaType' => 'none', 'items' => ['<strong>Pros:</strong> Noiseless pickups, excellent value, locking tuners, quality build', '<strong>Cons:</strong> Not quite American Pro quality, limited color options', '<strong>Best For:</strong> Players wanting Strat versatility without hum issues']], 'order' => 6]
                ]
            ],
            [
                'title' => 'Electro-Harmonix Big Muff Pi Review',
                'slug' => 'ehx-big-muff-pi-review',
                'tags' => ['gear-review', 'pedals'],
                'categories' => ['Reviews', 'Gear', 'Effects Pedals'],
                'custom_fields' => ['author_name' => 'Jon Wiederhorn', 'read_time' => 7, 'rating' => 4.5, 'excerpt' => 'Classic fuzz pedal still rocks.'],
                'image' => ['src' => 'https://images.unsplash.com/photo-1614963366795-aad6e05143e3?auto=format&fit=crop&w=800&q=80', 'alt' => 'Big Muff'],
                'badge' => ['text' => 'Review', 'color' => 'success'],
                'content' => [
                    ['type' => 'image', 'data' => ['src' => 'https://images.unsplash.com/photo-1614963366795-aad6e05143e3?auto=format&fit=crop&w=800&q=80', 'alt' => 'Electro-Harmonix Big Muff Pi', 'caption' => 'The legendary Big Muff Pi fuzz pedal', 'layout' => 'full', 'alignment' => 'fullscreen'], 'order' => 1],
                    ['type' => 'heading', 'data' => ['text' => 'Fuzzy Legend', 'subtitle' => 'The Big Muff Pi remains essential after 50 years', 'level' => 2], 'order' => 2],
                    ['type' => 'text', 'data' => ['paragraphs' => ['Since 1969, the Electro-Harmonix Big Muff Pi has defined the sound of fuzz guitar. From David Gilmour\'s soaring leads to Billy Corgan\'s wall of distortion, the Big Muff has shaped rock music for five decades.', 'This current production version maintains the circuit that made the pedal legendary while offering modern reliability. It\'s proof that great tone doesn\'t require digital processing or complex features.']], 'order' => 3],
                    ['type' => 'info', 'data' => ['infoType' => 'note', 'description' => 'The Big Muff Pi uses four transistor gain stages and has been used on countless classic albums by Pink Floyd, Smashing Pumpkins, The White Stripes, and hundreds more.'], 'order' => 4],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The Big Muff tone is unmistakable - thick, creamy sustain with pronounced midrange scoop. It\'s not a transparent overdrive; it\'s a saturated fuzz that transforms your guitar\'s voice entirely.', 'The three-knob interface is elegantly simple. Volume controls output level, Tone sweeps from dark to bright, and Sustain adjusts gain from mild fuzz to infinite sustain. The tone control is particularly musical, making it easy to dial in usable sounds.', 'This pedal loves humbuckers and high output pickups. The thick fuzz smooths out any harshness while maintaining note definition. With single-coils, it produces that classic Gilmour tone - singing sustain perfect for expressive leads.']], 'order' => 5],
                    ['type' => 'quote', 'data' => ['text' => 'The Big Muff Pi is one of those rare pedals that sounds exactly like your favorite records. It\'s been cloned a thousand times, but nothing quite captures the magic of the original circuit.', 'attribution' => 'Jon Wiederhorn, Guitar World'], 'order' => 6]
                ]
            ],
            [
                'title' => 'Taylor 814ce Builder\'s Edition Review',
                'slug' => 'taylor-814ce-builders-edition-review',
                'tags' => ['gear-review', 'acoustic-guitar'],
                'categories' => ['Reviews', 'Guitars', 'Acoustic Guitars'],
                'custom_fields' => ['author_name' => 'Chris Gill', 'read_time' => 11, 'rating' => 5, 'excerpt' => 'Taylor\'s finest acoustic.'],
                'image' => ['src' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?auto=format&fit=crop&w=800&q=80', 'alt' => 'Taylor Acoustic'],
                'badge' => ['text' => 'Review', 'color' => 'success'],
                'content' => [
                    ['type' => 'image', 'data' => ['src' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?auto=format&fit=crop&w=800&q=80', 'alt' => 'Taylor 814ce Builder\'s Edition', 'caption' => 'Taylor\'s pinnacle acoustic guitar', 'layout' => 'full', 'alignment' => 'fullscreen'], 'order' => 1],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The Taylor 814ce Builder\'s Edition represents master luthier Andy Powers\' vision of the ultimate Grand Auditorium acoustic. Every detail has been reconsidered and refined to create an instrument that sets new standards for modern acoustic guitar design.', 'With V-Class bracing, beveled armrest, Silent Satin finish, and premium Indian rosewood, this is Taylor\'s statement piece - an acoustic guitar that sounds as good as it looks and plays.']], 'order' => 2],
                    ['type' => 'heading', 'data' => ['text' => 'Revolutionary Bracing', 'subtitle' => 'V-Class changes everything', 'level' => 3], 'order' => 3],
                    ['type' => 'note', 'data' => ['title' => 'Builder\'s Edition Features', 'paragraphs' => ['V-Class Bracing: Revolutionary design improves sustain, volume, and intonation', 'Premium Tonewoods: Lutz spruce top, Indian rosewood back/sides', 'Ergonomic Design: Beveled armrest, chamfered body edge for comfort', 'Silent Satin Finish: Ultra-thin matte finish lets wood resonate freely', 'Expression System 2: Taylor\'s acclaimed piezo pickup system'], 'alignment' => 'fullscreen'], 'order' => 4],
                    ['type' => 'text', 'data' => ['paragraphs' => ['V-Class bracing is a revelation. Notes sustain longer with more bloom and clarity than traditional X-braced guitars. Intonation up the neck is noticeably better - chords sound perfectly in tune in any position.', 'The tone is balanced and refined. The Lutz spruce top provides power and headroom while the Indian rosewood adds warmth and complexity. It\'s equally at home fingerpicking or strumming.', 'The Expression System 2 electronics are the best factory-installed system we\'ve tested. Plugged in, this guitar sounds remarkably natural with no piezo quack. The three-band EQ allows precise tone shaping for any venue.', 'Comfort features like the beveled armrest and chamfered body edge make extended playing sessions effortless. The Silent Satin finish feels broken-in immediately while looking elegant.']], 'order' => 5],
                    ['type' => 'list', 'data' => ['listType' => 'ul', 'schemaType' => 'none', 'items' => ['<strong>Innovation:</strong> V-Class bracing delivers measurable improvements', '<strong>Sound:</strong> Balanced, powerful, with exceptional sustain', '<strong>Playability:</strong> Comfortable for hours of playing', '<strong>Electronics:</strong> Best factory system available', '<strong>Investment:</strong> Heirloom quality with resale value']], 'order' => 6]
                ]
            ],
            [
                'title' => 'Orange Rockerverb 50 MKIII Review',
                'slug' => 'orange-rockerverb-50-mkiii-review',
                'tags' => ['gear-review', 'amps'],
                'categories' => ['Reviews', 'Gear', 'Amplifiers'],
                'custom_fields' => ['author_name' => 'Phil Weller', 'read_time' => 10, 'rating' => 4.5, 'excerpt' => 'British tone at its finest.'],
                'image' => ['src' => 'https://images.unsplash.com/photo-1563330232-57114bb0823c?auto=format&fit=crop&w=800&q=80', 'alt' => 'Orange Amp'],
                'badge' => ['text' => 'Review', 'color' => 'success'],
                'content' => [
                    ['type' => 'image', 'data' => ['src' => 'https://images.unsplash.com/photo-1563330232-57114bb0823c?auto=format&fit=crop&w=800&q=80', 'alt' => 'Orange Rockerverb 50 MKIII', 'caption' => 'Orange Rockerverb 50 MKIII head', 'layout' => 'full', 'alignment' => 'fullscreen'], 'order' => 1],
                    ['type' => 'heading', 'data' => ['text' => 'British Tone Refined', 'subtitle' => 'Orange\'s flagship amp reaches new heights', 'level' => 2], 'order' => 2],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The Orange Rockerverb 50 MKIII is the latest evolution of Orange\'s most popular amplifier series. With 50 watts from four EL34 power tubes, two footswitchable channels, and Orange\'s distinctive British voicing, it\'s equally at home in the studio or on stage.', 'New for MKIII are improved switching circuitry, enhanced reverb, and a half-power mode that maintains tone at reduced volumes. Orange has refined an already excellent design without losing the character that made it legendary.']], 'order' => 3],
                    ['type' => 'note', 'data' => ['title' => 'Specifications', 'paragraphs' => ['Power: 50 watts (switchable to 25 watts)', 'Tubes: Four EL34 power tubes, four 12AX7 preamp tubes', 'Channels: Two footswitchable channels (Natural/Dirty)', 'Controls: Independent gain, master, 3-band EQ per channel', 'Effects Loop: Buffered series loop', 'Reverb: Spring reverb on both channels'], 'alignment' => 'fullscreen'], 'order' => 4],
                    ['type' => 'text', 'data' => ['paragraphs' => ['The Natural channel delivers everything from sparkling cleans to edge-of-breakup tones. It has tremendous headroom and clarity. Pushing the gain reveals classic British crunch that responds beautifully to picking dynamics.', 'The Dirty channel is where the Rockerverb truly shines. It delivers thick, saturated distortion with pronounced midrange presence. This is the Orange tone - fat, punchy, and incredibly musical. Even at extreme gain, notes remain defined.', 'The half-power mode is genuinely useful. At 25 watts, you can achieve power tube saturation at manageable volumes. The tone remains fat and full - this isn\'t just a volume reduction.', 'Build quality is exceptional. The head is built like a tank with point-to-point wiring throughout. The iconic orange tolex and picture frame edging make this one of the most recognizable amps on any stage.']], 'order' => 5],
                    ['type' => 'quote', 'data' => ['text' => 'The Rockerverb 50 MKIII is pure British tone perfection. It sounds huge, looks iconic, and delivers pro-level performance night after night.', 'attribution' => 'Phil Weller, Guitar World'], 'order' => 6]
                ]
            ],
            [
                'title' => 'D\'Addario NYXL Strings Review',
                'slug' => 'daddario-nyxl-strings-review',
                'tags' => ['gear-review', 'strings'],
                'categories' => ['Reviews', 'Gear', 'Accessories'],
                'custom_fields' => ['author_name' => 'Chris Gill', 'read_time' => 6, 'rating' => 4.5, 'excerpt' => 'Premium strings worth the price.'],
                'image' => ['src' => 'https://images.unsplash.com/photo-1525201548942-d8732f6617a0?auto=format&fit=crop&w=800&q=80', 'alt' => 'Guitar Strings'],
                'badge' => ['text' => 'Review', 'color' => 'success'],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => ['src' => 'https://images.unsplash.com/photo-1525201548942-d8732f6617a0?auto=format&fit=crop&w=800&q=80', 'alt' => 'D\'Addario NYXL Strings', 'caption' => 'D\'Addario NYXL: Premium performance strings', 'layout' => 'full', 'alignment' => 'fullscreen'], 'order' => 1],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => ['D\'Addario\'s NYXL strings promise 131% greater tuning stability and 40% more break resistance than standard strings. These bold claims are backed by advanced metallurgy and precision manufacturing that set new standards for string performance.', 'We tested NYXL sets across multiple guitars over six weeks, subjecting them to aggressive playing, frequent retuning, and temperature extremes to verify D\'Addario\'s claims.']], 'order' => 2],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Advanced Metallurgy', 'subtitle' => 'Science meets guitar strings', 'level' => 3], 'order' => 3],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'NYXL Technology',
                            'paragraphs' => [
                                'High Carbon Steel Core: Improved break resistance and tuning stability',
                                'Fusion Twist Process: Plain strings wind around ball end for strength',
                                'NY Steel Wrap Wire: Enhanced magnetic properties for better tone',
                                'Reformulated Nickel Plating: Corrosion resistance without tone sacrifice'
                            ], 'alignment' => 'fullscreen'
                        ],
                        'order' => 4
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The tuning stability claims are accurate. Fresh NYXL sets stay in tune noticeably better than standard strings, requiring fewer adjustments during the critical break-in period.',
                                'Tonally, NYXLs are brighter and more articulate than standard D\'Addarios. The enhanced magnetic properties produce stronger output and better harmonic content. These strings sound alive under your fingers.',
                                'Longevity is excellent. After six weeks of heavy use, our test sets showed minimal corrosion and maintained brightness better than any strings we\'ve tested at this price point.',
                                'The feel is slightly stiffer initially, but strings break in quickly. The increased tension provides better note definition and sustain without feeling difficult to play.'
                            ]
                        ],
                        'order' => 5
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'schemaType' => 'none',
                            'items' => [
                                '<strong>Pros:</strong> Superior tuning stability, extended lifespan, enhanced tone',
                                '<strong>Cons:</strong> Premium pricing, slightly stiffer than standard strings',
                                '<strong>Best For:</strong> Working musicians, frequent retuners, players seeking maximum performance'
                            ]
                        ],
                        'order' => 6
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
            'meta_title' => $data['title'] . ' - Guitar World',
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
            $fieldDef = CustomFieldDefinition::where('key', $key)->where('site_id', $this->site->id)->first();
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
            'title' => 'About Guitar World',
            'page_type' => 'content',
            'slug' => 'about',
            'status' => 'published',
            'meta_title' => 'About Guitar World - The Ultimate Guitar Magazine',
            'meta_description' => 'Learn about Guitar World, the world\'s leading guitar publication.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'About',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
            'sort_order' => 90
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'About Guitar World',
                    'subtitle' => 'The world\'s leading guitar publication since 1980',
                    'ctaText' => 'Our Team',
                    'ctaUrl' => '#team',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Guitar World has been the premier destination for guitarists since 1980. For over four decades, we\'ve provided comprehensive coverage of guitars, gear, techniques, and the artists who inspire us.',
                        'Our mission is simple: to help guitarists of all levels play better, sound better, and enjoy their instruments more. From beginners picking up their first guitar to touring professionals seeking the latest gear insights, Guitar World serves the entire guitar community.',
                        'We combine expert reviews, in-depth interviews, comprehensive lessons, and breaking news to create the most complete guitar resource available anywhere.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'By The Numbers',
                    'stats' => [
                        ['number' => '45+', 'label' => 'Years Publishing', 'icon' => '📅'],
                        ['number' => '10M+', 'label' => 'Monthly Readers', 'icon' => '👥'],
                        ['number' => '5,000+', 'label' => 'Gear Reviews', 'icon' => '🎸'],
                        ['number' => '1,000+', 'label' => 'Artist Interviews', 'icon' => '🎤']
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Editorial Team',
                    'subtitle' => 'Meet the guitarists behind Guitar World',
                    'level' => 2
                ],
                'order' => 4
            ],
            [
                'type' => 'team',
                'data' => [
                    'title' => '',
                    'layout' => 'grid',
                    'members' => [
                        [
                            'name' => 'Michael Astley-Brown',
                            'role' => 'Editor-in-Chief',
                            'bio' => 'Former touring musician with 20+ years covering the guitar industry. Michael has interviewed everyone from Eric Clapton to St. Vincent.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'michael@guitarworld.com'
                        ],
                        [
                            'name' => 'Jonathan Horsley',
                            'role' => 'Senior Gear Editor',
                            'bio' => 'Gear obsessive specializing in vintage guitars and boutique effects. Jonathan has reviewed over 500 guitars and 1,000+ pedals.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'jonathan@guitarworld.com'
                        ],
                        [
                            'name' => 'Richard Barrett',
                            'role' => 'Lessons Editor',
                            'bio' => 'Professional guitar instructor and touring session player with credits on over 100 albums.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'richard@guitarworld.com'
                        ],
                        [
                            'name' => 'Chris Gill',
                            'role' => 'Contributing Editor',
                            'bio' => 'Fender specialist and author of multiple guitar instruction books.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=400&q=80'],
                            'email' => 'chris@guitarworld.com'
                        ]
                    ]
                ],
                'order' => 5
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'Guitar World isn\'t just a magazine - it\'s a community of players united by our passion for the guitar.',
                    'attribution' => 'Michael Astley-Brown, Editor-in-Chief'
                ],
                'order' => 6
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createContactPage(): void
    {
        $page = Page::create([
            'title' => 'Contact Guitar World',
            'page_type' => 'content',
            'slug' => 'contact',
            'status' => 'published',
            'meta_title' => 'Contact Guitar World',
            'meta_description' => 'Get in touch with the Guitar World editorial team.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Contact',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
            'sort_order' => 100
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Get In Touch',
                    'subtitle' => 'Questions, feedback, or story ideas? We\'d love to hear from you',
                    'showSearch' => false
                ],
                'order' => 1
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Guitar World Editorial',
                    'role' => 'Contact Information',
                    'email' => 'editorial@guitarworld.com',
                    'phone' => '+1 (212) 378-0400',
                    'address' => 'Future Publishing Inc.
11 West 42nd Street, 15th Floor
New York, NY 10036

Editorial Office Hours:
Mon-Fri: 9AM-5PM EST',
                    'displayType' => 'contact'
                ],
                'order' => 2
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'For editorial inquiries and story pitches, please contact editorial@guitarworld.com',
                        'For gear review submissions, email reviews@guitarworld.com',
                        'For advertising opportunities, contact advertising@futureplc.com',
                        'For subscription support, visit our help center or email subscriptions@guitarworld.com'
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Send Us a Message',
                    'subtitle' => 'Our team typically responds within 2 business days',
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
}