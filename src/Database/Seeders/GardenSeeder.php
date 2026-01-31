<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\Site;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class GardenSeeder extends Seeder
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
        $this->site = Site::where('slug', 'haven-hearth')->first();

        $this->createArticles();
    }

    private function createArticles(): void
    {
        $articles = [
            [
                'title' => '10 Budget-Friendly DIY Storage Solutions for Small Spaces',
                'slug' => 'diy-storage-solutions-small-spaces',
                'tags' => ['featured', 'diy', 'storage-solutions', 'small-spaces', 'budget-friendly'],
                'categories' => ['DIY & Projects', 'Storage Solutions'],
                'custom_fields' => [
                    'author_name' => 'Emma Nordström',
                    'author_bio' => 'Emma is a Swedish interior designer specializing in Nordic minimalism with over 15 years of experience.',
                    'read_time' => 9,
                    'difficulty_level' => 'beginner',
                    'project_cost' => '£20-£100',
                    'project_time' => '1-3 hours per project',
                    'excerpt' => 'Maximize your small space with these clever, affordable storage solutions you can build or assemble in a weekend.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1595428774223-ef52624120d2?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Organized Small Space Storage',
                            'caption' => 'Smart storage transforms cramped quarters into functional, organized spaces',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Small spaces require creative thinking when it comes to storage. Every square inch counts, and traditional storage solutions often don\'t fit the bill. The good news? With a few inexpensive materials and a weekend\'s work, you can create custom storage that maximizes your space while staying on budget.',
                                'These DIY projects require minimal tools and experience. Most can be completed in an afternoon, and all cost under £100 in materials. Let\'s transform your cramped quarters into an organized oasis.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '1. Floating Corner Shelves',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Corners are often wasted space in small rooms. Floating corner shelves utilize this vertical real estate for books, plants, or decorative items. You can build these using simple pine boards and L-brackets.',
                                'Cost: £15-25 | Time: 1 hour | Difficulty: Beginner'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Materials: Pine boards (cut to size), L-brackets, screws, paint or stain',
                                'Tools needed: Drill, level, stud finder, saw (or have lumber yard cut)',
                                'Pro tip: Install shelves at varying heights for visual interest'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '2. Under-Bed Rolling Storage',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1556228841-ac0d85d3dc3f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                            'alt' => 'Under-bed storage drawers',
                            'caption' => 'Rolling storage makes use of typically wasted space',
                            'layout' => 'standard'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The space under your bed is prime storage real estate. Create custom rolling drawers using wooden crates, plywood bases, and caster wheels. These pull out easily and can store seasonal clothing, shoes, or linens.',
                                'Cost: £30-45 | Time: 2 hours | Difficulty: Beginner'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '3. Pegboard Wall Organizer',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Pegboard is incredibly versatile for vertical storage. Use it in kitchens for utensils, in home offices for supplies, or in entryways for keys and bags. Paint it to match your décor and customize with various hooks and baskets.',
                                'Cost: £25-40 | Time: 2 hours | Difficulty: Beginner'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '4. Ladder Shelf Display',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'An old wooden ladder makes a charming and functional display shelf. Lean it against a wall and use the rungs to hang baskets, display plants, or store rolled towels in a bathroom. This works especially well in farmhouse or rustic interiors.',
                                'Cost: £10-20 (if buying used ladder) | Time: 30 minutes | Difficulty: Beginner'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '5. Hanging Fabric Organizers',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Over-the-door fabric organizers aren\'t just for shoes. Use them for cleaning supplies, craft materials, toiletries, or pantry items. You can even make your own by sewing pockets onto a canvas backing.',
                                'Cost: £15-25 | Time: 1 hour (or 3 hours if sewing) | Difficulty: Beginner'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '6. Magnetic Spice Rack',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Free up valuable cabinet space by mounting spices on a magnetic strip or board. Use small tins with magnetic backs or attach magnets to existing spice containers. This solution works on any metal surface, including the side of a refrigerator.',
                                'Cost: £20-35 | Time: 1 hour | Difficulty: Beginner'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Magnetic Spice Tin Set',
                            'brand' => 'Gneiss Spice',
                            'productName' => 'Magnetic Spice Tin Set',
                            'image' => 'https://images.unsplash.com/photo-1596040033229-a0b4f7d8b6d8?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'price' => 42,
                            'salePrice' => 0,
                            'currency' => '£',
                            'description' => 'Set of 24 magnetic spice tins with clear lids. Includes labels and fits standard spice quantities. Strong magnets work on any metal surface.',
                            'link' => 'https://example.com/magnetic-spice-tins',
                            'linkText' => 'View Product',
                            'displayAs' => 'button',
                            'layout' => 'standard',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 4.6,
                                'pros' => [
                                    'Clear lids let you see contents',
                                    'Strong magnetic hold',
                                    'Uniform look tidies up kitchen',
                                    'Easy to refill'
                                ],
                                'cons' => [
                                    'May need to buy spices in bulk to fill',
                                    'Labels can wear over time'
                                ]
                            ],
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '7. Tension Rod Dividers',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Tension rods aren\'t just for curtains. Install them vertically in cabinets to organize baking sheets and cutting boards, or horizontally under sinks to hang spray bottles. They require no drilling and can be repositioned easily.',
                                'Cost: £8-15 | Time: 15 minutes | Difficulty: Beginner'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '8. File Organizer Pot Lid Holder',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'A desktop file organizer mounted inside a cabinet door makes a perfect pot lid holder. This keeps lids accessible and organized while freeing up cabinet space. Use command strips for a no-drill installation.',
                                'Cost: £10-18 | Time: 20 minutes | Difficulty: Beginner'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '9. Crate Storage Bench',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Wooden Crate Bench',
                                    'description' => 'Stack and secure crates, add cushion on top',
                                    'image' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'DIY crate storage bench'
                                ],
                                [
                                    'title' => 'Storage Cubbies',
                                    'description' => 'Each crate becomes a storage cubby',
                                    'image' => 'https://images.unsplash.com/photo-1595428774223-ef52624120d2?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Crate cubbies for storage'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Stack wooden crates (secured together) to create a storage bench for an entryway or bedroom. Each crate becomes a cubby for shoes, bags, or toys. Top with a cushion for comfortable seating.',
                                'Cost: £35-60 | Time: 2-3 hours | Difficulty: Beginner-Intermediate'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => '10. Shower Curtain Ring Organizers',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Shower curtain rings are surprisingly versatile organizers. Hang them from a rod in your closet to organize scarves, tank tops, or belts. Use them in the bathroom for hair ties and accessories, or in the kitchen to hang measuring cups.',
                                'Cost: £5-12 | Time: 10 minutes | Difficulty: Beginner'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Before You Start',
                            'paragraphs' => [
                                'Measure your space carefully before purchasing materials. Small spaces require precision—a storage solution that\'s even an inch too large won\'t work.',
                                'Consider your rental situation. If you can\'t drill holes, focus on solutions using command strips, tension rods, or freestanding options.',
                                'Choose light colors for storage solutions in small spaces. Dark, bulky storage can make a room feel even more cramped.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The secret to living in a small space is not having less stuff, but having the right storage to keep it organized and accessible.',
                            'attribution' => 'Marie Kondo'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Complete Guide to Growing Herbs Indoors Year-Round',
                'slug' => 'growing-herbs-indoors-guide',
                'tags' => ['garden', 'herbs', 'indoor', 'sustainable', 'beginner-friendly'],
                'categories' => ['Garden & Outdoor', 'Gardening', 'Herbs'],
                'custom_fields' => [
                    'author_name' => 'Sarah Green',
                    'author_bio' => 'Sarah is a landscape designer and horticulturist with a passion for sustainable gardening.',
                    'read_time' => 11,
                    'difficulty_level' => 'beginner',
                    'project_cost' => '£30-£80',
                    'excerpt' => 'Fresh herbs at your fingertips all year long. Learn how to successfully grow a thriving indoor herb garden with minimal space and effort.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1466692476868-aef1dfb1e735?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Indoor herb garden on windowsill',
                            'caption' => 'A thriving windowsill herb garden provides fresh flavors year-round',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'There\'s nothing quite like snipping fresh herbs for your dinner—the vibrant flavors and aromas elevate any dish. While outdoor herb gardens are wonderful, growing herbs indoors means fresh basil in January and rosemary in December, regardless of weather.',
                                'Indoor herb gardening is easier than you might think. With the right setup and basic care, even apartment dwellers can enjoy a productive herb garden. This comprehensive guide covers everything from choosing herbs to harvesting, troubleshooting common problems, and maintaining your garden year-round.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Best Herbs for Indoor Growing',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Not all herbs thrive indoors. Some require too much space, while others need more light than a typical home provides. These herbs are well-suited to indoor conditions and provide the best results for beginners.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Herb', 'Light Needs', 'Growth Rate', 'Best For'],
                                ['Basil', '6-8 hours', 'Fast', 'Italian dishes, pesto, salads'],
                                ['Parsley', '4-6 hours', 'Moderate', 'Universal garnish, tabbouleh'],
                                ['Chives', '4-6 hours', 'Fast', 'Soups, potatoes, eggs'],
                                ['Mint', '4-6 hours', 'Very fast', 'Drinks, desserts, lamb'],
                                ['Thyme', '6-8 hours', 'Slow', 'Roasted meats, Mediterranean dishes'],
                                ['Oregano', '6-8 hours', 'Moderate', 'Pizza, pasta, Greek cuisine'],
                                ['Cilantro', '4-6 hours', 'Fast', 'Mexican, Asian, Indian dishes'],
                                ['Rosemary', '6-8 hours', 'Slow', 'Roasted meats, potatoes, bread']
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Start with basil, parsley, and chives if you\'re new to indoor gardening. These three are forgiving, grow quickly, and are useful in countless recipes.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Essential Supplies',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Containers with drainage holes (6-8 inches deep minimum)',
                                'High-quality potting mix (not garden soil)',
                                'Saucers or trays to catch drainage water',
                                'Herb seeds or starter plants',
                                'Grow light (if you lack a sunny window)',
                                'Watering can with narrow spout',
                                'Organic fertilizer for herbs',
                                'Spray bottle for misting',
                                'Small pruning scissors or herb scissors'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'LED Grow Light for Herbs',
                            'brand' => 'Spider Farmer',
                            'productName' => 'SF-1000 LED Grow Light',
                            'image' => 'https://images.unsplash.com/photo-1530836369250-ef72a3f5cda8?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'price' => 89,
                            'salePrice' => 0,
                            'currency' => '£',
                            'description' => 'Full-spectrum LED grow light perfect for herbs. Energy-efficient, low heat output, covers up to 2x2 feet of growing area. Adjustable height and intensity.',
                            'link' => 'https://example.com/grow-light',
                            'linkText' => 'View Grow Light',
                            'displayAs' => 'button',
                            'layout' => 'standard',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 4.7,
                                'pros' => [
                                    'Full spectrum promotes healthy growth',
                                    'Low energy consumption',
                                    'Minimal heat means no burn risk',
                                    'Adjustable for different plant heights'
                                ],
                                'cons' => [
                                    'Initial investment',
                                    'Requires timer for best results'
                                ]
                            ],
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Setting Up Your Indoor Herb Garden',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Choose your location: South-facing window is ideal. East or west-facing works but may need supplemental light.',
                                'Prepare containers: Ensure drainage holes exist. Add pebbles at bottom if needed for extra drainage.',
                                'Fill with potting mix: Use fresh, high-quality potting soil. Garden soil is too dense for containers.',
                                'Plant herbs: If using seedlings, plant at the same depth they were in nursery pots. For seeds, follow packet instructions.',
                                'Water thoroughly: Water until it drains from bottom. Let excess drain away completely.',
                                'Label everything: You\'ll forget which herb is which, especially when they\'re small.',
                                'Set up grow light if needed: Position 6-12 inches above plants, run 12-16 hours daily.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Daily and Weekly Care',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Watering: Check soil daily by pressing your finger in an inch deep. Water when soil feels dry. Herbs prefer slightly dry to overwatered. Ensure water drains completely—never let pots sit in standing water.',
                                'Light: Rotate pots every few days so all sides receive equal light. If using a window, this prevents plants from leaning. With grow lights, rotation isn\'t as critical but still beneficial.',
                                'Temperature: Herbs prefer 60-70°F (15-21°C). Avoid placing pots near heating vents or cold drafty windows.',
                                'Humidity: Most herbs prefer moderate humidity. If your home is very dry (below 40%), mist plants lightly every few days or use a humidity tray.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Fertilizing Your Herbs',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Container plants need regular feeding since nutrients wash out with watering. Use a balanced, organic liquid fertilizer diluted to half strength every 2-3 weeks during active growth.',
                                'Reduce fertilizing in winter when growth naturally slows. Over-fertilizing produces lush foliage but diminished flavor—you want herbs, not houseplants.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'The Secret to Bushy, Productive Herbs',
                            'paragraphs' => [
                                'Regular harvesting is the key to productive herbs. When you snip leaves, the plant responds by growing more. Always harvest from the top, cutting just above a leaf node (where leaves attach to stem).',
                                'Never remove more than one-third of the plant at once. This maintains plant health while encouraging new growth. Harvest in the morning after dew dries for peak flavor and essential oils.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Troubleshooting Common Problems',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Yellow leaves usually indicate overwatering. Let soil dry out more between waterings and check that drainage is adequate.'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Leggy, stretched growth: Insufficient light. Move closer to window or add/upgrade grow light.',
                                'Brown leaf tips: Usually from dry air or fluoride in tap water. Increase humidity and try filtered water.',
                                'Wilting despite moist soil: Root rot from overwatering. Reduce watering frequency and ensure proper drainage.',
                                'Small, pale leaves: Needs fertilizer. Resume regular feeding schedule.',
                                'Pests (aphids, spider mites): Isolate plant, spray with diluted neem oil solution, repeat weekly until clear.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Harvesting and Storage',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'carousel',
                            'slides' => [
                                [
                                    'title' => 'Fresh Use',
                                    'image' => 'https://images.unsplash.com/photo-1509358271058-acd22cc93898?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                                    'alt' => 'Fresh herbs being chopped',
                                    'description' => 'Use fresh herbs immediately for maximum flavor'
                                ],
                                [
                                    'title' => 'Drying',
                                    'image' => 'https://images.unsplash.com/photo-1594756202469-9ff9799b2e4e?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                                    'alt' => 'Herbs hanging to dry',
                                    'description' => 'Hang bundles upside down in a cool, dark place'
                                ],
                                [
                                    'title' => 'Freezing',
                                    'image' => 'https://images.unsplash.com/photo-1608797178974-15b35a64ede9?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                                    'alt' => 'Herbs frozen in ice cube trays',
                                    'description' => 'Freeze in oil or water for convenient portions'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Fresh herbs are best used immediately after cutting. Store short-term (up to a week) by placing stems in water like cut flowers, loosely covered in the refrigerator.',
                                'For longer storage, dry or freeze your harvest. Drying works best for woody herbs like rosemary, thyme, and oregano. Freezing preserves delicate herbs like basil, cilantro, and parsley better than drying.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Growing your own herbs connects you to your food in a profound way. There\'s magic in plucking fresh basil for your pasta sauce or mint for your tea, knowing you nurtured it from seed.',
                            'attribution' => 'Rosalind Creasy, Garden Writer'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Choosing the Perfect Paint Color: A Room-by-Room Guide',
                'slug' => 'choosing-paint-colors-room-guide',
                'tags' => ['interior-design', 'color-schemes', 'diy', 'renovation', 'buying-guide'],
                'categories' => ['Interior Design', 'Elements', 'Color'],
                'custom_fields' => [
                    'author_name' => 'Emma Nordström',
                    'author_bio' => 'Emma is a Swedish interior designer specializing in Nordic minimalism with over 15 years of experience.',
                    'read_time' => 13,
                    'excerpt' => 'Navigate the overwhelming world of paint colors with expert guidance for every room in your home. Learn which colors work best and why.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1562259949-e8e7689d7828?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Paint color swatches and samples',
                            'caption' => 'Choosing the right paint color transforms a space from ordinary to extraordinary',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Paint color is one of the most impactful design decisions you\'ll make, yet it\'s also one of the most intimidating. With thousands of options and color psychology to consider, where do you even start?',
                                'The truth is, there\'s no universally "perfect" paint color. The right choice depends on your room\'s natural light, size, function, and existing elements. This guide breaks down color selection by room, explaining the psychology behind color choices and offering foolproof options for every space in your home.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Understanding Light and Color',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Before choosing any color, understand your room\'s light. Natural light changes throughout the day, and artificial light affects how colors appear. A color that looks perfect in the showroom might disappoint in your home.',
                                'North-facing rooms receive cool, indirect light that can make colors appear darker and cooler. South-facing rooms get warm, bright light that intensifies colors. East-facing rooms have warm morning light and cool afternoon light. West-facing rooms are the opposite.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'The Paint Sample Rule',
                            'paragraphs' => [
                                'Never choose paint from a small chip alone. Paint large swatches (at least 2x2 feet) directly on your walls. Observe them at different times of day and in different lighting conditions for at least 48 hours before deciding.',
                                'Paint the sample on multiple walls to see how different light exposures affect the color. What looks perfect on one wall might look completely different on another.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Living Room Colors',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Living rooms are multi-functional spaces for relaxation, entertainment, and family time. Colors should be welcoming yet sophisticated, working well from morning coffee to evening entertaining.',
                                'Neutral bases (warm whites, greiges, soft grays) provide flexibility for changing décor and artwork. They create a calming backdrop without competing for attention. If you crave color, add it through accent walls, furniture, and accessories rather than painting all four walls.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Popular Living Room Paint Colors',
                            'productA' => 'Warm Neutrals',
                            'productB' => 'Cool Neutrals',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Traditional, cozy, welcoming spaces'],
                                        ['value' => 'Modern, crisp, contemporary spaces']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Works With',
                                    'items' => [
                                        ['value' => 'Wood tones, warm metals, earth tones'],
                                        ['value' => 'White trim, chrome, blues and grays']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Light Direction',
                                    'items' => [
                                        ['value' => 'North-facing rooms needing warmth'],
                                        ['value' => 'South-facing rooms with abundant light']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Top Picks',
                                    'items' => [
                                        ['value' => 'Accessible Beige, Edgecomb Gray'],
                                        ['value' => 'Agreeable Gray, Repose Gray']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Avoid If',
                                    'items' => [
                                        ['value' => 'You prefer crisp, modern aesthetic'],
                                        ['value' => 'Room lacks natural light (can feel stark)']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Kitchen Colors',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Kitchens benefit from colors that feel clean and energizing. White remains the most popular choice for good reason—it reflects light, makes spaces feel larger, and provides a timeless backdrop for changing trends.',
                                'However, pure white can feel sterile. Warmer whites with undertones of cream or yellow create a more inviting atmosphere. For those wanting color, soft blues and greens work beautifully in kitchens, evoking freshness and cleanliness.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Classic White (Simply White, White Dove): Clean, timeless, works with any cabinet color',
                                'Warm White (Swiss Coffee, Alabaster): Cozier feel, prevents stark coldness',
                                'Soft Blue (Palladian Blue, Quiet Moments): Calming, fresh, pairs beautifully with white cabinets',
                                'Sage Green (Silver Sage, Sea Salt): On-trend, organic feel, complements natural materials',
                                'Soft Gray (Agreeable Gray, Revere Pewter): Modern and neutral, works with stainless appliances'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'In kitchens with white cabinets, painting walls a different color adds dimension. In kitchens with colored cabinets, keep walls neutral to avoid overwhelming the space.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Bedroom Colors',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Soft Blues',
                                    'description' => 'Promote relaxation and lower blood pressure',
                                    'image' => 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Blue bedroom'
                                ],
                                [
                                    'title' => 'Warm Neutrals',
                                    'description' => 'Create cozy, enveloping comfort',
                                    'image' => 'https://images.unsplash.com/photo-1616594266537-7b05b1f7729e?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Beige bedroom'
                                ],
                                [
                                    'title' => 'Soft Greens',
                                    'description' => 'Nature-inspired serenity and balance',
                                    'image' => 'https://images.unsplash.com/photo-1615873968403-89e068629265?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Green bedroom'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Bedrooms should promote rest and relaxation. Cool colors (blues, greens, lavenders) have been shown to lower heart rate and blood pressure, making them ideal for sleep spaces. Avoid energizing colors like bright red or orange unless you struggle to wake up.',
                                'Darker colors can work beautifully in bedrooms, creating a cocoon-like atmosphere. Deep blues, charcoal grays, or even black can be sophisticated and restful when balanced with good lighting and lighter bedding.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Bathroom Colors',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Bathrooms tolerate bolder color choices since they\'re small spaces you don\'t spend extended time in. This is your opportunity to experiment with dramatic colors or fun patterns you might shy away from elsewhere.',
                                'That said, lighter colors make small bathrooms feel more spacious and spa-like. Soft blues and greens evoke cleanliness and tranquility. If your bathroom lacks natural light, avoid dark colors that will make it feel like a cave.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Home Office Colors',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Home offices require colors that promote focus and creativity without causing fatigue. Blues and greens are excellent choices—blue enhances productivity and calm, while green reduces eye strain and promotes balance.',
                                'Avoid stark white in offices, as it can cause eye strain when staring at computer screens all day. A soft neutral or gentle color is easier on the eyes during long work sessions.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Navy Blue: Serious, professional, promotes concentration',
                                'Sage Green: Balancing, reduces stress, connects to nature',
                                'Warm Gray: Professional but not sterile, versatile',
                                'Soft Yellow: Energizing, creative, optimistic (use sparingly as accent)'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Dining Room Colors',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Dining rooms can handle more drama than other spaces. Rich, saturated colors create an intimate atmosphere perfect for entertaining. Deep reds, burgundies, navy blues, and forest greens all work beautifully in dining spaces.',
                                'These deeper colors look especially stunning with candlelight and overhead lighting, creating a sophisticated ambiance for dinner parties. Balance dark walls with lighter ceilings to prevent the room from feeling oppressive.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Trim and Ceiling Colors',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Trim color significantly impacts how wall color appears. Bright white trim creates crisp contrast and makes colors pop—perfect for modern aesthetics. Off-white or cream trim creates softer contrast suited to traditional styles.',
                                'Ceiling color affects perceived room height. Pure white ceilings can feel disconnected from colored walls. Try painting ceilings in your wall color at 25% intensity, or one shade lighter than walls for a cohesive, enveloping feel.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Avoid these common mistakes: Choosing color from tiny chips alone, ignoring how existing flooring/countertops interact with paint, painting without primer, and assuming one coat is enough (most colors need two coats for true color).'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Testing Colors Properly',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Narrow down to 3-5 colors based on room function and light',
                                'Purchase sample pots (most brands sell 8oz testers)',
                                'Paint large swatches (minimum 2x2 feet) on different walls',
                                'Observe at different times: morning, afternoon, evening, night with artificial light',
                                'Look at samples with existing furniture, flooring, and fabrics in room',
                                'Live with samples for at least 2-3 days before deciding',
                                'Don\'t rush—repainting costs time and money'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Paint Sample Kit',
                            'brand' => 'Samplize',
                            'productName' => 'Peel-and-Stick Paint Samples',
                            'image' => 'https://images.unsplash.com/photo-1589939705384-5185137a7f0f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'price' => 6.99,
                            'salePrice' => 0,
                            'currency' => '£',
                            'description' => 'Real paint samples on peel-and-stick sheets. Test colors without painting! Reusable, repositionable, and made with actual paint from major brands.',
                            'link' => 'https://example.com/paint-samples',
                            'linkText' => 'Order Samples',
                            'displayAs' => 'button',
                            'layout' => 'standard',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 4.5,
                                'pros' => [
                                    'No painting required',
                                    'Easily test multiple colors',
                                    'Move samples around room',
                                    'True color representation'
                                ],
                                'cons' => [
                                    'More expensive than traditional samples',
                                    'Limited to available brands'
                                ]
                            ],
                            'noFollow' => false,
                            'sponsored' => true,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Color is a power which directly influences the soul. Color is the keyboard, the eyes are the hammers, the soul is the piano with many strings.',
                            'attribution' => 'Wassily Kandinsky'
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
                                'Choosing paint color is personal—what feels calming to one person might feel cold to another. Trust your instincts but test thoroughly. The small investment in sample pots prevents expensive regrets.',
                                'Remember that paint is impermanent. If you make a choice you later dislike, you can repaint. Don\'t let fear of the "wrong" choice paralyze you. Sometimes the best way to learn what you love is to live with it for a while.',
                                'Finally, consider the flow between rooms. You don\'t need every room the same color, but colors should complement each other as you move through your home. Stand in doorways and hallways to see how colors interact—this cohesion creates a professionally designed feel.'
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
}