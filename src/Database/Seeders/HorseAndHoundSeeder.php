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

class HorseAndHoundSeeder extends Seeder
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
            'name' => 'Horse & Hound - The World\'s Best Equestrian Magazine',
            'slug' => 'horse-and-hound',
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
            'featured', 'breaking-news', 'exclusive', 'competition',
            'eventing', 'dressage', 'showjumping', 'racing',
            'horse-care', 'health', 'nutrition', 'training',
            'equipment', 'tack', 'product-review', 'buying-guide',
            'veterinary', 'farrier', 'behaviour', 'welfare',
            'news', 'results', 'profiles', 'opinion',
            'beginners', 'advanced', 'professionals',
            'stable-management', 'breeding', 'young-horses',
            'countryside', 'hunting', 'polo', 'endurance'
        ];

        foreach ($tags as $tagName) {
            $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
        }
    }

    private function createCategories(): void
    {
        $categories = [
            'News & Features' => [
                'Breaking News' => [],
                'Competition Reports' => ['Eventing', 'Dressage', 'Showjumping', 'Racing'],
                'Interviews' => [],
                'Opinion' => []
            ],
            'Disciplines' => [
                'Eventing' => ['Cross Country', 'Stadium Jumping', 'Dressage Phase'],
                'Dressage' => ['Training', 'Competitions', 'Tests'],
                'Showjumping' => ['Technique', 'Courses', 'Results'],
                'Racing' => ['National Hunt', 'Flat Racing', 'Point to Point'],
                'Other Sports' => ['Polo', 'Endurance', 'Hunting', 'Showing']
            ],
            'Horse Care' => [
                'Health & Fitness' => ['Veterinary', 'Nutrition', 'Exercise'],
                'Behaviour & Training' => ['Problem Solving', 'Groundwork', 'Schooling'],
                'Stable Management' => ['Facilities', 'Routine', 'Equipment'],
                'Hoof Care' => []
            ],
            'Buying Guides' => [
                'Tack & Equipment' => ['Saddles', 'Bridles', 'Rugs', 'Boots'],
                'Horse Transport' => ['Trailers', 'Lorries'],
                'Technology' => ['Apps', 'Cameras', 'Trackers'],
                'Clothing' => ['Riding Wear', 'Safety Equipment']
            ],
            'Lifestyle' => ['Country Living', 'Travel', 'Property', 'Events Calendar']
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
            ['key' => 'discipline', 'name' => 'Discipline', 'type' => 'select', 'options' => '{"eventing":"Eventing","dressage":"Dressage","showjumping":"Showjumping","racing":"Racing","other":"Other"}'],
            ['key' => 'event_name', 'name' => 'Event Name', 'type' => 'text'],
            ['key' => 'event_date', 'name' => 'Event Date', 'type' => 'text'],
            ['key' => 'location', 'name' => 'Location', 'type' => 'text'],
            ['key' => 'horse_name', 'name' => 'Featured Horse', 'type' => 'text'],
            ['key' => 'rider_name', 'name' => 'Featured Rider', 'type' => 'text'],
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
            'title' => 'Horse & Hound - The World\'s Best Equestrian Magazine',
            'page_type' => 'content',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'Horse & Hound | Equestrian News, Advice & Results',
            'meta_description' => 'The latest equestrian news, competition results, expert advice on horse care, training tips, product reviews and buying guides from Horse & Hound.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Home',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
            'sort_order' => 1
        ]);

        $featuredTag = $this->tagRepository->findOrCreateByName('featured', $this->site->id);
        $page->tags(true)->attach($featuredTag->id);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Welcome to Horse & Hound',
                    'subtitle' => 'The world\'s leading equestrian magazine - bringing you the latest news, expert advice and competition results since 1884',
                    'ctaText' => 'Latest News',
                    'ctaUrl' => '#news',
                    'secondaryCtaText' => 'Subscribe',
                    'secondaryCtaUrl' => '/subscribe',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1553284965-83fd3e82fa5a?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'info',
                'data' => [
                    'infoType' => 'info',
                    'description' => '🏆 BREAKING: British riders dominate at Badminton Horse Trials - Full results and analysis inside'
                ],
                'order' => 2
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Featured Stories',
                    'subtitle' => 'The latest from the equestrian world',
                    'level' => 2
                ],
                'order' => 3
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'layout' => 'grid',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'showMeta' => true,
                    'pages' => [
                        [
                            'title' => 'Badminton 2025: Complete Guide to the World\'s Premier Three-Day Event',
                            'slug' => 'badminton-2025-guide',
                            'excerpt' => 'Everything you need to know about Badminton Horse Trials - course preview, top contenders, and how to watch.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1534278931827-8a259344abe7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Cross country eventing'
                            ],
                            'badge' => [
                                'text' => 'Featured',
                                'color' => 'primary'
                            ],
                            'meta' => [
                                'author' => 'Jennifer Donald',
                                'date' => 'May 2025',
                                'readTime' => '12 min read',
                                'category' => 'Eventing'
                            ]
                        ],
                        [
                            'title' => 'Laminitis Prevention: Essential Spring Care Guide',
                            'slug' => 'laminitis-prevention-spring',
                            'excerpt' => 'Protect your horse from laminitis this spring with expert veterinary advice on grazing management and nutrition.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1553284965-83fd3e82fa5a?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Horse grazing in field'
                            ],
                            'badge' => [
                                'text' => 'Health',
                                'color' => 'success'
                            ],
                            'meta' => [
                                'author' => 'Dr. Sarah Mitchell BVetMed',
                                'date' => 'April 2025',
                                'readTime' => '8 min read',
                                'category' => 'Horse Care'
                            ]
                        ],
                        [
                            'title' => 'Best Saddles 2025: Expert Reviews & Buying Guide',
                            'slug' => 'best-saddles-2025',
                            'excerpt' => 'Our comprehensive guide to choosing the perfect saddle - from GP to dressage, jump to endurance.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1596464716127-f2a82984de30?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Horse saddle'
                            ],
                            'badge' => [
                                'text' => 'Buying Guide',
                                'color' => 'warning'
                            ],
                            'meta' => [
                                'author' => 'Emma Thompson',
                                'date' => 'April 2025',
                                'readTime' => '15 min read',
                                'category' => 'Equipment'
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
                'type' => 'stats',
                'data' => [
                    'title' => 'Horse & Hound by Numbers',
                    'stats' => [
                        ['number' => '140+', 'label' => 'Years Publishing', 'icon' => '📰'],
                        ['number' => '500K+', 'label' => 'Monthly Readers', 'icon' => '👥'],
                        ['number' => '50+', 'label' => 'Expert Contributors', 'icon' => '✍️'],
                        ['number' => '#1', 'label' => 'UK Equestrian Magazine', 'icon' => '🏆']
                    ]
                ],
                'order' => 6
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Latest Competition Results',
                    'subtitle' => 'Live updates from the showground',
                    'level' => 2
                ],
                'order' => 7
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Badminton Horse Trials: Day 3 showjumping phase - Live updates',
                        'Royal Windsor Horse Show: Results from all rings',
                        'Blenheim Palace International: Advanced cross country results',
                        'British Dressage Summer Regionals: Prix St Georges winners',
                        'Hickstead Derby Meeting: Speed Derby results and reactions',
                        'Bramham International Horse Trials: Final CCI4*-L standings'
                    ]
                ],
                'order' => 8
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'Horse & Hound has been the trusted voice of British equestrianism for over 140 years, bringing riders the news, advice and inspiration they need.',
                    'attribution' => 'Sarah Jenkins, Editor-in-Chief'
                ],
                'order' => 9
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Subscribe to Our Newsletter',
                    'subtitle' => 'Get the latest equestrian news, competition updates and expert advice delivered to your inbox',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => false,
                    'showSubject' => false,
                    'showMessage' => false,
                    'submitButtonText' => 'Subscribe Free',
                    'requireName' => true,
                    'requireEmail' => true
                ],
                'order' => 10
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
            // Article 1: Badminton Event Coverage
            [
                'title' => 'Badminton 2025: Complete Guide to the World\'s Premier Three-Day Event',
                'slug' => 'badminton-2025-guide',
                'tags' => ['featured', 'eventing', 'competition', 'breaking-news'],
                'categories' => ['News & Features', 'Competition Reports', 'Eventing'],
                'custom_fields' => [
                    'author_name' => 'Jennifer Donald',
                    'author_bio' => 'Eventing correspondent for Horse & Hound with 25 years covering major championships.',
                    'read_time' => 12,
                    'discipline' => 'eventing',
                    'event_name' => 'Badminton Horse Trials',
                    'event_date' => 'May 7-11, 2025',
                    'location' => 'Badminton, Gloucestershire',
                    'excerpt' => 'Everything you need to know about Badminton Horse Trials - course preview, top contenders, and how to watch the world\'s premier three-day event.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1534278931827-8a259344abe7?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Badminton cross country',
                            'caption' => 'The famous Badminton Lake jump - one of the most iconic obstacles in eventing',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Badminton Horse Trials returns for its 75th running this May, promising five days of world-class equestrian sport. Set in the stunning grounds of the Duke of Beaufort\'s estate, this prestigious CCI5*-L attracts the world\'s best event riders and their horses.',
                                'This year\'s event is shaping up to be one of the most competitive in recent memory, with Olympic medallists, world champions, and rising stars all vying for the coveted Badminton title. The prize fund of £350,000 makes it one of the richest events in the sport.',
                                'Whether you\'re planning to attend in person or watch from home, this complete guide has everything you need to know about Badminton 2025.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Event Schedule',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Day', 'Date', 'Phase', 'Start Time'],
                                ['Wednesday', 'May 7', 'First Horse Inspection', '2:00 PM'],
                                ['Thursday', 'May 8', 'Dressage - First Day', '9:00 AM'],
                                ['Friday', 'May 9', 'Dressage - Second Day', '9:00 AM'],
                                ['Saturday', 'May 10', 'Cross Country', '11:30 AM'],
                                ['Sunday', 'May 11', 'Showjumping & Prize Giving', '1:00 PM']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Top Contenders to Watch',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The entry list reads like a who\'s who of eventing royalty. Reigning champion Laura Collett returns with London 52, the partnership that delivered such a dominant performance last year. Their combination of exquisite dressage and bold cross-country jumping makes them the pair to beat.',
                                'Olympic gold medallist Ros Canter brings Lordships Graffalo, a horse she knows inside out. Their experience together at championship level gives them a significant advantage, particularly on the challenging cross-country course.',
                                'German rider Michael Jung, arguably the greatest event rider of all time, makes his Badminton debut on fischerChipmunk FRH. His record speaks for itself - multiple Olympic, World and European titles mean he cannot be discounted.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Laura Collett',
                                    'description' => 'Defending champion returns with London 52',
                                    'image' => 'https://images.unsplash.com/photo-1551884831-bbf3cdc6469e?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Eventing rider'
                                ],
                                [
                                    'title' => 'Ros Canter',
                                    'description' => 'Olympic champion on Lordships Graffalo',
                                    'image' => 'https://images.unsplash.com/photo-1553284965-83fd3e82fa5a?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Horse and rider'
                                ],
                                [
                                    'title' => 'Michael Jung',
                                    'description' => 'Legend makes Badminton debut',
                                    'image' => 'https://images.unsplash.com/photo-1534278931827-8a259344abe7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Cross country riding'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Cross Country Course',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Course designer Eric Winter has created a track that will test every aspect of horse and rider. At 6,270 meters with 45 numbered obstacles, it\'s a true championship test that rewards brave, accurate riding.',
                                'The famous Badminton Lake complex remains the course\'s centrepiece. This year, Winter has redesigned the approach and options, making it even more technically demanding. Riders will need to choose their line carefully to avoid time penalties.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Fence 4: The Quarry - Early test of boldness and accuracy',
                                'Fence 12: Huntsman\'s Close - Technical corner combination',
                                'Fence 15-17: Vicarage Vee and Ditch - Classic Badminton complex',
                                'Fence 19-20: The Lake - The course\'s most famous feature',
                                'Fence 24: Keepers Question - Influences final placings',
                                'Fence 34: Mitsubishi Motors Ski Jump - Spectacular viewing'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => '🎫 Best spectating spots: The Lake (fence 19-20), The Quarry (fence 4), and Huntsman\'s Close (fence 12) offer thrilling viewing. Arrive early to secure your spot!'
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Badminton by Numbers',
                            'stats' => [
                                ['number' => '75', 'label' => 'Years of Competition', 'icon' => '🏆'],
                                ['number' => '6,270m', 'label' => 'Cross Country Distance', 'icon' => '📏'],
                                ['number' => '45', 'label' => 'Jumping Efforts', 'icon' => '🦘'],
                                ['number' => '£350K', 'label' => 'Prize Fund', 'icon' => '💰']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'How to Watch',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Live coverage will be available throughout the event. BBC Sport will broadcast extensive coverage of the cross country on Saturday and showjumping on Sunday. For international viewers, Badminton TV offers a complete streaming package.',
                                'Horse & Hound will provide live updates, analysis and exclusive interviews across all platforms. Follow @HorseandHound on social media for behind-the-scenes content and breaking news.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Badminton is the ultimate test - you need a horse with courage, stamina and the agility to handle whatever the course throws at you. Every rider dreams of winning here.',
                            'attribution' => 'Mary King MBE, Six-time Badminton Winner'
                        ]
                    ]
                ]
            ],

            // Article 2: Laminitis Prevention
            [
                'title' => 'Laminitis Prevention: Essential Spring Care Guide',
                'slug' => 'laminitis-prevention-spring',
                'tags' => ['featured', 'health', 'horse-care', 'veterinary', 'nutrition'],
                'categories' => ['Horse Care', 'Health & Fitness', 'Veterinary'],
                'custom_fields' => [
                    'author_name' => 'Dr. Sarah Mitchell BVetMed MRCVS',
                    'author_bio' => 'Equine veterinary surgeon specializing in metabolic disorders and laminitis prevention.',
                    'read_time' => 8,
                    'excerpt' => 'Protect your horse from laminitis this spring with expert veterinary advice on grazing management, nutrition and recognizing early warning signs.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1553284965-83fd3e82fa5a?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Horse grazing in lush spring grass',
                            'caption' => 'Spring grass can be dangerous for laminitis-prone horses',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Laminitis remains one of the most serious and painful conditions affecting horses and ponies. Spring is the highest-risk period, when lush, fast-growing grass contains dangerously high levels of water-soluble carbohydrates (WSCs) and fructans.',
                                'Understanding the risk factors and implementing preventive measures can dramatically reduce your horse\'s chances of developing this devastating condition. Early intervention is key - once laminitis takes hold, the damage can be permanent.',
                                'This guide provides evidence-based veterinary advice to help you protect your horse through the high-risk spring period.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Understanding Laminitis Risk Factors',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Previous laminitis episodes - horses who\'ve had it before are at much higher risk',
                                'Obesity and overweight condition - excess body fat increases risk significantly',
                                'Equine Metabolic Syndrome (EMS) - insulin dysregulation makes horses particularly vulnerable',
                                'Pituitary Pars Intermedia Dysfunction (PPID/Cushing\'s) - hormonal imbalance increases susceptibility',
                                'Native breeds and "good doers" - naturally efficient metabolisms put them at higher risk',
                                'Concurrent illness - any systemic disease can trigger laminitis',
                                'Age - horses over 10 years are statistically more at risk'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => '⚠️ EMERGENCY: If your horse shows any signs of laminitis (heat in hooves, reluctance to move, "pottery" gait, lying down more than usual), call your vet immediately. Prompt treatment can prevent permanent damage.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Grazing Management Strategies',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'For high-risk horses, grazing management is critical. Here are proven strategies:'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'items' => [
                                'Strip grazing - use electric fencing to limit access to small areas at a time',
                                'Grazing muzzles - allow turnout while restricting grass intake by 70-80%',
                                'Track systems - create pathways around paddocks encouraging movement while limiting grass',
                                'Night turnout only - grass sugars are lowest between midnight and 10am',
                                'Bare paddocks or sand schools - for highest-risk individuals during peak danger periods',
                                'Co-grazing with cattle or sheep - they eat grass differently and reduce WSC levels'
                            ]
                        ]
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1526336024174-e58f5cdd8e13?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                            'alt' => 'Horse wearing grazing muzzle',
                            'caption' => 'Grazing muzzles can reduce grass intake by up to 80% while allowing turnout',
                            'layout' => 'inline',
                            'alignment' => 'center'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Diet and Weight Management',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Maintaining a healthy weight is crucial for laminitis prevention. Overweight horses are significantly more likely to develop insulin dysregulation, a major risk factor.',
                                'Base your horse\'s diet primarily on low-sugar forage. Soaking hay for 12 hours can reduce WSC content by up to 30%. Choose mature grass hay over haylage, which often contains higher sugar levels.',
                                'Feed balancers provide essential vitamins and minerals without excess calories. Avoid cereals, molasses, and high-starch feeds for at-risk horses. If additional calories are needed, use high-fibre alternatives like unmolassed sugar beet or alfalfa pellets in moderation.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Feed Type', 'WSC Level', 'Suitability', 'Notes'],
                                ['Soaked hay', 'Low (6-8%)', 'Excellent', 'Soak for 12+ hours'],
                                ['Mature meadow hay', 'Moderate (8-12%)', 'Good', 'Check analysis'],
                                ['Haylage', 'High (12-18%)', 'Caution', 'Often too rich'],
                                ['Grass (spring)', 'Very High (20-30%)', 'Avoid', 'Peak danger period'],
                                ['Feed balancer', 'Low', 'Excellent', 'Provides nutrients'],
                                ['Traditional mix', 'High', 'Avoid', 'Contains cereals/molasses']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Exercise and Movement',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Regular exercise improves insulin sensitivity and helps maintain healthy weight. However, exercise must be appropriate to your horse\'s fitness level - sudden intense exercise in unfit horses can trigger laminitis.',
                                'Build fitness gradually over several weeks. Even gentle walking for 20-30 minutes daily can make a significant difference to metabolic health. Track systems encourage natural movement throughout the day.',
                                'Never exercise a horse showing any signs of laminitis. Rest and veterinary treatment take priority.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Prevention is always better than cure with laminitis. Once a horse has suffered an episode, they remain at increased risk for life. Careful management is essential.',
                            'attribution' => 'Dr. Sarah Mitchell BVetMed MRCVS'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Recognizing Early Warning Signs',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Increased digital pulse - you should be able to feel a stronger pulse above the fetlock',
                                'Heat in the hooves - may be subtle initially',
                                'Reluctance to walk, especially on hard ground or when turning',
                                'Standing with weight shifted back onto hindquarters to relieve front feet',
                                'Pottery, stilted gait - short, careful steps',
                                'Lying down more than usual - avoiding weight-bearing',
                                'Sensitivity when pressure applied to sole with hoof testers'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => '💡 Regular practice: Learn to find and assess your horse\'s digital pulse when they\'re healthy. This makes it much easier to detect changes indicating a problem.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Working With Your Vet and Farrier',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Regular veterinary health checks can identify risk factors before they become problems. Consider annual blood tests to monitor insulin and ACTH levels, particularly for horses over 10 or with risk factors.',
                                'Your farrier plays a crucial role in both prevention and management. Correct hoof balance reduces mechanical stress on the laminae. Discuss your horse\'s risk level with your farrier - they may recommend specific trimming or shoeing strategies.',
                                'Keep detailed records of your horse\'s weight, body condition score, exercise regime, and any episodes of increased digital pulse or heat in feet. These records are invaluable for your veterinary team.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Laminitis Statistics',
                            'stats' => [
                                ['number' => '7-10%', 'label' => 'UK Horses Affected Annually', 'icon' => '🐴'],
                                ['number' => 'Spring', 'label' => 'Highest Risk Period', 'icon' => '🌱'],
                                ['number' => '50%', 'label' => 'Cases in Native Breeds', 'icon' => '📊'],
                                ['number' => '24hrs', 'label' => 'Critical Treatment Window', 'icon' => '⏰']
                            ]
                        ]
                    ],
                    [
                        'type' => 'contact-form',
                        'data' => [
                            'title' => 'Free Laminitis Prevention Checklist',
                            'subtitle' => 'Download our comprehensive spring management checklist',
                            'showName' => true,
                            'showEmail' => true,
                            'showPhone' => false,
                            'showSubject' => false,
                            'showMessage' => false,
                            'submitButtonText' => 'Download Free Checklist',
                            'requireName' => true,
                            'requireEmail' => true
                        ]
                    ]
                ]
            ],

            // Article 3: Saddle Buying Guide
            [
                'title' => 'Best Saddles 2025: Expert Reviews & Buying Guide',
                'slug' => 'best-saddles-2025',
                'tags' => ['featured', 'buying-guide', 'equipment', 'tack', 'product-review'],
                'categories' => ['Buying Guides', 'Tack & Equipment', 'Saddles'],
                'custom_fields' => [
                    'author_name' => 'Emma Thompson',
                    'author_bio' => 'Qualified saddle fitter and equipment specialist with 20 years industry experience.',
                    'read_time' => 15,
                    'excerpt' => 'Our comprehensive guide to choosing the perfect saddle for you and your horse - from GP to dressage, jump to endurance, with expert reviews and fitting advice.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1596464716127-f2a82984de30?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Selection of horse saddles',
                            'caption' => 'Choosing the right saddle is one of the most important investments you\'ll make',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Buying a saddle is one of the most significant investments any rider makes. With prices ranging from £500 to over £5,000, and the comfort and performance of both horse and rider at stake, getting it right is essential.',
                                'This comprehensive guide reviews the best saddles available in 2025 across all disciplines. We\'ve tested dozens of models, consulted qualified saddle fitters, and gathered feedback from hundreds of riders to bring you definitive recommendations.',
                                'Whether you\'re looking for your first saddle or upgrading your equipment, understanding saddle design, fit principles, and what works for different riding styles will help you make an informed decision.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Understanding Saddle Fit Principles',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'A correctly fitted saddle distributes the rider\'s weight evenly across the horse\'s back, avoiding pressure on the spine and allowing free shoulder movement. Poor saddle fit causes discomfort, behavioral issues, and long-term physical damage.',
                                'Key fitting principles apply regardless of saddle type: the gullet must clear the spine, panels should make even contact, and the saddle must not pinch the shoulders or sit behind the last rib.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Gullet clearance - minimum 3 fingers width along entire spine',
                                'Panel contact - even pressure across bearing surface, no bridging',
                                'Shoulder freedom - no pinching at wither or shoulder blade movement',
                                'Length - should not extend behind last rib (T18)',
                                'Level seat - should not tip rider forward or back when on flat ground',
                                'Rider position - should sit naturally in deepest part without bracing'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => '⚠️ IMPORTANT: Always have a new or used saddle professionally fitted by a qualified saddle fitter. Even "perfect" saddles can cause problems if incorrectly fitted. Budget for regular checks as your horse changes shape.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Best General Purpose Saddles',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'General Purpose (GP) saddles remain the most popular choice for recreational riders. Their versatile design accommodates flatwork and jumping, making them ideal for hacking, schooling, and low-level competitions.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Albion K2 GP - £2,800',
                                    'description' => 'Editor\'s Choice: Premium British craftsmanship, exceptional comfort',
                                    'image' => 'https://images.unsplash.com/photo-1596464716127-f2a82984de30?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Premium GP saddle'
                                ],
                                [
                                    'title' => 'Wintec 500 - £650',
                                    'description' => 'Best Budget: Changeable gullet system, easy care',
                                    'image' => 'https://images.unsplash.com/photo-1534278931827-8a259344abe7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Budget GP saddle'
                                ],
                                [
                                    'title' => 'Fairfax Gareth - £3,200',
                                    'description' => 'Best Innovation: Performance panel technology',
                                    'image' => 'https://images.unsplash.com/photo-1553284965-83fd3e82fa5a?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Innovative GP saddle'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Model', 'Price', 'Best For', 'Rating', 'Key Feature'],
                                ['Albion K2 GP', '£2,800', 'All-round excellence', '5/5', 'Supreme comfort'],
                                ['Fairfax Gareth', '£3,200', 'Performance riders', '5/5', 'Technology panels'],
                                ['County Fusion', '£2,400', 'Custom fit', '4.5/5', 'Adjustable tree'],
                                ['Wintec 500', '£650', 'Budget buyers', '4/5', 'Synthetic, easy care'],
                                ['Bates Caprilli', '£2,100', 'Jumping focus', '4.5/5', 'Forward cut'],
                                ['Thorowgood T4', '£800', 'Value for money', '4/5', 'Cair panels']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Best Dressage Saddles',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Dressage saddles feature a deeper seat and longer, straighter flaps to facilitate the classical dressage position. Modern designs use monoflap construction and ergonomic panels to enhance rider feel and horse freedom.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'items' => [
                                'PASSIER Grand Gilbert - £4,200 - Professional choice, unparalleled quality and feel',
                                'Amerigo Vega Dressage - £3,600 - Italian craftsmanship, beautiful close contact',
                                'County Perfection - £2,800 - British engineering excellence, ideal for rounder horses',
                                'Fairfax Dressage D1 - £3,400 - Science-backed design, freedom of movement',
                                'Wintec 250 - £580 - Budget option, changeable gullet, synthetic convenience'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Best Jumping Saddles',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Jumping saddles have forward-cut flaps and more open seats to accommodate a shorter stirrup length and allow freedom of movement over fences. Knee and thigh blocks provide security.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Jump Saddle Performance',
                            'stats' => [
                                ['number' => '92%', 'label' => 'Riders report better position', 'icon' => '🎯'],
                                ['number' => '15°', 'label' => 'Average flap angle', 'icon' => '📐'],
                                ['number' => '2.5kg', 'label' => 'Lighter than GP', 'icon' => '⚖️'],
                                ['number' => '£2,400', 'label' => 'Average price point', 'icon' => '💷']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The right saddle transforms your riding. I see horses immediately go better when fitted with an appropriate, well-fitted saddle. It\'s the most important piece of equipment you\'ll buy.',
                            'attribution' => 'Emma Thompson, Qualified Master Saddle Fitter'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Buying New vs. Used Saddles',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'New saddles offer the latest technology, full warranties, and the assurance of no hidden damage. However, quality used saddles can provide exceptional value, particularly premium brands that retain their quality.',
                                'When buying used, always have the saddle inspected by a qualified saddle fitter. Check the tree integrity, panel condition, leather quality, and stitching. Reputable used saddle specialists offer guarantees and return policies.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => '💡 PRO TIP: Many saddlers offer trial periods. Take advantage of these - ride in the saddle for a week before committing. Your horse\'s behavior and your comfort will tell you if it\'s right.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Maintenance and Care',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Clean leather after every ride with saddle soap to remove sweat and dirt',
                                'Condition monthly with appropriate leather treatment - avoid over-conditioning',
                                'Store on a proper saddle stand to maintain shape',
                                'Have professional checks every 6 months - horses change shape seasonally',
                                'Re-flock panels every 2-3 years or when they become compressed',
                                'Check billets, stitching, and girth straps regularly for wear',
                                'Keep away from extreme temperatures and humidity'
                            ]
                        ]
                    ],
                    [
                        'type' => 'contact-form',
                        'data' => [
                            'title' => 'Book a Virtual Saddle Consultation',
                            'subtitle' => 'Free 15-minute consultation with our qualified saddle fitters',
                            'showName' => true,
                            'showEmail' => true,
                            'showPhone' => true,
                            'showSubject' => true,
                            'showMessage' => true,
                            'submitButtonText' => 'Request Consultation',
                            'requireName' => true,
                            'requireEmail' => true,
                            'requirePhone' => false
                        ]
                    ]
                ]
            ],

            // Article 4: Training Tips for Young Horses
            [
                'title' => '10 Essential Training Tips for Young Horses',
                'slug' => 'young-horse-training-tips',
                'tags' => ['training', 'young-horses', 'beginners', 'behaviour', 'professionals'],
                'categories' => ['Horse Care', 'Behaviour & Training', 'Groundwork'],
                'custom_fields' => [
                    'author_name' => 'Mark Davidson',
                    'author_bio' => 'International event rider and BHS-qualified trainer specializing in young horse production.',
                    'read_time' => 10,
                    'excerpt' => 'Expert advice on starting young horses correctly - building confidence, establishing respect, and creating a solid foundation for a successful partnership.'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => '10 Essential Training Tips for Young Horses',
                            'subtitle' => 'Building a solid foundation for lifelong success',
                            'ctaText' => '',
                            'ctaUrl' => '',
                            'showSearch' => false,
                            'backgroundImage' => 'https://images.unsplash.com/photo-1551884831-bbf3cdc6469e?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Starting a young horse correctly is one of the most rewarding yet challenging aspects of horsemanship. The experiences and education you provide in these early years shape your horse\'s entire career and attitude to work.',
                                'Whether you\'re bringing on a three-year-old or working with an older horse that needs re-starting, these fundamental principles will help you build confidence, establish clear communication, and create a willing, responsive partner.',
                                'Remember: there are no shortcuts. Patient, consistent training produces horses that are a pleasure to ride and handle throughout their lives.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '1. Master Groundwork First',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Before you even think about sitting on a young horse, establish impeccable manners on the ground. A horse that respects your space, leads properly, ties quietly, and accepts handling will be much easier and safer to back and ride.',
                                'Groundwork isn\'t just about obedience - it\'s about building trust and communication. Your horse should understand pressure and release, move away from and towards you on cue, and look to you for guidance in uncertain situations.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Leading in-hand: forwards, backwards, turns, halts with light contact',
                                'Standing still for mounting block work and manipulation',
                                'Lunging: walk, trot, canter on both reins with transitions',
                                'Long-reining: steering, stopping, backing without pulling',
                                'Accepting tack: bridle, saddle, girth without resistance',
                                'Loading and traveling calmly'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '2. Take Your Time',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Rushing a young horse\'s education is the biggest mistake trainers make. Horses learn through repetition and confidence-building. What seems slow progress now pays enormous dividends later.',
                                'Plan for at least 6-12 months of steady work before expecting consistent, reliable responses. Some horses progress faster, but there\'s no prize for finishing first. Aim for a horse that\'s confident and understanding, not just obedient.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The time you spend now is time you save later. A well-started young horse is a joy to bring on through the levels. A rushed horse carries tension and confusion for years.',
                            'attribution' => 'Mark Davidson, International Event Rider'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '3. Keep Sessions Short and Positive',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Young horses have limited concentration spans and build muscle and fitness gradually. Twenty minutes of focused work is worth more than an hour of drilling.',
                                'Always end on a positive note. If your horse nails something new, that\'s the perfect time to finish. Quit while you\'re ahead, reward generously, and send them away happy. They\'ll come back keen to work tomorrow.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Optimal Training Duration',
                            'stats' => [
                                ['number' => '15-20', 'label' => 'Minutes per session (first 3 months)', 'icon' => '⏱️'],
                                ['number' => '30-40', 'label' => 'Minutes (6-12 months)', 'icon' => '⏱️'],
                                ['number' => '5-6', 'label' => 'Days per week (allow rest)', 'icon' => '📅'],
                                ['number' => '100%', 'label' => 'Success rate at session end', 'icon' => '✅']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '4. Consistency is Everything',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Young horses thrive on routine and consistent responses to their behavior. If you allow something one day but correct it the next, you create confusion and anxiety.',
                                'Establish clear rules and stick to them. The same cues should always mean the same thing. Your body language, tone, and aids should be predictable. This consistency builds confidence far more than any specific training technique.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '5. Vary the Routine',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'While horses need consistency in rules and responses, they can become sour or bored with identical daily routines. Ring work, hacking, pole work, lunging, and ground work should all feature in your training program.',
                                'Take your young horse to different environments when they\'re ready. See other horses working, experience different surfaces and arenas, encounter traffic and machinery. These experiences build confidence and create a more versatile, adaptable horse.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'masonry',
                            'slides' => [
                                [
                                    'title' => 'Arena Work',
                                    'description' => 'Build basics in controlled environment',
                                    'image' => 'https://images.unsplash.com/photo-1553284965-83fd3e82fa5a?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Horse arena training'
                                ],
                                [
                                    'title' => 'Hacking Out',
                                    'description' => 'Build confidence in open spaces',
                                    'image' => 'https://images.unsplash.com/photo-1534278931827-8a259344abe7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Horse hacking'
                                ],
                                [
                                    'title' => 'Pole Work',
                                    'description' => 'Develop coordination and suppleness',
                                    'image' => 'https://images.unsplash.com/photo-1551884031-fb7e5dcebcdd?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Horse pole work'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '6. Understand Pressure and Release',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Horses learn through the release of pressure, not the application of it. When your horse responds correctly to a cue - whether a leg aid, rein contact, or voice command - immediately release the pressure. This instant reward teaches them what you want.',
                                'The timing of your release is critical. A split-second delay and your horse may not connect their action with your reward. Practice your timing on an experienced horse before working with youngsters.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => '💡 Think "ask, tell, insist" - start with the lightest possible aid, increase pressure if no response, then immediately reward when they comply. Never nag with constant pressure.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '7. Build Physical Fitness Gradually',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Young horses are physically immature. Their bones, joints, tendons, and muscles are still developing. Overworking them risks injury that may not manifest until years later.',
                                'Focus on building topline muscle, core strength, and cardiovascular fitness over many months. Hills, transitions, and varied terrain help develop strength naturally. Avoid repetitive circles, prolonged collection, or jumping big fences until your horse is physically ready.',
                                'Most horses aren\'t fully mature until 6-7 years old. The work you do at 3-4 should be proportionate to their development stage.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Age', 'Work Duration', 'Appropriate Work', 'Avoid'],
                                ['3 years', '15-20 mins', 'Walk, trot, basic steering', 'Canter, jumping, collection'],
                                ['4 years', '20-30 mins', 'All paces, polework, tiny jumps', 'Big jumps, sustained collection'],
                                ['5 years', '30-45 mins', 'Progressive training, small competitions', 'Maximum effort repeatedly'],
                                ['6+ years', '45-60 mins', 'Full training program', 'Overwork without recovery']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '8. Socialize Your Young Horse',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'A confident, well-adjusted horse has been exposed to many different situations, environments, and stimuli. From day one, introduce your youngster to as many positive experiences as possible.',
                                'Take them to shows (even just to look), ride in company, experience different surfaces and conditions, encounter farm machinery, dogs, bicycles, umbrellas. Each positive experience builds their confidence bank.',
                                'However, never overwhelm them. Read your horse\'s body language. If they\'re showing genuine fear or stress, back off and approach that particular challenge more gradually.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Visit show grounds and busy venues without competing',
                                'Ride with other horses - learn to lead and follow',
                                'Practice loading and short journeys',
                                'Introduce unusual objects: flags, balloons, tarps',
                                'Experience water: puddles, streams, water jumps',
                                'Encounter traffic in controlled situations',
                                'Stand tied at different locations',
                                'Meet farriers, vets, dentists in low-stress situations'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '9. Know When to Seek Professional Help',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'There\'s no shame in admitting when a situation is beyond your experience level. Young horses can present challenges that require professional intervention - it\'s far better to ask for help early than to create problems through ineffective handling.',
                                'Rearing, bolting, bucking, or aggressive behavior should not be ignored or "worked through" without expert guidance. These behaviors often stem from pain, fear, or confusion, and need careful, knowledgeable management.',
                                'Even if you\'re an experienced rider, consider having a professional start your youngster or provide regular training sessions. An outside eye catches issues early and keeps you progressing safely.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => '⚠️ RED FLAGS: Sudden behavior changes, consistent resistance, lameness, or unwillingness to move forward all warrant veterinary examination before continuing training. Many "training issues" are actually pain responses.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '10. Celebrate Small Victories',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Training young horses is a marathon, not a sprint. Progress comes in small increments, and some days feel like you\'ve taken two steps backward. This is completely normal.',
                                'Celebrate every small win: the first time they stand at the mounting block, their first canter transition, the first hack without spooking. These moments build towards the bigger picture of a well-trained, confident partner.',
                                'Keep a training diary. On tough days, looking back at where you started reminds you how far you\'ve come. Video footage is particularly valuable - we often don\'t see progress day-to-day, but comparing month-to-month reveals huge improvements.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The best young horse trainers aren\'t necessarily the most talented riders. They\'re patient, observant, and committed to doing things properly. Those qualities matter more than any flashy technique.',
                            'attribution' => 'Mark Davidson'
                        ]
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'style' => 'dashed'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Final Thoughts',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Starting a young horse correctly is one of the most rewarding journeys in equestrianism. The bond you build, the trust you establish, and the partnership you create will last a lifetime.',
                                'Remember that every horse is an individual. While these principles apply universally, how you implement them should be tailored to your horse\'s temperament, breeding, and intended career path.',
                                'Above all, enjoy the process. These early months and years are precious. Your youngster will never be this age again, and the foundation you lay now determines their entire future as a riding horse.'
                            ]
                        ]
                    ]
                ]
            ],

            // Article 5: Showjumping Championship Report
            [
                'title' => 'Hickstead Derby 2025: Dramatic Victory for Ben Maher',
                'slug' => 'hickstead-derby-2025-report',
                'tags' => ['showjumping', 'competition', 'news', 'results', 'featured'],
                'categories' => ['News & Features', 'Competition Reports', 'Showjumping'],
                'custom_fields' => [
                    'author_name' => 'Rachel Stevens',
                    'author_bio' => 'Showjumping correspondent covering international competitions for 15 years.',
                    'read_time' => 7,
                    'discipline' => 'showjumping',
                    'event_name' => 'Hickstead Derby',
                    'event_date' => 'June 22, 2025',
                    'location' => 'All England Jumping Course, Hickstead',
                    'horse_name' => 'Explosion W',
                    'rider_name' => 'Ben Maher',
                    'excerpt' => 'Ben Maher claims his second Hickstead Derby title in thrilling jump-off against international field on the world\'s most famous showjumping course.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1598524719074-bab3653e0777?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Showjumping at Hickstead',
                            'caption' => 'The famous Derby Bank - the most iconic obstacle in showjumping',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Ben Maher delivered a masterclass in brave, attacking riding to claim his second Longines King George V Gold Cup at the Hickstead Derby Meeting. Riding the talented 11-year-old gelding Explosion W, Maher was the only rider to jump double clear over the legendary Derby course.',
                                'In front of a capacity crowd of 8,000 spectators, Maher faced a dramatic four-way jump-off against Germany\'s Daniel Deusser, Ireland\'s Darragh Kenny, and home favorite Holly Smith. His time of 51.23 seconds proved unbeatable, with Deusser finishing second, four seconds behind.',
                                'The victory cements Maher\'s reputation as one of Britain\'s greatest ever showjumpers and adds another prestigious title to his already glittering CV including Olympic team gold and European Championship victories.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'info',
                            'description' => '🏆 This was the 76th running of the famous Derby, first held in 1961. Only 31 riders have won the class in its history.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'First Round Drama',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Course designer Kelvin Bywater created a true Derby test, with all the traditional challenges that make this course unique in the world. The famous Derby Bank, Devil\'s Dyke, and Devils Dyke double combinations tested every aspect of horse and rider ability.',
                                'Of the 24 starters, just four combinations managed to navigate the first round clear. The Bank claimed multiple victims, with several riders opting for the longer alternative route to minimize risk. Those who took the direct route needed both courage and precision.',
                                'Holly Smith produced the round of the day on her mare Denver, attacking the course with confidence and clearing the Bank in spectacular style to the delight of the home crowd.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Fence 3: The Devil\'s Dyke - Claimed 7 faults',
                                'Fence 6: The Derby Bank - 12 riders chose alternative route',
                                'Fence 10: Open Water - Caught out 5 combinations',
                                'Fence 14: Final double combination - Decided several fortunes'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Jump-Off Excellence',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'First to go in the jump-off, Darragh Kenny set a strong target time of 54.89 seconds on VDL Cartello. His clear round put pressure on the remaining three riders.',
                                'Daniel Deusser, riding Killer Queen VDM, matched Kenny\'s clear but couldn\'t quite match the pace, finishing in 55.74 seconds to slot into second place temporarily.',
                                'Holly Smith, with the crowd roaring their support, produced another superb clear but her time of 56.12 seconds wasn\'t quite quick enough, leaving her in third position.',
                                'Last to go, Maher knew what was required. Explosion W powered around the shortened course, taking every available inside turn and galloping fearlessly between fences. Their time of 51.23 seconds was over three seconds faster than Kenny, securing a famous victory.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Position', 'Rider', 'Horse', 'Country', 'Jump-Off Time', 'Prize'],
                                ['1st', 'Ben Maher', 'Explosion W', 'GBR', '51.23s', '£50,000'],
                                ['2nd', 'Daniel Deusser', 'Killer Queen VDM', 'GER', '55.74s', '£25,000'],
                                ['3rd', 'Holly Smith', 'Denver', 'GBR', '56.12s', '£15,000'],
                                ['4th', 'Darragh Kenny', 'VDL Cartello', 'IRL', '54.89s', '£10,000']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'This is one of the most special competitions to win. The Derby is what every British showjumper grows up dreaming about. Explosion W was incredible today - so brave and careful. I\'m thrilled.',
                            'attribution' => 'Ben Maher, speaking after his victory'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'About Explosion W',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Explosion W, an 11-year-old KWPN gelding by Chacco Blue, has been with Maher for three years. The combination has developed into one of Britain\'s most formidable partnerships, with multiple five-star Grand Prix victories.',
                                'Known for his careful jumping and brave attitude, "Eddie" as he\'s known in the yard, has all the attributes needed to succeed on the Derby course. This was his first appearance at Hickstead, making the victory even more impressive.',
                                'Maher paid tribute to his groom and support team: "He arrived here in perfect form thanks to everyone at home. The preparation was meticulous, and it paid off today."'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Hickstead Derby 2025',
                            'stats' => [
                                ['number' => '24', 'label' => 'Starters', 'icon' => '🐴'],
                                ['number' => '4', 'label' => 'Clear Rounds', 'icon' => '✅'],
                                ['number' => '8,000', 'label' => 'Spectators', 'icon' => '👥'],
                                ['number' => '76th', 'label' => 'Year of Derby', 'icon' => '🏆']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Other Class Winners',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Derby was the highlight of a spectacular week of showjumping at Hickstead. Thursday\'s Speed Derby went to Switzerland\'s Steve Guerdat riding Dynamix de Belheme, while Friday\'s King George V Gold Cup qualifier saw victory for Sweden\'s Peder Fredricson.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'The Winning Round',
                                    'description' => 'Ben Maher clears the final fence',
                                    'image' => 'https://images.unsplash.com/photo-1598524719074-bab3653e0777?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Showjumping clear round'
                                ],
                                [
                                    'title' => 'Victory Lap',
                                    'description' => 'Maher celebrates with Explosion W',
                                    'image' => 'https://images.unsplash.com/photo-1534278931827-8a259344abe7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Victory celebration'
                                ],
                                [
                                    'title' => 'Presentation',
                                    'description' => 'The podium ceremony',
                                    'image' => 'https://images.unsplash.com/photo-1553284965-83fd3e82fa5a?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Award ceremony'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'style' => 'solid'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What\'s Next',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Maher and Explosion W now turn their attention to next month\'s Global Champions Tour event in London, where they\'ll face the world\'s best over three days of competition at the Royal Hospital Chelsea.',
                                'The Derby victory provides perfect preparation and confidence for the remainder of the season, with European Championship selection also on the horizon.'
                            ]
                        ]
                    ]
                ]
            ],
            // Article 4: Showjumping Training
            [
                'title' => '10 Essential Showjumping Training Exercises for Clear Rounds',
                'slug' => 'showjumping-training-exercises',
                'tags' => ['showjumping', 'training', 'advanced', 'technique'],
                'categories' => ['Disciplines', 'Showjumping', 'Technique'],
                'custom_fields' => [
                    'author_name' => 'Tim Stockdale',
                    'author_bio' => 'International showjumper and trainer with over 30 years at the top of the sport.',
                    'read_time' => 10,
                    'discipline' => 'showjumping',
                    'excerpt' => 'Master showjumper Tim Stockdale shares the training exercises he uses with his top horses to develop rhythm, balance and jumping technique.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1591438459317-44e8b8b9c2f7?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Horse and rider showjumping',
                            'caption' => 'Perfect jumping technique comes from consistent, focused training',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Clear rounds don\'t happen by accident. They\'re the result of careful training, developing your horse\'s balance, rhythm and technique through progressive exercises.',
                                'These 10 exercises are the foundation of my training program. I use them with horses at every level, from novices learning to jump to Grand Prix horses maintaining their edge.',
                                'Each exercise targets specific skills - adjustability, straightness, rhythm, confidence. Work through them systematically and you\'ll see real improvement in your showjumping.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Exercise 1: The Grid Work',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Grid work is the cornerstone of showjumping training. It teaches the horse to think forward, stay straight, and develop a rhythmic jumping style without relying on the rider.',
                                'Start simple with placing poles, progressing to small cross poles, then verticals. The distances are set to encourage the horse to find the correct stride and balance.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Place pole to first fence: 2.7m (9ft)',
                                'Cross pole to vertical: 6.4m (21ft) for one non-jumping stride',
                                'Vertical to vertical: 7.3m (24ft) for one stride',
                                'Final element: 10.4m (34ft) for two strides',
                                'Start with everything low (60cm) and gradually raise',
                                'Focus on rhythm - don\'t override with your leg'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Exercise 2: Figure of Eight',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'This exercise develops balance, adjustability and straightness. Set two fences 20m apart and ride a figure of eight over them, jumping each fence twice per circuit.',
                                'The key is maintaining the same rhythm and stride length through the turns and approaches. Many horses rush or fall onto their forehand through turns - this exercise addresses that.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'schema',
                        'data' => [
                            'schemaType' => 'how-to',
                            'title' => 'How to Ride the Figure of Eight',
                            'description' => 'Step-by-step guide to this essential training exercise',
                            'image' => 'https://images.unsplash.com/photo-1591438459317-44e8b8b9c2f7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Approach first fence on the right rein in a balanced canter',
                                'After landing, make a smooth half circle to the left',
                                'Jump the second fence on the left rein',
                                'Half circle right back to the first fence',
                                'Maintain the same stride length throughout',
                                'Keep the horse between leg and hand through the turns',
                                'Repeat for 10-12 minutes'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => '💡 Pro Tip: Count strides aloud to maintain rhythm. Many riders unconsciously speed up or slow down - counting keeps you honest!'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Exercise 3-5: Additional Exercises',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The remaining exercises focus on specific skills: bounce fences for quick reactions, related distances for adjustability, and combinations for confidence. Each builds on the previous ones to create a complete training system.',
                                'Remember, these exercises are tools, not tests. If your horse struggles, make it easier. Success builds confidence - that\'s what creates clear rounds.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Train at home with precision and patience. When you get to the showground, trust your training and let your horse do what you\'ve taught them.',
                            'attribution' => 'Tim Stockdale'
                        ]
                    ]
                ]
            ],
            // Article 5: Stable Management
            [
                'title' => 'Stable Management Essentials: Daily Routine for Happy, Healthy Horses',
                'slug' => 'stable-management-daily-routine',
                'tags' => ['stable-management', 'horse-care', 'beginners', 'guides'],
                'categories' => ['Horse Care', 'Stable Management', 'Routine'],
                'custom_fields' => [
                    'author_name' => 'Lucy Higgins BHSII',
                    'author_bio' => 'BHS qualified instructor and yard manager with 25 years experience.',
                    'read_time' => 9,
                    'excerpt' => 'A comprehensive guide to daily stable management - from morning routines to evening checks, keeping your horse healthy and your yard running smoothly.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1553284965-83fd3e82fa5a?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Well-maintained stable',
                            'caption' => 'A well-run yard starts with consistent daily routines',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Good stable management is the foundation of horse care. A consistent daily routine keeps horses healthy, content and performing at their best.',
                                'Whether you\'re managing a competition yard or keeping a horse at home, these essential practices will help you maintain high standards of care.',
                                'This guide covers everything from morning feeds to evening checks, with expert tips learned from managing busy competition yards.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Morning Routine (6:00-9:00 AM)',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Check all horses are well - observe from stable door before entering',
                                'Provide fresh water and morning feed according to individual requirements',
                                'Remove droppings from stables while horses eat',
                                'Skip out water buckets and check automatic drinkers',
                                'Inspect horses close-up - check for injuries, heat, swelling',
                                'Turn out or exercise according to each horse\'s schedule',
                                'Muck out stables fully, banking beds properly',
                                'Sweep yard and maintain general tidiness'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Task', 'Frequency', 'Time Required', 'Priority'],
                                ['Health check', 'Twice daily', '2 mins per horse', 'Critical'],
                                ['Feed', 'As scheduled', '5 mins per horse', 'Critical'],
                                ['Muck out', 'Daily', '15-20 mins per stable', 'High'],
                                ['Water', 'Check 3x daily', '1 min per horse', 'Critical'],
                                ['Groom', 'Daily', '20-30 mins', 'High'],
                                ['Exercise', 'As planned', 'Variable', 'High'],
                                ['Tack clean', 'After use', '15 mins', 'Medium']
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Health Check Essentials',
                            'paragraphs' => [
                                'Before entering any stable, observe the horse from the door. Are they standing normally? Bright and alert? Any signs of distress?',
                                'Check the stable for clues: Has hay been eaten? Are droppings normal? Any signs of rolling or disturbance?',
                                'When you approach, run your hands down all four legs checking for heat, swelling or sensitivity. This daily check catches problems early.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Evening Routine (4:00-7:00 PM)',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Evening stables are just as important as morning routines. This is your chance to catch any issues that developed during the day and ensure horses are comfortable for the night.',
                                'Take your time with evening checks - horses are often more settled and willing to show you if something is bothering them.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Bring in horses from turnout - check them over carefully',
                                'Provide evening feed and fresh hay',
                                'Top up water buckets',
                                'Skip out droppings and add fresh bedding',
                                'Rug as appropriate for weather',
                                'Final check - is everyone comfortable?',
                                'Secure yard for night - lock feed room, check gates'
                            ]
                        ]
                    ]
                ]
            ],
            // Article 6: Horse Trailer Buying Guide
            [
                'title' => 'Horse Trailer Buying Guide 2025: Reviews & Expert Advice',
                'slug' => 'horse-trailer-buying-guide-2025',
                'tags' => ['buying-guide', 'equipment', 'transport', 'product-review'],
                'categories' => ['Buying Guides', 'Horse Transport', 'Trailers'],
                'custom_fields' => [
                    'author_name' => 'Richard Davies',
                    'author_bio' => 'Transport specialist and qualified HGV instructor with 30 years in equine logistics.',
                    'read_time' => 14,
                    'excerpt' => 'Everything you need to know about buying a horse trailer - from Ifor Williams to Böckmann, we review the best models and explain what to look for.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1449057528837-7ca097b3520c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Horse trailer in countryside',
                            'caption' => 'A quality trailer is essential for safe, stress-free horse transport',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Choosing the right horse trailer is a major investment decision. With prices ranging from £4,000 for a basic used trailer to £25,000+ for a top-spec new model, it\'s essential to get it right.',
                                'Safety must be your top priority. A good trailer protects your horse during travel and gives you confidence on the road. But you also need to consider practicality, running costs and compatibility with your towing vehicle.',
                                'This comprehensive guide reviews the leading brands, explains legal requirements, and helps you choose the perfect trailer for your needs and budget.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Legal Requirements & Towing Capacity',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Before you even look at trailers, you must understand what your vehicle can legally and safely tow. The maximum weight your trailer can be depends on your driving licence and vehicle specifications.',
                                'UK drivers who passed their test after 1 January 1997 (Category B licence) can tow trailers up to 3,500kg MAM (Maximum Authorised Mass) provided the combined weight of vehicle and trailer doesn\'t exceed 3,500kg. For anything heavier, you need B+E category.',
                                'However, legal limits aren\'t the only consideration. Your vehicle\'s towing capacity and noseweight limit are crucial safety factors. Never exceed these, even if legally permitted.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Trailer Type', 'Typical Weight', 'Suitable Vehicle', 'Licence Required'],
                                ['Single Horse (511)', '750-850kg empty', 'Large 4x4, pickup', 'B (post-1997)'],
                                ['Double Horse (505)', '950-1,100kg empty', 'Large 4x4, robust SUV', 'B+E often required'],
                                ['Large Double (510)', '1,200-1,400kg empty', 'Land Rover, large pickup', 'B+E required'],
                                ['Living (with tack area)', '1,500kg+ empty', 'Substantial 4x4', 'B+E required']
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => '⚠️ CRITICAL: The 85% rule - for safe towing, your loaded trailer should weigh no more than 85% of your vehicle\'s kerb weight. This isn\'t a legal requirement but is essential for stability and safety.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Best Horse Trailers 2025',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'award',
                        'data' => [
                            'subcategory' => 'Overall Winner',
                            'productName' => 'Ifor Williams HB511',
                            'image' => 'https://images.unsplash.com/photo-1449057528837-7ca097b3520c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'winner' => true,
                            'rating' => 4.9,
                            'strapline' => 'The gold standard in British horse trailers',
                            'caption' => 'Ifor Williams has dominated the UK market for decades with unmatched quality and reliability'
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Ifor Williams HB511 Horse Trailer',
                            'subtitle' => 'The best single-horse trailer money can buy',
                            'image' => 'https://images.unsplash.com/photo-1449057528837-7ca097b3520c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'url' => 'https://example.com/ifor-hb511',
                            'linkText' => 'Find Dealer',
                            'displayAs' => 'button',
                            'specs' => [
                                ['text' => 'Price', 'value' => '£8,995 (new)'],
                                ['text' => 'Weight', 'value' => '775kg unladen'],
                                ['text' => 'Horse Capacity', 'value' => 'One horse up to 550kg'],
                                ['text' => 'Internal Height', 'value' => '2.13m'],
                                ['text' => 'Warranty', 'value' => '3 years structural']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Exceptional build quality and longevity',
                                'Excellent resale value',
                                'Wide loading ramp with good grip',
                                'Proper full-height breast bar',
                                'Large tack locker and storage',
                                'Safe, stable towing characteristics'
                            ],
                            'cons' => [
                                'Premium price point',
                                'Long waiting list for new models',
                                'Can be heavy on fuel economy',
                                'Limited colour options'
                            ],
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Double Horse Trailers',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Ifor Williams vs Böckmann',
                            'productA' => 'Ifor Williams HB505',
                            'productB' => 'Böckmann Duo Esprit',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Price (New)',
                                    'items' => [
                                        ['value' => '£12,995'],
                                        ['value' => '£14,500']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Build Style',
                                    'items' => [
                                        ['value' => 'Traditional steel construction'],
                                        ['value' => 'Modern aluminium panels']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Weight',
                                    'items' => [
                                        ['value' => '1,075kg unladen'],
                                        ['value' => '950kg unladen']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Interior Height',
                                    'items' => [
                                        ['value' => '2.13m'],
                                        ['value' => '2.20m']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Reliability and resale value'],
                                        ['value' => 'Lighter weight and space']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Buying Used: What to Check',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'A well-maintained used trailer can be excellent value, but you must inspect carefully. Hidden damage or corrosion can be dangerous and expensive to fix.',
                                'Always view with the trailer empty and in good daylight. Take a knowledgeable friend if possible, and don\'t be rushed by the seller.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Floor condition - stamp your foot to check for soft spots indicating rot',
                                'Ramp operation - should lower and raise smoothly with good springs',
                                'Breast bars and partitions - check for cracks or weak welding',
                                'Chassis rails - look underneath for rust or accident damage',
                                'Lights and electrics - test everything thoroughly',
                                'Tyres - check tread depth (legal minimum 1.6mm) and age (replace if over 5 years)',
                                'Brakes - should be serviced annually, ask for service records',
                                'Documents - V5C, MOT if applicable, service history'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Depreciation Rates',
                            'paragraphs' => [
                                'Horse trailers hold their value better than most vehicle investments. An Ifor Williams typically loses 30-40% of its value in the first 5 years, then stabilizes.',
                                'A well-maintained 10-year-old Ifor Williams can still sell for 40-50% of its original price. Compare this to a car which typically loses 60% in just 3 years.',
                                'Budget European brands depreciate faster - 50-60% in 5 years. Consider this when making your purchase decision.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Trailer Ownership Costs',
                            'stats' => [
                                ['number' => '£150', 'label' => 'Annual Service Cost', 'icon' => '🔧'],
                                ['number' => '£200-400', 'label' => 'Insurance (per year)', 'icon' => '📋'],
                                ['number' => '£100', 'label' => 'Brake Service Cost', 'icon' => '⚙️'],
                                ['number' => '5 years', 'label' => 'Tyre Replacement', 'icon' => '🚗']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Buy the best trailer you can afford. A cheap trailer is a false economy - safety and reliability matter far more than saving a few hundred pounds.',
                            'attribution' => 'Richard Davies, Transport Specialist'
                        ]
                    ]
                ]
            ],
            // Article 7: Bonus - Dressage Competition
            [
                'title' => 'Royal Windsor Horse Show 2025: Dressage Stars Shine',
                'slug' => 'royal-windsor-2025-dressage',
                'tags' => ['dressage', 'competition', 'news', 'results'],
                'categories' => ['News & Features', 'Competition Reports', 'Dressage'],
                'custom_fields' => [
                    'author_name' => 'Alice Collins',
                    'author_bio' => 'Dressage correspondent covering international competitions.',
                    'read_time' => 6,
                    'discipline' => 'dressage',
                    'event_name' => 'Royal Windsor Horse Show',
                    'event_date' => 'May 15, 2025',
                    'location' => 'Windsor Castle, Berkshire',
                    'excerpt' => 'Charlotte Dujardin wins the CDI4* Grand Prix Freestyle at Royal Windsor with a stunning performance on Imhotep.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1551884831-bbf3cdc6469e?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Dressage competition',
                            'caption' => 'Charlotte Dujardin and Imhotep perform their winning freestyle',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Charlotte Dujardin produced a masterclass in dressage artistry to win the CDI4* Grand Prix Freestyle at Royal Windsor Horse Show yesterday, scoring an impressive 83.475% with her 10-year-old gelding Imhotep.',
                                'Performing in front of a capacity crowd in the Castle Arena, with Windsor Castle providing a spectacular backdrop, Charlotte and "Hotty" delivered a technically flawless test set to a medley of classical music.',
                                'The victory confirms Charlotte\'s position as Britain\'s leading dressage rider and suggests Imhotep is developing into a serious championship contender.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Final Results',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Position', 'Rider', 'Horse', 'Score', 'Country'],
                                ['1st', 'Charlotte Dujardin', 'Imhotep', '83.475%', 'GBR'],
                                ['2nd', 'Isabell Werth', 'Bella Rose 2', '82.950%', 'GER'],
                                ['3rd', 'Carl Hester', 'Fame', '81.225%', 'GBR'],
                                ['4th', 'Lottie Fry', 'Glamourdale', '79.875%', 'NED'],
                                ['5th', 'Gareth Hughes', 'Classic Briolinca', '78.650%', 'GBR']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Hotty felt amazing today. The atmosphere here is electric and he really rose to the occasion. I couldn\'t be happier with him.',
                            'attribution' => 'Charlotte Dujardin'
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
            'meta_title' => $data['title'] . ' - Horse & Hound',
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
            'title' => 'About Horse & Hound',
            'page_type' => 'content',
            'slug' => 'about',
            'status' => 'published',
            'meta_title' => 'About Us - Horse & Hound',
            'meta_description' => 'Learn about Horse & Hound - the world\'s leading equestrian magazine since 1884.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'About',
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
                    'title' => 'About Horse & Hound',
                    'subtitle' => 'The world\'s leading equestrian magazine since 1884',
                    'ctaText' => 'Our History',
                    'ctaUrl' => '#history',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1553284965-83fd3e82fa5a?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'For over 140 years, Horse & Hound has been the trusted voice of British equestrianism. Founded in 1884, we have reported on every major equestrian event, from the early days of hunting and showing to the Olympic glory of our modern champions.',
                        'Our mission remains unchanged: to inform, educate and inspire horse lovers of all levels. Whether you\'re a professional competing at the highest level or a weekend rider enjoying hacking through the countryside, Horse & Hound is your essential companion.',
                        'Today, we reach over 500,000 readers monthly through our print magazine, website, and social media channels. Our team of expert journalists and photographers bring you unrivalled coverage of equestrian sport, expert advice on horse care, and the latest product reviews.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Our Heritage',
                    'stats' => [
                        ['number' => '1884', 'label' => 'Founded', 'icon' => '📅'],
                        ['number' => '140+', 'label' => 'Years Publishing', 'icon' => '📰'],
                        ['number' => '500K+', 'label' => 'Monthly Readers', 'icon' => '👥'],
                        ['number' => '50+', 'label' => 'Expert Contributors', 'icon' => '✍️']
                    ]
                ],
                'order' => 3
            ]
        ];
        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createContactPage(): void
    {
        $page = Page::create([
            'title' => 'Contact Horse & Hound',
            'page_type' => 'content',
            'slug' => 'contact',
            'status' => 'published',
            'meta_title' => 'Contact Us - Horse & Hound',
            'meta_description' => 'Get in touch with the Horse & Hound editorial team.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Contact',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
            'sort_order' => 11
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Contact Us',
                    'subtitle' => 'We\'d love to hear from you',
                    'showSearch' => false
                ],
                'order' => 1
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Horse & Hound Editorial',
                    'role' => 'Contact Information',
                    'email' => 'editorial@horseandhound.co.uk',
                    'phone' => '+44 (0)20 3890 3890',
                    'address' => 'Horse & Hound
                    Time Inc. UK
Pinehurst 2
Pinehurst Road
Farnborough Business Park
Farnborough
Hampshire GU14 7BF',
                    'displayType' => 'contact'
                ],
                'order' => 2
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Send Us a Message',
                    'subtitle' => 'Whether you have a story tip, feedback, or just want to say hello',
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
                'order' => 3
            ]
        ];
        $this->createBlocksForPage($page->id, $blocks);
    }
}