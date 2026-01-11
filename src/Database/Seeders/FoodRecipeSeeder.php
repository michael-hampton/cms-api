<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\Block;
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

class FoodRecipeSeeder extends Seeder
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
//        $this->createSite();
//        $this->createMenu();
//        $this->createTags();
//        $this->createCategories();
//        $this->createCustomFields();
//        $this->createHomepage();
//        $this->createComprehensiveArticle(); // Uses ALL blocks
//        $this->createAboutPage();
//        $this->createContactPage();
//        $this->createArticle2(); // Mexican Street Tacos
//        $this->createArticle3(); // Asian Noodle Bowls
//        $this->createArticle4(); // Perfect Chocolate Cake
//        $this->createArticle5(); // Mediterranean Diet Guide
//        $this->createArticle6(); // Kitchen Knife Buying Guide
//        $this->attachArticlesToHomepage();
    }

    public function attachArticlesToHomepage(): void
    {
        $site = Site::where('slug', 'taste-table')->first();

        if (!$site) {
            echo "Site 'taste-table' not found. Run FoodRecipeSeeder first.\n";
            return;
        }

        $homepage = Page::where('slug', 'home')
            ->where('site_id', $site->id)
            ->first();

        if (!$homepage) {
            echo "Homepage not found.\n";
            return;
        }

        // Get all published articles except homepage
        $articles = Page::where('site_id', $site->id)
            ->where('status', 'published')
            ->where('slug', '!=', 'home')
            ->where('slug', '!=', 'about')
            ->where('slug', '!=', 'contact')
            ->get();

        if ($articles->isEmpty()) {
            echo "No articles found to display.\n";
            return;
        }

        // Build page grid data
        $pageGridData = [
            'title' => 'Latest Recipes & Guides',
            'subtitle' => 'Discover our most popular content',
            'layout' => 'grid',
            'columns' => 3,
            'showExcerpt' => true,
            'showImage' => true,
            'pages' => []
        ];

        foreach ($articles as $article) {
            // Get custom fields for excerpt
            $excerpt = '';
            $customFields = $article->customFields ?? [];
            foreach ($customFields as $field) {
                if ($field->customFieldDefinition && $field->customFieldDefinition->key === 'excerpt') {
                    $excerpt = $field->field_value;
                    break;
                }
            }

            // Get first image block or use placeholder
            $image = 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80';
            $blocks = $article->blocks ?? [];
            foreach ($blocks as $block) {
                $blockData = $block->data;
                if ($block->type === 'hero' && !empty($blockData['backgroundImage'])) {
                    $image = $blockData['backgroundImage'];
                    break;
                } elseif ($block->type === 'image' && !empty($blockData['src'])) {
                    $image = $blockData['src'];
                    break;
                }
            }

            // Determine badge based on tags
            $badge = ['text' => 'Recipe', 'color' => 'primary'];
            $tags = $article->tags ?? [];
            foreach ($tags as $tag) {
                if ($tag->name === 'featured') {
                    $badge = ['text' => 'Featured', 'color' => 'success'];
                    break;
                } elseif ($tag->name === 'trending') {
                    $badge = ['text' => 'Trending', 'color' => 'warning'];
                    break;
                }
            }

            $pageGridData['pages'][] = [
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $excerpt ?: substr(strip_tags($article->meta_description ?? ''), 0, 150) . '...',
                'image' => [
                    'src' => $image,
                    'alt' => $article->title
                ],
                'badge' => $badge
            ];
        }

        // Get max order from existing blocks
        $maxOrder = 2;

        // Create page grid block
        Block::create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode($pageGridData),
            'order' => $maxOrder + 1
        ]);

        echo "Successfully added article grid to homepage with " . count($pageGridData['pages']) . " articles.\n";
    }

    private function createSite(): void
    {
        $this->site = Site::create([
            'name' => 'Taste & Table - Food Magazine',
            'slug' => 'taste-table',
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
            'featured', 'trending', 'seasonal', 'quick-easy', 'healthy',
            'comfort-food', 'vegetarian', 'vegan', 'gluten-free', 'keto',
            'italian', 'mexican', 'asian', 'mediterranean', 'american',
            'breakfast', 'lunch', 'dinner', 'dessert', 'snacks',
            'baking', 'grilling', 'slow-cooker', 'instant-pot',
            'meal-prep', 'budget-friendly', 'family-friendly',
            'recipe', 'cooking-tips', 'product-review', 'kitchen-gear'
        ];

        foreach ($tags as $tagName) {
            $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
        }
    }

    private function createCategories(): void
    {
        $categories = [
            'Recipes' => [
                'By Meal' => ['Breakfast', 'Lunch', 'Dinner', 'Dessert', 'Snacks'],
                'By Cuisine' => ['Italian', 'Mexican', 'Asian', 'Mediterranean', 'American'],
                'By Diet' => ['Vegetarian', 'Vegan', 'Gluten-Free', 'Keto', 'Paleo']
            ],
            'Cooking Guides' => ['Techniques', 'Meal Prep', 'Kitchen Tips', 'Ingredient Guides'],
            'Product Reviews' => ['Kitchen Tools', 'Appliances', 'Cookware', 'Ingredients'],
            'Food Events' => ['Cooking Classes', 'Food Festivals', 'Tastings', 'Workshops']
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
            ['key' => 'prep_time', 'name' => 'Prep Time (minutes)', 'type' => 'number'],
            ['key' => 'cook_time', 'name' => 'Cook Time (minutes)', 'type' => 'number'],
            ['key' => 'servings', 'name' => 'Servings', 'type' => 'number'],
            ['key' => 'difficulty', 'name' => 'Difficulty Level', 'type' => 'select', 'options' => '{"easy":"Easy","medium":"Medium","hard":"Hard"}'],
            ['key' => 'cuisine', 'name' => 'Cuisine Type', 'type' => 'text'],
            ['key' => 'dietary', 'name' => 'Dietary Info', 'type' => 'text'],
            ['key' => 'excerpt', 'name' => 'Recipe Excerpt', 'type' => 'textarea'],
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
            'title' => 'Taste & Table - Food & Recipe Magazine',
            'page_type' => 'content',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'Taste & Table - Delicious Recipes & Cooking Guides',
            'meta_description' => 'Discover mouthwatering recipes, expert cooking tips, kitchen product reviews, and food events.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Home',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Cook, Eat, Enjoy',
                    'subtitle' => 'Delicious recipes and cooking inspiration for every meal',
                    'ctaText' => 'Explore Recipes',
                    'ctaUrl' => '#featured',
                    'secondaryCtaText' => 'Cooking Classes',
                    'secondaryCtaUrl' => '/events',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createComprehensiveArticle(): void
    {
        $article = [
            'title' => 'The Ultimate Guide to Italian Cooking: Pasta, Pizza & Everything In Between',
            'slug' => 'ultimate-italian-cooking-guide',
            'tags' => ['featured', 'italian', 'recipe', 'cooking-tips'],
            'categories' => ['Recipes', 'By Cuisine', 'Italian'],
            'custom_fields' => [
                'author_name' => 'Chef Maria Romano',
                'author_bio' => 'Italian cuisine expert with 20 years of culinary experience.',
                'prep_time' => 30,
                'cook_time' => 45,
                'servings' => 6,
                'difficulty' => 'medium',
                'cuisine' => 'Italian',
                'excerpt' => 'Master authentic Italian cooking with this comprehensive guide covering techniques, recipes, and essential tools.'
            ],
            'content' => [
                // 1. HERO
                [
                    'type' => 'hero',
                    'data' => [
                        'title' => 'Master Italian Cooking',
                        'subtitle' => 'From pasta to pizza - learn authentic Italian techniques',
                        'ctaText' => 'Start Cooking',
                        'ctaUrl' => '#recipes',
                        'backgroundImage' => 'https://images.unsplash.com/photo-1556761175-b413da4baf72?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                    ]
                ],
                // 2. TEXT
                [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Italian cuisine is beloved worldwide for its simplicity, fresh ingredients, and incredible flavors. Whether you\'re making pasta from scratch, perfecting your pizza dough, or slow-simmering a ragù, Italian cooking is all about technique and quality ingredients.',
                            'In this comprehensive guide, we\'ll walk you through everything you need to know to cook authentic Italian food at home. From essential techniques to must-have kitchen tools, you\'ll learn the secrets of Italian nonnas passed down through generations.'
                        ]
                    ]
                ],
                // 3. HEADING
                [
                    'type' => 'heading',
                    'data' => [
                        'text' => 'Essential Italian Cooking Techniques',
                        'subtitle' => 'Master these fundamentals',
                        'level' => 2
                    ]
                ],
                // 4. IMAGE
                [
                    'type' => 'image',
                    'data' => [
                        'src' => 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                        'alt' => 'Fresh handmade pasta dough',
                        'caption' => 'Making pasta from scratch is easier than you think',
                        'layout' => 'full',
                        'alignment' => 'fullscreen'
                    ]
                ],
                // 5. LIST
                [
                    'type' => 'list',
                    'data' => [
                        'listType' => 'ul',
                        'items' => [
                            'Al Dente: Cook pasta until firm to the bite, never mushy',
                            'Soffritto: The flavor base of onion, celery, and carrot',
                            'Emulsification: Creating creamy sauces without cream',
                            'Low and Slow: Patience is key for ragùs and braised dishes',
                            'Fresh Herbs: Add at the end to preserve bright flavors'
                        ]
                    ]
                ],
                // 6. INFO
                [
                    'type' => 'info',
                    'data' => [
                        'infoType' => 'tip',
                        'description' => 'Always save some pasta cooking water! The starchy water helps bind sauces to pasta and creates a silky texture.'
                    ]
                ],
                // 7. SECTION
                [
                    'type' => 'section',
                    'data' => [
                        'title' => 'Homemade Pasta Recipes',
                        'headingType' => 'h2',
                        'navigationText' => 'Pasta Recipes',
                        'excludeFromNav' => false
                    ]
                ],
                // 8. SCHEMA (How-To)
                [
                    'type' => 'schema',
                    'data' => [
                        'schemaType' => 'how-to',
                        'title' => 'How to Make Fresh Pasta Dough',
                        'description' => 'Learn the traditional method for making fresh egg pasta.',
                        'image' => 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'
                    ]
                ],
                //9. INFO (ingredients)
                [
                    'type' => 'info',
                    'data' => [
                        'infoType' => 'ingredients',
                        'description' => '2 cups (250g) 00 flour • 3 large eggs • 1/2 tsp salt • 1 tbsp olive oil'
                    ]
                ],
                // 10. LIST (ordered steps)
                [
                    'type' => 'list',
                    'data' => [
                        'listType' => 'ol',
                        'schemaType' => 'steps',
                        'items' => [
                            'Mound flour on a clean surface and make a well in the center',
                            'Crack eggs into the well and add salt and olive oil',
                            'Using a fork, gradually incorporate flour into eggs',
                            'Knead dough for 10 minutes until smooth and elastic',
                            'Wrap in plastic and rest for 30 minutes at room temperature',
                            'Roll out and cut into your desired pasta shape'
                        ]
                    ]
                ],
                // 11. NOTE/BOXOUT
                [
                    'type' => 'note',
                    'data' => [
                        'title' => 'Pro Chef Tip',
                        'paragraphs' => [
                            'The dough should be firm but pliable. If it\'s too dry, add water one teaspoon at a time. If too wet, add more flour.',
                            'Resting the dough is crucial - it allows the gluten to relax, making it easier to roll thin.'
                        ],
                        'alignment' => 'fullscreen'
                    ]
                ],
                // 12. GALLERY
                [
                    'type' => 'gallery',
                    'data' => [
                        'layout' => 'carousel',
                        'slides' => [
                            [
                                'title' => 'Fettuccine',
                                'description' => 'Classic flat ribbons, perfect with creamy sauces',
                                'image' => 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                                'alt' => 'Fresh fettuccine pasta'
                            ],
                            [
                                'title' => 'Pappardelle',
                                'description' => 'Wide ribbons ideal for hearty meat ragùs',
                                'image' => 'https://images.unsplash.com/photo-1621996346886-2638608ed6fa?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                                'alt' => 'Pappardelle pasta'
                            ],
                            [
                                'title' => 'Tagliatelle',
                                'description' => 'Traditional Bolognese pasta shape',
                                'image' => 'https://images.unsplash.com/photo-1622973536968-3ead9e780960?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                                'alt' => 'Tagliatelle pasta'
                            ]
                        ]
                    ]
                ],
                // 13. HEADING
                [
                    'type' => 'heading',
                    'data' => [
                        'text' => 'Essential Kitchen Tools',
                        'subtitle' => 'Gear up your Italian kitchen',
                        'level' => 2
                    ]
                ],
                // 14. PRODUCT
                [
                    'type' => 'product',
                    'data' => [
                        'name' => 'Marcato Atlas 150 Pasta Machine',
                        'brand' => 'Marcato',
                        'productName' => 'Atlas 150 Pasta Machine',
                        'image' => 'https://images.unsplash.com/photo-1615719413546-198b25453f43?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                        'price' => 89.99,
                        'currency' => '£',
                        'description' => 'The gold standard of home pasta machines. Made in Italy with chrome-plated steel, includes pasta cutter and hand crank.',
                        'link' => 'https://example.com/pasta-machine',
                        'linkText' => 'View Product',
                        'displayAs' => 'button',
                        'layout' => 'standard',
                        'showReviewPanel' => true,
                        'review' => [
                            'rating' => 4.8,
                            'pros' => [
                                'Sturdy construction lasts decades',
                                'Easy to use and clean',
                                'Makes consistent, thin pasta sheets',
                                'Wide range of thickness settings'
                            ],
                            'cons' => [
                                'Takes up counter space',
                                'Manual crank requires some effort'
                            ]
                        ],
                        'noFollow' => false,
                        'sponsored' => true,
                        'openInNewTab' => true
                    ]
                ],
                // 15. BUYING GUIDE
                [
                    'type' => 'buying-guide',
                    'data' => [
                        'title' => 'KitchenAid Stand Mixer with Pasta Attachment',
                        'subtitle' => 'Professional-grade power for home cooks',
                        'image' => 'https://images.unsplash.com/photo-1578916171728-46686eac8d58?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                        'url' => 'https://example.com/kitchenaid',
                        'linkText' => 'Check Price',
                        'displayAs' => 'button',
                        'specs' => [
                            ['text' => 'Motor', 'value' => '325 Watts'],
                            ['text' => 'Bowl Capacity', 'value' => '4.8L'],
                            ['text' => 'Speed Settings', 'value' => '10'],
                            ['text' => 'Attachments', 'value' => 'Pasta roller included'],
                            ['text' => 'Warranty', 'value' => '5 years']
                        ],
                        'showReviewPanel' => true,
                        'pros' => [
                            'Multiple functions beyond pasta making',
                            'Electric operation - no manual cranking',
                            'Produces large batches quickly',
                            'Wide variety of attachments available'
                        ],
                        'cons' => [
                            'Significant investment',
                            'Takes up permanent counter space',
                            'Attachments sold separately'
                        ],
                        'noFollow' => false,
                        'sponsored' => false,
                        'openInNewTab' => true
                    ]
                ],
                // 16. PRODUCT COMPARISON
                [
                    'type' => 'product-comparison',
                    'data' => [
                        'title' => 'Manual vs Electric Pasta Makers',
                        'productA' => 'Marcato Atlas (Manual)',
                        'productB' => 'KitchenAid (Electric)',
                        'comparisons' => [
                            [
                                'subtitle' => 'Price',
                                'items' => [
                                    ['value' => '£90'],
                                    ['value' => '£450+']
                                ]
                            ],
                            [
                                'subtitle' => 'Ease of Use',
                                'items' => [
                                    ['value' => 'Manual effort required'],
                                    ['value' => 'Hands-free operation']
                                ]
                            ],
                            [
                                'subtitle' => 'Batch Size',
                                'items' => [
                                    ['value' => 'Small to medium'],
                                    ['value' => 'Large batches']
                                ]
                            ],
                            [
                                'subtitle' => 'Versatility',
                                'items' => [
                                    ['value' => 'Pasta only'],
                                    ['value' => 'Multiple uses']
                                ]
                            ],
                            [
                                'subtitle' => 'Best For',
                                'items' => [
                                    ['value' => 'Traditional enthusiasts'],
                                    ['value' => 'Frequent bakers']
                                ]
                            ]
                        ]
                    ]
                ],
                // 17. DEAL
                [
                    'type' => 'deal',
                    'data' => [
                        'title' => 'Limited Time Offer',
                        'productName' => 'Complete Italian Cooking Kit',
                        'brand' => 'Taste & Table Exclusive',
                        'image' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                        'price' => 149.99,
                        'salePrice' => 99.99,
                        'currency' => '£',
                        'description' => 'Everything you need to start making authentic Italian food: pasta machine, wooden spoon set, pizza stone, and Italian cookbook.',
                        'link' => 'https://example.com/italian-kit',
                        'showDealButton' => true,
                        'starBlock' => true,
                        'noFollow' => false,
                        'sponsored' => false,
                        'openInNewTab' => true
                    ]
                ],
                // 18. DIVIDER
                [
                    'type' => 'divider',
                    'data' => [
                        'style' => 'solid'
                    ]
                ],
                // 19. HEADING
                [
                    'type' => 'heading',
                    'data' => [
                        'text' => 'Classic Italian Recipes',
                        'subtitle' => 'Must-try dishes',
                        'level' => 2
                    ]
                ],
                // 20. PAGE GRID
                [
                    'type' => 'page_grid',
                    'data' => [
                        'title' => '',
                        'layout' => 'grid',
                        'columns' => 3,
                        'showExcerpt' => true,
                        'showImage' => true,
                        'pages' => [
                            [
                                'title' => 'Authentic Bolognese Ragù',
                                'slug' => 'bolognese-ragu',
                                'excerpt' => 'Slow-simmered meat sauce that\'s the heart of Italian cooking',
                                'image' => [
                                    'src' => 'https://images.unsplash.com/photo-1598866594230-a7c12756260f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Bolognese sauce'
                                ],
                                'badge' => [
                                    'text' => 'Classic',
                                    'color' => 'primary'
                                ]
                            ],
                            [
                                'title' => 'Neapolitan Pizza',
                                'slug' => 'neapolitan-pizza',
                                'excerpt' => 'Master the art of authentic Italian pizza with this recipe',
                                'image' => [
                                    'src' => 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Neapolitan pizza'
                                ],
                                'badge' => [
                                    'text' => 'Popular',
                                    'color' => 'success'
                                ]
                            ],
                            [
                                'title' => 'Tiramisu',
                                'slug' => 'tiramisu-recipe',
                                'excerpt' => 'The classic Italian dessert - coffee, cream, and ladyfingers',
                                'image' => [
                                    'src' => 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Tiramisu dessert'
                                ],
                                'badge' => [
                                    'text' => 'Dessert',
                                    'color' => 'warning'
                                ]
                            ]
                        ]
                    ]
                ],
                // 21. AWARD
                [
                    'type' => 'award',
                    'data' => [
                        'subcategory' => 'Editor\'s Choice',
                        'productName' => 'Best Italian Cookbook 2025',
                        'image' => 'https://images.unsplash.com/photo-1512820790803-83ca734da794?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                        'alt' => 'Italian cookbook',
                        'winner' => true,
                        'rating' => 5.0,
                        'strapline' => 'Comprehensive guide with over 200 authentic recipes',
                        'caption' => 'The Silver Spoon cookbook is the definitive collection of Italian home cooking recipes.'
                    ]
                ],
                // 22. QUOTE
                [
                    'type' => 'quote',
                    'data' => [
                        'text' => 'Life is a combination of magic and pasta.',
                        'attribution' => 'Federico Fellini'
                    ]
                ],
                // 23. TABLE
                [
                    'type' => 'table',
                    'data' => [
                        'hasHeader' => true,
                        'rows' => [
                            ['Pasta Shape', 'Best Sauce Pairing', 'Cooking Time', 'Origin'],
                            ['Spaghetti', 'Carbonara, Aglio e Olio', '8-10 min', 'Southern Italy'],
                            ['Penne', 'Arrabbiata, Vodka', '11-13 min', 'Campania'],
                            ['Fettuccine', 'Alfredo, Bolognese', '8-10 min', 'Rome'],
                            ['Rigatoni', 'Amatriciana', '12-15 min', 'Central Italy'],
                            ['Orecchiette', 'Broccoli Rabe', '12-14 min', 'Puglia']
                        ]
                    ]
                ],
                // 24. STATS
                [
                    'type' => 'stats',
                    'data' => [
                        'title' => 'Italian Cuisine By The Numbers',
                        'stats' => [
                            ['number' => '350+', 'label' => 'Pasta Shapes', 'icon' => '🍝'],
                            ['number' => '20', 'label' => 'Regional Cuisines', 'icon' => '🇮🇹'],
                            ['number' => '2,000+', 'label' => 'Traditional Recipes', 'icon' => '📖'],
                            ['number' => '#1', 'label' => 'World\'s Favorite Cuisine', 'icon' => '⭐']
                        ]
                    ]
                ],
                // 25. TESTIMONIAL
                [
                    'type' => 'testimonial',
                    'data' => [
                        'layout' => 'grid',
                        'testimonials' => [
                            [
                                'text' => 'This guide completely transformed my Italian cooking! The pasta recipes are authentic and the techniques are explained so clearly.',
                                'author' => 'James Mitchell',
                                'role' => 'Home Cook',
                                'rating' => 5,
                                'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'
                            ],
                            [
                                'text' => 'I\'ve been cooking Italian food for years, but I still learned new tricks from this comprehensive guide. Highly recommended!',
                                'author' => 'Sophie Anderson',
                                'role' => 'Food Blogger',
                                'rating' => 5,
                                'image' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'
                            ],
                            [
                                'text' => 'The product recommendations were spot-on. I bought the pasta machine and now make fresh pasta every weekend!',
                                'author' => 'David Chen',
                                'role' => 'Restaurant Chef',
                                'rating' => 5,
                                'image' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'
                            ]
                        ]
                    ]
                ],
                // 26. TEAM
                [
                    'type' => 'team',
                    'data' => [
                        'title' => 'Meet Our Italian Cuisine Experts',
                        'subtitle' => 'Chefs and food writers bringing you authentic Italian recipes',
                        'layout' => 'grid',
                        'members' => [
                            [
                                'name' => 'Chef Maria Romano',
                                'role' => 'Italian Cuisine Specialist',
                                'bio' => 'Born in Tuscany, Maria brings 20 years of culinary expertise and family recipes passed down through generations.',
                                'image' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                                'email' => 'maria@tastetable.com'
                            ],
                            [
                                'name' => 'Chef Antonio Rossi',
                                'role' => 'Pasta Master',
                                'bio' => 'Antonio specializes in handmade pasta and has trained in kitchens across Italy.',
                                'image' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                                'email' => 'antonio@tastetable.com'
                            ]
                        ]
                    ]
                ],
                // 27. PERSON (Contact Display)
                [
                    'type' => 'person',
                    'data' => [
                        'name' => 'Cooking Questions?',
                        'role' => 'Contact Our Culinary Team',
                        'email' => 'recipes@tastetable.com',
                        'phone' => '+44 20 7123 4567',
                        'displayType' => 'contact'
                    ]
                ],
                // 28. EVENT
                [
                    'type' => 'event',
                    'data' => [
                        'title' => 'Authentic Italian Pasta Making Workshop',
                        'description' => 'Join Chef Maria for an immersive 3-hour workshop where you\'ll learn to make fresh pasta from scratch. This hands-on class covers pasta dough basics, rolling techniques, and three classic shapes. Plus, enjoy a family-style lunch featuring the pasta you\'ve made!',
                        'startDate' => '2025-04-15',
                        'startTime' => '10:00 AM',
                        'endTime' => '1:00 PM',
                        'location' => 'Taste & Table Cooking Studio',
                        'address' => '123 Culinary Lane, London SW1A 1AA',
                        'ticketPrice' => 75.00,
                        'currency' => '£',
                        'ticketUrl' => 'https://example.com/pasta-workshop',
                        'capacity' => 12,
                        'organizerName' => 'Taste & Table',
                        'organizerEmail' => 'events@tastetable.com',
                        'category' => 'Cooking Class',
                        'image' => 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                        'showSignupForm' => true
                    ]
                ],
                // 29. SCHEMA (FAQ/Question)
                [
                    'type' => 'schema',
                    'data' => [
                        'schemaType' => 'question',
                        'question' => 'What type of flour is best for making pasta?',
                        'answer' => '00 flour (doppio zero) is the gold standard for pasta making. It\'s finely milled Italian flour that creates smooth, elastic dough.',
                        'expansion' => 'While 00 flour is ideal, you can substitute with all-purpose flour if needed. The texture will be slightly different but still delicious. For whole wheat pasta, use 50/50 whole wheat and 00 flour for best results.'
                    ]
                ],
                // 30. CONTACT FORM
                [
                    'type' => 'contact-form',
                    'data' => [
                        'title' => 'Share Your Italian Cooking Success!',
                        'subtitle' => 'Made one of our recipes? We\'d love to hear about it',
                        'showName' => true,
                        'showEmail' => true,
                        'showPhone' => false,
                        'showSubject' => true,
                        'showMessage' => true,
                        'submitButtonText' => 'Send Your Story',
                        'requireName' => true,
                        'requireEmail' => true,
                        'requireMessage' => true
                    ]
                ],
                // FINAL: TEXT (Conclusion)
                [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Italian cooking is a journey of discovery, filled with regional variations, family traditions, and the joy of sharing meals with loved ones. Whether you\'re making your first batch of fresh pasta or perfecting your nonna\'s ragù recipe, remember that the best Italian food comes from the heart.',
                            'Now that you have the knowledge, techniques, and tools, it\'s time to get cooking. Start with one recipe, master it, and then expand your repertoire. Before long, you\'ll be creating authentic Italian feasts that would make any Italian proud.',
                            'Buon appetito!'
                        ]
                    ]
                ]
            ]
        ];

        $this->createArticle($article);
    }

    private function createArticle(array $data): void
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => $data['title'] . ' - Taste & Table',
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
            'title' => 'About Taste & Table',
            'page_type' => 'content',
            'slug' => 'about',
            'status' => 'published',
            'meta_title' => 'About Us - Taste & Table',
            'meta_description' => 'Learn about Taste & Table - Your source for delicious recipes and cooking inspiration.',
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
                    'title' => 'About Taste & Table',
                    'subtitle' => 'Bringing delicious recipes to your kitchen since 2018',
                    'ctaText' => 'Our Team',
                    'ctaUrl' => '#team',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'Taste & Table was born from a simple idea: everyone should have access to delicious, achievable recipes that bring joy to their daily meals. Whether you\'re a beginner learning to boil water or an experienced home cook looking for new inspiration, we\'re here to guide you.',
                        'Our team of chefs, food writers, and recipe testers work tirelessly to bring you thoroughly tested recipes, honest product reviews, and expert cooking advice. Every recipe is tested multiple times in real home kitchens to ensure success.',
                        'From quick weeknight dinners to impressive dinner party showstoppers, we believe that good food brings people together and creates lasting memories.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Our Impact',
                    'stats' => [
                        ['number' => '2M+', 'label' => 'Monthly Readers', 'icon' => '👨‍🍳'],
                        ['number' => '5,000+', 'label' => 'Tested Recipes', 'icon' => '🍽️'],
                        ['number' => '50+', 'label' => 'Expert Contributors', 'icon' => '⭐'],
                        ['number' => '100K+', 'label' => 'Newsletter Subscribers', 'icon' => '📧']
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
            'title' => 'Contact Taste & Table',
            'page_type' => 'content',
            'slug' => 'contact',
            'status' => 'published',
            'meta_title' => 'Contact Us - Taste & Table',
            'meta_description' => 'Get in touch with the Taste & Table team.',
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
                    'subtitle' => 'Questions about recipes? Want to collaborate? We\'d love to hear from you',
                    'showSearch' => false
                ],
                'order' => 1
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Taste & Table Editorial',
                    'role' => 'Contact Information',
                    'email' => 'hello@tastetable.com',
                    'phone' => '+44 20 3456 7890',
                    'address' => 'Taste & Table Magazine
