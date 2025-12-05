<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\Product;
use App\Models\Site;
use App\Repositories\BlockRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;

class ComprehensiveContentSeeder extends Seeder
{
    private $pageRepository;
    private $blockRepository;
    private $tagRepository;
    private $categoryRepository;
    private $blockParserService;

    private array $createdMoneyWeekProducts = [];
    private array $createdGamesRadarProducts = [];
    private array $createdHorseAndHoundProducts = [];
    private array $createdTechWeeklyProducts = [];
    private array $createdVogueNoirProducts = [];
    private array $createdWineChronicleProducts = [];

    public function __construct()
    {
        $this->pageRepository = new PageRepository();
        $this->blockRepository = new BlockRepository();
        $this->tagRepository = new TagRepository();
        $this->categoryRepository = new CategoryRepository();
        $this->blockParserService = (new Container())->resolve(\App\Services\BlockParserService::class);

        parent::__construct();
    }

    public function run(): void
    {
        // 1. Create products for MoneyWeek
//        $this->createMoneyWeekProducts();
//
//        $this->createGamesRadarProducts();
//
//        $this->createHorseAndHoundProducts();
//
//        $this->createTechWeeklyProducts();
//
//        $this->createVogueNoirProducts();
//
//        $this->createWineChronicleProducts();
//
//        // 2. Add product grids to MoneyWeek homepage
//        $this->addProductGridsToMoneyWeek();
//
//        $this->addProductGridsToGamesRadar();
//
//        $this->addProductGridsToHorseAndHound();
//
//        $this->addProductGridsToTechWeekly();
//
//        $this->addProductGridsToVogueNoir();
//
//        $this->addProductGridsToWineChronicle();
//
//        // 3. Add team block to MoneyWeek homepage
//        $this->addTeamBlockToMoneyWeek();
//
//        $this->addTeamBlockToHavenAndHearth();

        // 4. Create Haven & Hearth themed content
        $this->createHavenHearthThemedContent();
    }

    private function createHavenHearthThemedContent(): void
    {
        $havenSite = Site::where('slug', 'haven-hearth')->first();
        if (!$havenSite) {
            echo "Haven & Hearth site not found.\n";
            return;
        }

        // Theme 1: Scandinavian Design
        $this->createScandinavianTheme($havenSite);

        // Theme 2: Garden & Outdoor Living
        $this->createGardenTheme($havenSite);

        // Theme 3: Smart Home Technology
        $this->createSmartHomeTheme($havenSite);

        // Theme 4: Sustainable Living
        $this->createSustainableTheme($havenSite);

        // Add theme grids to homepage
        $this->addThemeGridsToHavenHearth($havenSite);
    }

