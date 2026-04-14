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
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class FashionMagazineSeeder extends Seeder
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

    public function createSite(): void
    {
        $this->site = Site::find(4);
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
            'featured', 'trending', 'exclusive', 'runway', 'street-style',
            'haute-couture', 'sustainable-fashion', 'designer-spotlight',
            'fashion-week', 'paris-fashion-week', 'milan-fashion-week',
            'new-york-fashion-week', 'london-fashion-week',
            'spring-summer', 'fall-winter', 'resort', 'pre-fall',
            'accessories', 'shoes', 'bags', 'jewelry', 'watches',
            'beauty', 'makeup', 'skincare', 'haircare',
            'celebrity-style', 'red-carpet', 'style-guide',
            'vintage', 'luxury', 'affordable-fashion', 'fast-fashion',
            'editorial', 'photoshoot', 'behind-the-scenes'
        ];

        foreach ($tags as $tagName) {
            $this->tagRepository->findOrCreateByName($tagName, $this->site->id);;
        }
    }

    private function createCategories(): void
    {
        $categories = [
            'Fashion' => [
                'Runway' => ['Paris', 'Milan', 'New York', 'London'],
                'Street Style' => ['Celebrity', 'Influencer', 'Editorial'],
                'Trends' => ['Spring/Summer', 'Fall/Winter', 'Resort']
            ],
            'Beauty' => [
                'Makeup' => ['Tutorials', 'Product Reviews', 'Trends'],
                'Skincare' => ['Routines', 'Product Reviews', 'Tips'],
                'Hair' => ['Styles', 'Care', 'Color']
            ],
            'Designers' => ['Established', 'Emerging', 'Sustainable'],
            'Lifestyle' => ['Travel', 'Culture', 'Wellness'],
            'Shopping' => ['Luxury', 'High Street', 'Vintage']
        ];

        $this->createCategoriesRecursively($categories);
    }

    private function createCategoriesRecursively(array $categories, ?int $parentId = null): void
    {
        foreach ($categories as $name => $children) {
            $category = $this->categoryRepository->findOrCreateByName($name, $this->site->id);;
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
            ['key' => 'featured_image', 'name' => 'Featured Image', 'type' => 'text'],
            ['key' => 'excerpt', 'name' => 'Article Excerpt', 'type' => 'textarea'],
            ['key' => 'season', 'name' => 'Season', 'type' => 'select', 'options' => '{"ss":"Spring/Summer","fw":"Fall/Winter","resort":"Resort","pre-fall":"Pre-Fall"}'],
            ['key' => 'designer', 'name' => 'Designer', 'type' => 'text'],
            ['key' => 'collection', 'name' => 'Collection', 'type' => 'text'],
            ['key' => 'fashion_week', 'name' => 'Fashion Week', 'type' => 'select', 'options' => '{"paris":"Paris","milan":"Milan","newyork":"New York","london":"London"}'],
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
            'title' => 'VOGUE NOIR - Fashion Forward',
            'page_type' => 'content',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'VOGUE NOIR - The Ultimate Fashion Magazine',
            'meta_description' => 'Discover the latest fashion trends, runway shows, designer collections, and style inspiration from around the world.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Home',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        $featuredTag = $this->tagRepository->findOrCreateByName('featured', 1);
        $page->tags(true)->attach($featuredTag->id);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Fashion Forward',
                    'subtitle' => 'Discover the latest trends, runway shows, and style inspiration from the world\'s fashion capitals',
                    'ctaText' => 'Explore Stories',
                    'ctaUrl' => '#featured',
                    'secondaryCtaText' => 'Subscribe',
                    'secondaryCtaUrl' => '/subscribe',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Featured Stories',
                    'subtitle' => 'Our Editor\'s Picks',
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
                            'title' => 'Paris Fashion Week: The Collections That Defined Spring 2025',
                            'slug' => 'paris-fashion-week-spring-2025',
                            'excerpt' => 'From Chanel\'s aquatic dreams to Dior\'s architectural revolution, we break down the most breathtaking moments from the City of Light.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Paris Fashion Week Runway'
                            ],
                            'badge' => [
                                'text' => 'Featured',
                                'color' => 'primary'
                            ],
                            'meta' => [
                                'author' => 'Sophie Laurent',
                                'date' => 'March 15, 2025',
                                'readTime' => '8 min read'
                            ]
                        ],
                        [
                            'title' => 'Sustainable Luxury: The Designers Changing Fashion\'s Future',
                            'slug' => 'sustainable-luxury-designers',
                            'excerpt' => 'Meet the visionaries proving that high fashion and environmental responsibility can coexist beautifully.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Sustainable Fashion'
                            ],
                            'badge' => [
                                'text' => 'Trending',
                                'color' => 'success'
                            ],
                            'meta' => [
                                'author' => 'Emma Green',
                                'date' => 'March 14, 2025',
                                'readTime' => '6 min read'
                            ]
                        ],
                        [
                            'title' => 'Street Style Chronicles: New York\'s Fashion Week Finale',
                            'slug' => 'nyfw-street-style',
                            'excerpt' => 'The most daring, innovative, and unforgettable looks from outside the shows.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1509631179647-0177331693ae?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Street Style Fashion'
                            ],
                            'badge' => [
                                'text' => 'Exclusive',
                                'color' => 'warning'
                            ],
                            'meta' => [
                                'author' => 'Marcus Chen',
                                'date' => 'March 13, 2025',
                                'readTime' => '5 min read'
                            ]
                        ]
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'divider',
                'data' => [
                    'style' => 'decorative'
                ],
                'order' => 4
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Latest Trends',
                    'subtitle' => 'What\'s Hot Right Now',
                    'level' => 2
                ],
                'order' => 5
            ],
            [
                'type' => 'gallery',
                'data' => [
                    'layout' => 'grid',
                    'slides' => [
                        [
                            'title' => 'Bold Colors',
                            'description' => 'Vibrant hues dominate the runway',
                            'image' => 'https://images.unsplash.com/photo-1558769132-cb1aea3c5f0c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Bold Fashion Colors',
                            'link' => '/trends/bold-colors'
                        ],
                        [
                            'title' => 'Oversized Silhouettes',
                            'description' => 'Comfort meets couture',
                            'image' => 'https://images.unsplash.com/photo-1558769132-cb1aea3c5f0c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Oversized Fashion',
                            'link' => '/trends/oversized'
                        ],
                        [
                            'title' => 'Metallic Accents',
                            'description' => 'Shine bright this season',
                            'image' => 'https://images.unsplash.com/photo-1558769132-cb1aea3c5f0c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Metallic Fashion',
                            'link' => '/trends/metallics'
                        ]
                    ]
                ],
                'order' => 6
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'Fashion is the armor to survive the reality of everyday life.',
                    'attribution' => 'Bill Cunningham'
                ],
                'order' => 7
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Subscribe to Our Newsletter',
                    'subtitle' => 'Get the latest fashion news delivered to your inbox',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => false,
                    'showSubject' => false,
                    'showMessage' => false,
                    'submitButtonText' => 'Subscribe Now',
                    'requireName' => true,
                    'requireEmail' => true
                ],
                'order' => 8
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createArticles(): void
    {
        $articles = [
            [
                'title' => 'Paris Fashion Week: The Collections That Defined Spring 2025',
                'slug' => 'paris-fashion-week-spring-2025',
                'tags' => ['featured', 'fashion-week', 'paris-fashion-week', 'spring-summer', 'runway'],
                'categories' => ['Fashion', 'Runway', 'Paris'],
                'custom_fields' => [
                    'author_name' => 'Sophie Laurent',
                    'author_bio' => 'Sophie is our Paris correspondent with over 15 years covering haute couture.',
                    'read_time' => 8,
                    'excerpt' => 'From Chanel\'s aquatic dreams to Dior\'s architectural revolution, we break down the most breathtaking moments from the City of Light.',
                    'season' => 'ss',
                    'fashion_week' => 'paris'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Paris Fashion Week Runway Show',
                            'caption' => 'A model walks the runway at Chanel Spring 2025',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Paris Fashion Week has once again proven why it remains the pinnacle of haute couture. This season brought us collections that pushed boundaries, challenged conventions, and reminded us why fashion is art.',
                                'The Spring 2025 collections showcased a remarkable shift towards fluidity and movement, with designers embracing water-inspired silhouettes and flowing fabrics that seemed to dance down the runway.',
                                'From Karl Lagerfeld\'s final posthumous collection at Chanel to Maria Grazia Chiuri\'s feminist manifesto at Dior, this season was nothing short of spectacular.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Chanel: Aquatic Dreams',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Chanel transformed the Grand Palais into an underwater wonderland, complete with a massive water feature as the centerpiece. The collection featured flowing chiffons in ocean blues and greens, adorned with pearl embellishments that caught the light like sunlight on water.',
                                'The iconic tweed suit was reimagined with aquatic motifs, featuring wave-like patterns and iridescent sequins that shimmered with movement. It was a collection that honored tradition while diving headfirst into innovation.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'carousel',
                            'slides' => [
                                [
                                    'title' => 'Look 1',
                                    'image' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                                    'alt' => 'Chanel Spring 2025 Look 1',
                                    'description' => 'Flowing blue chiffon with pearl details'
                                ],
                                [
                                    'title' => 'Look 2',
                                    'image' => 'https://images.unsplash.com/photo-1509631179647-0177331693ae?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                                    'alt' => 'Chanel Spring 2025 Look 2',
                                    'description' => 'Reimagined tweed with aquatic motifs'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Dior: Architectural Revolution',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Maria Grazia Chiuri presented a collection that was both powerful and feminine, drawing inspiration from brutalist architecture and strong, geometric forms. The show opened with sharp-shouldered jackets in pristine white, gradually evolving into softer, more romantic pieces.',
                                'The standout moments included structured dresses that seemed to defy gravity, featuring dramatic pleating and architectural draping. Each piece was a testament to the craftsmanship and innovation that defines Dior.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Fashion is about dreaming and making other people dream.',
                            'attribution' => 'Donatella Versace'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Key Trends',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Fluid, water-inspired silhouettes dominate the runway',
                                'Bold architectural shapes create dramatic impact',
                                'Sustainable fabrics take center stage',
                                'Iridescent and pearl embellishments add ethereal beauty',
                                'Sharp tailoring contrasts with romantic draping',
                                'Ocean blues and greens emerge as key colors'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Editor\'s Note',
                            'paragraphs' => [
                                'This season\'s collections represent a turning point in fashion, where sustainability and artistry merge seamlessly. Designers are no longer choosing between ethics and aesthetics – they\'re proving both are essential.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Sustainable Luxury: The Designers Changing Fashion\'s Future',
                'slug' => 'sustainable-luxury-designers',
                'tags' => ['featured', 'sustainable-fashion', 'designer-spotlight', 'trending'],
                'categories' => ['Fashion', 'Designers', 'Sustainable'],
                'custom_fields' => [
                    'author_name' => 'Emma Green',
                    'author_bio' => 'Emma specializes in sustainable fashion and ethical luxury.',
                    'read_time' => 6,
                    'excerpt' => 'Meet the visionaries proving that high fashion and environmental responsibility can coexist beautifully.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Sustainable Fashion Design',
                            'caption' => 'Stella McCartney\'s latest sustainable collection',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The fashion industry is undergoing a quiet revolution. While headlines focus on fast fashion\'s environmental impact, a new generation of designers is proving that luxury and sustainability aren\'t mutually exclusive.',
                                'These visionaries are reimagining every aspect of fashion production, from sourcing materials to manufacturing processes, creating garments that are as kind to the planet as they are beautiful.',
                                'We sat down with five pioneering designers who are leading this change, each bringing their unique perspective to sustainable luxury.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Stella McCartney: The OG Sustainable Designer',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Long before sustainability became a buzzword, Stella McCartney was championing ethical fashion. Her brand has never used leather or fur, instead pioneering innovative alternatives that rival traditional materials in quality and aesthetics.',
                                '"I\'ve always believed that you don\'t have to harm animals or the planet to create beautiful clothes," McCartney tells us. "It\'s about innovation, creativity, and responsibility."',
                                'Her latest collection features Bio-fabricated leather made from mycelium, a material that could revolutionize the industry.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Look for certifications like GOTS (Global Organic Textile Standard) and B Corp when shopping for sustainable fashion.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Gabriela Hearst: Luxury Meets Zero Waste',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Gabriela Hearst\'s approach to sustainability is holistic. Every aspect of her business, from energy consumption to packaging, is designed to minimize environmental impact.',
                                'Her brand became the first luxury fashion house to achieve B Corp certification, setting a new standard for the industry. "Sustainability isn\'t a marketing strategy," Hearst explains. "It\'s the foundation of how we operate."'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Designer', 'Key Innovation', 'Impact'],
                                ['Stella McCartney', 'Bio-fabricated materials', 'Zero animal products'],
                                ['Gabriela Hearst', 'Zero waste production', 'B Corp certified'],
                                ['Marine Serre', 'Upcycled fabrics', '50% regenerated materials'],
                                ['Christopher Raeburn', 'Remade military surplus', 'Circular fashion model']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The fashion industry must change. We have the power to make a difference, and we have the responsibility to use it.',
                            'attribution' => 'Stella McCartney'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Street Style Chronicles: New York\'s Fashion Week Finale',
                'slug' => 'nyfw-street-style',
                'tags' => ['featured', 'street-style', 'new-york-fashion-week', 'exclusive'],
                'categories' => ['Fashion', 'Street Style'],
                'custom_fields' => [
                    'author_name' => 'Marcus Chen',
                    'author_bio' => 'Marcus is a street style photographer and fashion commentator.',
                    'read_time' => 5,
                    'excerpt' => 'The most daring, innovative, and unforgettable looks from outside the shows.',
                    'fashion_week' => 'newyork'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1509631179647-0177331693ae?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'New York Street Style',
                            'caption' => 'Street style outside the Marc Jacobs show',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'While the runway shows captivate audiences inside the venues, the real fashion action often happens on the streets. New York Fashion Week\'s sidewalks transform into impromptu catwalks where influencers, editors, and fashion enthusiasts showcase their most daring looks.',
                                'This season, street style reached new heights of creativity, with attendees mixing high fashion with vintage finds, luxury labels with independent designers, creating looks that were uniquely personal and undeniably stylish.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Bold Layering',
                                    'image' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Street style layering',
                                    'description' => 'Master class in texture and proportion'
                                ],
                                [
                                    'title' => 'Color Blocking',
                                    'image' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Color blocking street style',
                                    'description' => 'Fearless color combinations'
                                ],
                                [
                                    'title' => 'Vintage Revival',
                                    'image' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Vintage fashion street style',
                                    'description' => '90s meets contemporary'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Key Street Style Trends',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Oversized blazers paired with cycling shorts',
                                'Statement sunglasses as the ultimate accessory',
                                'Vintage band tees mixed with designer pieces',
                                'Bold animal prints making a comeback',
                                'Platform shoes adding height and attitude',
                                'Bucket hats styled with elegant outfits'
                            ]
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
            'meta_title' => $data['title'] . ' - VOGUE NOIR',
            'meta_description' => $data['custom_fields']['excerpt'] ?? '',
            'site_id' => $this->site->id,
        ]);

        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, $this->site->id);;
            $page->tags(true)->attach($tag->id);
        }

        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, $this->site->id);;
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
            'title' => 'About VOGUE NOIR',
            'page_type' => 'content',
            'slug' => 'about',
            'status' => 'published',
            'meta_title' => 'About Us - VOGUE NOIR',
            'meta_description' => 'Learn about VOGUE NOIR - Your premier source for fashion news, trends, and inspiration.',
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
                    'title' => 'About VOGUE NOIR',
                    'subtitle' => 'Celebrating fashion, creativity, and individuality since 2010',
                    'ctaText' => 'Our Team',
                    'ctaUrl' => '#team',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'VOGUE NOIR is more than a fashion magazine – it\'s a celebration of style, creativity, and the power of self-expression. Founded in 2010, we\'ve been at the forefront of fashion journalism, bringing our readers exclusive access to runway shows, designer interviews, and trend forecasts.',
                        'Our mission is to inspire and empower our readers through thoughtful fashion coverage that goes beyond the surface. We believe fashion is a form of art, a means of communication, and a reflection of our times.',
                        'From sustainable fashion to street style, haute couture to high street, we cover it all with passion, insight, and an unwavering commitment to quality journalism.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Our Impact',
                    'stats' => [
                        ['number' => '10M+', 'label' => 'Monthly Readers', 'icon' => '👥'],
                        ['number' => '500+', 'label' => 'Fashion Shows Covered', 'icon' => '🎭'],
                        ['number' => '15+', 'label' => 'Years of Excellence', 'icon' => '⭐'],
                        ['number' => '200+', 'label' => 'Designer Collaborations', 'icon' => '✨']
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Editorial Team',
                    'subtitle' => 'Meet the experts behind the stories',
                    'level' => 2
                ],
                'order' => 4
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Isabella Rossi',
                    'role' => 'Editor-in-Chief',
                    'bio' => 'Isabella has over 20 years of experience in fashion journalism. She previously worked at Vogue Italia and Harper\'s Bazaar before joining VOGUE NOIR.',
                    'email' => 'isabella@voguenoir.com',
                    'image' => 'https://images.unsplash.com/photo-1494790108755-2616b332e234?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                    'displayType' => 'profile'
                ],
                'order' => 5
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Sophie Laurent',
                    'role' => 'Paris Fashion Correspondent',
                    'bio' => 'Sophie is our Paris correspondent with over 15 years covering haute couture and the French fashion scene.',
                    'email' => 'sophie@voguenoir.com',
                    'image' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                    'displayType' => 'profile'
                ],
                'order' => 6
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Marcus Chen',
                    'role' => 'Street Style Editor',
                    'bio' => 'Marcus is a street style photographer and fashion commentator specializing in youth culture and emerging trends.',
                    'email' => 'marcus@voguenoir.com',
                    'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                    'displayType' => 'profile'
                ],
                'order' => 7
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createContactPage(): void
    {
        $page = Page::create([
            'title' => 'Contact VOGUE NOIR',
            'page_type' => 'content',
            'slug' => 'contact',
            'status' => 'published',
            'meta_title' => 'Contact Us - VOGUE NOIR',
            'meta_description' => 'Get in touch with the VOGUE NOIR editorial team.',
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
                    'title' => 'Get In Touch',
                    'subtitle' => 'We\'d love to hear from you',
                    'showSearch' => false
                ],
                'order' => 1
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'VOGUE NOIR Editorial',
                    'role' => 'Contact Information',
                    'email' => 'editorial@voguenoir.com',
                    'phone' => '+44 20 7946 0958',
                    'address' => '10 Vogue Street\nLondon, W1F 8GQ\n\nOffice Hours:\nMon-Fri: 9AM-6PM',
                    'displayType' => 'contact'
                ],
                'order' => 2
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Send Us a Message',
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
}