123 Culinary Street
London, SW1A 1AA

Office Hours:
Monday-Friday: 9:00 AM - 5:30 PM',
                    'displayType' => 'contact'
                ],
                'order' => 2
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Send Us a Message',
                    'subtitle' => 'We typically respond within 24 hours',
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

    private function createArticle2(): void
    {
        $article = [
            'title' => 'Authentic Mexican Street Tacos: A Complete Guide',
            'slug' => 'mexican-street-tacos-guide',
            'tags' => ['featured', 'mexican', 'recipe', 'quick-easy', 'dinner'],
            'categories' => ['Recipes', 'By Cuisine', 'Mexican'],
            'custom_fields' => [
                'author_name' => 'Chef Carlos Mendez',
                'author_bio' => 'Mexican cuisine expert specializing in authentic street food.',
                'prep_time' => 20,
                'cook_time' => 15,
                'servings' => 4,
                'difficulty' => 'easy',
                'cuisine' => 'Mexican',
                'excerpt' => 'Learn to make authentic Mexican street tacos with tender meat, fresh toppings, and homemade salsa.'
            ],
            'content' => [
                [
                    'type' => 'image',
                    'data' => [
                        'src' => 'https://images.unsplash.com/photo-1565299585323-38d6b0865b47?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                        'alt' => 'Authentic Mexican street tacos',
                        'caption' => 'Street tacos are all about fresh ingredients and bold flavors',
                        'layout' => 'full',
                        'alignment' => 'fullscreen'
                    ]
                ],
                [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Street tacos are the heart and soul of Mexican street food. Unlike their Tex-Mex cousins, authentic Mexican tacos are simple, using small corn tortillas, perfectly seasoned meat, and fresh toppings.',
                            'The secret to great street tacos is quality ingredients and proper technique. Today, we\'ll show you how to make tacos that rival your favorite taqueria.'
                        ]
                    ]
                ],
                [
                    'type' => 'heading',
                    'data' => [
                        'text' => 'Essential Ingredients',
                        'level' => 2
                    ]
                ],
                [
                    'type' => 'info',
                    'data' => [
                        'infoType' => 'ingredients',
                        'description' => '1.5 lbs flank steak or pork shoulder • 12 small corn tortillas • 1 white onion, diced • Fresh cilantro, chopped • 2 limes, cut into wedges • Your favorite salsa'
                    ]
                ],
                [
                    'type' => 'heading',
                    'data' => [
                        'text' => 'Marinade',
                        'level' => 3
                    ]
                ],
                [
                    'type' => 'list',
                    'data' => [
                        'listType' => 'ul',
                        'items' => [
                            '3 cloves garlic, minced',
                            '2 tbsp lime juice',
                            '1 tbsp chili powder',
                            '1 tsp cumin',
                            '1 tsp oregano',
                            '1/2 tsp salt',
                            '2 tbsp vegetable oil'
                        ]
                    ]
                ],
                [
                    'type' => 'list',
                    'data' => [
                        'listType' => 'ol',
                        'schemaType' => 'steps',
                        'items' => [
                            'Mix all marinade ingredients in a bowl',
                            'Slice meat thinly against the grain',
                            'Marinate meat for at least 2 hours (overnight is best)',
                            'Heat a cast iron skillet or grill to high heat',
                            'Cook meat in batches, 2-3 minutes per side',
                            'Let meat rest 5 minutes, then chop into small pieces',
                            'Warm tortillas on the grill until slightly charred',
                            'Assemble tacos: tortilla, meat, onion, cilantro, lime'
                        ]
                    ]
                ],
                [
                    'type' => 'note',
                    'data' => [
                        'title' => 'Pro Tip',
                        'paragraphs' => [
                            'Always use two tortillas per taco - it\'s the authentic way! The first tortilla holds the filling, the second catches any drips and can be eaten separately.'
                        ],
                        'alignment' => 'left'
                    ]
                ],
                [
                    'type' => 'product',
                    'data' => [
                        'name' => 'Lodge Cast Iron Skillet 12-inch',
                        'brand' => 'Lodge',
                        'productName' => 'Cast Iron Skillet',
                        'image' => 'https://images.unsplash.com/photo-1556911220-bff31c812dba?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                        'price' => 34.99,
                        'currency' => '£',
                        'description' => 'Essential for getting that perfect char on taco meat. Pre-seasoned and ready to use.',
                        'link' => 'https://example.com/cast-iron',
                        'linkText' => 'View Product',
                        'noFollow' => false,
                        'sponsored' => true
                    ]
                ],
                [
                    'type' => 'quote',
                    'data' => [
                        'text' => 'A taco is not just food, it\'s an experience - every bite should be an adventure.',
                        'attribution' => 'Traditional Mexican Saying'
                    ]
                ]
            ]
        ];
        $this->createArticle($article);
    }

    private function createArticle3(): void
    {
        $article = [
            'title' => 'Asian Noodle Bowls: 5 Quick Recipes Under 30 Minutes',
            'slug' => 'asian-noodle-bowls-quick-recipes',
            'tags' => ['featured', 'asian', 'quick-easy', 'dinner', 'healthy'],
            'categories' => ['Recipes', 'By Cuisine', 'Asian'],
            'custom_fields' => [
                'author_name' => 'Chef Lin Zhang',
                'author_bio' => 'Asian cuisine specialist with focus on quick weeknight meals.',
                'prep_time' => 15,
                'cook_time' => 15,
                'servings' => 2,
                'difficulty' => 'easy',
                'cuisine' => 'Asian Fusion',
                'excerpt' => 'Fast, flavorful noodle bowls perfect for busy weeknights - from ramen to pho.'
            ],
            'content' => [
                [
                    'type' => 'image',
                    'data' => [
                        'src' => 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                        'alt' => 'Asian noodle bowl with vegetables',
                        'caption' => 'Colorful, healthy, and ready in minutes',
                        'layout' => 'full',
                        'alignment' => 'fullscreen'
                    ]
                ],
                [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Noodle bowls are the ultimate comfort food - warm, satisfying, and infinitely customizable. The best part? Most can be made in less time than it takes to order takeout.',
                            'We\'re sharing five authentic Asian noodle recipes that deliver restaurant-quality results with minimal effort.'
                        ]
                    ]
                ],
                [
                    'type' => 'page_grid',
                    'data' => [
                        'title' => '5 Quick Noodle Bowl Recipes',
                        'layout' => 'grid',
                        'columns' => 3,
                        'showExcerpt' => true,
                        'showImage' => true,
                        'pages' => [
                            [
                                'title' => 'Quick Ramen Bowl',
                                'slug' => 'quick-ramen',
                                'excerpt' => 'Upgraded instant ramen with soft-boiled egg and vegetables',
                                'image' => [
                                    'src' => 'https://images.unsplash.com/photo-1591814468924-caf88d1232e1?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Ramen bowl'
                                ]
                            ],
                            [
                                'title' => 'Thai Pad Thai',
                                'slug' => 'pad-thai',
                                'excerpt' => 'Classic sweet and tangy rice noodles with peanuts',
                                'image' => [
                                    'src' => 'https://images.unsplash.com/photo-1626804475297-41608ea09aeb?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Pad Thai'
                                ]
                            ],
                            [
                                'title' => 'Vietnamese Pho',
                                'slug' => 'quick-pho',
                                'excerpt' => 'Aromatic beef noodle soup with fresh herbs',
                                'image' => [
                                    'src' => 'https://images.unsplash.com/photo-1591814459308-10e4a6baa4c7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Pho bowl'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'table',
                    'data' => [
                        'hasHeader' => true,
                        'rows' => [
                            ['Noodle Type', 'Best Sauce', 'Cooking Time', 'Protein Pairing'],
                            ['Ramen', 'Miso or Soy', '3 mins', 'Pork, Chicken, Egg'],
                            ['Rice Noodles', 'Fish Sauce', '5 mins', 'Shrimp, Tofu'],
                            ['Udon', 'Dashi Broth', '8 mins', 'Beef, Tempura'],
                            ['Soba', 'Tsuyu Sauce', '5 mins', 'Duck, Vegetables'],
                            ['Glass Noodles', 'Sweet Chili', '3 mins', 'Ground Pork']
                        ]
                    ]
                ],
                [
                    'type' => 'deal',
                    'data' => [
                        'title' => 'Special Offer',
                        'productName' => 'Asian Noodle Sampler Pack',
                        'brand' => 'Taste & Table',
                        'image' => 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                        'price' => 29.99,
                        'salePrice' => 19.99,
                        'currency' => '£',
                        'description' => 'Try 5 different authentic Asian noodle types with recipe cards included!',
                        'link' => 'https://example.com/noodle-pack',
                        'showDealButton' => true
                    ]
                ],
                [
                    'type' => 'testimonial',
                    'data' => [
                        'layout' => 'grid',
                        'testimonials' => [
                            [
                                'text' => 'These recipes changed my weeknight dinners! So much better than takeout.',
                                'author' => 'Emma Thompson',
                                'role' => 'Busy Parent',
                                'rating' => 5
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $this->createArticle($article);
    }

    private function createArticle4(): void
    {
        $article = [
            'title' => 'The Perfect Chocolate Cake: Moist, Rich & Foolproof',
            'slug' => 'perfect-chocolate-cake-recipe',
            'tags' => ['featured', 'dessert', 'baking', 'chocolate', 'recipe'],
            'categories' => ['Recipes', 'By Meal', 'Dessert'],
            'custom_fields' => [
                'author_name' => 'Chef Michelle Baker',
                'author_bio' => 'Pastry chef and baking instructor with 15 years experience.',
                'prep_time' => 25,
                'cook_time' => 35,
                'servings' => 12,
                'difficulty' => 'medium',
                'excerpt' => 'This is THE chocolate cake recipe you\'ll make again and again - perfectly moist with deep chocolate flavor.'
            ],
            'content' => [
                [
                    'type' => 'image',
                    'data' => [
                        'src' => 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                        'alt' => 'Chocolate layer cake with frosting',
                        'caption' => 'The ultimate chocolate cake - moist, rich, and absolutely delicious',
                        'layout' => 'full',
                        'alignment' => 'fullscreen'
                    ]
                ],
                [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'After testing dozens of chocolate cake recipes, this is the one. It\'s incredibly moist thanks to a secret ingredient (coffee!), has deep chocolate flavor, and the texture is absolutely perfect.',
                            'Whether you\'re celebrating a birthday or just need chocolate cake in your life, this recipe delivers every single time.'
                        ]
                    ]
                ],
                [
                    'type' => 'award',
                    'data' => [
                        'subcategory' => 'Reader\'s Choice Award',
                        'productName' => 'Most Popular Cake Recipe 2025',
                        'winner' => true,
                        'rating' => 5.0,
                        'strapline' => 'Over 10,000 five-star reviews from home bakers'
                    ]
                ],
                [
                    'type' => 'info',
                    'data' => [
                        'infoType' => 'ingredients',
                        'description' => '2 cups sugar • 1¾ cups flour • ¾ cup cocoa powder • 2 tsp baking soda • 1 tsp baking powder • 1 tsp salt • 2 eggs • 1 cup buttermilk • 1 cup hot coffee • ½ cup oil • 1 tsp vanilla'
                    ]
                ],
                [
                    'type' => 'list',
                    'data' => [
                        'listType' => 'ol',
                        'schemaType' => 'steps',
                        'items' => [
                            'Preheat oven to 350°F (175°C). Grease two 9-inch round pans',
                            'Mix all dry ingredients in a large bowl',
                            'Add eggs, buttermilk, oil, and vanilla. Beat 2 minutes',
                            'Stir in hot coffee (batter will be thin)',
                            'Pour evenly into prepared pans',
                            'Bake 30-35 minutes until toothpick comes out clean',
                            'Cool 10 minutes in pans, then turn onto cooling racks',
                            'Frost when completely cool'
                        ]
                    ]
                ],
                [
                    'type' => 'note',
                    'data' => [
                        'title' => 'Why Coffee?',
                        'paragraphs' => [
                            'Coffee doesn\'t make the cake taste like coffee - it enhances and deepens the chocolate flavor. It\'s the secret to making chocolate taste MORE chocolatey!'
                        ],
                        'alignment' => 'fullscreen'
                    ]
                ],
                [
                    'type' => 'buying-guide',
                    'data' => [
                        'title' => 'KitchenAid Artisan Stand Mixer',
                        'subtitle' => 'Makes mixing cake batter effortless',
                        'url' => 'https://example.com/kitchenaid-mixer',
                        'specs' => [
                            ['text' => 'Capacity', 'value' => '4.8L bowl'],
                            ['text' => 'Power', 'value' => '325 watts'],
                            ['text' => 'Speeds', 'value' => '10 + pulse'],
                            ['text' => 'Warranty', 'value' => '5 years']
                        ],
                        'showReviewPanel' => true,
                        'pros' => [
                            'Powerful motor handles thick batters',
                            'Planetary mixing action',
                            'Wide range of attachments',
                            'Built to last decades'
                        ],
                        'cons' => [
                            'Premium price point',
                            'Takes up counter space'
                        ]
                    ]
                ]
            ]
        ];
        $this->createArticle($article);
    }

    private function createArticle5(): void
    {
        $article = [
            'title' => 'Mediterranean Diet Guide: Healthy Eating Made Delicious',
            'slug' => 'mediterranean-diet-complete-guide',
            'tags' => ['featured', 'healthy', 'mediterranean', 'meal-prep', 'cooking-tips'],
            'categories' => ['Cooking Guides', 'Meal Prep'],
            'custom_fields' => [
                'author_name' => 'Nutritionist Dr. Elena Costa',
                'author_bio' => 'Registered dietitian specializing in Mediterranean cuisine and nutrition.',
                'excerpt' => 'Discover why the Mediterranean diet is consistently rated the world\'s healthiest - with delicious recipes and meal plans.'
            ],
            'content' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'title' => 'The Mediterranean Diet',
                        'subtitle' => 'Eat well, live better - the world\'s healthiest diet',
                        'ctaText' => 'Start Your Journey',
                        'ctaUrl' => '#meal-plan',
                        'backgroundImage' => 'https://images.unsplash.com/photo-1544025162-d76694265947?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                    ]
                ],
                [
                    'type' => 'stats',
                    'data' => [
                        'title' => 'Mediterranean Diet Benefits',
                        'stats' => [
                            ['number' => '30%', 'label' => 'Lower Heart Disease Risk', 'icon' => '❤️'],
                            ['number' => '52%', 'label' => 'Reduced Diabetes Risk', 'icon' => '🩺'],
                            ['number' => '#1', 'label' => 'Best Diet (8 Years Running)', 'icon' => '🏆'],
                            ['number' => '40%', 'label' => 'Better Cognitive Function', 'icon' => '🧠']
                        ]
                    ]
                ],
                [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The Mediterranean diet isn\'t just a diet - it\'s a lifestyle celebrated in Greece, Italy, Spain, and Southern France. It emphasizes whole foods, healthy fats, and enjoying meals with family and friends.',
                            'Research consistently shows that people following this eating pattern have lower rates of heart disease, diabetes, and certain cancers. Best of all? The food is absolutely delicious.'
                        ]
                    ]
                ],
                [
                    'type' => 'heading',
                    'data' => [
                        'text' => 'Core Principles',
                        'level' => 2
                    ]
                ],
                [
                    'type' => 'list',
                    'data' => [
                        'listType' => 'ul',
                        'items' => [
                            'Eat primarily plant-based foods: fruits, vegetables, whole grains, legumes, nuts',
                            'Use olive oil as your main fat source',
                            'Eat fish and seafood at least twice per week',
                            'Enjoy moderate amounts of poultry, eggs, cheese, and yogurt',
                            'Limit red meat to a few times per month',
                            'Choose water and wine (in moderation) over sugary drinks',
                            'Stay active and enjoy meals with others'
                        ]
                    ]
                ],
                [
                    'type' => 'gallery',
                    'data' => [
                        'layout' => 'grid',
                        'slides' => [
                            [
                                'title' => 'Greek Salad',
                                'description' => 'Fresh vegetables, olives, feta, and olive oil',
                                'image' => 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Greek salad'
                            ],
                            [
                                'title' => 'Grilled Fish',
                                'description' => 'Omega-3 rich fish with lemon and herbs',
                                'image' => 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Grilled fish'
                            ],
                            [
                                'title' => 'Whole Grains',
                                'description' => 'Farro, bulgur, and whole wheat pasta',
                                'image' => 'https://images.unsplash.com/photo-1586201375761-83865001e31c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Whole grains bowl'
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'event',
                    'data' => [
                        'title' => 'Mediterranean Cooking Workshop',
                        'description' => 'Learn to cook authentic Mediterranean dishes in this hands-on class. We\'ll prepare a full three-course meal featuring Greek, Italian, and Spanish favorites.',
                        'startDate' => '2025-05-20',
                        'startTime' => '6:00 PM',
                        'endTime' => '9:00 PM',
                        'location' => 'Taste & Table Cooking Studio',
                        'ticketPrice' => 85.00,
                        'currency' => '£',
                        'ticketUrl' => 'https://example.com/med-workshop',
                        'category' => 'Cooking Class'
                    ]
                ]
            ]
        ];
        $this->createArticle($article);
    }

    private function createArticle6(): void
    {
        $article = [
            'title' => 'Kitchen Knife Buying Guide 2025: From Chef\'s Knives to Specialty Blades',
            'slug' => 'kitchen-knife-buying-guide-2025',
            'tags' => ['product-review', 'buying-guide', 'kitchen-gear'],
            'categories' => ['Product Reviews', 'Kitchen Tools'],
            'custom_fields' => [
                'author_name' => 'Chef Robert Stevens',
                'author_bio' => 'Professional chef and culinary instructor with expertise in knife skills.',
                'excerpt' => 'Everything you need to know about buying quality kitchen knives - from essential chef\'s knives to specialized blades.'
            ],
            'content' => [
                [
                    'type' => 'image',
                    'data' => [
                        'src' => 'https://images.unsplash.com/photo-1593618998160-e34014e67546?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                        'alt' => 'Professional chef knives',
                        'caption' => 'Quality knives are the foundation of good cooking',
                        'layout' => 'full',
                        'alignment' => 'fullscreen'
                    ]
                ],
                [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'A good knife is the most important tool in your kitchen. We tested over 50 knives to find the best options at every price point.',
                            'Whether you\'re building your first knife set or upgrading to professional-grade blades, this guide will help you make the right choice.'
                        ]
                    ]
                ],
                [
                    'type' => 'heading',
                    'data' => [
                        'text' => 'Types of Kitchen Knives',
                        'level' => 2
                    ]
                ],
                [
                    'type' => 'table',
                    'data' => [
                        'hasHeader' => true,
                        'rows' => [
                            ['Knife Type', 'Primary Use', 'Essential?', 'Typical Size'],
                            ['Chef\'s Knife', 'All-purpose cutting', 'Yes', '8-10 inches'],
                            ['Paring Knife', 'Small precise tasks', 'Yes', '3-4 inches'],
                            ['Bread Knife', 'Slicing bread, tomatoes', 'Yes', '8-10 inches'],
                            ['Santoku', 'Slicing, dicing, mincing', 'Optional', '5-7 inches'],
                            ['Boning Knife', 'Removing meat from bone', 'Optional', '5-6 inches'],
                            ['Cleaver', 'Heavy chopping', 'Optional', '6-8 inches']
                        ]
                    ]
                ],
                [
                    'type' => 'award',
                    'data' => [
                        'subcategory' => 'Best Overall Chef\'s Knife',
                        'productName' => 'Wüsthof Classic 8-inch',
                        'image' => 'https://images.unsplash.com/photo-1593618998160-e34014e67546?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                        'winner' => true,
                        'rating' => 4.9,
                        'strapline' => 'German craftsmanship meets perfect balance and edge retention'
                    ]
                ],
                [
                    'type' => 'buying-guide',
                    'data' => [
                        'title' => 'Wüsthof Classic 8-Inch Chef\'s Knife',
                        'subtitle' => 'The gold standard for home cooks and professionals',
                        'image' => 'https://images.unsplash.com/photo-1593618998160-e34014e67546?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                        'url' => 'https://example.com/wusthof-chefs-knife',
                        'specs' => [
                            ['text' => 'Blade Length', 'value' => '8 inches'],
                            ['text' => 'Steel Type', 'value' => 'High-carbon stainless'],
                            ['text' => 'Handle', 'value' => 'Triple-riveted synthetic'],
                            ['text' => 'Weight', 'value' => '8.8 oz'],
                            ['text' => 'Warranty', 'value' => 'Lifetime']
                        ],
                        'showReviewPanel' => true,
                        'pros' => [
                            'Exceptional edge retention',
                            'Perfect weight and balance',
                            'Comfortable grip for extended use',
                            'Easy to sharpen',
                            'Made in Germany with quality craftsmanship'
                        ],
                        'cons' => [
                            'Premium price (£140)',
                            'Requires hand washing',
                            'Heavier than Japanese knives'
                        ]
                    ]
                ],
                [
                    'type' => 'product-comparison',
                    'data' => [
                        'title' => 'German vs Japanese Chef\'s Knives',
                        'productA' => 'Wüsthof (German)',
                        'productB' => 'Shun (Japanese)',
                        'comparisons' => [
                            [
                                'subtitle' => 'Price',
                                'items' => [
                                    ['value' => '£140'],
                                    ['value' => '£180']
                                ]
                            ],
                            [
                                'subtitle' => 'Blade Angle',
                                'items' => [
                                    ['value' => '14° per side'],
                                    ['value' => '16° per side']
                                ]
                            ],
                            [
                                'subtitle' => 'Weight',
                                'items' => [
                                    'items' => [
                                        ['value' => 'Heavier, more robust'],
                                        ['value' => 'Lighter']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->createArticle($article);
    }
}