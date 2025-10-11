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

class HomeGardenSeeder extends Seeder
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
            'name' => 'Haven & Hearth - Home & Garden',
            'slug' => 'haven-hearth',
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
            'featured', 'trending', 'seasonal', 'budget-friendly', 'luxury',
            'interior-design', 'home-decor', 'furniture', 'lighting',
            'bedroom', 'living-room', 'kitchen', 'bathroom', 'outdoor',
            'garden', 'landscaping', 'plants', 'gardening-tips',
            'diy', 'renovation', 'before-after', 'makeover',
            'minimalist', 'modern', 'rustic', 'scandinavian', 'bohemian',
            'storage-solutions', 'small-spaces', 'organization',
            'sustainable', 'eco-friendly', 'upcycling',
            'color-schemes', 'textures', 'patterns',
            'spring', 'summer', 'fall', 'winter',
            'product-review', 'buying-guide', 'comparison', 'deals'
        ];

        foreach ($tags as $tagName) {
            $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
        }
    }

    private function createCategories(): void
    {
        $categories = [
            'Interior Design' => [
                'Living Spaces' => ['Living Room', 'Bedroom', 'Kitchen', 'Bathroom'],
                'Design Styles' => ['Modern', 'Rustic', 'Scandinavian', 'Bohemian', 'Minimalist'],
                'Elements' => ['Color', 'Lighting', 'Textures', 'Patterns']
            ],
            'Furniture & Decor' => [
                'Furniture' => ['Sofas', 'Tables', 'Chairs', 'Storage', 'Beds'],
                'Decor' => ['Wall Art', 'Textiles', 'Accessories', 'Plants'],
                'Lighting' => ['Ceiling', 'Floor Lamps', 'Table Lamps', 'Outdoor']
            ],
            'Garden & Outdoor' => [
                'Gardening' => ['Vegetables', 'Flowers', 'Herbs', 'Trees'],
                'Outdoor Living' => ['Patios', 'Decking', 'Furniture', 'BBQ'],
                'Landscaping' => ['Design', 'Maintenance', 'Water Features']
            ],
            'DIY & Projects' => ['Renovations', 'Upcycling', 'Crafts', 'Tutorials'],
            'Product Reviews' => ['Furniture', 'Tools', 'Appliances', 'Decor'],
            'Buying Guides' => ['Seasonal', 'Budget', 'Luxury', 'Essentials']
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
            ['key' => 'difficulty_level', 'name' => 'Project Difficulty', 'type' => 'select', 'options' => '{"beginner":"Beginner","intermediate":"Intermediate","advanced":"Advanced","expert":"Expert"}'],
            ['key' => 'project_cost', 'name' => 'Estimated Cost', 'type' => 'text'],
            ['key' => 'project_time', 'name' => 'Time Required', 'type' => 'text'],
            ['key' => 'room_type', 'name' => 'Room Type', 'type' => 'select', 'options' => '{"living":"Living Room","bedroom":"Bedroom","kitchen":"Kitchen","bathroom":"Bathroom","outdoor":"Outdoor"}'],
            ['key' => 'style', 'name' => 'Design Style', 'type' => 'text'],
            ['key' => 'excerpt', 'name' => 'Article Excerpt', 'type' => 'textarea'],
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
            'title' => 'Haven & Hearth - Transform Your Living Spaces',
            'page_type' => 'content',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'Haven & Hearth - Home & Garden Design Inspiration',
            'meta_description' => 'Discover inspiring home decor ideas, expert gardening tips, product reviews, and DIY projects to transform your living spaces.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Home',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        $featuredTag = $this->tagRepository->findOrCreateByName('featured', $this->site->id);
        $page->tags(true)->attach($featuredTag->id);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'About Haven & Hearth',
                    'subtitle' => 'Inspiring beautiful living spaces since 2010',
                    'ctaText' => 'Meet Our Team',
                    'ctaUrl' => '#team',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1556912173-3bb406ef7e77?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Haven & Hearth was born from a simple belief: everyone deserves a beautiful, functional home that reflects their personality and supports their lifestyle. Whether you\'re redesigning a single room or transforming an entire garden, we\'re here to guide you through every step.',
                        'Our team of interior designers, landscape architects, and home improvement experts brings decades of combined experience. We test products, interview designers, visit showrooms, and dig in the dirt ourselves—all to provide you with honest, practical advice you can trust.',
                        'From budget-friendly DIY projects to luxury renovations, from tiny urban balconies to sprawling country gardens, we cover it all. Our mission is to make beautiful, comfortable living accessible to everyone.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Our Reach',
                    'stats' => [
                        ['number' => '500K+', 'label' => 'Monthly Readers', 'icon' => '📖'],
                        ['number' => '1,200+', 'label' => 'Articles Published', 'icon' => '✍️'],
                        ['number' => '150+', 'label' => 'Expert Contributors', 'icon' => '👥'],
                        ['number' => '50K+', 'label' => 'Products Reviewed', 'icon' => '⭐']
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Team',
                    'subtitle' => 'Meet the experts behind Haven & Hearth',
                    'level' => 2
                ],
                'order' => 4
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Emma Nordström',
                    'role' => 'Lead Interior Designer',
                    'bio' => 'Emma specializes in Scandinavian and minimalist design. With 15 years of experience, she helps readers create serene, functional living spaces.',
                    'email' => 'emma@havenhearth.com',
                    'image' => 'https://images.unsplash.com/photo-1494790108755-2616b332e234?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                    'displayType' => 'profile'
                ],
                'order' => 5
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Sarah Green',
                    'role' => 'Landscape Designer & Horticulturist',
                    'bio' => 'Sarah brings expertise in sustainable gardening and outdoor living spaces. She\'s passionate about helping people grow beautiful, eco-friendly gardens.',
                    'email' => 'sarah@havenhearth.com',
                    'image' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                    'displayType' => 'profile'
                ],
                'order' => 6
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'James Chen',
                    'role' => 'Smart Home Technology Editor',
                    'bio' => 'James tests and reviews the latest smart home products, from lighting systems to security cameras, helping readers navigate the connected home revolution.',
                    'email' => 'james@havenhearth.com',
                    'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                    'displayType' => 'profile'
                ],
                'order' => 7
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Values',
                    'level' => 2
                ],
                'order' => 8
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Honesty: We only recommend products we\'ve tested and truly believe in',
                        'Accessibility: Beautiful design shouldn\'t be just for the wealthy',
                        'Sustainability: We prioritize eco-friendly solutions whenever possible',
                        'Practicality: Our advice works in real homes, not just photo shoots',
                        'Community: We learn from our readers and value their experiences'
                    ]
                ],
                'order' => 9
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'Your home should tell the story of who you are, and be a collection of what you love.',
                    'attribution' => 'Nate Berkus'
                ],
                'order' => 10
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createContactPage(): void
    {
        $page = Page::create([
            'title' => 'Contact Haven & Hearth',
            'page_type' => 'content',
            'slug' => 'contact',
            'status' => 'published',
            'meta_title' => 'Contact Us - Haven & Hearth',
            'meta_description' => 'Get in touch with the Haven & Hearth team. We\'d love to hear from you!',
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
                    'subtitle' => 'Questions, collaboration ideas, or just want to say hello? We\'d love to hear from you',
                    'showSearch' => false
                ],
                'order' => 1
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Haven & Hearth Editorial',
                    'role' => 'Contact Information',
                    'email' => 'hello@havenhearth.com',
                    'phone' => '+44 20 3384 5678',
                    'address' => 'Haven & Hearth Magazine
123 Design Quarter
London, SW1A 1AA

Office Hours:
Monday-Friday: 9:00 AM - 5:30 PM
Weekend: Closed',
                    'displayType' => 'contact'
                ],
                'order' => 2
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'We welcome contributions from talented designers, gardeners, and home improvement enthusiasts. If you have expertise to share and a passion for great writing, we\'d love to hear your pitch. Send your ideas to editorial@havenhearth.com.',
                        'For product review inquiries and brand partnerships, please contact partnerships@havenhearth.com.',
                        'Technical support for the website can be reached at support@havenhearth.com.'
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Send Us a Message',
                    'subtitle' => 'Fill out the form below and we\'ll get back to you within 2 business days',
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
            ],
            [
                'type' => 'info',
                'data' => [
                    'infoType' => 'note',
                    'description' => 'Follow us on Instagram @havenhearth for daily design inspiration, behind-the-scenes content, and community features!'
                ],
                'order' => 5
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
                'title' => 'The Ultimate Guide to Scandinavian Interior Design',
                'slug' => 'scandinavian-interior-design-guide',
                'tags' => ['featured', 'interior-design', 'scandinavian', 'minimalist', 'living-room'],
                'categories' => ['Interior Design', 'Design Styles', 'Scandinavian'],
                'custom_fields' => [
                    'author_name' => 'Emma Nordström',
                    'author_bio' => 'Emma is a Swedish interior designer specializing in Nordic minimalism with over 15 years of experience.',
                    'read_time' => 10,
                    'excerpt' => 'Discover the principles of minimalist Nordic design and how to create a serene, functional living space with clean lines and natural materials.',
                    'style' => 'Scandinavian, Minimalist'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Scandinavian Living Room with Natural Light',
                            'caption' => 'A classic Scandinavian living room featuring light wood, neutral tones, and abundant natural light',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Scandinavian interior design has captivated homeowners worldwide with its perfect balance of form and function. Rooted in the Nordic principle of "lagom" (just the right amount), this design philosophy creates spaces that are both beautiful and livable.',
                                'The long, dark winters of Northern Europe shaped this aesthetic. Scandinavian design maximizes natural light, uses pale color palettes to create brightness, and incorporates warm textures to combat the cold. The result is a style that feels both minimalist and cozy—a paradox that perfectly captures hygge.',
                                'In this comprehensive guide, we\'ll explore the key principles of Scandinavian design and show you how to transform your home into a Nordic sanctuary.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Core Principles of Scandinavian Design',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Functionality First: Every piece should serve a purpose while being aesthetically pleasing',
                                'Natural Light: Maximize windows and use sheer curtains or no window treatments',
                                'Neutral Color Palette: Whites, grays, and beiges create a calm foundation',
                                'Natural Materials: Wood, leather, wool, and linen bring warmth and texture',
                                'Decluttered Spaces: "Less is more" with thoughtful curation',
                                'Quality Over Quantity: Invest in well-made pieces that will last decades'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Essential Furniture for Scandinavian Interiors',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Nordic Oak Dining Table',
                            'brand' => 'Muuto',
                            'productName' => 'Nordic Oak Dining Table',
                            'image' => 'https://images.unsplash.com/photo-1617098900591-3f90928e8c54?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'price' => 1299,
                            'salePrice' => 0,
                            'currency' => '£',
                            'description' => 'Handcrafted solid oak dining table with clean lines and subtle organic curves. Seats 6-8 comfortably. Sustainable forestry certified.',
                            'link' => 'https://example.com/nordic-oak-table',
                            'linkText' => 'View Product',
                            'displayAs' => 'button',
                            'layout' => 'standard',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 4.8,
                                'pros' => [
                                    'Beautiful natural wood grain',
                                    'Extremely sturdy construction',
                                    'Easy to clean and maintain',
                                    'Timeless design'
                                ],
                                'cons' => [
                                    'Premium price point',
                                    'Requires assembly'
                                ]
                            ],
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The foundation of Scandinavian design is furniture that combines beauty with practicality. Look for pieces with clean lines, organic shapes, and natural materials. Iconic Scandinavian furniture—from Arne Jacobsen\'s Egg Chair to Hans Wegner\'s Wishbone Chair—demonstrates this principle perfectly.',
                                'When selecting furniture, prioritize quality construction and timeless design over trendy pieces. Scandinavian homes often feature the same furniture for decades, with pieces becoming more beautiful as they age and develop character.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Color Palette: Beyond White',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'While white is the foundation of Scandinavian interiors, the palette extends to soft grays, warm beiges, and muted pastels. These colors create a serene backdrop that makes rooms feel larger and brighter.',
                                'Accent colors appear sparingly—a dusty blue throw pillow, sage green pottery, or terracotta planters. These touches of color reference nature without overwhelming the space. Black is used strategically in light fixtures, window frames, and furniture legs to add contrast and definition.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'carousel',
                            'slides' => [
                                [
                                    'title' => 'White & Natural Wood',
                                    'image' => 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                                    'alt' => 'White walls with natural wood furniture',
                                    'description' => 'Classic combination creating bright, airy spaces'
                                ],
                                [
                                    'title' => 'Soft Gray Accents',
                                    'image' => 'https://images.unsplash.com/photo-1615873968403-89e068629265?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                                    'alt' => 'Gray textiles in Scandinavian room',
                                    'description' => 'Layered grays add depth without darkness'
                                ],
                                [
                                    'title' => 'Muted Pastels',
                                    'image' => 'https://images.unsplash.com/photo-1616594266537-7b05b1f7729e?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                                    'alt' => 'Pastel accents in Nordic interior',
                                    'description' => 'Soft blues and greens bring subtle color'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Lighting: The Soul of Scandinavian Design',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'In regions where winter daylight lasts only a few hours, lighting design becomes crucial. Scandinavian homes layer multiple light sources at different heights to create warm, inviting spaces even in the darkest months.',
                                'Natural light is maximized through large windows with minimal or no window treatments. When privacy is needed, sheer curtains diffuse light rather than block it. Artificial lighting includes ambient ceiling fixtures, task lighting for reading and cooking, and accent lighting to create atmosphere.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Popular Scandinavian Pendant Lights Compared',
                            'productA' => 'Louis Poulsen PH5',
                            'productB' => 'Muuto E27',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Price',
                                    'items' => [
                                        ['value' => '£495'],
                                        ['value' => '£185']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Design',
                                    'items' => [
                                        ['value' => 'Iconic multi-shade system'],
                                        ['value' => 'Simple dome shape']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Light Quality',
                                    'items' => [
                                        ['value' => 'Glare-free, diffused'],
                                        ['value' => 'Direct with some diffusion']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Dining tables, statement piece'],
                                        ['value' => 'Clustered groups, budget-conscious']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Materials',
                                    'items' => [
                                        ['value' => 'Aluminum, copper'],
                                        ['value' => 'Silicone rubber']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Textures and Textiles',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'To prevent minimalist spaces from feeling cold, Scandinavian design relies heavily on texture. Layer different materials—smooth leather, chunky knits, soft wool, rough linen, and natural wood grain—to create visual interest and tactile warmth.',
                                'Textiles play a crucial role in adding coziness. Think chunky throw blankets, sheepskin rugs, linen curtains, and wool cushions. These elements invite touch and create that essential hygge feeling that makes Scandinavian homes so inviting.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Pro Tip: The 70/20/10 Rule',
                            'paragraphs' => [
                                'Interior designers use the 70/20/10 rule for balanced spaces: 70% should be your dominant neutral color (usually white or light gray), 20% should be secondary colors (natural wood tones, soft grays), and 10% should be accent colors (blacks, muted colors, or natural greens from plants).',
                                'This creates visual harmony while preventing monotony. Apply this rule to walls, furniture, and accessories for a professionally designed look.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Bringing Nature Indoors',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Scandinavian design embraces biophilic design—the human need to connect with nature. This manifests in several ways: abundant houseplants, natural materials like wood and stone, nature-inspired artwork, and organic shapes in furniture and decor.',
                                'Even in urban apartments, Scandinavians bring the outdoors in through branches in vases, pine cones in bowls, and fresh flowers on tables. This connection to nature is essential to the style\'s calming effect.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Start with a neutral base: Paint walls white or light gray and choose natural wood flooring',
                                'Invest in quality furniture: Select timeless pieces in natural materials',
                                'Layer lighting: Install ambient, task, and accent lighting throughout',
                                'Add texture through textiles: Layer throws, cushions, and rugs in natural fabrics',
                                'Incorporate plants: Start with easy-care varieties like pothos or snake plants',
                                'Declutter ruthlessly: Keep only items that are beautiful or functional',
                                'Add personal touches: Display a few meaningful items rather than many accessories'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Scandinavian design is not about creating a perfect showroom. It\'s about creating a functional, beautiful space that supports your daily life and brings you peace.',
                            'attribution' => 'Ilse Crawford, Designer'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Common Mistakes to Avoid',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Don\'t make your space too sterile. Scandinavian design should feel warm and lived-in, not like a minimalist gallery. Add personal items, books, and touches that reflect your personality.'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The most common mistake is equating Scandinavian design with stark minimalism. While the aesthetic is pared down, it should never feel cold or uncomfortable. The goal is "lagom"—just enough, but not too little.',
                                'Another pitfall is ignoring scale. Scandinavian furniture often has clean lines and appears delicate, but pieces should still be appropriately sized for your space. A tiny sofa in a large room looks lost; an oversized table in a small room feels overwhelming.',
                                'Finally, don\'t forget that Scandinavian homes are meant to be lived in. Don\'t be afraid to add personal touches, family photos, or items that don\'t fit the aesthetic perfectly. Authenticity matters more than adhering rigidly to rules.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Best Smart Home Lighting Systems: 2025 Comparison',
                'slug' => 'smart-lighting-comparison-2025',
                'tags' => ['featured', 'product-review', 'comparison', 'lighting', 'smart-home'],
                'categories' => ['Product Reviews', 'Lighting'],
                'custom_fields' => [
                    'author_name' => 'James Chen',
                    'author_bio' => 'James is a smart home technology expert who tests and reviews the latest home automation products.',
                    'read_time' => 12,
                    'excerpt' => 'We tested the top smart lighting systems to find the perfect balance of features, ease of use, and value for your home.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Modern Smart Home Lighting Setup',
                            'caption' => 'Smart lighting systems offer convenience, ambiance, and energy savings',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Smart lighting has evolved from a luxury to a practical upgrade that improves daily life. Modern systems offer voice control, automation, mood lighting, and significant energy savings. But with dozens of options on the market, choosing the right system can be overwhelming.',
                                'We spent three months testing the leading smart lighting systems in real homes. We evaluated ease of installation, app functionality, smart home integration, light quality, reliability, and overall value. Here\'s what we found.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Our Testing Methodology',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Installation: How easy is setup for beginners?',
                                'App Experience: Is the interface intuitive and responsive?',
                                'Light Quality: Color accuracy, brightness range, and consistency',
                                'Smart Features: Automation, scheduling, and routines',
                                'Integration: Compatibility with Alexa, Google Home, Apple HomeKit',
                                'Reliability: Connection stability and responsiveness',
                                'Value: Features and quality relative to price'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Winner: Philips Hue',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'award',
                        'data' => [
                            'subcategory' => 'Best Overall Smart Lighting',
                            'productName' => 'Philips Hue White & Color Ambiance',
                            'image' => 'https://images.unsplash.com/photo-1585260713696-9d0a4a5c66ba?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Philips Hue Bulbs and Bridge',
                            'winner' => true,
                            'rating' => 4.7,
                            'strapline' => 'The gold standard for smart lighting with unmatched features and reliability'
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Philips Hue White & Color Ambiance Starter Kit',
                            'subtitle' => 'Premium smart lighting with extensive ecosystem',
                            'image' => 'https://images.unsplash.com/photo-1585260713696-9d0a4a5c66ba?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'url' => 'https://example.com/philips-hue-starter',
                            'linkText' => 'Check Latest Price',
                            'displayAs' => 'button',
                            'specs' => [
                                ['text' => 'Lumens', 'value' => '800 per bulb'],
                                ['text' => 'Colors', 'value' => '16 million'],
                                ['text' => 'Connectivity', 'value' => 'Zigbee (Bridge required)'],
                                ['text' => 'Hub Required', 'value' => 'Yes (included in starter kit)'],
                                ['text' => 'Voice Control', 'value' => 'Alexa, Google, Siri'],
                                ['text' => 'Lifespan', 'value' => '25,000 hours']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Exceptional color accuracy and brightness',
                                'Rock-solid reliability and fast response',
                                'Extensive third-party integration',
                                'Huge ecosystem of compatible products',
                                'Advanced features like entertainment sync',
                                'Local control doesn\'t require internet'
                            ],
                            'cons' => [
                                'Premium pricing',
                                'Requires hub (additional cost)',
                                'Bulbs are larger than standard'
                            ],
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Philips Hue remains the king of smart lighting for good reason. The system is incredibly reliable, with instant response times and rock-solid connections. The color reproduction is outstanding—whites are crisp and true, while colors are vibrant without being oversaturated.',
                                'The Hue ecosystem is vast, including not just bulbs but light strips, outdoor lighting, table lamps, and even sync boxes that match lighting to your TV content. The app is intuitive, and advanced features like dynamic scenes and automation are powerful without being overwhelming.',
                                'The main drawback is cost. Hue products command a premium, and you need the Bridge hub to unlock full functionality. However, if budget allows, Hue delivers the best overall experience.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Best Budget Option: Wyze Bulb Color',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => 'Limited Time Offer',
                            'productName' => 'Wyze Bulb Color 4-Pack',
                            'brand' => 'Wyze',
                            'image' => 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'price' => 39.99,
                            'salePrice' => 29.99,
                            'currency' => '£',
                            'description' => 'Impressive features at an unbeatable price. No hub required—connects directly to Wi-Fi. Great for budget-conscious smart home beginners.',
                            'link' => 'https://example.com/wyze-bulb-deal',
                            'showDealButton' => true,
                            'starBlock' => true,
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'For those on a budget, Wyze offers remarkable value. At less than half the price of Hue, Wyze bulbs deliver solid performance with no hub required. They connect directly to your Wi-Fi, making setup incredibly simple.',
                                'Color quality doesn\'t match Hue\'s precision, but it\'s more than adequate for most uses. The app is straightforward, and integration with Alexa and Google Assistant works well. Where Wyze falls short is reliability—occasional connection drops and slower response times can be frustrating.',
                                'Still, for the price, Wyze is hard to beat. It\'s an excellent entry point into smart lighting, especially for renters or anyone wanting to test the waters before committing to a premium system.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Detailed Comparison',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Top Smart Lighting Systems Compared',
                            'productA' => 'Philips Hue',
                            'productB' => 'LIFX',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Price per Bulb',
                                    'items' => [
                                        ['value' => '£45-50'],
                                        ['value' => '£40-45']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Hub Required',
                                    'items' => [
                                        ['value' => 'Yes (£45)'],
                                        ['value' => 'No']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Brightness',
                                    'items' => [
                                        ['value' => '800 lumens'],
                                        ['value' => '1100 lumens']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Color Quality',
                                    'items' => [
                                        ['value' => 'Excellent'],
                                        ['value' => 'Very Good']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Reliability',
                                    'items' => [
                                        ['value' => 'Excellent'],
                                        ['value' => 'Good']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Ecosystem',
                                    'items' => [
                                        ['value' => 'Extensive'],
                                        ['value' => 'Moderate']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Whole-home systems'],
                                        ['value' => 'Simple Wi-Fi setup']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Installation and Setup',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Choose your system based on budget and needs',
                                'Install hub (if required) and connect to router',
                                'Download manufacturer app and create account',
                                'Screw in smart bulbs and power on',
                                'Follow app instructions to add bulbs to system',
                                'Connect to voice assistant (Alexa, Google, or Siri)',
                                'Create rooms and scenes in the app',
                                'Set up automations and schedules'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Money-Saving Tip',
                            'paragraphs' => [
                                'Don\'t replace every bulb at once. Start with your most-used rooms (living room, bedroom) to see if smart lighting fits your lifestyle. Many people find they don\'t need color-changing bulbs everywhere—warm white bulbs cost less and are perfect for bedrooms and hallways.',
                                'Also, watch for seasonal sales. Black Friday, Prime Day, and holiday sales typically offer 20-40% discounts on smart lighting starter kits.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Verdict',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'For most users, Philips Hue remains the best choice if budget allows. The reliability, ecosystem, and feature set justify the premium price. LIFX is a solid alternative for those who want bright bulbs without a hub.',
                                'Budget-conscious buyers should seriously consider Wyze. While not perfect, it delivers impressive functionality at a fraction of the cost. For whole-home systems, the savings quickly add up.',
                                'Whichever system you choose, smart lighting transforms how you interact with your home. Automated scenes, voice control, and energy monitoring make this one of the most worthwhile smart home investments.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Transform Your Outdoor Space: Spring Garden Makeover Ideas',
                'slug' => 'spring-garden-makeover-ideas',
                'tags' => ['featured', 'seasonal', 'spring', 'garden', 'outdoor', 'diy'],
                'categories' => ['Garden & Outdoor', 'Outdoor Living'],
                'custom_fields' => [
                    'author_name' => 'Sarah Green',
                    'author_bio' => 'Sarah is a landscape designer and horticulturist with a passion for sustainable gardening.',
                    'read_time' => 8,
                    'difficulty_level' => 'intermediate',
                    'project_time' => '2-4 weekends',
                    'excerpt' => 'Practical tips and creative ideas to refresh your garden this spring, from planting schedules to hardscaping projects.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1585320806297-9794b3e4eeae?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Beautiful Spring Garden with Blooming Flowers',
                            'caption' => 'A well-planned spring garden provides months of color and enjoyment',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Spring is nature\'s invitation to get outside and reimagine your outdoor space. Whether you have a sprawling backyard or a modest patio, this season offers the perfect opportunity to refresh your garden and create an outdoor retreat.',
                                'This comprehensive guide covers everything from soil preparation to design ideas, helping you plan a garden makeover that fits your budget, time, and skill level. Let\'s transform your outdoor space into a beautiful, functional haven.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Start with a Plan',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Assess your space: Measure dimensions, note sun exposure, and identify problem areas',
                                'Define your goals: Entertaining space? Cut flower garden? Low-maintenance? Vegetable production?',
                                'Set a realistic budget: Allocate funds for plants, hardscaping, and ongoing maintenance',
                                'Create a timeline: Break the project into manageable phases',
                                'Sketch a layout: Even a rough drawing helps visualize the final result'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Take photos of your space at different times of day to understand how sunlight moves through your garden. This is crucial for placing sun-loving and shade-tolerant plants correctly.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Essential Garden Tools',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Quality tools make garden work easier and more enjoyable. While you don\'t need every tool immediately, certain essentials are worth investing in from the start.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Fiskars Ergo Garden Tool Set',
                            'subtitle' => 'Professional-grade tools with ergonomic design',
                            'image' => 'https://images.unsplash.com/photo-1617077288467-3f4e8879603e?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'url' => 'https://example.com/fiskars-garden-tools',
                            'linkText' => 'View Garden Tools',
                            'displayAs' => 'button',
                            'specs' => [
                                ['text' => 'Includes', 'value' => 'Spade, fork, trowel, cultivator, weeder'],
                                ['text' => 'Handle Material', 'value' => 'Softgrip with FiberComp'],
                                ['text' => 'Blade Material', 'value' => 'Hardened stainless steel'],
                                ['text' => 'Warranty', 'value' => 'Lifetime'],
                                ['text' => 'Weight', 'value' => 'Lightweight design']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Ergonomic handles reduce hand fatigue',
                                'Stainless steel resists rust',
                                'Lifetime warranty shows quality',
                                'Bright orange color easy to spot in garden'
                            ],
                            'cons' => [
                                'Higher initial investment',
                                'May be too large for small hands'
                            ],
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Plant Selection for Spring',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Choosing the right plants is crucial for a successful garden. Consider your climate zone, soil type, sun exposure, and maintenance commitment. Mix perennials (return year after year) with annuals (one season) for continuous color.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Plant Type', 'Best For', 'Sun Needs', 'Maintenance'],
                                ['Lavender', 'Borders, fragrance', 'Full sun', 'Low'],
                                ['Hostas', 'Shade gardens', 'Shade to part sun', 'Very low'],
                                ['Roses', 'Focal points, cutting', 'Full sun', 'Medium-high'],
                                ['Daylilies', 'Mass planting, slopes', 'Full to part sun', 'Very low'],
                                ['Hydrangeas', 'Shrub borders, specimens', 'Part sun to shade', 'Low-medium'],
                                ['Salvia', 'Pollinator gardens', 'Full sun', 'Low']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Hardscaping Ideas',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Hardscaping—paths, patios, walls, and structures—provides the bones of your garden. These permanent features define spaces, improve functionality, and add visual interest year-round.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Gravel Pathways',
                                    'description' => 'Budget-friendly and easy to install, gravel paths add texture and define garden routes',
                                    'image' => 'https://images.unsplash.com/photo-1598902108854-10e335adac99?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Garden gravel pathway',
                                    'link' => '/projects/gravel-pathways'
                                ],
                                [
                                    'title' => 'Raised Beds',
                                    'description' => 'Improve drainage, extend season, and make gardening more accessible',
                                    'image' => 'https://images.unsplash.com/photo-1592419044706-39796d40f98c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Garden raised beds',
                                    'link' => '/projects/raised-beds'
                                ],
                                [
                                    'title' => 'Pergolas & Arbors',
                                    'description' => 'Create vertical interest, provide shade, and support climbing plants',
                                    'image' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Garden pergola',
                                    'link' => '/projects/pergolas'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Budget-Friendly Makeover Tips',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Divide existing perennials instead of buying new plants',
                                'Start plants from seed—costs pennies compared to nursery plants',
                                'Use mulch to suppress weeds and give gardens a polished look',
                                'Shop end-of-season sales for deep discounts on plants and tools',
                                'Join local garden clubs for plant swaps and advice',
                                'Repurpose materials: old bricks for edging, pallets for vertical gardens',
                                'Focus on high-impact areas visible from your home first'
                            ]
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => 'Spring Savings',
                            'productName' => 'Premium Garden Soil & Compost Bundle',
                            'brand' => 'Miracle-Gro',
                            'image' => 'https://images.unsplash.com/photo-1615671524827-c1fe3973b648?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'price' => 45.99,
                            'salePrice' => 32.99,
                            'currency' => '£',
                            'description' => 'Everything you need to prep garden beds this spring. Includes 3 bags potting soil, 2 bags compost, and 1 bag perlite for drainage.',
                            'link' => 'https://example.com/soil-bundle-deal',
                            'showDealButton' => true,
                            'starBlock' => false,
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Maintenance Schedule',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Spring Garden Calendar',
                            'paragraphs' => [
                                'Early Spring: Clean up winter debris, cut back dead perennials, test and amend soil, apply pre-emergent weed control',
                                'Mid-Spring: Plant cool-season vegetables, direct sow hardy annuals, divide perennials, apply fresh mulch',
                                'Late Spring: Plant warm-season vegetables and tender annuals after last frost, prune spring-blooming shrubs after flowering, maintain consistent watering schedule'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'A garden requires patient labor and attention. Plants do not grow merely to satisfy ambitions or to fulfill good intentions. They thrive because someone expended effort on them.',
                            'attribution' => 'Liberty Hyde Bailey'
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
            'meta_title' => $data['title'] . ' - Haven & Hearth',
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
            'title' => 'About Haven & Hearth',
            'page_type' => 'content',
            'slug' => 'about',
            'status' => 'published',
            'meta_title' => 'About Us - Haven & Hearth',
            'meta_description' => 'Learn about Haven & Hearth - Your trusted source for home design inspiration and garden expertise.',
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
                    'title' => 'Create Your Perfect Home',
                    'subtitle' => 'Expert advice, inspiring ideas, and curated products for every room and garden',
                    'ctaText' => 'Explore Ideas',
                    'ctaUrl' => '#featured',
                    'secondaryCtaText' => 'Latest Deals',
                    'secondaryCtaUrl' => '#deals',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1556912173-3bb406ef7e77?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'info',
                'data' => [
                    'infoType' => 'info',
                    'description' => '🌟 New Spring Collection: Refresh your home with our curated selection of sustainable furniture and decor →'
                ],
                'order' => 2
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Featured Articles',
                    'subtitle' => 'Inspiring ideas to transform your space',
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
                            'title' => 'The Ultimate Guide to Scandinavian Interior Design',
                            'slug' => 'scandinavian-interior-design-guide',
                            'excerpt' => 'Discover the principles of minimalist Nordic design and how to create a serene, functional living space with clean lines and natural materials.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Scandinavian Living Room'
                            ],
                            'badge' => [
                                'text' => 'Featured',
                                'color' => 'primary'
                            ],
                            'meta' => [
                                'author' => 'Emma Nordström',
                                'date' => 'March 15, 2025',
                                'readTime' => '10 min read'
                            ]
                        ],
                        [
                            'title' => 'Best Smart Home Lighting Systems: 2025 Comparison',
                            'slug' => 'smart-lighting-comparison-2025',
                            'excerpt' => 'We tested the top smart lighting systems to find the perfect balance of features, ease of use, and value for your home.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Smart Home Lighting'
                            ],
                            'badge' => [
                                'text' => 'Product Review',
                                'color' => 'success'
                            ],
                            'meta' => [
                                'author' => 'James Chen',
                                'date' => 'March 14, 2025',
                                'readTime' => '12 min read'
                            ]
                        ],
                        [
                            'title' => 'Transform Your Outdoor Space: Spring Garden Makeover Ideas',
                            'slug' => 'spring-garden-makeover-ideas',
                            'excerpt' => 'Practical tips and creative ideas to refresh your garden this spring, from planting schedules to hardscaping projects.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1585320806297-9794b3e4eeae?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Spring Garden'
                            ],
                            'badge' => [
                                'text' => 'Seasonal',
                                'color' => 'warning'
                            ],
                            'meta' => [
                                'author' => 'Sarah Green',
                                'date' => 'March 13, 2025',
                                'readTime' => '8 min read'
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
                'type' => 'heading',
                'data' => [
                    'text' => 'Popular Design Styles',
                    'subtitle' => 'Find your aesthetic',
                    'level' => 2
                ],
                'order' => 6
            ],
            [
                'type' => 'gallery',
                'data' => [
                    'layout' => 'grid',
                    'slides' => [
                        [
                            'title' => 'Modern Minimalist',
                            'description' => 'Clean lines, neutral colors, functional beauty',
                            'image' => 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Modern Minimalist Interior',
                            'link' => '/style/modern-minimalist'
                        ],
                        [
                            'title' => 'Rustic Farmhouse',
                            'description' => 'Warm woods, vintage charm, cozy comfort',
                            'image' => 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Rustic Farmhouse Interior',
                            'link' => '/style/rustic-farmhouse'
                        ],
                        [
                            'title' => 'Bohemian Eclectic',
                            'description' => 'Vibrant colors, mixed patterns, global influences',
                            'image' => 'https://images.unsplash.com/photo-1616594266537-7b05b1f7729e?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'alt' => 'Bohemian Interior',
                            'link' => '/style/bohemian'
                        ]
                    ]
                ],
                'order' => 7
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Join Our Community',
                    'stats' => [
                        ['number' => '500K+', 'label' => 'Monthly Readers', 'icon' => '📖'],
                        ['number' => '1,200+', 'label' => 'Design Articles', 'icon' => '✨'],
                        ['number' => '150+', 'label' => 'Expert Contributors', 'icon' => '👥'],
                        ['number' => '50K+', 'label' => 'Products Reviewed', 'icon' => '⭐']
                    ]
                ],
                'order' => 8
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'Home is not a place, it\'s a feeling. Every choice you make in design should reflect who you are and how you want to live.',
                    'attribution' => 'Kelly Wearstler, Interior Designer'
                ],
                'order' => 9
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Get Our Weekly Newsletter',
                    'subtitle' => 'Design inspiration, product deals, and DIY tips delivered every Friday',
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
}