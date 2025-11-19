<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Framework\Support\Str;
use App\Models\Author;
use App\Models\Category;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageAuthor;
use App\Models\PageCategory;
use App\Models\PageCustomField;
use App\Models\PageGrid;
use App\Models\PageTag;
use App\Models\Site;
use App\Models\Tag;
use App\Repositories\BlockRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;
use App\Services\BlockParserService;

class HomeAndGardenSeeder extends Seeder
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
        $this->site = Site::find(2);

        if (!$this->site) {
            echo "TechWeekly site not found.\n";
            return;
        }

        $this->createTaxonomies();
        $this->createExtendedArticles();
    }

    private function createTaxonomies(): void
    {
        // Create Plant Types taxonomy
        $plantType = Category::create([
            'site_id' => $this->site->id,
            'name' => 'Plant Types',
            'slug' => 'plant-types',
            'description' => 'Categories of plants for gardening',
            'is_active' => true
        ]);

        $plantTypeTerms = [
            'Flowers' => ['Roses', 'Tulips', 'Daffodils', 'Sunflowers'],
            'Vegetables' => ['Tomatoes', 'Peppers', 'Lettuce', 'Carrots'],
            'Herbs' => ['Basil', 'Mint', 'Rosemary', 'Thyme'],
            'Trees' => ['Oak', 'Maple', 'Pine', 'Birch']
        ];

        foreach ($plantTypeTerms as $parent => $children) {
            Category::create([
                'parent_id' => $plantType->id,
                'name' => $parent,
                'slug' => strtolower($parent),
                'is_active' => true
            ]);

            foreach ($children as $child) {
                Category::create([
                    'parent_id' => $plantType->id,
                    'name' => $child,
                    'slug' => strtolower(str_replace(' ', '-', $child)),
                    'is_active' => true
                ]);
            }
        }

        // Create Design Styles taxonomy
        $designStyle = Category::create([
            'site_id' => $this->site->id,
            'name' => 'Design Styles',
            'slug' => 'design-styles',
            'description' => 'Interior design style categories',
            'is_active' => true
        ]);

        $styles = ['Modern', 'Rustic', 'Scandinavian', 'Bohemian', 'Minimalist', 'Industrial', 'Farmhouse'];
        foreach ($styles as $index => $style) {
            Tag::create([
                'category_id' => $designStyle->id,
                'name' => $style,
                'slug' => strtolower($style),
                'is_active' => true
            ]);
        }

        // Create Furniture Brands taxonomy
        $brands = Category::create([
            'site_id' => $this->site->id,
            'name' => 'Furniture Brands',
            'slug' => 'brands',
            'description' => 'Furniture and decor brands',
            'is_active' => true
        ]);

        $brandNames = ['IKEA', 'West Elm', 'Crate & Barrel', 'Pottery Barn', 'CB2', 'Article'];
        foreach ($brandNames as $index => $brand) {
            Tag::create([
                'category_id' => $brands->id,
                'name' => $brand,
                'slug' => strtolower(str_replace(' ', '-', str_replace('&', 'and', $brand))),
                'is_active' => true
            ]);
        }
    }

    private function createExtendedArticles(): void
    {
        $articles = [
            [
                'title' => 'Ultimate Guide to Growing Roses in Your Garden',
                'slug' => 'growing-roses-guide',
                'tags' => ['garden', 'flowers', 'roses', 'beginner-friendly'],
                'categories' => ['Garden & Outdoor', 'Gardening', 'Flowers'],
                'taxonomies' => [
                    'plant-types' => ['flowers', 'roses']
                ],
                'custom_fields' => [
                    'author_name' => 'Sarah Green',
                    'read_time' => 10,
                    'difficulty_level' => 'beginner',
                    'excerpt' => 'Everything you need to know about planting, caring for, and maintaining beautiful roses in your garden.'
                ],
                'author' => [
                    'name' => 'Sarah Green'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Growing Beautiful Roses',
                            'subtitle' => 'A complete guide for beginners',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1518709594023-6eab9bab7b23?auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Roses are among the most beloved flowers in gardens worldwide. With proper care and attention, anyone can successfully grow stunning roses that bloom abundantly season after season.',
                                'This comprehensive guide covers everything from choosing the right rose varieties to planting, pruning, and pest control. Whether you\'re a complete beginner or looking to improve your rose-growing skills, you\'ll find valuable insights here.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Choosing the Right Rose Varieties',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Hybrid Tea Roses',
                                    'description' => 'Classic long-stemmed beauties perfect for cutting',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Hybrid tea rose'
                                ],
                                [
                                    'title' => 'Climbing Roses',
                                    'description' => 'Ideal for fences, arbors, and vertical spaces',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1529241735536-9e77bb5cf04e?auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Climbing roses'
                                ],
                                [
                                    'title' => 'Floribunda Roses',
                                    'description' => 'Clusters of blooms provide continuous color',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1561329919-c2b1b6d63746?auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Floribunda roses'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Select a sunny location with at least 6 hours of direct sunlight daily',
                                'Prepare soil by adding compost and ensuring good drainage',
                                'Dig a hole twice as wide and deep as the root ball',
                                'Place rose bush and spread roots gently',
                                'Fill hole with soil mixture, water thoroughly',
                                'Apply mulch around base to retain moisture',
                                'Water deeply once or twice weekly depending on rainfall'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Premium Rose Food 4lb',
                            'brand' => 'Miracle-Gro',
                            'productName' => 'Rose Plant Food',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1585208798174-6cedd86e019a?auto=format&fit=crop&w=800&q=80'],
                            'price' => 15.99,
                            'currency' => '£',
                            'description' => 'Specially formulated for roses with essential nutrients for vibrant blooms and healthy growth.',
                            'link' => 'https://example.com/rose-food',
                            'linkText' => 'Buy Now',
                            'displayAs' => 'button',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 4.7,
                                'pros' => [
                                    'Promotes abundant blooms',
                                    'Easy to apply',
                                    'Visible results in 2-3 weeks',
                                    'Suitable for all rose types'
                                ],
                                'cons' => [
                                    'Needs reapplication every 6 weeks',
                                    'Strong smell when first applied'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Deadhead spent blooms regularly to encourage more flowers and maintain plant energy for new growth.'
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Rose Type', 'Mature Height', 'Best For', 'Hardiness'],
                                ['Hybrid Tea', '3-6 feet', 'Cutting gardens', 'Zones 5-9'],
                                ['Floribunda', '2-4 feet', 'Borders, mass planting', 'Zones 5-9'],
                                ['Climbing', '8-15 feet', 'Fences, arbors', 'Zones 5-9'],
                                ['Shrub', '3-5 feet', 'Landscapes, hedges', 'Zones 4-9'],
                                ['Miniature', '1-2 feet', 'Containers, edging', 'Zones 5-9']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'A garden without roses is like a day without sunshine.',
                            'attribution' => 'Unknown'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Scandinavian Living Room Makeover: Before & After',
                'slug' => 'scandinavian-living-room-makeover',
                'tags' => ['interior-design', 'scandinavian', 'living-room', 'before-after', 'makeover'],
                'categories' => ['Interior Design', 'Living Spaces', 'Living Room'],
                'taxonomies' => [
                    'design-styles' => ['scandinavian']
                ],
                'custom_fields' => [
                    'author_name' => 'Emma Nordström',
                    'read_time' => 8,
                    'room_type' => 'living',
                    'style' => 'Scandinavian',
                    'excerpt' => 'See how we transformed a dark, cluttered living room into a bright Scandinavian oasis on a budget of £2,000.'
                ],
                'author' => [
                    'name' => 'Emma Nordström',
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Bright Scandinavian living room',
                            'caption' => 'The finished space: bright, airy, and functional',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'When Sarah contacted us about her dark, outdated living room, she dreamed of a Scandinavian-inspired space that felt both cozy and spacious. With a modest budget of £2,000, we created a stunning transformation.',
                                'The key to this makeover was embracing Scandinavian principles: maximizing natural light, using a neutral color palette, and selecting multifunctional furniture.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Before vs After',
                            'productA' => 'Before',
                            'productB' => 'After',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Wall Color',
                                    'items' => [
                                        ['value' => 'Dark brown'],
                                        ['value' => 'White with warm undertones']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Flooring',
                                    'items' => [
                                        ['value' => 'Dark carpet'],
                                        ['value' => 'Light wood laminate']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Lighting',
                                    'items' => [
                                        ['value' => 'Single ceiling fixture'],
                                        ['value' => 'Layered: ceiling, floor, and table lamps']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Furniture',
                                    'items' => [
                                        ['value' => 'Oversized dark pieces'],
                                        ['value' => 'Streamlined light wood furniture']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Feeling',
                                    'items' => [
                                        ['value' => 'Dark, cramped, cluttered'],
                                        ['value' => 'Bright, spacious, serene']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Budget Breakdown',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Total Investment: £2,000',
                            'stats' => [
                                ['number' => '£450', 'label' => 'Paint & Flooring', 'icon' => '🎨'],
                                ['number' => '£800', 'label' => 'Sofa', 'icon' => '🛋️'],
                                ['number' => '£350', 'label' => 'Lighting', 'icon' => '💡'],
                                ['number' => '£400', 'label' => 'Accessories & Textiles', 'icon' => '🏠']
                            ]
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => 'Featured Product',
                            'productName' => 'Norsborg 3-Seater Sofa',
                            'brand' => 'IKEA',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=800&q=80'],
                            'price' => 899.00,
                            'salePrice' => 799.00,
                            'currency' => '£',
                            'description' => 'The centerpiece of our makeover: clean lines, comfortable seating, and available in multiple colors.',
                            'link' => 'https://example.com/norsborg-sofa',
                            'showDealButton' => true
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Best IKEA Hacks for Small Apartments',
                'slug' => 'ikea-hacks-small-apartments',
                'tags' => ['diy', 'ikea', 'small-spaces', 'budget-friendly', 'organization'],
                'categories' => ['DIY & Projects', 'Storage Solutions'],
                'taxonomies' => [
                    'brands' => ['ikea']
                ],
                'custom_fields' => [
                    'author_name' => 'Emma Nordström',
                    'read_time' => 12,
                    'difficulty_level' => 'beginner',
                    'project_cost' => '£50-£200',
                    'excerpt' => '15 creative IKEA hacks that transform basic furniture into custom solutions for small living spaces.'
                ],
                'author' => [
                    'name' => 'Emma Nordström'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'IKEA Hacks for Small Spaces',
                            'subtitle' => '15 budget-friendly transformations',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1556912172-45b7abe8b7e1?auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'IKEA furniture is affordable and functional, but with a few creative modifications, it can become truly custom. These 15 hacks transform basic IKEA pieces into solutions perfectly suited for small apartment living.',
                                'Most of these projects require minimal tools and can be completed in an afternoon. The results? Furniture that looks expensive and works exactly how you need it to.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'page_grid',
                        'data' => [
                            'title' => 'Top 5 IKEA Hacks',
                            'layout' => 'grid',
                            'columns' => 3,
                            'showExcerpt' => true,
                            'showImage' => true,
                            'pages' => [
                                [
                                    'title' => 'KALLAX Room Divider with Storage',
                                    'slug' => '#kallax',
                                    'excerpt' => 'Turn a KALLAX shelf unit into a stylish room divider that provides storage on both sides.',
                                    'image' => [
                                        'src' => 'https://images.unsplash.com/photo-1524758631624-e2822e304c36?auto=format&fit=crop&w=800&q=80',
                                        'alt' => 'KALLAX divider'
                                    ]
                                ],
                                [
                                    'title' => 'TARVA Dresser Upgrade',
                                    'slug' => '#tarva',
                                    'excerpt' => 'Transform a plain pine dresser with paint, new hardware, and wooden legs for a mid-century look.',
                                    'image' => [
                                        'src' => 'https://images.unsplash.com/photo-1595428774223-ef52624120d2?auto=format&fit=crop&w=800&q=80',
                                        'alt' => 'TARVA dresser hack'
                                    ]
                                ],
                                [
                                    'title' => 'BILLY Bookcase Built-Ins',
                                    'slug' => '#billy',
                                    'excerpt' => 'Create the look of custom built-ins by combining multiple BILLY bookcases with crown molding.',
                                    'image' => [
                                        'src' => 'https://images.unsplash.com/photo-1524758631624-e2822e304c36?auto=format&fit=crop&w=800&q=80',
                                        'alt' => 'BILLY built-ins'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'LACK side table becomes nightstand with hidden storage',
                                'IVAR cabinet transforms into stylish bar cart',
                                'RAST dresser upgrades to modern changing table',
                                'MOPPE mini storage gets marble contact paper makeover',
                                'FINTORP rails create vertical kitchen organization'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Pro Tips for IKEA Hacks',
                            'paragraphs' => [
                                'Always assemble the IKEA piece first before attempting modifications. This ensures everything fits properly.',
                                'Invest in quality hardware and paint. The materials you add will determine how "custom" your hack looks.',
                                'Take your time with prep work—sanding and priming make all the difference in the final finish.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Creating a Container Herb Garden: Complete Guide',
                'slug' => 'container-herb-garden-guide',
                'tags' => ['garden', 'herbs', 'containers', 'beginner-friendly', 'urban-gardening'],
                'categories' => ['Garden & Outdoor', 'Gardening', 'Herbs'],
                'taxonomies' => [
                    'plant-types' => ['herbs', 'basil', 'mint', 'rosemary']
                ],
                'custom_fields' => [
                    'author_name' => 'Sarah Green',
                    'read_time' => 9,
                    'difficulty_level' => 'beginner',
                    'project_cost' => '£30-£60',
                    'excerpt' => 'Learn how to create a thriving container herb garden perfect for balconies, patios, or small spaces.'
                ],
                'author' => [
                    'name' => 'Sarah Green'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1466692476868-aef1dfb1e735?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Container herb garden',
                            'caption' => 'Fresh herbs at your fingertips, even in the smallest spaces',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'You don\'t need a garden to grow fresh herbs. Container gardening brings the joy of homegrown herbs to apartments, balconies, and small patios. With the right containers, soil, and care, you can harvest fresh basil, mint, rosemary, and more all season long.',
                                'This guide covers everything from choosing containers to selecting herbs, planting, and ongoing care. By the end, you\'ll have a thriving herb garden no matter how limited your space.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Choosing the Right Containers',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Minimum 6-8 inches deep for most herbs',
                                'Drainage holes are essential—no exceptions',
                                'Terracotta breathes well but dries quickly',
                                'Plastic retains moisture longer',
                                'Glazed ceramic offers style with good moisture retention',
                                'Self-watering containers ideal for busy schedules'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Herb', 'Container Size', 'Sun Needs', 'Water Frequency', 'Growth Rate'],
                                ['Basil', '8-10 inches', 'Full sun', 'Daily', 'Fast'],
                                ['Mint', '10-12 inches', 'Part shade OK', 'Keep moist', 'Very fast'],
                                ['Rosemary', '10-12 inches', 'Full sun', 'Let dry between', 'Slow'],
                                ['Thyme', '6-8 inches', 'Full sun', 'Let dry between', 'Moderate'],
                                ['Parsley', '8-10 inches', 'Part shade OK', '2-3x weekly', 'Moderate'],
                                ['Cilantro', '8-10 inches', 'Part shade', 'Keep moist', 'Fast']
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Complete Container Herb Garden Kit',
                            'subtitle' => 'Everything you need to start growing',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1466692476868-aef1dfb1e735?auto=format&fit=crop&w=800&q=80'],
                            'url' => 'https://example.com/herb-garden-kit',
                            'linkText' => 'Buy Starter Kit',
                            'specs' => [
                                ['text' => 'Includes', 'value' => '6 containers with saucers'],
                                ['text' => 'Potting Mix', 'value' => '20L organic blend'],
                                ['text' => 'Seeds', 'value' => '8 herb varieties'],
                                ['text' => 'Labels', 'value' => 'Wooden plant markers'],
                                ['text' => 'Guide', 'value' => 'Detailed growing instructions']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Complete kit for beginners',
                                'Quality containers with proper drainage',
                                'Organic potting mix included',
                                'Wide variety of herb seeds'
                            ],
                            'cons' => [
                                'Containers are basic style',
                                'May want to buy more decorative pots'
                            ]
                        ]
                    ],
                    [
                        'type' => 'schema',
                        'data' => [
                            'schemaType' => 'question',
                            'question' => 'Can I grow herbs indoors?',
                            'answer' => 'Yes! Most herbs thrive indoors with 6+ hours of bright light from a south-facing window or grow lights.',
                            'expansion' => 'Basil, parsley, chives, and mint are particularly well-suited to indoor growing. Use well-draining potting mix and ensure containers have drainage holes. Herbs grown indoors may need supplemental lighting during winter months. LED grow lights placed 6-12 inches above plants for 12-14 hours daily work excellently.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Farmhouse Kitchen Renovation: £5,000 Budget Makeover',
                'slug' => 'farmhouse-kitchen-renovation',
                'tags' => ['renovation', 'kitchen', 'farmhouse', 'budget-friendly', 'before-after'],
                'categories' => ['Interior Design', 'Living Spaces', 'Kitchen'],
                'taxonomies' => [
                    'design-styles' => ['farmhouse', 'rustic']
                ],
                'custom_fields' => [
                    'author_name' => 'Emma Nordström',
                    'read_time' => 15,
                    'room_type' => 'kitchen',
                    'style' => 'Farmhouse',
                    'project_cost' => '£5,000',
                    'excerpt' => 'See how we transformed a dated 1980s kitchen into a charming farmhouse space for just £5,000.'
                ],
                'author' => [
                    'name' => 'Emma Nordström',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Farmhouse Kitchen on a Budget',
                            'subtitle' => 'Complete renovation for £5,000',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'When the Thompson family purchased their home, the kitchen was stuck in the 1980s with dark oak cabinets, laminate countertops, and outdated appliances. With a £5,000 budget and a vision for a warm farmhouse aesthetic, we created a stunning transformation.',
                                'The key to staying on budget? Strategic updates rather than a complete gut renovation. We kept the existing layout and cabinet boxes, focusing investment on high-impact changes.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Renovation Timeline & Budget',
                            'stats' => [
                                ['number' => '3 weeks', 'label' => 'Total Time', 'icon' => '📅'],
                                ['number' => '£5,000', 'label' => 'Total Budget', 'icon' => '💰'],
                                ['number' => '£2,500', 'label' => 'DIY Savings', 'icon' => '🔨'],
                                ['number' => '85%', 'label' => 'Increase in Home Value', 'icon' => '📈']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Budget Breakdown',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Item', 'Cost', 'DIY vs Pro', 'Impact'],
                                ['Cabinet painting & hardware', '£800', 'DIY', 'Very High'],
                                ['Butcher block countertops', '£1,200', 'Pro install', 'High'],
                                ['Farmhouse sink', '£400', 'Pro install', 'High'],
                                ['Subway tile backsplash', '£600', 'DIY', 'Medium'],
                                ['Open shelving', '£300', 'DIY', 'Medium'],
                                ['Lighting fixtures', '£500', 'DIY', 'High'],
                                ['Appliances (paint)', '£100', 'DIY', 'Medium'],
                                ['Flooring', '£800', 'DIY', 'High'],
                                ['Paint & misc', '£300', 'DIY', 'Low']
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'carousel',
                            'slides' => [
                                [
                                    'title' => 'Before: Dark Oak Cabinets',
                                    'description' => 'Original 1980s kitchen with heavy oak',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=1200&q=80'],
                                    'alt' => 'Kitchen before renovation'
                                ],
                                [
                                    'title' => 'After: Bright White Cabinets',
                                    'description' => 'Same cabinets, painted white with new hardware',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1556912173-46686eac8d58?auto=format&fit=crop&w=1200&q=80'],
                                    'alt' => 'Kitchen after renovation'
                                ],
                                [
                                    'title' => 'New Farmhouse Sink',
                                    'description' => 'Apron-front sink becomes the focal point',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1600489000022-c2086d79f9d4?auto=format&fit=crop&w=1200&q=80'],
                                    'alt' => 'Farmhouse sink detail'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Key Design Decisions',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Paint existing cabinets white instead of replacing (saved £3,000)',
                                'Choose butcher block over stone/quartz counters (saved £1,500)',
                                'DIY subway tile backsplash (saved £400)',
                                'Replace upper cabinets with open shelving (saved £800, added character)',
                                'Paint existing appliances with appliance epoxy (saved £2,000)',
                                'Install luxury vinyl plank flooring ourselves (saved £500)'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Kraus Farmhouse Sink 33-inch',
                            'brand' => 'Kraus',
                            'productName' => 'White Fireclay Apron-Front Sink',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1600489000022-c2086d79f9d4?auto=format&fit=crop&w=800&q=80'],
                            'price' => 399.00,
                            'currency' => '£',
                            'description' => 'The centerpiece of our farmhouse kitchen. Durable fireclay construction with timeless apron-front design.',
                            'link' => 'https://example.com/farmhouse-sink',
                            'linkText' => 'View Product',
                            'displayAs' => 'button',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 4.8,
                                'pros' => [
                                    'Authentic farmhouse look',
                                    'Scratch and chip resistant',
                                    'Easy to clean',
                                    'Large single basin',
                                    'Excellent quality for price'
                                ],
                                'cons' => [
                                    'Heavy—requires sturdy cabinet support',
                                    'Professional installation recommended'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'DIY Cabinet Painting Tips',
                            'paragraphs' => [
                                'Success with painted cabinets comes down to proper prep. Remove all doors and hardware, clean thoroughly with TSP, sand lightly, and apply quality primer before paint.',
                                'We used Benjamin Moore Advance paint in White Dove—it dries to a furniture-like finish that\'s durable enough for kitchen use. Three thin coats with light sanding between created a factory-quality finish.',
                                'Total painting time: 5 days including drying time. Total cost: £250 in paint and supplies vs £3,000+ for new cabinets.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'testimonial',
                        'data' => [
                            'layout' => 'grid',
                            'testimonials' => [
                                [
                                    'text' => 'I can\'t believe this is the same kitchen! The farmhouse sink and open shelving completely changed the feel. Best £5,000 we\'ve ever spent.',
                                    'author' => 'Claire Thompson',
                                    'role' => 'Homeowner',
                                    'rating' => 5
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'You don\'t need unlimited funds to create the kitchen of your dreams. Strategic updates and DIY effort can achieve stunning results on a real-world budget.',
                            'attribution' => 'Emma Nordström'
                        ]
                    ]
                ]
            ]
        ];

        $pages = [];

        foreach ($articles as $articleData) {
            $page = $this->createArticle($articleData);

            $pages[] = $page;

            // Attach taxonomy terms
            if (!empty($articleData['taxonomies'])) {
                foreach ($articleData['taxonomies'] as $taxonomySlug => $termSlugs) {
                    $taxonomy = Category::where('slug', $taxonomySlug)->first();
                    if ($taxonomy) {

                        PageCategory::create([
                            'category_id' => $taxonomy->id,
                            'page_id' => $page->id
                        ]);

                        foreach ($termSlugs as $termSlug) {
                            $term = Tag::where('slug', $termSlug)->first();
                            if ($term) {

                                PageTag::create([
                                    'page_id' => $page->id,
                                    'tag_id' => $term->id
                                ]);
                            }
                        }
                    }
                }
            }
        }

        $this->createArticleGrid($pages);
    }

    private function createArticle(array $data): Page
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => $data['title'] . ' - TechWeekly',
            'meta_description' => $data['custom_fields']['excerpt'] ?? '',
            'site_id' => 6,
        ]);

        if (!empty($data['author'])) {
            $data['author']['slug'] = Str::slug($data['author']['name']);
            $author = Author::create($data['author']);
            Pageauthor::create([
                'page_id' => $page->id,
                'author_id' => $author->id
            ]);
        }

        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, 1);
            $page->tags(true)->attach($tag->id);
        }

        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, 1);
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

        return $page;
    }

    private function createArticleGrid($pages): void
    {
        $site = Site::find(6);

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
            'site_id' => 8
        ]);
    }
}