    private function createScandinavianTheme($site): void
    {
        $pages = [
            [
                'title' => '10 Essential Scandinavian Furniture Pieces for Your Home',
                'slug' => 'scandinavian-furniture-essentials',
                'excerpt' => 'Discover the iconic furniture pieces that define Nordic minimalism and how to incorporate them into your space.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Scandinavian furniture',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Scandinavian furniture design has captivated the world with its perfect blend of form, function, and timeless beauty. These pieces aren\'t just furniture—they\'re investments in quality and style that will serve your home for decades.',
                                'In this guide, we\'ll explore ten essential pieces that form the foundation of Nordic interior design, from iconic chairs to versatile storage solutions.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'items' => [
                                'The Wishbone Chair - Hans Wegner\'s masterpiece of organic design',
                                'Solid Wood Dining Table - Natural beauty as a family gathering point',
                                'Modular Shelving System - String or Muuto for flexible storage',
                                'Leather Lounge Chair - Classic comfort with clean lines',
                                'Oak Sideboard - Essential storage with minimalist aesthetic',
                                'Wool Area Rug - Texture and warmth underfoot',
                                'Pendant Light - Statement lighting with functional beauty',
                                'Linen Sofa - Relaxed elegance in neutral tones',
                                'Wooden Bench - Versatile seating for any room',
                                'Ceramic Table Lamp - Sculptural lighting as art'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Creating a Hygge-Inspired Living Room',
                'slug' => 'hygge-living-room-guide',
                'excerpt' => 'Transform your living space into a cozy sanctuary with these hygge design principles and practical tips.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1616594266537-7b05b1f7729e?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Cozy hygge living room',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Hygge—the Danish concept of cozy contentment—has become a global design phenomenon. But creating a truly hygge-inspired space goes beyond adding candles and blankets. It\'s about crafting an environment that nourishes your well-being.',
                                'Let\'s explore how to transform your living room into a hygge sanctuary where comfort, connection, and contentment naturally flourish.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Essential Elements',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Layered Lighting: Combine ambient, task, and candle light',
                                'Textured Textiles: Mix wool, linen, and faux fur',
                                'Natural Materials: Wood, stone, and ceramic',
                                'Neutral Palette: Warm whites, soft grays, natural browns',
                                'Personal Touches: Family photos, meaningful objects',
                                'Reading Nook: Comfortable chair with good lighting',
                                'Live Plants: Bring nature indoors',
                                'Quality Over Quantity: Fewer, better pieces'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Scandinavian Kitchen Design: Form Meets Function',
                'slug' => 'scandinavian-kitchen-design',
                'excerpt' => 'Practical tips for creating a beautiful, functional kitchen inspired by Nordic design principles.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1556912172-45b7abe8b7e1?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Modern Scandinavian kitchen',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Scandinavian kitchen is the heart of the home—a place where functionality and beauty coexist in perfect harmony. These spaces are designed for real life, combining efficient workflows with warm, inviting aesthetics.',
                                'Whether you\'re planning a full renovation or a simple refresh, these Nordic-inspired ideas will help you create a kitchen that\'s both practical and beautiful.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Key Design Principles',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'White or light wood cabinets for brightness',
                                'Open shelving for frequently used items',
                                'Under-cabinet LED lighting',
                                'Quality appliances in clean designs',
                                'Butcher block or quartz countertops',
                                'Minimal upper cabinets to avoid heaviness',
                                'Herb garden on windowsill',
                                'Matching containers for organized storage'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        foreach ($pages as $pageData) {
            $this->createThemedPage($site, $pageData, ['scandinavian', 'interior-design', 'featured']);
        }

        echo "Created Scandinavian theme content.\n";
    }

    private function createThemedPage($site, array $pageData, array $tags): void
    {
        $page = Page::create([
            'title' => $pageData['title'],
            'slug' => $pageData['slug'],
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => $pageData['title'] . ' - Haven & Hearth',
            'meta_description' => $pageData['excerpt'],
            'site_id' => $site->id,
        ]);

        // Add tags
        foreach ($tags as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, $site->id);
            $page->tags(true)->attach($tag->id);
        }

        // Add custom field for excerpt
        $excerptField = CustomFieldDefinition::where('key', 'excerpt')->first();
        if ($excerptField) {
            PageCustomField::create([
                'custom_field_definition_id' => $excerptField->id,
                'field_value' => $pageData['excerpt'],
                'page_id' => $page->id
            ]);
        }

        // Create blocks
        foreach ($pageData['content'] as $index => $blockData) {
            $this->blockRepository->create([
                'page_id' => $page->id,
                'type' => $blockData['type'],
                'data' => json_encode($blockData['data']),
                'order' => $index + 1
            ]);
        }

        $this->createdPages[] = [
            'page' => $page,
            'theme' => $tags[0]
        ];
    }

    private function createGardenTheme($site): void
    {
        $pages = [
            [
                'title' => 'Container Gardening for Small Spaces',
                'slug' => 'container-gardening-small-spaces',
                'excerpt' => 'Maximize your growing potential with creative container gardening solutions perfect for balconies, patios, and tiny yards.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1585320806297-9794b3e4eeae?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Container garden',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Limited space doesn\'t mean limited gardening opportunities. Container gardening opens up a world of possibilities for urban dwellers, apartment residents, and anyone with a small outdoor area.',
                                'With the right containers, soil, and plant selection, you can create a thriving garden that produces fresh herbs, vegetables, and beautiful flowers—all in pots.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Best Plants for Container Growing',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Herbs: Basil, mint, parsley, thyme, rosemary',
                                'Vegetables: Tomatoes, peppers, lettuce, radishes',
                                'Flowers: Petunias, geraniums, pansies, marigolds',
                                'Fruits: Strawberries, dwarf citrus, blueberries',
                                'Ornamentals: Hostas, ferns, ornamental grasses'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Creating a Pollinator-Friendly Garden',
                'slug' => 'pollinator-friendly-garden',
                'excerpt' => 'Support bees, butterflies, and beneficial insects while creating a vibrant, colorful garden.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1597693116831-7522321175a6?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Pollinator garden with butterflies',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Pollinators are in decline worldwide, but your garden can be part of the solution. By choosing the right plants and avoiding harmful chemicals, you can create a haven for bees, butterflies, and other beneficial insects.',
                                'A pollinator-friendly garden isn\'t just good for the environment—it\'s also a feast for the eyes, filled with colorful blooms and fascinating wildlife.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Top Pollinator Plants',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Lavender - attracts bees and butterflies',
                                'Coneflowers - long blooming, butterfly favorite',
                                'Bee Balm - hummingbirds and bees love it',
                                'Milkweed - essential for monarch butterflies',
                                'Black-eyed Susans - easy care, pollinator magnet',
                                'Catmint - drought tolerant, bee friendly',
                                'Salvia - various colors, continuous bloom',
                                'Native wildflowers - adapted to local pollinators'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Outdoor Living: Creating Your Perfect Patio',
                'slug' => 'perfect-patio-design',
                'excerpt' => 'Design and furnish an outdoor living space that extends your home and provides a comfortable retreat.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Beautiful outdoor patio',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Your patio can be so much more than an afterthought—it can become your favorite room in the house. With thoughtful design and the right furnishings, outdoor living spaces rival any interior for comfort and style.',
                                'Let\'s explore how to create a patio that\'s perfect for entertaining, relaxing, and enjoying the outdoors in comfort.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Essential Patio Elements',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'items' => [
                                'Comfortable seating - invest in quality outdoor furniture',
                                'Shade solution - umbrella, pergola, or sail shade',
                                'Lighting - string lights, lanterns, or LED fixtures',
                                'Outdoor rug - defines space and adds color',
                                'Fire feature - firepit or chiminea for ambiance',
                                'Dining area - table for outdoor meals',
                                'Plants in containers - softens hardscaping',
                                'Weather-resistant storage - for cushions and supplies'
                            ]
                        ]
                    ]
                ]
            ]
        ];
        foreach ($pages as $pageData) {
            $this->createThemedPage($site, $pageData, ['garden', 'outdoor', 'featured']);
        }

        echo "Created Garden & Outdoor theme content.\n";
    }

    private function createSmartHomeTheme($site): void
    {
        $pages = [
            [
                'title' => 'Smart Home Starter Guide for Beginners',
                'slug' => 'smart-home-starter-guide',
                'excerpt' => 'Navigate the world of smart home technology with this comprehensive guide to getting started.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Smart home devices',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Smart home technology can seem overwhelming at first, but starting small and building gradually makes the transition smooth and enjoyable. The key is choosing devices that solve real problems in your daily life.',
                                'This guide will help you make smart choices about which devices to buy first and how to integrate them into your home.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Best First Smart Home Devices',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'items' => [
                                'Smart Speaker - Amazon Echo or Google Nest Hub',
                                'Smart Bulbs - Philips Hue or LIFX for easy lighting control',
                                'Video Doorbell - See who\'s at the door from anywhere',
                                'Smart Thermostat - Save energy and money automatically',
                                'Smart Plugs - Make any device smart instantly',
                                'Security Camera - Monitor your home remotely',
                                'Smart Lock - Keyless entry and remote access',
                                'Robot Vacuum - Automated cleaning convenience'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Smart Thermostats: Complete Buying Guide',
                'slug' => 'smart-thermostat-buying-guide',
                'excerpt' => 'Compare the best smart thermostats and learn how to choose the right one for your home and HVAC system.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1545259741-2ea3ebf61fa3?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Smart thermostat',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'A smart thermostat is one of the few smart home devices that actually pays for itself through energy savings. Modern thermostats learn your schedule, adjust automatically, and provide detailed energy reports.',
                                'But with dozens of options on the market, how do you choose the right one? This guide breaks down the key features and top models.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Key Features to Consider',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'HVAC Compatibility - check your system type',
                                'Learning Capability - auto-adjusts to your schedule',
                                'Remote Access - control from anywhere via app',
                                'Energy Reports - track usage and savings',
                                'Voice Control - works with Alexa, Google, Siri',
                                'Geofencing - adjusts when you leave/arrive',
                                'Multi-room Sensors - even temperature throughout',
                                'Installation Ease - DIY friendly or pro required'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Home Security Systems: What You Need to Know',
                'slug' => 'home-security-systems-guide',
                'excerpt' => 'Protect your home and family with the right security system. Compare options and features.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1558002038-1055907df827?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Home security camera',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Modern home security has evolved far beyond simple alarm systems. Today\'s smart security systems offer comprehensive protection with cameras, sensors, and professional monitoring—all controlled from your smartphone.',
                                'Whether you rent or own, live in an apartment or house, there\'s a security solution that fits your needs and budget.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Essential Security Components',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Door/Window Sensors - detect unauthorized entry',
                                'Indoor Cameras - monitor activity inside',
                                'Outdoor Cameras - deter intruders, record footage',
                                'Motion Sensors - detect movement when armed',
                                'Smart Locks - control access remotely',
                                'Glass Break Sensors - detect window breaking',
                                'Smoke/CO Detectors - life safety protection',
                                'Professional Monitoring - 24/7 emergency response'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        foreach ($pages as $pageData) {
            $this->createThemedPage($site, $pageData, ['smart-home', 'technology', 'featured']);
        }

        echo "Created Smart Home theme content.\n";
    }

    private function createSustainableTheme($site): void
    {
        $pages = [
            [
                'title' => 'Zero-Waste Kitchen: Practical Steps to Reduce Waste',
                'slug' => 'zero-waste-kitchen-guide',
                'excerpt' => 'Transform your kitchen into an eco-friendly space with these practical zero-waste strategies.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Zero waste kitchen',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The kitchen generates more household waste than any other room. But with simple swaps and mindful habits, you can dramatically reduce your environmental impact without sacrificing convenience.',
                                'Going zero-waste isn\'t about perfection—it\'s about making better choices one step at a time. This guide will help you start your journey.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Essential Zero-Waste Swaps',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Reusable produce bags instead of plastic',
                                'Glass storage containers replace plastic wrap',
                                'Beeswax wraps for food storage',
                                'Cloth napkins instead of paper',
                                'Compost bin for food scraps',
                                'Reusable shopping bags',
                                'Stainless steel straws',
                                'Bulk buying to reduce packaging',
                                'Refillable soap and cleaning products',
                                'Reusable coffee filters'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Sustainable Materials Guide for Home Renovations',
                'slug' => 'sustainable-materials-guide',
                'excerpt' => 'Choose eco-friendly materials for your next home project without compromising quality or style.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1600607687644-aac4c3eac7f4?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Sustainable building materials',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Sustainable building materials have come a long way. Today\'s eco-friendly options rival traditional materials in durability, beauty, and cost-effectiveness—while dramatically reducing your home\'s environmental footprint.',
                                'Whether you\'re planning a major renovation or a small update, choosing sustainable materials is easier than ever.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Best Sustainable Materials',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Bamboo flooring - rapidly renewable resource',
                                'Reclaimed wood - unique character, zero waste',
                                'Cork flooring - sustainable, comfortable, durable',
                                'Recycled glass countertops - beautiful and eco-friendly',
                                'Low-VOC paints - healthier indoor air quality',
                                'Wool insulation - natural, effective, non-toxic',
                                'Recycled steel - durable framing material',
                                'Clay or lime plaster - breathable wall finishes'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Energy-Efficient Homes: Complete Guide',
                'slug' => 'energy-efficient-homes-guide',
                'excerpt' => 'Reduce energy bills and environmental impact with these proven efficiency upgrades.',
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Energy efficient home',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'An energy-efficient home isn\'t just good for the planet—it\'s good for your wallet too. The right upgrades can cut your energy bills by 30-50% while increasing comfort and home value.',
                                'From simple no-cost changes to major investments, this guide covers the most effective ways to improve your home\'s energy efficiency.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Top Energy Efficiency Upgrades',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'items' => [
                                'Air sealing - biggest impact per dollar spent',
                                'Insulation upgrades - attic, walls, basement',
                                'High-efficiency HVAC system',
                                'Energy Star windows - reduce heat loss/gain',
                                'LED lighting throughout home',
                                'Smart thermostat - optimize heating/cooling',
                                'Solar panels - generate your own power',
                                'Energy-efficient appliances',
                                'Tankless water heater - on-demand hot water',
                                'Programmable power strips - eliminate phantom loads'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        foreach ($pages as $pageData) {
            $this->createThemedPage($site, $pageData, ['sustainable', 'eco-friendly', 'featured']);
        }

        echo "Created Sustainable Living theme content.\n";
    }

    private function addThemeGridsToHavenHearth($site): void
    {
        $homepage = Page::where('slug', 'home')->where('site_id', $site->id)->first();
        if (!$homepage) {
            echo "Haven & Hearth homepage not found.\n";
            return;
        }

        $maxOrder = $homepage->blocks()->max('order') ?? 0;

        // Group pages by theme
        $themes = [
            'scandinavian' => [
                'title' => 'Scandinavian Style',
                'subtitle' => 'Nordic minimalism and cozy hygge for your home'
            ],
            'garden' => [
                'title' => 'Garden & Outdoor',
                'subtitle' => 'Transform your outdoor spaces into beautiful retreats'
            ],
            'smart-home' => [
                'title' => 'Smart Home Tech',
                'subtitle' => 'Modern technology for comfortable living'
            ],
            'sustainable' => [
                'title' => 'Sustainable Living',
                'subtitle' => 'Eco-friendly choices for a better home and planet'
            ]
        ];

        $order = $maxOrder + 1;

        foreach ($themes as $themeKey => $themeData) {
            $themePages = array_filter($this->createdPages, function ($item) use ($themeKey) {
                return $item['theme'] === $themeKey;
            });

            $gridItems = [];
            foreach ($themePages as $item) {
                $page = $item['page'];

                // Get excerpt from custom fields
                $excerpt = '';
                foreach ($page->customFields as $cf) {
                    if ($cf->customFieldDefinition->key === 'excerpt') {
                        $excerpt = $cf->field_value;
                        break;
                    }
                }

                // Get first image from blocks
                $image = 'https://images.unsplash.com/photo-1556912173-3bb406ef7e77?auto=format&fit=crop&w=800&q=80';
                foreach ($page->blocks as $block) {
                    if ($block->type === 'image' && !empty($block->data['src'])) {
                        $image = $block->data['src'];
                        break;
                    }
                }

                $gridItems[] = [
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'excerpt' => $excerpt,
                    'image' => [
                        'src' => $image,
                        'alt' => $page->title
                    ],
                    'badge' => [
                        'text' => ucfirst($themeKey),
                        'color' => 'primary'
                    ],
                    'meta' => [
                        'readTime' => '5 min read'
                    ],
                    'actions' => [
                        [
                            'text' => 'Read More',
                            'url' => "/{$site->slug}/{$page->slug}",
                            'style' => 'primary'
                        ]
                    ]
                ];
            }

            if (!empty($gridItems)) {
                $this->blockRepository->create([
                    'page_id' => $homepage->id,
                    'type' => 'page_grid',
                    'data' => json_encode([
                        'title' => $themeData['title'],
                        'subtitle' => $themeData['subtitle'],
                        'layout' => 'grid',
                        'columns' => 3,
                        'showExcerpt' => true,
                        'showImage' => true,
                        'showFeatures' => false,
                        'showActions' => true,
                        'pages' => $gridItems,
                        'button' => [
                            'text' => 'View All ' . $themeData['title'],
                            'url' => "/{$site->slug}/category/" . $themeKey
                        ]
                    ]),
                    'order' => $order++
                ]);
            }
        }

        echo "Added " . count($themes) . " theme grids to Haven & Hearth homepage.\n";
    }

    private function createMoneyWeekProducts(): void
    {
        $moneyWeekSite = Site::find(49);
        if (!$moneyWeekSite) {
            echo "MoneyWeek site not found.\n";
            return;
        }

        // Create brand if doesn't exist
        $brand = Brand::firstOrCreate(
            ['slug' => 'financial-tools'],
            ['name' => 'Financial Tools', 'site_id' => $moneyWeekSite->id]
        );

        // Create category if doesn't exist
        $category = Category::firstOrCreate(
            ['slug' => 'investment-products', 'site_id' => $moneyWeekSite->id],
            ['name' => 'Investment Products']
        );

        $products = [
            [
                'name' => 'Premium Stock Screener Pro',
                'slug' => 'premium-stock-screener-pro',
                'description' => 'Advanced stock screening tool with real-time data, custom filters, and AI-powered insights. Perfect for serious investors looking to identify opportunities.',
                'price' => 299.00,
                'sale_price' => 249.00,
                'image' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=800&q=80',
                'meta_description' => 'Professional-grade stock screening with AI insights',
                'stock_quantity' => 999,
            ],
            [
                'name' => 'Portfolio Tracker Elite',
                'slug' => 'portfolio-tracker-elite',
                'description' => 'Comprehensive portfolio management software. Track performance, analyze risk, and optimize asset allocation across multiple accounts.',
                'price' => 199.00,
                'sale_price' => 149.00,
                'image' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=800&q=80',
                'meta_description' => 'Complete portfolio management and analysis',
                'stock_quantity' => 999,
            ],
            [
                'name' => 'Dividend Growth Analyzer',
                'slug' => 'dividend-growth-analyzer',
                'description' => 'Specialized tool for dividend investors. Analyze dividend history, growth rates, payout ratios, and forecast future income streams.',
                'price' => 149.00,
                'sale_price' => 0,
                'image' => 'https://images.unsplash.com/photo-1579621970588-a35d0e7ab936?auto=format&fit=crop&w=800&q=80',
                'meta_description' => 'Advanced dividend analysis and forecasting',
                'stock_quantity' => 999,
            ],
            [
                'name' => 'Tax Optimization Calculator',
                'slug' => 'tax-optimization-calculator',
                'description' => 'Maximize your after-tax returns with our sophisticated tax loss harvesting and optimization calculator. Supports UK and international accounts.',
                'price' => 129.00,
                'sale_price' => 99.00,
                'image' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=800&q=80',
                'meta_description' => 'Sophisticated tax optimization for investors',
                'stock_quantity' => 999,
            ],
            [
                'name' => 'Retirement Planning Suite',
                'slug' => 'retirement-planning-suite',
                'description' => 'Complete retirement planning toolkit. Model different scenarios, calculate required savings, and optimize withdrawal strategies.',
                'price' => 179.00,
                'sale_price' => 159.00,
                'image' => 'https://images.unsplash.com/photo-1579621970795-87facc2f976d?auto=format&fit=crop&w=800&q=80',
                'meta_description' => 'Comprehensive retirement planning tools',
                'stock_quantity' => 999,
            ],
            [
                'name' => 'Options Strategy Builder',
                'slug' => 'options-strategy-builder',
                'description' => 'Design, test, and execute options strategies with confidence. Includes risk analysis, profit/loss visualization, and position monitoring.',
                'price' => 249.00,
                'sale_price' => 0,
                'image' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=800&q=80',
                'meta_description' => 'Advanced options strategy analysis',
                'stock_quantity' => 999,
            ],
            [
                'name' => 'Real Estate Investment Calculator',
                'slug' => 'real-estate-investment-calculator',
                'description' => 'Analyze rental properties, REITs, and real estate investments. Calculate cash flow, ROI, cap rates, and compare financing options.',
                'price' => 159.00,
                'sale_price' => 129.00,
                'image' => 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=800&q=80',
                'meta_description' => 'Professional real estate investment analysis',
                'stock_quantity' => 999,
            ],
            [
                'name' => 'Crypto Portfolio Manager',
                'slug' => 'crypto-portfolio-manager',
                'description' => 'Track and manage cryptocurrency investments across multiple exchanges. Real-time pricing, tax reporting, and performance analytics.',
                'price' => 199.00,
                'sale_price' => 0,
                'image' => 'https://images.unsplash.com/photo-1621416894569-0f39ed31d247?auto=format&fit=crop&w=800&q=80',
                'meta_description' => 'Comprehensive crypto portfolio tracking',
                'stock_quantity' => 999,
            ],
            [
                'name' => 'Bond Ladder Builder',
                'slug' => 'bond-ladder-builder',
                'description' => 'Create and manage bond ladders for steady income. Analyze yields, maturities, and interest rate risk across different bond types.',
                'price' => 139.00,
                'sale_price' => 119.00,
                'image' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=800&q=80',
                'meta_description' => 'Strategic bond ladder construction tool',
                'stock_quantity' => 999,
            ],
            [
                'name' => 'Market News Aggregator Pro',
                'slug' => 'market-news-aggregator-pro',
                'description' => 'Stay ahead with AI-curated financial news. Custom alerts, sentiment analysis, and personalized news feeds for your portfolio.',
                'price' => 89.00,
                'sale_price' => 69.00,
                'image' => 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=800&q=80',
                'meta_description' => 'AI-powered financial news curation',
                'stock_quantity' => 999,
            ],
            [
                'name' => 'ESG Investment Screener',
                'slug' => 'esg-investment-screener',
                'description' => 'Invest according to your values. Screen stocks and funds based on environmental, social, and governance criteria.',
                'price' => 169.00,
                'sale_price' => 0,
                'image' => 'https://images.unsplash.com/photo-1532619187608-e5375cab36aa?auto=format&fit=crop&w=800&q=80',
                'meta_description' => 'Values-based investment screening',
                'stock_quantity' => 999,
            ],
            [
                'name' => 'Financial Independence Calculator',
                'slug' => 'financial-independence-calculator',
                'description' => 'Plan your path to financial independence. Calculate your FI number, track progress, and model different FIRE strategies.',
                'price' => 119.00,
                'sale_price' => 99.00,
                'image' => 'https://images.unsplash.com/photo-1579621970795-87facc2f976d?auto=format&fit=crop&w=800&q=80',
                'meta_description' => 'Achieve financial independence faster',
                'stock_quantity' => 999,
            ],
        ];

        foreach ($products as $productData) {
            $product = Product::create([
                'name' => $productData['name'],
                'slug' => $productData['slug'],
                'description' => $productData['description'],
                'price' => $productData['price'],
                'sale_price' => $productData['sale_price'],
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'image' => $productData['image'],
                'meta_title' => $productData['name'] . ' - MoneyWeek',
                'meta_description' => $productData['meta_description'],
                'site_id' => $moneyWeekSite->id,
                'is_active' => true,
                'stock_quantity' => $productData['stock_quantity'],
            ]);

            $this->createdMoneyWeekProducts[] = $product;
        }

        echo "Created " . count($this->createdMoneyWeekProducts) . " products for MoneyWeek.\n";
    }

    private function createVogueNoirProducts(): void
    {
        $site = Site::find(6); // Example site ID for Vogue Noir
        if (!$site) {
            echo "Vogue Noir site not found.\n";
            return;
        }

        // Create brand if not exists
        $brand = Brand::firstOrCreate(
            ['slug' => 'vogue-noir-collection'],
            ['name' => 'Vogue Noir Collection', 'site_id' => $site->id]
        );

        // Create category
        $category = Category::firstOrCreate(
            ['slug' => 'luxury-fashion', 'site_id' => $site->id],
            ['name' => 'Luxury Fashion & Beauty']
        );

        $products = [
            [
                'name' => 'Midnight Silk Evening Gown',
                'slug' => 'midnight-silk-evening-gown',
                'description' => 'A couture evening gown crafted with hand-stitched silk panels, featuring a flowing noir silhouette and sculpted waistline.',
                'price' => 899.00,
                'sale_price' => 749.00,
                'image' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?q=80&w=1170&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'meta_description' => 'Couture silk evening gown in noir finish',
                'stock_quantity' => 50,
            ],
            [
                'name' => 'Velvet Noir Blazer',
                'slug' => 'velvet-noir-blazer',
                'description' => 'A tailored luxury blazer made from deep velvet fabric, perfect for editorial looks and evening wear.',
                'price' => 499.00,
                'sale_price' => 449.00,
                'image' => 'https://images.unsplash.com/photo-1529139574466-a303027c1d8b?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'meta_description' => 'Tailored velvet blazer with a refined noir finish',
                'stock_quantity' => 150,
            ],
            [
                'name' => 'Crystal Stiletto Heels',
                'slug' => 'crystal-stiletto-heels',
                'description' => 'Limited-edition stilettos adorned with hand-set crystals, offering elegance, balance, and runway presence.',
                'price' => 379.00,
                'sale_price' => 0,
                'image' => 'https://plus.unsplash.com/premium_photo-1675186049419-d48f4b28fe7c?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'meta_description' => 'Hand-set crystal luxury heels',
                'stock_quantity' => 80,
            ],
            [
                'name' => 'Noir Leather Tote Bag',
                'slug' => 'noir-leather-tote-bag',
                'description' => 'Premium full-grain leather tote with a structured silhouette and gold hand-brushed hardware.',
                'price' => 279.00,
                'sale_price' => 249.00,
                'image' => 'https://images.unsplash.com/photo-1558769132-cb1aea458c5e?q=80&w=1074&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'meta_description' => 'Luxury leather tote crafted for elegance',
                'stock_quantity' => 200,
            ],
            [
                'name' => 'Signature Rouge Lipstick',
                'slug' => 'signature-rouge-lipstick',
                'description' => 'A bold, velvety red lipstick with long-lasting pigments and a hydrating satin finish.',
                'price' => 39.00,
                'sale_price' => 29.00,
                'image' => 'https://plus.unsplash.com/premium_photo-1677526496932-1b4bddeee554?q=80&w=784&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'meta_description' => 'Signature deep red luxury lipstick',
                'stock_quantity' => 500,
            ],
            [
                'name' => 'Gold Detail Sunglasses',
                'slug' => 'gold-detail-sunglasses',
                'description' => 'Oversized sunglasses with gold-trim titanium frames, crafted for runway appeal.',
                'price' => 159.00,
                'sale_price' => 0,
                'image' => 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?q=80&w=880&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'meta_description' => 'Oversized luxury sunglasses with gold detailing',
                'stock_quantity' => 300,
            ],
            [
                'name' => 'Designer Leather Boots',
                'slug' => 'designer-leather-boots',
                'description' => 'High-ankle boots handcrafted from premium calf leather with a dramatic noir polish.',
                'price' => 349.00,
                'sale_price' => 299.00,
                'image' => 'https://plus.unsplash.com/premium_photo-1673367751802-ed858d3950d2?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'meta_description' => 'Handcrafted designer leather boots',
                'stock_quantity' => 120,
            ],
            [
                'name' => 'Silk Scarf – Midnight Pattern',
                'slug' => 'silk-scarf-midnight-pattern',
                'description' => 'A lightweight 100% silk scarf featuring an exclusive midnight abstract pattern.',
                'price' => 119.00,
                'sale_price' => 99.00,
                'image' => 'https://plus.unsplash.com/premium_photo-1672680441245-fcd192661cb9?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MXx8c2lsayUyMHNjYXJmfGVufDB8fDB8fHww',
                'meta_description' => 'Luxury silk scarf with bespoke midnight pattern',
                'stock_quantity' => 400,
            ],
            [
                'name' => 'Noir Edition Wristwatch',
                'slug' => 'noir-edition-wristwatch',
                'description' => 'A minimalist wristwatch with a matte black steel case, sapphire glass, and Japanese precision movement.',
                'price' => 229.00,
                'sale_price' => 199.00,
                'image' => 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8Mnx8d2F0Y2h8ZW58MHx8MHx8fDA%3D',
                'meta_description' => 'Minimalist noir timepiece with sapphire glass',
                'stock_quantity' => 180,
            ],
            [
                'name' => 'Matte Black Perfume – Eclipse',
                'slug' => 'matte-black-perfume-eclipse',
                'description' => 'A sensual unisex perfume with notes of amber, cedar, and midnight florals in a matte noir bottle.',
                'price' => 159.00,
                'sale_price' => 139.00,
                'image' => 'https://images.unsplash.com/photo-1523293182086-7651a899d37f?q=80&w=1170&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'meta_description' => 'Noir-inspired unisex luxury fragrance',
                'stock_quantity' => 250,
            ],
            [
                'name' => 'Designer Statement Earrings',
                'slug' => 'designer-statement-earrings',
                'description' => 'A bold pair of gold-plated statement earrings designed for high-fashion editorial pieces.',
                'price' => 89.00,
                'sale_price' => 0,
                'image' => 'https://images.unsplash.com/photo-1629224316810-9d8805b95e76?q=80&w=1170&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'meta_description' => 'Gold-plated couture statement earrings',
                'stock_quantity' => 350,
            ],
            [
                'name' => 'Noir Velvet Evening Gloves',
                'slug' => 'noir-velvet-evening-gloves',
                'description' => 'Full-length velvet gloves designed for high-fashion editorial elegance.',
                'price' => 129.00,
                'sale_price' => 99.00,
                'image' => 'https://images.unsplash.com/photo-1617118602199-d3c05ae37ed8?q=80&w=1170&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'meta_description' => 'Elegant velvet evening gloves in deep noir',
                'stock_quantity' => 300,
            ],
            [
                'name' => 'Pearl Noir Choker Necklace',
                'slug' => 'pearl-noir-choker-necklace',
                'description' => 'A modern reinterpretation of the classic pearl choker with dark metallic accents.',
                'price' => 179.00,
                'sale_price' => 149.00,
                'image' => 'https://images.unsplash.com/photo-1589128777073-263566ae5e4d?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'meta_description' => 'Modern pearl choker with noir accents',
                'stock_quantity' => 180,
            ],
            [
                'name' => 'Noir Lace Bodysuit',
                'slug' => 'noir-lace-bodysuit',
                'description' => 'A delicately crafted bodysuit featuring intricate lace and sculpted contours.',
                'price' => 229.00,
                'sale_price' => 0,
                'image' => 'https://placehold.co/900x600/171717/ffffff?text=Noir+Lace+Bodysuit',
                'meta_description' => 'Luxury lace bodysuit with sculpted contours',
                'stock_quantity' => 140,
            ],
            [
                'name' => 'Gold Accent Belt',
                'slug' => 'gold-accent-belt',
                'description' => 'A minimalistic designer belt featuring a polished gold clasp and matte noir leather.',
                'price' => 159.00,
                'sale_price' => 139.00,
                'image' => 'https://placehold.co/900x600/171717/ffffff?text=Gold+Accent+Belt',
                'meta_description' => 'Designer noir leather belt with gold accents',
                'stock_quantity' => 260,
            ],
            [
                'name' => 'Editorial Haute Couture Hat',
                'slug' => 'editorial-haute-couture-hat',
                'description' => 'A dramatic structured hat designed for photo shoots and red-carpet events.',
                'price' => 289.00,
                'sale_price' => 0,
                'image' => 'https://placehold.co/900x600/171717/ffffff?text=Couture+Hat',
                'meta_description' => 'Statement couture hat for high-fashion styling',
                'stock_quantity' => 90,
            ],
            [
                'name' => 'Noir Satin Clutch',
                'slug' => 'noir-satin-clutch',
                'description' => 'A sleek satin clutch with a magnetic gold clasp and compact vanity mirror.',
                'price' => 199.00,
                'sale_price' => 179.00,
                'image' => 'https://placehold.co/900x600/171717/ffffff?text=Noir+Satin+Clutch',
                'meta_description' => 'Elegant satin clutch with gold detailing',
                'stock_quantity' => 220,
            ],
            [
                'name' => 'Runway Sheer Tights',
                'slug' => 'runway-sheer-tights',
                'description' => 'Premium sheer tights engineered for durability, comfort, and editorial styling.',
                'price' => 49.00,
                'sale_price' => 39.00,
                'image' => 'https://placehold.co/900x600/171717/ffffff?text=Runway+Sheer+Tights',
                'meta_description' => 'Premium sheer tights designed for runway quality',
                'stock_quantity' => 600,
            ],
            [
                'name' => 'Noir Sculpted Corset',
                'slug' => 'noir-sculpted-corset',
                'description' => 'A structured corset with steel boning and satin finishing for dramatic silhouettes.',
                'price' => 249.00,
                'sale_price' => 0,
                'image' => 'https://placehold.co/900x600/171717/ffffff?text=Noir+Sculpted+Corset',
                'meta_description' => 'High-fashion sculpted corset with satin finish',
                'stock_quantity' => 120,
            ],

        ];

        foreach ($products as $productData) {
            $product = Product::create([
                'name' => $productData['name'],
                'slug' => $productData['slug'],
                'description' => $productData['description'],
                'price' => $productData['price'],
                'sale_price' => $productData['sale_price'],
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'image' => $productData['image'],
                'meta_title' => $productData['name'] . ' - Vogue Noir',
                'meta_description' => $productData['meta_description'],
                'site_id' => $site->id,
                'is_active' => true,
                'stock_quantity' => $productData['stock_quantity'],
            ]);

            $this->createdVogueNoirProducts[] = $product;
        }

        echo "Created " . count($this->createdVogueNoirProducts) . " products for Vogue Noir.\n";
    }

    private function createTechWeeklyProducts(): void
    {
        $site = Site::find(2); // Example site ID for Tech Weekly
        if (!$site) {
            echo "Tech Weekly site not found.\n";
            return;
        }

        $brand = Brand::firstOrCreate(
            ['slug' => 'tech-weekly-gear'],
            ['name' => 'Tech Weekly Gear', 'site_id' => $site->id]
        );

        $category = Category::firstOrCreate(
            ['slug' => 'consumer-electronics', 'site_id' => $site->id],
            ['name' => 'Consumer Electronics & Gadgets']
        );

        $products = [
            [
                'name' => 'QuantumForce Pro Gaming Laptop',
                'slug' => 'quantumforce-pro-laptop',
                'description' => 'A high-performance gaming laptop with a 16-core processor, 240Hz display, and liquid metal cooling.',
                'price' => 1899.00,
                'sale_price' => 1699.00,
                'image' => 'https://placehold.co/900x600/29335C/ffffff?text=Gaming+Laptop',
                'meta_description' => 'High-performance gaming laptop with liquid cooling and a 240Hz display',
                'stock_quantity' => 40,
            ],
            [
                'name' => 'AeroPod Wireless Earbuds X',
                'slug' => 'aeropod-wireless-earbuds-x',
                'description' => 'Noise-cancelling wireless earbuds with adaptive EQ technology for an immersive listening experience.',
                'price' => 179.00,
                'sale_price' => 149.00,
                'image' => 'https://placehold.co/900x600/29335C/ffffff?text=Wireless+Earbuds',
                'meta_description' => 'Wireless earbuds with advanced noise cancellation',
                'stock_quantity' => 300,
            ],
            [
                'name' => 'HoloView 4K Smart Monitor',
                'slug' => 'holoview-4k-monitor',
                'description' => 'A frameless 4K monitor with integrated smart controls and ultra-wide color accuracy.',
                'price' => 499.00,
                'sale_price' => 0,
                'image' => 'https://placehold.co/900x600/29335C/ffffff?text=4K+Monitor',
                'meta_description' => 'Frameless 4K smart monitor with color-accurate display',
                'stock_quantity' => 110,
            ],
            [
                'name' => 'TechShield Portable SSD 2TB',
                'slug' => 'techshield-portable-ssd',
                'description' => 'Ultra-fast NVMe portable SSD with rugged casing and 2TB of secure storage.',
                'price' => 249.00,
                'sale_price' => 219.00,
                'image' => 'https://placehold.co/900x600/29335C/ffffff?text=Portable+SSD+2TB',
                'meta_description' => 'Rugged NVMe portable SSD with 2TB capacity',
                'stock_quantity' => 250,
            ],
            [
                'name' => 'HyperCore Mechanical Keyboard Pro',
                'slug' => 'hypercore-mechanical-keyboard-pro',
                'description' => 'Customizable RGB mechanical keyboard with hot-swappable switches and aluminum casing.',
                'price' => 159.00,
                'sale_price' => 139.00,
                'image' => 'https://placehold.co/900x600/29335C/ffffff?text=Mechanical+Keyboard',
                'meta_description' => 'RGB mechanical keyboard with aluminum frame',
                'stock_quantity' => 350,
            ],
            [
                'name' => 'AeroWave Wireless Headset',
                'slug' => 'aerowave-wireless-headset',
                'description' => 'Noise-cancelling wireless headset with 40-hour battery life and spatial audio.',
                'price' => 199.00,
                'sale_price' => 169.00,
                'image' => 'https://placehold.co/900x600/29335C/ffffff?text=Wireless+Headset',
                'meta_description' => 'Wireless noise-cancelling headset with spatial audio',
                'stock_quantity' => 260,
            ],
            [
                'name' => 'SmartDesk LightBar Pro',
                'slug' => 'smartdesk-lightbar-pro',
                'description' => 'LED monitor lightbar with adaptive brightness and blue-light reduction.',
                'price' => 89.00,
                'sale_price' => 79.00,
                'image' => 'https://placehold.co/900x600/29335C/ffffff?text=Desk+LightBar',
                'meta_description' => 'Adaptive LED monitor lightbar for desk setups',
                'stock_quantity' => 420,
            ],
            [
                'name' => 'NanoLink Mini Drone',
                'slug' => 'nanolink-mini-drone',
                'description' => 'Compact 4K mini drone with gesture control, GPS stabilization, and obstacle sensors.',
                'price' => 299.00,
                'sale_price' => 249.00,
                'image' => 'https://placehold.co/900x600/29335C/ffffff?text=Mini+Drone',
                'meta_description' => 'Ultra-compact 4K drone with GPS stabilization',
                'stock_quantity' => 190,
            ],
            [
                'name' => 'VoltCharge GaN Fast Charger 120W',
                'slug' => 'voltcharge-gan-120w',
                'description' => 'Next-generation GaN charger with triple-port output for laptops, tablets, and phones.',
                'price' => 89.00,
                'sale_price' => 69.00,
                'image' => 'https://placehold.co/900x600/29335C/ffffff?text=GaN+Charger+120W',
                'meta_description' => '120W GaN fast charger with triple-port output',
                'stock_quantity' => 500,
            ],
            [
                'name' => 'CyberMount Adjustable Laptop Stand',
                'slug' => 'cybermount-laptop-stand',
                'description' => 'Ergonomic aluminum laptop stand with full-angle rotation and cooling vents.',
                'price' => 59.00,
                'sale_price' => 49.00,
                'image' => 'https://placehold.co/900x600/29335C/ffffff?text=Laptop+Stand',
                'meta_description' => 'Ergonomic adjustable laptop stand with cooling',
                'stock_quantity' => 600,
            ],
            [
                'name' => 'PulseFit Smartwatch Series X',
                'slug' => 'pulsefit-smartwatch-series-x',
                'description' => 'Advanced fitness smartwatch with ECG, sleep tracking, and 2-week battery life.',
                'price' => 229.00,
                'sale_price' => 199.00,
                'image' => 'https://placehold.co/900x600/29335C/ffffff?text=Smartwatch+X',
                'meta_description' => 'Health-focused smartwatch with long battery life',
                'stock_quantity' => 300,
            ],
            [
                'name' => 'QuantumTouch Stylus Pen',
                'slug' => 'quantumtouch-stylus-pen',
                'description' => 'Precision stylus with 4096 pressure levels for tablets and touchscreen laptops.',
                'price' => 59.00,
                'sale_price' => 0,
                'image' => 'https://placehold.co/900x600/29335C/ffffff?text=Stylus+Pen',
                'meta_description' => 'Professional-grade stylus for artists and designers',
                'stock_quantity' => 280,
            ],
        ];

        foreach ($products as $productData) {
            $product = Product::create([
                'name' => $productData['name'],
                'slug' => $productData['slug'],
                'description' => $productData['description'],
                'price' => $productData['price'],
                'sale_price' => $productData['sale_price'],
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'image' => $productData['image'],
                'meta_title' => $productData['name'] . ' - Tech Weekly',
                'meta_description' => $productData['meta_description'],
                'site_id' => $site->id,
                'is_active' => true,
                'stock_quantity' => $productData['stock_quantity'],
            ]);

            $this->createdTechWeeklyProducts[] = $product;
        }

        echo "Created " . count($this->createdTechWeeklyProducts) . " products for Tech Weekly.\n";
    }

    private function createGamesRadarProducts(): void
    {
        $site = Site::find(38); // Example site ID for GamesRadar
        if (!$site) {
            echo "GamesRadar site not found.\n";
            return;
        }

        $brand = Brand::firstOrCreate(
            ['slug' => 'gamesradar-essentials'],
            ['name' => 'GamesRadar Essentials', 'site_id' => $site->id]
        );

        $category = Category::firstOrCreate(
            ['slug' => 'gaming-accessories', 'site_id' => $site->id],
            ['name' => 'Gaming Gear & Accessories']
        );

        $products = [
            [
                'name' => 'Nebula RGB Mechanical Keyboard',
                'slug' => 'nebula-rgb-mechanical-keyboard',
                'description' => 'A premium mechanical keyboard with hot-swappable switches and full RGB spectrum lighting.',
                'price' => 149.00,
                'sale_price' => 129.00,
                'image' => 'https://placehold.co/900x600/9C27B0/ffffff?text=RGB+Keyboard',
                'meta_description' => 'Hot-swappable RGB gaming mechanical keyboard',
                'stock_quantity' => 220,
            ],
            [
                'name' => 'Astra Pro Gaming Headset',
                'slug' => 'astra-pro-gaming-headset',
                'description' => 'Surround-sound headset with deep bass drivers and breathable comfort padding.',
                'price' => 129.00,
                'sale_price' => 99.00,
                'image' => 'https://placehold.co/900x600/9C27B0/ffffff?text=Gaming+Headset',
                'meta_description' => 'Surround sound headset with comfort padding',
                'stock_quantity' => 280,
            ],
            [
                'name' => 'StealthPlay Elite Controller',
                'slug' => 'stealthplay-elite-controller',
                'description' => 'Pro-level wireless controller with adjustable triggers and swappable thumbsticks.',
                'price' => 199.00,
                'sale_price' => 169.00,
                'image' => 'https://placehold.co/900x600/9C27B0/ffffff?text=Elite+Controller',
                'meta_description' => 'Elite gaming controller with customizable components',
                'stock_quantity' => 180,
            ],
            [
                'name' => 'HyperMat XL Gaming Mouse Pad',
                'slug' => 'hypermat-xl-mouse-pad',
                'description' => 'Oversized anti-slip mouse pad with stitched edges and ultra-smooth tracking surface.',
                'price' => 39.00,
                'sale_price' => 0,
                'image' => 'https://placehold.co/900x600/9C27B0/ffffff?text=XL+Mouse+Pad',
                'meta_description' => 'XL gaming mouse pad with anti-slip base',
                'stock_quantity' => 600,
            ],
            [
                'name' => 'Nebula RGB Gaming Mouse',
                'slug' => 'nebula-rgb-gaming-mouse',
                'description' => 'Ultra-light gaming mouse with RGB lighting and 20,000 DPI optical sensor.',
                'price' => 79.00,
                'sale_price' => 59.00,
                'image' => 'https://placehold.co/900x600/9C27B0/ffffff?text=RGB+Gaming+Mouse',
                'meta_description' => 'High-DPI RGB gaming mouse for pro gamers',
                'stock_quantity' => 450,
            ],
            [
                'name' => 'RetroWave Console Dock',
                'slug' => 'retrowave-console-dock',
                'description' => 'Multi-console charging dock with retro neon lighting effects.',
                'price' => 129.00,
                'sale_price' => 0,
                'image' => 'https://placehold.co/900x600/9C27B0/ffffff?text=Console+Dock',
                'meta_description' => 'Retro-style console dock with neon lighting',
                'stock_quantity' => 200,
            ],
            [
                'name' => 'ArcForge Pro Fight Stick',
                'slug' => 'arcforge-pro-fight-stick',
                'description' => 'Tournament-grade arcade fight stick with Sanwa components and customizable artwork.',
                'price' => 199.00,
                'sale_price' => 179.00,
                'image' => 'https://placehold.co/900x600/9C27B0/ffffff?text=Pro+Fight+Stick',
                'meta_description' => 'Tournament fight stick with high-end components',
                'stock_quantity' => 130,
            ],
            [
                'name' => 'Galaxy Quest Gaming Chair',
                'slug' => 'galaxy-quest-gaming-chair',
                'description' => 'Ergonomic gaming chair with memory foam padding and adjustable lumbar support.',
                'price' => 299.00,
                'sale_price' => 259.00,
                'image' => 'https://placehold.co/900x600/9C27B0/ffffff?text=Gaming+Chair',
                'meta_description' => 'Premium gaming chair with ergonomic build',
                'stock_quantity' => 90,
            ],
            [
                'name' => 'PixelMaster Collectible Figures – Series 1',
                'slug' => 'pixelmaster-collectibles-series-1',
                'description' => 'A set of limited-edition collectible gaming figurines.',
                'price' => 49.00,
                'sale_price' => 39.00,
                'image' => 'https://placehold.co/900x600/9C27B0/ffffff?text=Collectible+Figures',
                'meta_description' => 'Limited-edition gaming collectible figurines',
                'stock_quantity' => 500,
            ],
            [
                'name' => 'Vortex Soundbar Mini',
                'slug' => 'vortex-soundbar-mini',
                'description' => 'Compact gaming soundbar with virtual surround and bass enhancement.',
                'price' => 119.00,
                'sale_price' => 99.00,
                'image' => 'https://placehold.co/900x600/9C27B0/ffffff?text=Soundbar+Mini',
                'meta_description' => 'Compact soundbar for immersive gaming audio',
                'stock_quantity' => 320,
            ],
            [
                'name' => 'TitanForge Gaming Desk Mat',
                'slug' => 'titanforge-gaming-desk-mat',
                'description' => 'XL desk mat with anti-slip base and smooth micro-weave fabric.',
                'price' => 39.00,
                'sale_price' => 29.00,
                'image' => 'https://placehold.co/900x600/9C27B0/ffffff?text=Gaming+Desk+Mat',
                'meta_description' => 'Oversized gaming desk mat with micro-weave fabric',
                'stock_quantity' => 650,
            ],
            [
                'name' => 'Legendary Loot Mystery Box',
                'slug' => 'legendary-loot-mystery-box',
                'description' => 'A curated mystery box packed with gaming merch, accessories, and exclusive collectibles.',
                'price' => 69.00,
                'sale_price' => 0,
                'image' => 'https://placehold.co/900x600/9C27B0/ffffff?text=Mystery+Box',
                'meta_description' => 'Exclusive themed mystery loot box for gamers',
                'stock_quantity' => 300,
            ],

        ];

        foreach ($products as $productData) {
            $product = Product::create([
                'name' => $productData['name'],
                'slug' => $productData['slug'],
                'description' => $productData['description'],
                'price' => $productData['price'],
                'sale_price' => $productData['sale_price'],
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'image' => $productData['image'],
                'meta_title' => $productData['name'] . ' - GamesRadar',
                'meta_description' => $productData['meta_description'],
                'site_id' => $site->id,
                'is_active' => true,
                'stock_quantity' => $productData['stock_quantity'],
            ]);

            $this->createdGamesRadarProducts[] = $product;
        }

        echo "Created " . count($this->createdGamesRadarProducts) . " products for GamesRadar.\n";
    }

    private function createHorseAndHoundProducts(): void
    {
        $site = Site::find(29); // Example site ID for Horse & Hound
        if (!$site) {
            echo "Horse & Hound site not found.\n";
            return;
        }

        $brand = Brand::firstOrCreate(
            ['slug' => 'horse-hound-essentials'],
            ['name' => 'Horse & Hound Essentials', 'site_id' => $site->id]
        );

        $category = Category::firstOrCreate(
            ['slug' => 'equestrian-gear', 'site_id' => $site->id],
            ['name' => 'Equestrian Equipment & Riding Gear']
        );

        $products = [
            [
                'name' => 'Premium Leather Riding Boots',
                'slug' => 'premium-leather-riding-boots',
                'description' => 'Handcrafted riding boots made from full-grain leather with reinforced stitching.',
                'price' => 299.00,
                'sale_price' => 259.00,
                'image' => 'https://placehold.co/900x600/4CAF50/ffffff?text=Riding+Boots',
                'meta_description' => 'Handcrafted equestrian leather riding boots',
                'stock_quantity' => 90,
            ],
            [
                'name' => 'EquiComfort Saddle Pad',
                'slug' => 'equicomfort-saddle-pad',
                'description' => 'Breathable, shock-absorbing saddle pad designed for long-distance riding comfort.',
                'price' => 79.00,
                'sale_price' => 0,
                'image' => 'https://placehold.co/900x600/4CAF50/ffffff?text=Saddle+Pad',
                'meta_description' => 'Comfort saddle pad with breathable materials',
                'stock_quantity' => 250,
            ],
            [
                'name' => 'StallMaster Grooming Kit',
                'slug' => 'stallmaster-grooming-kit',
                'description' => 'Complete grooming kit including brushes, combs, hoof picks, and detangling tools.',
                'price' => 119.00,
                'sale_price' => 99.00,
                'image' => 'https://placehold.co/900x600/4CAF50/ffffff?text=Grooming+Kit',
                'meta_description' => 'Full equestrian grooming kit with premium tools',
                'stock_quantity' => 180,
            ],
            [
                'name' => 'EquinePro Performance Bridle',
                'slug' => 'equinepro-performance-bridle',
                'description' => 'High-performance leather bridle with brass fittings and precision stitching.',
                'price' => 159.00,
                'sale_price' => 139.00,
                'image' => 'https://placehold.co/900x600/4CAF50/ffffff?text=Performance+Bridle',
                'meta_description' => 'Premium equestrian bridle with brass fittings',
                'stock_quantity' => 120,
            ],
            [
                'name' => 'Premium Leather Saddle Soap Kit',
                'slug' => 'premium-saddle-soap-kit',
                'description' => 'Complete saddle care kit including cleanser, conditioner, and polishing cloth.',
                'price' => 49.00,
                'sale_price' => 39.00,
                'image' => 'https://placehold.co/900x600/4CAF50/ffffff?text=Saddle+Soap+Kit',
                'meta_description' => 'Horse saddle cleaning and conditioning kit',
                'stock_quantity' => 500,
            ],
            [
                'name' => 'Rider’s Weatherproof Jacket',
                'slug' => 'riders-weatherproof-jacket',
                'description' => 'Breathable, waterproof jacket for year-round riding comfort.',
                'price' => 159.00,
                'sale_price' => 129.00,
                'image' => 'https://placehold.co/900x600/4CAF50/ffffff?text=Rider+Jacket',
                'meta_description' => 'Waterproof riding jacket for all-weather conditions',
                'stock_quantity' => 200,
            ],
            [
                'name' => 'SoftFleece Horse Cooler Blanket',
                'slug' => 'softfleece-horse-cooler',
                'description' => 'Lightweight moisture-wicking cooler blanket ideal for post-training.',
                'price' => 79.00,
                'sale_price' => 69.00,
                'image' => 'https://placehold.co/900x600/4CAF50/ffffff?text=Cooler+Blanket',
                'meta_description' => 'Moisture-wicking cooler blanket for horses',
                'stock_quantity' => 320,
            ],
            [
                'name' => 'Elite Riding Gloves – Grip Edition',
                'slug' => 'elite-riding-gloves',
                'description' => 'Premium and breathable riding gloves with reinforced grip patterns.',
                'price' => 39.00,
                'sale_price' => 29.00,
                'image' => 'https://placehold.co/900x600/4CAF50/ffffff?text=Riding+Gloves',
                'meta_description' => 'Reinforced breathable gloves for horse riders',
                'stock_quantity' => 550,
            ],
            [
                'name' => 'StableCare Grooming Brush Set',
                'slug' => 'stablecare-grooming-brush-set',
                'description' => 'A complete set of grooming brushes for coat, mane, and hooves.',
                'price' => 49.00,
                'sale_price' => 0,
                'image' => 'https://placehold.co/900x600/4CAF50/ffffff?text=Brush+Set',
                'meta_description' => 'Multi-brush grooming kit for horses',
                'stock_quantity' => 450,
            ],
            [
                'name' => 'TrailRider Thermal Socks',
                'slug' => 'trailrider-thermal-socks',
                'description' => 'Insulated riding socks designed for winter trail rides.',
                'price' => 25.00,
                'sale_price' => 19.00,
                'image' => 'https://placehold.co/900x600/4CAF50/ffffff?text=Thermal+Socks',
                'meta_description' => 'Thermal equestrian socks for cold weather',
                'stock_quantity' => 700,
            ],
            [
                'name' => 'Horse Hydration Portable Kit',
                'slug' => 'horse-hydration-portable-kit',
                'description' => 'Portable watering system designed for travel, shows, and long trail rides.',
                'price' => 89.00,
                'sale_price' => 79.00,
                'image' => 'https://placehold.co/900x600/4CAF50/ffffff?text=Hydration+Kit',
                'meta_description' => 'Portable hydration system for horses',
                'stock_quantity' => 210,
            ],
            [
                'name' => 'EquiTrack Horse Fitness Monitor',
                'slug' => 'equitrack-horse-fitness-monitor',
                'description' => 'Wearable horse fitness tracker that monitors speed, heart rate, and recovery.',
                'price' => 249.00,
                'sale_price' => 229.00,
                'image' => 'https://placehold.co/900x600/4CAF50/ffffff?text=Fitness+Monitor',
                'meta_description' => 'Advanced equine fitness and health tracker',
                'stock_quantity' => 100,
            ],

        ];

        foreach ($products as $productData) {
            $product = Product::create([
                'name' => $productData['name'],
                'slug' => $productData['slug'],
                'description' => $productData['description'],
                'price' => $productData['price'],
                'sale_price' => $productData['sale_price'],
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'image' => $productData['image'],
                'meta_title' => $productData['name'] . ' - Horse & Hound',
                'meta_description' => $productData['meta_description'],
                'site_id' => $site->id,
                'is_active' => true,
                'stock_quantity' => $productData['stock_quantity'],
            ]);

            $this->createdHorseAndHoundProducts[] = $product;
        }

        echo "Created " . count($this->createdHorseAndHoundProducts) . " products for Horse & Hound.\n";
    }

    private function createWineChronicleProducts(): void
    {
        $site = Site::find(10); // Example site ID for Wine Chronicle
        if (!$site) {
            echo "Wine Chronicle site not found.\n";
            return;
        }

        $brand = Brand::firstOrCreate(
            ['slug' => 'wine-chronicle-selection'],
            ['name' => 'The Wine Chronicle Selection', 'site_id' => $site->id]
        );

        $category = Category::firstOrCreate(
            ['slug' => 'fine-wine-spirits', 'site_id' => $site->id],
            ['name' => 'Fine Wine & Premium Spirits']
        );

        $products = [
            [
                'name' => 'Château Lumière 2018 Bordeaux',
                'slug' => 'chateau-lumiere-2018',
                'description' => 'A rich Bordeaux blend with notes of dark cherry, oak, and subtle spice.',
                'price' => 89.00,
                'sale_price' => 0,
                'image' => 'https://placehold.co/900x600/C62828/ffffff?text=Bordeaux+2018',
                'meta_description' => 'Luxurious 2018 Bordeaux with deep cherry notes',
                'stock_quantity' => 140,
            ],
            [
                'name' => 'Highland Crest 12-Year Scotch',
                'slug' => 'highland-crest-12-year-scotch',
                'description' => 'A smooth single-malt Scotch aged for 12 years, offering honey and oak undertones.',
                'price' => 119.00,
                'sale_price' => 99.00,
                'image' => 'https://placehold.co/900x600/C62828/ffffff?text=12-Year+Scotch',
                'meta_description' => '12-year single-malt Scotch with honeyed finish',
                'stock_quantity' => 85,
            ],
            [
                'name' => 'Rosadelight Sparkling Rosé',
                'slug' => 'rosadelight-sparkling-rose',
                'description' => 'A vibrant sparkling rosé with floral aromas and a crisp, refreshing finish.',
                'price' => 39.00,
                'sale_price' => 29.00,
                'image' => 'https://placehold.co/900x600/C62828/ffffff?text=Sparkling+Rosé',
                'meta_description' => 'Sparkling rosé with floral and crisp tasting notes',
                'stock_quantity' => 300,
            ],
            [
                'name' => 'Imperial Oak Barrel-Aged Gin',
                'slug' => 'imperial-oak-gin',
                'description' => 'A premium gin aged in oak barrels, featuring botanical complexity and a warm amber tone.',
                'price' => 69.00,
                'sale_price' => 59.00,
                'image' => 'https://placehold.co/900x600/C62828/ffffff?text=Barrel-Aged+Gin',
                'meta_description' => 'Oak-aged botanical gin with amber warmth',
                'stock_quantity' => 190,
            ],
            [
                'name' => 'Château Montclair Reserve 2015',
                'slug' => 'chateau-montclair-reserve-2015',
                'description' => 'A deep Bordeaux blend with notes of blackberry, cedar, and aged oak.',
                'price' => 129.00,
                'sale_price' => 0,
                'image' => 'https://placehold.co/900x600/C62828/ffffff?text=Reserve+2015',
                'meta_description' => 'Premium Bordeaux blend with oak-aged depth',
                'stock_quantity' => 90,
            ],
            [
                'name' => 'Amador Single Barrel Bourbon',
                'slug' => 'amador-single-barrel-bourbon',
                'description' => 'Rich caramel bourbon finished in toasted American oak barrels.',
                'price' => 89.00,
                'sale_price' => 79.00,
                'image' => 'https://placehold.co/900x600/C62828/ffffff?text=Single+Barrel+Bourbon',
                'meta_description' => 'Single barrel bourbon with caramel notes',
                'stock_quantity' => 140,
            ],
            [
                'name' => 'Vintage Port Classic 1999',
                'slug' => 'vintage-port-classic-1999',
                'description' => 'A matured port with plum, dark cherry, and chocolate undertones.',
                'price' => 159.00,
                'sale_price' => 139.00,
                'image' => 'https://placehold.co/900x600/C62828/ffffff?text=Vintage+Port+1999',
                'meta_description' => 'Matured vintage port with rich fruit notes',
                'stock_quantity' => 60,
            ],
            [
                'name' => 'Sommeliers’ Premium Wine Opener',
                'slug' => 'premium-wine-opener',
                'description' => 'A handcrafted sommelier tool with wood inlay and precision steel design.',
                'price' => 49.00,
                'sale_price' => 39.00,
                'image' => 'https://placehold.co/900x600/C62828/ffffff?text=Wine+Opener',
                'meta_description' => 'High-end sommelier corkscrew and opener',
                'stock_quantity' => 500,
            ],
            [
                'name' => 'Crystal Decanter – Aerate Edition',
                'slug' => 'crystal-decanter-aerate',
                'description' => 'Lead-free crystal decanter engineered for optimal aeration.',
                'price' => 119.00,
                'sale_price' => 0,
                'image' => 'https://placehold.co/900x600/C62828/ffffff?text=Crystal+Decanter',
                'meta_description' => 'Premium crystal wine decanter',
                'stock_quantity' => 240,
            ],
            [
                'name' => 'Highland Mist 12-Year Scotch',
                'slug' => 'highland-mist-12yr-scotch',
                'description' => 'Smooth highland scotch with sweet malt, honey, and a hint of smoke.',
                'price' => 139.00,
                'sale_price' => 119.00,
                'image' => 'https://placehold.co/900x600/C62828/ffffff?text=Highland+Mist+Scotch',
                'meta_description' => '12-year aged Highland scotch whisky',
                'stock_quantity' => 160,
            ],
            [
                'name' => 'Reserve Cabernet Sauvignon 2018',
                'slug' => 'reserve-cabernet-sauvignon-2018',
                'description' => 'A bold California Cabernet with ripe tannins and dark cherry notes.',
                'price' => 89.00,
                'sale_price' => 79.00,
                'image' => 'https://placehold.co/900x600/C62828/ffffff?text=Cabernet+2018',
                'meta_description' => 'Full-bodied cabernet with cherry and oak',
                'stock_quantity' => 200,
            ],
            [
                'name' => 'Master Distiller’s Gin – Citrus Twist',
                'slug' => 'distillers-gin-citrus-twist',
                'description' => 'Small-batch gin infused with lemon peel, juniper, and Mediterranean herbs.',
                'price' => 59.00,
                'sale_price' => 49.00,
                'image' => 'https://placehold.co/900x600/C62828/ffffff?text=Citrus+Twist+Gin',
                'meta_description' => 'Craft gin with citrus and herbal notes',
                'stock_quantity' => 210,
            ],
        ];

        foreach ($products as $productData) {
            $product = Product::create([
                'name' => $productData['name'],
                'slug' => $productData['slug'],
                'description' => $productData['description'],
                'price' => $productData['price'],
                'sale_price' => $productData['sale_price'],
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'image' => $productData['image'],
                'meta_title' => $productData['name'] . ' - The Wine Chronicle',
                'meta_description' => $productData['meta_description'],
                'site_id' => $site->id,
                'is_active' => true,
                'stock_quantity' => $productData['stock_quantity'],
            ]);

            $this->createdWineChronicleProducts[] = $product;
        }

        echo "Created " . count($this->createdWineChronicleProducts) . " products for The Wine Chronicle.\n";
    }

    private function addProductGridsToMoneyWeek(): void
    {
        $moneyWeekSite = Site::find(49);
        if (!$moneyWeekSite) return;

        $homepage = Page::where('slug', 'home')->where('site_id', $moneyWeekSite->id)->first();
        if (!$homepage) {
            echo "MoneyWeek homepage not found.\n";
            return;
        }

        $maxOrder = $homepage->blocks()->max('order') ?? 0;

        // Product Grid 1: Best Sellers
        $bestSellerProducts = array_slice($this->createdMoneyWeekProducts, 0, 4);
        $bestSellerItems = [];
        foreach ($bestSellerProducts as $product) {
            $bestSellerItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->price, 2),
                'badge' => $product->sale_price > 0 ? [
                    'text' => 'Save £' . number_format($product->price - $product->sale_price, 2),
                    'color' => 'success'
                ] : null,
                'actions' => [
                    [
                        'text' => 'View Details',
                        'url' => "http://localhost:5001/shop/details/{$product->slug}",
                        'style' => 'primary'
                    ]
                ]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'Recommended Investment Tools',
                'subtitle' => 'Professional software to enhance your investment strategy',
                'layout' => 'grid',
                'columns' => 4,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $bestSellerItems
            ]),
            'order' => $maxOrder + 1
        ]);

        // Product Grid 2: On Sale
        $saleProducts = array_filter($this->createdMoneyWeekProducts, function ($p) {
            return $p->sale_price > 0;
        });
        $saleProducts = array_slice(array_values($saleProducts), 0, 3);

        $saleItems = [];
        foreach ($saleProducts as $product) {
            $saleItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->sale_price, 2),
                'badge' => [
                    'text' => number_format((($product->price - $product->sale_price) / $product->price) * 100) . '% OFF',
                    'color' => 'warning'
                ],
                'actions' => [
                    [
                        'text' => 'Get Deal',
                        'url' => "http://localhost:5001/shop/details/{$product->slug}",
                        'style' => 'primary'
                    ]
                ]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'Limited Time Offers',
                'subtitle' => 'Save on premium investment tools',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $saleItems
            ]),
            'order' => $maxOrder + 2
        ]);

        // Product Grid 3: New Arrivals
        $newProducts = array_slice($this->createdMoneyWeekProducts, -3);
        $newItems = [];
        foreach ($newProducts as $product) {
            $newItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->price, 2),
                'badge' => [
                    'text' => 'New',
                    'color' => 'primary'
                ],
                'actions' => [
                    [
                        'text' => 'Learn More',
                        'url' => "http://localhost:5001/shop/details/{$product->slug}",
                        'style' => 'primary'
                    ]
                ]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'Newly Added Tools',
                'subtitle' => 'The latest additions to our investment toolkit',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $newItems
            ]),
            'order' => $maxOrder + 3
        ]);

        echo "Added 3 product grids to MoneyWeek homepage.\n";
    }

    private function addTeamBlockToMoneyWeek(): void
    {
        $moneyWeekSite = Site::find(49);
        if (!$moneyWeekSite) return;

        $homepage = Page::where('slug', 'home')->where('site_id', $moneyWeekSite->id)->first();
        if (!$homepage) return;

        $maxOrder = $homepage->blocks()->max('order') ?? 0;

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'team',
            'data' => json_encode([
                'title' => 'Our Editorial Team',
                'subtitle' => 'Expert analysis from seasoned financial professionals',
                'layout' => 'grid',
                'members' => [
                    [
                        'name' => 'Michael Sterling',
                        'role' => 'Chief Investment Strategist',
                        'bio' => 'CFA with 20+ years analyzing global markets. Former portfolio manager at major investment firms.',
                        'email' => 'michael.sterling@moneyweek.com',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80',
                            'alt' => 'Michael Sterling'
                        ],
                        'specialties' => ['Equities', 'Portfolio Strategy', 'Risk Management']
                    ],
                    [
                        'name' => 'Elizabeth Gray',
                        'role' => 'Fintech & Policy Editor',
                        'bio' => 'Former financial regulator covering central banking, digital currency, and fintech innovation.',
                        'email' => 'elizabeth.gray@moneyweek.com',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=400&q=80',
                            'alt' => 'Elizabeth Gray'
                        ],
                        'specialties' => ['CBDC', 'Fintech', 'Regulatory Policy']
                    ],
                    [
                        'name' => 'David Huang',
                        'role' => 'Cryptocurrency Analyst',
                        'bio' => 'Early Bitcoin investor and blockchain technology expert. Specializes in digital asset markets.',
                        'email' => 'david.huang@moneyweek.com',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=400&q=80',
                            'alt' => 'David Huang'
                        ],
                        'specialties' => ['Cryptocurrency', 'Blockchain', 'DeFi']
                    ],
                    [
                        'name' => 'Sarah Thompson',
                        'role' => 'Personal Finance Columnist',
                        'bio' => 'Certified Financial Planner helping readers build wealth through practical savings and investment strategies.',
                        'email' => 'sarah.thompson@moneyweek.com',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=400&q=80',
                            'alt' => 'Sarah Thompson'
                        ],
                        'specialties' => ['Retirement Planning', 'Tax Strategy', 'Budgeting']
                    ],
                    [
                        'name' => 'James Patterson',
                        'role' => 'Markets Correspondent',
                        'bio' => 'Real-time market analysis and breaking financial news. Former Bloomberg reporter.',
                        'email' => 'james.patterson@moneyweek.com',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=400&q=80',
                            'alt' => 'James Patterson'
                        ],
                        'specialties' => ['Market News', 'Technical Analysis', 'Trading']
                    ],
                    [
                        'name' => 'Rebecca Lawson',
                        'role' => 'Economics Editor',
                        'bio' => 'PhD economist covering macroeconomic trends, monetary policy, and global trade.',
                        'email' => 'rebecca.lawson@moneyweek.com',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?auto=format&fit=crop&w=400&q=80',
                            'alt' => 'Rebecca Lawson'
                        ],
                        'specialties' => ['Macroeconomics', 'Central Banking', 'Inflation']
                    ]
                ]
            ]),
            'order' => $maxOrder + 4
        ]);

        echo "Added team block to MoneyWeek homepage.\n";
    }

    private function addTeamBlockToHavenAndHearth(): void
    {
        $site = Site::find(8); // Example site ID for Haven & Hearth
        if (!$site) return;

        $homepage = Page::where('slug', 'home')->where('site_id', $site->id)->first();
        if (!$homepage) return;

        $maxOrder = $homepage->blocks()->max('order') ?? 0;

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'team',
            'data' => json_encode([
                'title' => 'Meet Our Editorial Team',
                'subtitle' => 'Crafting inspiration for every room, garden, and gathering',
                'layout' => 'grid',
                'members' => [
                    [
                        'name' => 'Clara Whitford',
                        'role' => 'Editor-in-Chief, Interiors',
                        'bio' => 'Award-winning interior designer with two decades of experience creating warm, modern living spaces.',
                        'email' => 'clara.whitford@havenhearth.com',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?q=80&w=1170&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                            'alt' => 'Clara Whitford'
                        ],
                        'specialties' => ['Interior Design', 'Home Styling', 'Modern Rustic Aesthetics']
                    ],
                    [
                        'name' => 'Thomas Greenfield',
                        'role' => 'Garden & Landscaping Editor',
                        'bio' => 'Horticulturist and landscape consultant helping readers cultivate sustainable, beautiful outdoor spaces.',
                        'email' => 'thomas.greenfield@havenhearth.com',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1599566150163-29194dcaad36?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                            'alt' => 'Thomas Greenfield'
                        ],
                        'specialties' => ['Gardening', 'Landscape Design', 'Sustainability']
                    ],
                    [
                        'name' => 'Elena Maxwell',
                        'role' => 'Home Décor Specialist',
                        'bio' => 'Décor stylist focused on seasonal aesthetics, DIY projects, and budget-friendly transformations.',
                        'email' => 'elena.maxwell@havenhearth.com',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?q=80&w=688&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                            'alt' => 'Elena Maxwell'
                        ],
                        'specialties' => ['Home Décor', 'DIY', 'Seasonal Styling']
                    ],
                    [
                        'name' => 'Oliver Hart',
                        'role' => 'Home Improvement Editor',
                        'bio' => 'Craftsman and renovation expert offering practical guidance for small projects and full remodels.',
                        'email' => 'oliver.hart@havenhearth.com',
                        'image' => [
                            'src' => 'https://plus.unsplash.com/premium_photo-1678197937465-bdbc4ed95815?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                            'alt' => 'Oliver Hart'
                        ],
                        'specialties' => ['DIY Renovation', 'Tools & Hardware', 'Construction Tips']
                    ],
                    [
                        'name' => 'Sophie Maren',
                        'role' => 'Lifestyle & Entertaining Editor',
                        'bio' => 'Event stylist and recipe developer helping readers create meaningful gatherings and beautiful tablescapes.',
                        'email' => 'sophie.maren@havenhearth.com',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?q=80&w=761&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                            'alt' => 'Sophie Maren'
                        ],
                        'specialties' => ['Entertaining', 'Tablescapes', 'Lifestyle']
                    ],
                    [
                        'name' => 'Henry Porter',
                        'role' => 'Sustainability & Eco-Living Columnist',
                        'bio' => 'Environmental writer covering low-waste living, natural materials, and eco-friendly home solutions.',
                        'email' => 'henry.porter@havenhearth.com',
                        'image' => [
                            'src' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                            'alt' => 'Henry Porter'
                        ],
                        'specialties' => ['Eco-Living', 'Sustainable Homes', 'Green Materials']
                    ]
                ]
            ]),
            'order' => $maxOrder + 4
        ]);

        echo "Added team block to Haven & Hearth homepage.\n";
    }

    private function addProductGridsToVogueNoir(): void
    {
        $site = Site::find(6); // example site ID
        if (!$site) return;

        $homepage = Page::where('slug', 'home')->where('site_id', $site->id)->first();
        if (!$homepage) {
            echo "Vogue Noir homepage not found.\n";
            return;
        }

        $maxOrder = $homepage->blocks()->max('order') ?? 0;

        /** --------------------
         *  Grid 1: Best Sellers
         * -------------------- */
        $bestSellerProducts = array_slice($this->createdVogueNoirProducts, 0, 4);
        $bestSellerItems = [];
        foreach ($bestSellerProducts as $product) {
            $bestSellerItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->price, 2),
                'badge' => $product->sale_price > 0 ? [
                    'text' => 'Save £' . number_format($product->price - $product->sale_price, 2),
                    'color' => 'success'
                ] : null,
                'actions' => [
                    [
                        'text' => 'View Details',
                        'url' => "http://localhost:5001/shop/details/{$product->slug}",
                        'style' => 'primary'
                    ]
                ]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'The Noir Edit',
                'subtitle' => 'Curated luxury pieces redefining modern couture',
                'layout' => 'grid',
                'columns' => 4,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $bestSellerItems
            ]),
            'order' => $maxOrder + 1
        ]);

        /** --------------------
         *  Grid 2: On Sale
         * -------------------- */
        $saleProducts = array_values(array_filter($this->createdVogueNoirProducts, fn($p) => $p->sale_price > 0));
        $saleProducts = array_slice($saleProducts, 0, 3);

        $saleItems = [];
        foreach ($saleProducts as $product) {
            $saleItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->sale_price, 2),
                'badge' => [
                    'text' => number_format((($product->price - $product->sale_price) / $product->price) * 100) . '% OFF',
                    'color' => 'warning'
                ],
                'actions' => [[
                    'text' => 'Get Deal',
                    'url' => "http://localhost:5001/shop/details/{$product->slug}",
                    'style' => 'primary'
                ]]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'Midnight Icons',
                'subtitle' => 'Signature fashion statements for the bold and timeless',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $saleItems
            ]),
            'order' => $maxOrder + 2
        ]);

        /** --------------------
         *  Grid 3: New Arrivals
         * -------------------- */
        $newProducts = array_slice($this->createdVogueNoirProducts, -3);

        $newItems = [];
        foreach ($newProducts as $product) {
            $newItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->price, 2),
                'badge' => ['text' => 'New', 'color' => 'primary'],
                'actions' => [[
                    'text' => 'Learn More',
                    'url' => "http://localhost:5001/shop/details/{$product->slug}",
                    'style' => 'primary'
                ]]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'Runway Reverie',
                'subtitle' => 'Where high-fashion artistry meets everyday elegance',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $newItems
            ]),
            'order' => $maxOrder + 3
        ]);

        echo "Added 3 product grids to Vogue Noir homepage.\n";
    }

    private function addProductGridsToTechWeekly(): void
    {
        $site = Site::find(2); // example site ID
        if (!$site) return;

        $homepage = Page::where('slug', 'home')->where('site_id', $site->id)->first();
        if (!$homepage) {
            echo "Tech Weekly Noir homepage not found.\n";
            return;
        }

        $maxOrder = $homepage->blocks()->max('order') ?? 0;

        /** --------------------
         *  Grid 1: Best Sellers
         * -------------------- */
        $bestSellerProducts = array_slice($this->createdTechWeeklyProducts, 0, 4);
        $bestSellerItems = [];
        foreach ($bestSellerProducts as $product) {
            $bestSellerItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->price, 2),
                'badge' => $product->sale_price > 0 ? [
                    'text' => 'Save £' . number_format($product->price - $product->sale_price, 2),
                    'color' => 'success'
                ] : null,
                'actions' => [
                    [
                        'text' => 'View Details',
                        'url' => "http://localhost:5001/shop/details/{$product->slug}",
                        'style' => 'primary'
                    ]
                ]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'Future Tech Essentials',
                'subtitle' => 'Must-have devices shaping tomorrow’s digital world',
                'layout' => 'grid',
                'columns' => 4,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $bestSellerItems
            ]),
            'order' => $maxOrder + 1
        ]);

        /** --------------------
         *  Grid 2: On Sale
         * -------------------- */
        $saleProducts = array_values(array_filter($this->createdTechWeeklyProducts, fn($p) => $p->sale_price > 0));
        $saleProducts = array_slice($saleProducts, 0, 3);

        $saleItems = [];
        foreach ($saleProducts as $product) {
            $saleItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->sale_price, 2),
                'badge' => [
                    'text' => number_format((($product->price - $product->sale_price) / $product->price) * 100) . '% OFF',
                    'color' => 'warning'
                ],
                'actions' => [[
                    'text' => 'Get Deal',
                    'url' => "http://localhost:5001/shop/details/{$product->slug}",
                    'style' => 'primary'
                ]]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'The Innovation Index',
                'subtitle' => 'Cutting-edge gear ranked, tested, and decoded',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $saleItems
            ]),
            'order' => $maxOrder + 2
        ]);

        /** --------------------
         *  Grid 3: New Arrivals
         * -------------------- */
        $newProducts = array_slice($this->createdTechWeeklyProducts, -3);

        $newItems = [];
        foreach ($newProducts as $product) {
            $newItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->price, 2),
                'badge' => ['text' => 'New', 'color' => 'primary'],
                'actions' => [[
                    'text' => 'Learn More',
                    'url' => "http://localhost:5001/shop/details/{$product->slug}",
                    'style' => 'primary'
                ]]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'Power Up Your Workflow',
                'subtitle' => 'Tools and gadgets engineered for peak productivity',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $newItems
            ]),
            'order' => $maxOrder + 3
        ]);

        echo "Added 3 product grids to Tech Weekly homepage.\n";
    }

    private function addProductGridsToGamesRadar(): void
    {
        $site = Site::find(38); // example site ID
        if (!$site) return;

        $homepage = Page::where('slug', 'home')->where('site_id', $site->id)->first();
        if (!$homepage) {
            echo "Games Radar homepage not found.\n";
            return;
        }

        $maxOrder = $homepage->blocks()->max('order') ?? 0;

        /** --------------------
         *  Grid 1: Best Sellers
         * -------------------- */
        $bestSellerProducts = array_slice($this->createdGamesRadarProducts, 0, 4);
        $bestSellerItems = [];
        foreach ($bestSellerProducts as $product) {
            $bestSellerItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->price, 2),
                'badge' => $product->sale_price > 0 ? [
                    'text' => 'Save £' . number_format($product->price - $product->sale_price, 2),
                    'color' => 'success'
                ] : null,
                'actions' => [
                    [
                        'text' => 'View Details',
                        'url' => "http://localhost:5001/shop/details/{$product->slug}",
                        'style' => 'primary'
                    ]
                ]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'Player’s Power Picks',
                'subtitle' => 'Top gaming gear and accessories dominating the year',
                'layout' => 'grid',
                'columns' => 4,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $bestSellerItems
            ]),
            'order' => $maxOrder + 1
        ]);

        /** --------------------
         *  Grid 2: On Sale
         * -------------------- */
        $saleProducts = array_values(array_filter($this->createdGamesRadarProducts, fn($p) => $p->sale_price > 0));
        $saleProducts = array_slice($saleProducts, 0, 3);

        $saleItems = [];
        foreach ($saleProducts as $product) {
            $saleItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->sale_price, 2),
                'badge' => [
                    'text' => number_format((($product->price - $product->sale_price) / $product->price) * 100) . '% OFF',
                    'color' => 'warning'
                ],
                'actions' => [[
                    'text' => 'Get Deal',
                    'url' => "http://localhost:5001/shop/details/{$product->slug}",
                    'style' => 'primary'
                ]]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'Level-Up Arsenal',
                'subtitle' => 'Elite hardware to enhance immersion and performance',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $saleItems
            ]),
            'order' => $maxOrder + 2
        ]);

        /** --------------------
         *  Grid 3: New Arrivals
         * -------------------- */
        $newProducts = array_slice($this->createdGamesRadarProducts, -3);

        $newItems = [];
        foreach ($newProducts as $product) {
            $newItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->price, 2),
                'badge' => ['text' => 'New', 'color' => 'primary'],
                'actions' => [[
                    'text' => 'Learn More',
                    'url' => "http://localhost:5001/shop/details/{$product->slug}",
                    'style' => 'primary'
                ]]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'Next-Gen Gear Hub',
                'subtitle' => 'Explore the tech that defines modern gaming',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $newItems
            ]),
            'order' => $maxOrder + 3
        ]);

        echo "Added 3 product grids to Games Radar homepage.\n";
    }

    private function addProductGridsToHorseAndHound(): void
    {
        $site = Site::find(29); // example site ID
        if (!$site) return;

        $homepage = Page::where('slug', 'home')->where('site_id', $site->id)->first();
        if (!$homepage) {
            echo "Horse and hound homepage not found.\n";
            return;
        }

        $maxOrder = $homepage->blocks()->max('order') ?? 0;

        /** --------------------
         *  Grid 1: Best Sellers
         * -------------------- */
        $bestSellerProducts = array_slice($this->createdHorseAndHoundProducts, 0, 4);
        $bestSellerItems = [];
        foreach ($bestSellerProducts as $product) {
            $bestSellerItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->price, 2),
                'badge' => $product->sale_price > 0 ? [
                    'text' => 'Save £' . number_format($product->price - $product->sale_price, 2),
                    'color' => 'success'
                ] : null,
                'actions' => [
                    [
                        'text' => 'View Details',
                        'url' => "http://localhost:5001/shop/details/{$product->slug}",
                        'style' => 'primary'
                    ]
                ]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'Rider’s Essentials',
                'subtitle' => 'Premium equipment trusted by professionals and enthusiasts alike',
                'layout' => 'grid',
                'columns' => 4,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $bestSellerItems
            ]),
            'order' => $maxOrder + 1
        ]);

        /** --------------------
         *  Grid 2: On Sale
         * -------------------- */
        $saleProducts = array_values(array_filter($this->createdHorseAndHoundProducts, fn($p) => $p->sale_price > 0));
        $saleProducts = array_slice($saleProducts, 0, 3);

        $saleItems = [];
        foreach ($saleProducts as $product) {
            $saleItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->sale_price, 2),
                'badge' => [
                    'text' => number_format((($product->price - $product->sale_price) / $product->price) * 100) . '% OFF',
                    'color' => 'warning'
                ],
                'actions' => [[
                    'text' => 'Get Deal',
                    'url' => "http://localhost:5001/shop/details/{$product->slug}",
                    'style' => 'primary'
                ]]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'Stable & Saddle Select',
                'subtitle' => 'High-quality gear for horse care, training, and sport',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $saleItems
            ]),
            'order' => $maxOrder + 2
        ]);

        /** --------------------
         *  Grid 3: New Arrivals
         * -------------------- */
        $newProducts = array_slice($this->createdHorseAndHoundProducts, -3);

        $newItems = [];
        foreach ($newProducts as $product) {
            $newItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->price, 2),
                'badge' => ['text' => 'New', 'color' => 'primary'],
                'actions' => [[
                    'text' => 'Learn More',
                    'url' => "http://localhost:5001/shop/details/{$product->slug}",
                    'style' => 'primary'
                ]]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'Equestrian Excellence',
                'subtitle' => 'Top-rated products enhancing comfort, performance, and partnership',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $newItems
            ]),
            'order' => $maxOrder + 3
        ]);

        echo "Added 3 product grids to Horse and Hound homepage.\n";
    }

    private function addProductGridsToWineChronicle(): void
    {
        $site = Site::find(10); // example site ID
        if (!$site) return;

        $homepage = Page::where('slug', 'home')->where('site_id', $site->id)->first();
        if (!$homepage) {
            echo "Wine chronicle homepage not found.\n";
            return;
        }

        $maxOrder = $homepage->blocks()->max('order') ?? 0;

        /** --------------------
         *  Grid 1: Best Sellers
         * -------------------- */
        $bestSellerProducts = array_slice($this->createdWineChronicleProducts, 0, 4);
        $bestSellerItems = [];
        foreach ($bestSellerProducts as $product) {
            $bestSellerItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->price, 2),
                'badge' => $product->sale_price > 0 ? [
                    'text' => 'Save £' . number_format($product->price - $product->sale_price, 2),
                    'color' => 'success'
                ] : null,
                'actions' => [
                    [
                        'text' => 'View Details',
                        'url' => "http://localhost:5001/shop/details/{$product->slug}",
                        'style' => 'primary'
                    ]
                ]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'Sommelier’s Showcase',
                'subtitle' => 'Exceptional bottles and tasting experiences curated for connoisseurs',
                'layout' => 'grid',
                'columns' => 4,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $bestSellerItems
            ]),
            'order' => $maxOrder + 1
        ]);

        /** --------------------
         *  Grid 2: On Sale
         * -------------------- */
        $saleProducts = array_values(array_filter($this->createdWineChronicleProducts, fn($p) => $p->sale_price > 0));
        $saleProducts = array_slice($saleProducts, 0, 3);

        $saleItems = [];
        foreach ($saleProducts as $product) {
            $saleItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->sale_price, 2),
                'badge' => [
                    'text' => number_format((($product->price - $product->sale_price) / $product->price) * 100) . '% OFF',
                    'color' => 'warning'
                ],
                'actions' => [[
                    'text' => 'Get Deal',
                    'url' => "http://localhost:5001/shop/details/{$product->slug}",
                    'style' => 'primary'
                ]]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'Cellar Treasures',
                'subtitle' => 'Premium wines and rare spirits worthy of any collection',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $saleItems
            ]),
            'order' => $maxOrder + 2
        ]);

        /** --------------------
         *  Grid 3: New Arrivals
         * -------------------- */
        $newProducts = array_slice($this->createdWineChronicleProducts, -3);

        $newItems = [];
        foreach ($newProducts as $product) {
            $newItems[] = [
                'title' => $product->name,
                'slug' => 'shop/details/' . $product->slug,
                'excerpt' => substr($product->description, 0, 120) . '...',
                'image' => [
                    'src' => $product->image,
                    'alt' => $product->name
                ],
                'price' => '£' . number_format($product->price, 2),
                'badge' => ['text' => 'New', 'color' => 'primary'],
                'actions' => [[
                    'text' => 'Learn More',
                    'url' => "http://localhost:5001/shop/details/{$product->slug}",
                    'style' => 'primary'
                ]]
            ];
        }

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => 'page_grid',
            'data' => json_encode([
                'title' => 'The Art of the Pour',
                'subtitle' => 'Celebrating craftsmanship in every bottle and blend',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'showFeatures' => false,
                'showActions' => true,
                'pages' => $newItems
            ]),
            'order' => $maxOrder + 3
        ]);

        echo "Added 3 product grids to Horse and Hound homepage.\n";
    }
}