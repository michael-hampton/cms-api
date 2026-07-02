<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Framework\Support\Str;
use App\Models\Author;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageAuthor;
use App\Models\PageCustomField;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class BuyingGuideSeeder extends Seeder
{
    private $pageRepository;
    private $blockRepository;
    private $tagRepository;
    private $categoryRepository;
    private $blockParserService;

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
//        $this->createTechWeeklyGuides();
//        $this->createVogueNoirGuides();
//        $this->createGoCompareGuides();
//        $this->createGamesRadarGuides();
        //$this->createGolfMonthlyGuides();
        $this->createGuitarWorldGuides();
    }

    private function createGuitarWorldGuides(): void
    {
        $siteId = 7;

        $guides = [
            [
                'title' => 'Best Electric Guitars 2026: Ultimate Buying Guide',
                'slug' => 'best-electric-guitars-2026',
                'tags' => ['buying-guide', 'guitars', 'electric', 'gear'],
                'categories' => ['Guitar Gear', 'Electric'],
                'author' => [
                    'name' => 'Michael Astley-Brown',
                    'bio' => 'Gear editor specializing in electric guitars, performance testing, and historical builds.',
                ],
                'custom_fields' => [
                    'author_name' => 'Michael Astley-Brown',
                    'read_time' => 18,
                    'excerpt' => 'Find your perfect six-string partner with our comprehensive 2026 round-up of the finest electric guitars available today.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Electric Guitars 2026',
                            'subtitle' => 'Your complete guide to choosing the perfect six-string weapon',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The electric guitar market in 2026 is richer than ever. Manufacturers are combining vintage aesthetics with modern playability tweaks—like compound-radius fingerboards, multi-voice pickups, and hyper-stable roasted maple necks. Yet, with prices ranging from £400 to £3,000+, finding the right model for your musical journey requires careful consideration.',
                                'This guide breaks down everything you need to know: from pickup configurations (SSS, HSS, HH) and tonewoods to hardware consistency and neck profiles. We\'ve tested dozens of new and classic models to bring you definitive recommendations for every budget and genre.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What to Look For',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Pickup Configuration: Single-coils for vintage chime, humbuckers for heavy rock power',
                                'Neck Profile: Slim profiles favor speed, while chunky \'50s necks provide classic comfort',
                                'Scale Length: 25.5" (Fender style) feels snappy; 24.75" (Gibson style) offers slinkier string tension',
                                'Fretwork & Radius: Flat fingerboards (12"+) make string bending clean and effortless',
                                'Tuning Stability: Locking tuners and self-lubricating nuts keep your intonation locked in',
                                'Tonewoods & Body Construction: Semi-hollows add resonant acoustic air; solid bodies maximize sustain',
                                'Electronics: Coil-splitting capabilities expand your single-coil tonal palette on humbucker rigs',
                                'Weight & Ergonomics: Contoured bodies reduce shoulder fatigue during long live sets'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Our Top Picks',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Overall: Fender American Ultra II Stratocaster',
                            'subtitle' => 'The perfect evolution of an icon with unrivaled modern performance',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1564186763535-ebb21ef5277f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Fender American Ultra II Stratocaster'
                            ],
                            'url' => 'https://example.com/fender-ultra-ii-strat',
                            'specs' => [
                                ['text' => 'Body', 'value' => 'Select Alder'],
                                ['text' => 'Neck Profile', 'value' => 'Modern "D" with rolled edges'],
                                ['text' => 'Pickups', 'value' => '3x Ultra II Noiseless Strat single-coils'],
                                ['text' => 'Scale Length', 'value' => '25.5"'],
                                ['text' => 'Fretboard', 'value' => '10"-14" Compound Radius Ebony'],
                                ['text' => 'Weight', 'value' => '3.6kg'],
                                ['text' => 'Price', 'value' => '£2,249']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Incredibly fast neck with zero upper-fret resistance',
                                'Noiseless single-coils deliver classic chime without 60-cycle hum',
                                'Advanced S-1 switch offers creative pickup wiring combinations',
                                'Flawless setup and premium hardshell case included',
                                'Highly stable locking tuners'
                            ],
                            'cons' => [
                                'Premium price point for a Stratocaster',
                                'Modern voice might lack raw grit for absolute vintage purists',
                                'Heavy tremolo arm usage requires subtle adjustments'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Budget: PRS SE Custom 24-08',
                            'subtitle' => 'Unbeatable build quality and massive tonal variety for the money',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1550985616-10810253b84d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'PRS SE Custom 24-08'
                            ],
                            'url' => 'https://example.com/prs-se-custom-24-08',
                            'specs' => [
                                ['text' => 'Body', 'value' => 'Mahogany with Maple Top & Flame Maple Veneer'],
                                ['text' => 'Neck Profile', 'value' => 'Wide Thin'],
                                ['text' => 'Pickups', 'value' => 'TCI "S" Bass & Treble Humbuckers'],
                                ['text' => 'Scale Length', 'value' => '25"'],
                                ['text' => 'Fretboard', 'value' => 'Rosewood with Bird Inlays'],
                                ['text' => 'Weight', 'value' => '3.4kg'],
                                ['text' => 'Price', 'value' => '£799']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Exemplary value for money and flawless finish',
                                'Eight distinct pickup combinations via individual coil-tap mini-toggles',
                                'Wonderfully comfortable 25" hybrid scale length',
                                'Extremely responsive molded tremolo bridge',
                                'High-end aesthetic looks far more expensive'
                            ],
                            'cons' => [
                                'Wide-thin neck might feel too slim for vintage enthusiasts',
                                'Included gig bag is good, but lacks a hard case shell',
                                'Nut can catch slightly on wide string-gauge changes'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Premium: Gibson Les Paul Standard \'60s',
                            'subtitle' => 'The definitive rock machine with unmatched thickness and sustain',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1615147342761-9238e15d8b96?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Gibson Les Paul Standard 60s'
                            ],
                            'url' => 'https://example.com/gibson-les-paul-60s',
                            'specs' => [
                                ['text' => 'Body', 'value' => 'Solid Mahogany with AA Figured Maple Top'],
                                ['text' => 'Neck Profile', 'value' => 'SlimTaper \'60s'],
                                ['text' => 'Pickups', 'value' => '60s Burstbucker dual humbuckers'],
                                ['text' => 'Scale Length', 'value' => '24.75"'],
                                ['text' => 'Fretboard', 'value' => 'Rosewood with Trapezoid Inlays'],
                                ['text' => 'Weight', 'value' => '4.2kg'],
                                ['text' => 'Price', 'value' => '£2,599']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Incredible, thick resonance and endless natural sustain',
                                'Strikingly beautiful nitrocellulose lacquer finish',
                                'Alnico V Burstbucker pickups capture authentic classic rock crunch',
                                'Hand-wired electronics with Orange Drop capacitors',
                                'Retains high resale value'
                            ],
                            'cons' => [
                                'Very heavy instrument; will weigh on your shoulder during long sets',
                                'Traditional neck joint lacks a modern contoured heel carve',
                                'No factory coil-splitting options out of the box'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Performance Comparison',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Model', 'Pickup Layout', 'Sustain (Secs)', 'Versatility /10', 'Playability Profile', 'Price'],
                                ['Fender American Ultra II', 'SSS Noiseless', '11s', '9.5', 'Ultra-Modern/Fast', '£2,249'],
                                ['PRS SE Custom 24-08', 'HH (Split Config)', '14s', '9.8', 'Comfortable/Fluid', '£799'],
                                ['Gibson Les Paul Standard', 'HH Alnico V', '20s+', '7.8', 'Chunky/Traditional', '£2,599'],
                                ['Ibanez Genesis RG550', 'HSH Japanese', '10s', '8.5', 'Super-Thin/Shred', '£999']
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Tip: Don\'t forget to budget for a proper professional setup. Most retailers can adjust the action and neck relief for your preferred string gauge right at purchase.'
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The best electric guitar is the one that forces you to pick it up every time you walk past it. Trust your hands and ears over spec sheets.',
                            'attribution' => 'Michael Astley-Brown, Gear Editor'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Best Guitar Multi-Effects Pedals 2026: Complete Buyer\'s Guide',
                'slug' => 'best-multi-effects-pedals-2026',
                'tags' => ['buying-guide', 'pedals', 'effects', 'reviews'],
                'categories' => ['Guitar Gear', 'Effects'],
                'author' => [
                    'name' => 'Chris Bird',
                    'bio' => 'Digital audio workstation specialist and effects architecture reviewer.',
                ],
                'custom_fields' => [
                    'author_name' => 'Chris Bird',
                    'read_time' => 15,
                    'excerpt' => 'Simplify your rig without sacrificing your tone. Our comprehensive guide to the best guitar multi-effects processors for stage and studio.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Multi-Effects Pedals 2026',
                            'subtitle' => 'Expert-tested modeling boards for premium studio tones on the floor',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The multi-effects landscape has advanced to a point where digital amp profiling and impulse responses are indistinguishable from genuine tube gear. Floorboards now serve as full recording interfaces, live routing hubs, and custom stompbox vaults—but which platform matches your workflow?',
                                'We\'ve run over 30 multi-effects processors through live PAs, studio monitors, and guitar power-amps to track down the absolute best units across every price tier.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Overall: Neural DSP Quad Cortex',
                            'subtitle' => 'Vast processing power meets jaw-dropping neural profiling tech',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1603048588665-791ca8aea617?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Neural DSP Quad Cortex'
                            ],
                            'url' => 'https://example.com/neural-dsp-quad-cortex',
                            'specs' => [
                                ['text' => 'Processor', 'value' => '2GHz Quad-SHARC Architecture'],
                                ['text' => 'Amp Models', 'value' => '90+ Amps, 1000+ Captures'],
                                ['text' => 'I/O', 'value' => 'Dual Combo Jacks, Dual FX Loops, MIDI, USB-C'],
                                ['text' => 'Screen', 'value' => '7" Multi-touch Display'],
                                ['text' => 'Chassis', 'value' => 'Anodized Aluminum'],
                                ['text' => 'Price', 'value' => '£1,449']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Astounding Neural Capture tech copies your real tube amps perfectly',
                                'Unbelievably simple, drag-and-drop touchscreen user interface',
                                'Incredibly compact footprint easily fits into a backpack',
                                'Massive processing power allows complex dual-amp routing structures',
                                'Sturdy, satisfying combined footswitch/rotary dials'
                            ],
                            'cons' => [
                                'No integrated expression pedal built onto the chassis',
                                'Desktop editor software took a long time to mature',
                                'Highly premium price point investment'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Value: Line 6 Pod Go',
                            'subtitle' => 'Legendary Helix-tier processing inside a budget-friendly layout',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1556442281-46fb1a62d85a?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Line 6 Pod Go'
                            ],
                            'url' => 'https://example.com/line-6-pod-go',
                            'specs' => [
                                ['text' => 'Processor', 'value' => 'HX Audio Engine'],
                                ['text' => 'Amp Models', 'value' => '80+ Amps, 200+ Effects'],
                                ['text' => 'I/O', 'value' => 'Guitar In, Main Out, FX Loop, Amp Out'],
                                ['text' => 'Screen', 'value' => '4.3" Color LCD Display'],
                                ['text' => 'Expression Pedal', 'value' => 'Integrated Onboard'],
                                ['text' => 'Price', 'value' => '£419']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Includes elite amp modules and effects directly from the premium Helix family',
                                'Lightweight, gig-ready setup with an integrated expression pedal',
                                'Crystal-clear color display makes snapshots easy to edit on the fly',
                                'Supports third-party Impulse Responses (IRs) for cabinet customization',
                                'Excellent performance as a standalone USB recording tool'
                            ],
                            'cons' => [
                                'Lacks dual-amp processing chains due to DSP ceilings',
                                'Molded plastic casing elements feel less rugged than the Helix',
                                'Fixed effects block signal routing chains limit wilder arrangements'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Flagship Processor Head-to-Head',
                            'productA' => 'Neural DSP Quad Cortex',
                            'productB' => 'Line 6 Helix Floor',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Amp Cloning / Capturing',
                                    'items' => [
                                        ['value' => 'Excellent (Hardware captures physical amps directly)'],
                                        ['value' => 'None (Relies completely on factory core component modeling)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'User Interface Flow',
                                    'items' => [
                                        ['value' => 'Modern touch navigation, smartphone-like configuration'],
                                        ['value' => 'Joystick and button layout, touch-capacitive switches']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Signal Loop Flexibility',
                                    'items' => [
                                        ['value' => 'Highly flexible virtual paths, up to 4 parallel rows'],
                                        ['value' => 'Unrivaled 4 external FX loops, vast physical jack routing']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Footprint Efficiency',
                                    'items' => [
                                        ['value' => 'Ultra-portable lunchbox dimensions, requires external expression'],
                                        ['value' => 'Large floor real estate, robust integrated expression treadle']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Important: Digital modelers depend immensely on your speaker choice. Running a top-tier modeler into a cheap guitar amp input can color the tone incorrectly; use a flat response FRFR monitor for best results.'
                        ]
                    ]
                ]
            ]
        ];

        foreach ($guides as $guideData) {
            $this->createGuide($guideData, $siteId);
        }
    }

    private function createTechWeeklyGuides(): void
    {
        $siteId = 2;

        $guides = [
            [
                'title' => 'Best Gaming Laptops 2025: Ultimate Buying Guide',
                'slug' => 'best-gaming-laptops-2025',
                'tags' => ['buying-guide', 'gaming', 'laptops', 'hardware'],
                'categories' => ['Technology', 'Hardware'],
                'author' => [
                    'name' => 'Marcus Chen',
                    'bio' => 'Hardware reviewer specializing in gaming tech',
                ],
                'custom_fields' => [
                    'author_name' => 'Marcus Chen',
                    'read_time' => 18,
                    'excerpt' => 'Find the perfect gaming laptop for your needs and budget with our comprehensive 2025 buyer\'s guide.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Gaming Laptops 2025',
                            'subtitle' => 'Your complete guide to choosing the perfect portable gaming rig',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Gaming laptops have evolved dramatically. Today\'s portable gaming rigs rival desktop performance while maintaining surprising portability. But with prices ranging from £800 to £4,000+, choosing the right one requires careful consideration.',
                                'This guide breaks down everything you need to know: from GPUs and CPUs to display refresh rates and cooling systems. We\'ve tested dozens of gaming laptops to bring you definitive recommendations for every budget and use case.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What to Look For',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'GPU Performance: The single most important factor for gaming',
                                'Display: 144Hz minimum for competitive gaming, 1440p or 4K for visuals',
                                'CPU: Modern 6-core minimum, 8-core recommended',
                                'RAM: 16GB minimum, 32GB for future-proofing',
                                'Storage: 512GB SSD minimum, preferably NVMe',
                                'Cooling: Adequate thermal management prevents throttling',
                                'Build Quality: Premium materials justify higher prices',
                                'Battery Life: Expect 3-5 hours for gaming, 8+ for productivity'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Our Top Picks',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Overall: ASUS ROG Zephyrus G16',
                            'subtitle' => 'The perfect balance of performance and portability',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'ASUS ROG Zephyrus G16'
                            ],
                            'url' => 'https://example.com/asus-zephyrus-g16',
                            'specs' => [
                                ['text' => 'GPU', 'value' => 'NVIDIA RTX 4070'],
                                ['text' => 'CPU', 'value' => 'Intel Core i9-14900H'],
                                ['text' => 'Display', 'value' => '16" 240Hz QHD+ OLED'],
                                ['text' => 'RAM', 'value' => '32GB DDR5'],
                                ['text' => 'Storage', 'value' => '1TB PCIe 4.0 SSD'],
                                ['text' => 'Weight', 'value' => '1.9kg'],
                                ['text' => 'Price', 'value' => '£2,499']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Stunning OLED display with 240Hz refresh',
                                'Excellent build quality and premium materials',
                                'Surprisingly good battery life (7+ hours)',
                                'Powerful performance in a slim chassis',
                                'Effective cooling system'
                            ],
                            'cons' => [
                                'Premium price point',
                                'No webcam (uses detachable module)',
                                'Limited port selection'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Budget: Lenovo Legion 5 Pro',
                            'subtitle' => 'Outstanding performance without breaking the bank',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Lenovo Legion 5 Pro'
                            ],
                            'url' => 'https://example.com/lenovo-legion-5-pro',
                            'specs' => [
                                ['text' => 'GPU', 'value' => 'NVIDIA RTX 4060'],
                                ['text' => 'CPU', 'value' => 'AMD Ryzen 7 7745HX'],
                                ['text' => 'Display', 'value' => '16" 165Hz WQXGA'],
                                ['text' => 'RAM', 'value' => '16GB DDR5'],
                                ['text' => 'Storage', 'value' => '512GB PCIe 4.0 SSD'],
                                ['text' => 'Weight', 'value' => '2.5kg'],
                                ['text' => 'Price', 'value' => '£1,299']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Excellent value for money',
                                'Solid 1440p gaming performance',
                                'Good port selection',
                                'Upgradeable RAM and storage',
                                'Competitive pricing'
                            ],
                            'cons' => [
                                'Bulkier and heavier design',
                                'Plastic construction feels less premium',
                                'Average battery life (4 hours)'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Premium: Razer Blade 16',
                            'subtitle' => 'No-compromise gaming in a MacBook-style chassis',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1625978963928-5c8a6aede5a9?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Razer Blade 16'
                            ],
                            'url' => 'https://example.com/razer-blade-16',
                            'specs' => [
                                ['text' => 'GPU', 'value' => 'NVIDIA RTX 4090'],
                                ['text' => 'CPU', 'value' => 'Intel Core i9-14900HX'],
                                ['text' => 'Display', 'value' => '16" 240Hz QHD+ Mini-LED'],
                                ['text' => 'RAM', 'value' => '32GB DDR5'],
                                ['text' => 'Storage', 'value' => '2TB PCIe 4.0 SSD'],
                                ['text' => 'Weight', 'value' => '2.4kg'],
                                ['text' => 'Price', 'value' => '£4,299']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Desktop-class RTX 4090 performance',
                                'Beautiful Mini-LED display',
                                'CNC aluminum unibody construction',
                                'Per-key RGB lighting',
                                'Thunderbolt 4 connectivity'
                            ],
                            'cons' => [
                                'Very expensive',
                                'Gets hot under sustained load',
                                'Limited battery life when gaming'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Performance Comparison',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Model', 'GPU', '1080p FPS', '1440p FPS', '4K FPS', 'Price'],
                                ['ASUS ROG Zephyrus G16', 'RTX 4070', '165', '120', '65', '£2,499'],
                                ['Lenovo Legion 5 Pro', 'RTX 4060', '144', '95', '45', '£1,299'],
                                ['Razer Blade 16', 'RTX 4090', '240+', '180', '110', '£4,299'],
                                ['MSI Stealth 17', 'RTX 4080', '200', '145', '85', '£3,199']
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Tip: Always check for student discounts. Many manufacturers offer 10-15% off for students and educators.'
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The best gaming laptop is the one that fits your specific needs and budget. Don\'t overspend on features you won\'t use.',
                            'attribution' => 'Marcus Chen, Hardware Reviewer'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Best Wireless Earbuds 2025: Complete Buyer\'s Guide',
                'slug' => 'best-wireless-earbuds-2025',
                'tags' => ['buying-guide', 'audio', 'earbuds', 'reviews'],
                'categories' => ['Technology', 'Audio'],
                'author' => [
                    'name' => 'Sarah Johnson',
                    'bio' => 'Audio specialist and tech journalist',
                ],
                'custom_fields' => [
                    'author_name' => 'Sarah Johnson',
                    'read_time' => 15,
                    'excerpt' => 'Cut the cord with confidence. Our expert guide to the best wireless earbuds for every use case and budget.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Wireless Earbuds 2025',
                            'subtitle' => 'Expert-tested recommendations for superior sound on the go',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The wireless earbud market has exploded, with options ranging from £50 budget picks to £350 audiophile-grade models. Sound quality, ANC performance, battery life, and fit all matter—but which features justify higher prices?',
                                'We\'ve tested over 50 pairs of wireless earbuds to identify the best options across every price point and use case.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Overall: Sony WF-1000XM5',
                            'subtitle' => 'Industry-leading ANC meets exceptional sound quality',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1606841837239-c5a1a4a07af7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Sony WF-1000XM5'
                            ],
                            'url' => 'https://example.com/sony-wf1000xm5',
                            'specs' => [
                                ['text' => 'Driver', 'value' => '8.4mm Dynamic'],
                                ['text' => 'ANC', 'value' => 'Industry-leading'],
                                ['text' => 'Battery', 'value' => '8hrs (24hrs w/ case)'],
                                ['text' => 'Codec', 'value' => 'LDAC, AAC, SBC'],
                                ['text' => 'Water Resistance', 'value' => 'IPX4'],
                                ['text' => 'Price', 'value' => '£259']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Best-in-class noise cancellation',
                                'Exceptional sound quality with LDAC',
                                'Comfortable fit for extended wear',
                                'Excellent call quality',
                                'Wireless charging included'
                            ],
                            'cons' => [
                                'No multipoint Bluetooth',
                                'Case is slightly bulky',
                                'Premium pricing'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Value: Nothing Ear (2)',
                            'subtitle' => 'Flagship features at mid-range prices',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1649859394614-dc4bb0996d3b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Nothing Ear 2'
                            ],
                            'url' => 'https://example.com/nothing-ear-2',
                            'specs' => [
                                ['text' => 'Driver', 'value' => '11.6mm Dynamic'],
                                ['text' => 'ANC', 'value' => 'Up to 40dB'],
                                ['text' => 'Battery', 'value' => '6hrs (30hrs w/ case)'],
                                ['text' => 'Codec', 'value' => 'AAC, SBC'],
                                ['text' => 'Water Resistance', 'value' => 'IP54'],
                                ['text' => 'Price', 'value' => '£129']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Outstanding value for money',
                                'Unique transparent design',
                                'Solid ANC performance',
                                'Good sound quality',
                                'Long battery life'
                            ],
                            'cons' => [
                                'No LDAC support',
                                'Fit may not suit everyone',
                                'Basic app features'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'ANC Performance Comparison',
                            'productA' => 'Sony WF-1000XM5',
                            'productB' => 'Apple AirPods Pro 2',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Low Frequency (Rumble)',
                                    'items' => [
                                        ['value' => 'Excellent (95% reduction)'],
                                        ['value' => 'Very Good (90% reduction)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Mid Frequency (Voices)',
                                    'items' => [
                                        ['value' => 'Excellent (85% reduction)'],
                                        ['value' => 'Good (75% reduction)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'High Frequency (Treble)',
                                    'items' => [
                                        ['value' => 'Good (60% reduction)'],
                                        ['value' => 'Good (65% reduction)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Transparency Mode',
                                    'items' => [
                                        ['value' => 'Natural but slightly processed'],
                                        ['value' => 'Most natural sounding']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Important: Always test fit before buying. Even the best earbuds won\'t perform well if they don\'t fit your ears properly.'
                        ]
                    ]
                ]
            ]
        ];

        foreach ($guides as $guideData) {
            $this->createGuide($guideData, $siteId);
        }
    }

    private function createGolfMonthlyGuides(): void
    {
        $siteId = 11;

        $guides = [
            [
                'title' => 'Best Golf Drivers 2026: Ultimate Buying Guide',
                'slug' => 'best-golf-drivers-2026',
                'tags' => ['buying-guide', 'drivers', 'clubs', 'equipment'],
                'categories' => ['Golf Gear', 'Clubs'],
                'author' => [
                    'name' => 'Joel Tadman',
                    'bio' => 'Technical Editor specializing in golf equipment testing and custom fitting.',
                ],
                'custom_fields' => [
                    'author_name' => 'Joel Tadman',
                    'read_time' => 15,
                    'excerpt' => 'Unlock more distance and maximize forgiveness off the tee with our comprehensive 2026 driver buyer\'s guide.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Golf Drivers 2026',
                            'subtitle' => 'Your complete guide to choosing the perfect big stick for your swing',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Driver technology has taken massive leaps forward over the last few seasons. Today\'s models rely on advanced multi-material carbon chassis and AI-optimized faces designed to preserve ball speed even on severe mis-hits. However, with premium options ranging from £380 to £550+, finding the right match for your specific launch conditions is critical.',
                                'This guide breaks down everything you need to know: from loft adjustability and shaft profiles to spin optimization and MOI (Moment of Inertia) ratings. We\'ve tested the latest releases out on the course and on launch monitors to bring you definitive recommendations.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What to Look For',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'MOI & Forgiveness: Higher MOI keeps ball speeds high on off-center strikes',
                                'Adjustability: Adjustable hosels let you fine-tune loft and lie settings',
                                'Spin Profiles: Low-spin models for high swing speeds; mid-to-high spin for slower tempos',
                                'Shaft Pairing: Stock shafts vary wildly; matching weight and flex is essential',
                                'Footprint and Aesthetics: Confidence at address is half the battle',
                                'Acoustics: Carbon crowns change the sound dynamic from metallic to a muted thud',
                                'Face Technology: AI-wrapped variable thickness maximizes the sweet spot'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Our Top Picks',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Overall: Titleist GT3 Driver',
                            'subtitle' => 'The ultimate blend of classic aesthetics, adjustability, and explosive speed',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1592919505780-303950717480?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Titleist GT3 Driver'
                            ],
                            'url' => 'https://example.com/titleist-gt3-driver',
                            'specs' => [
                                ['text' => 'Loft Options', 'value' => '8.0°, 9.0°, 10.0°, 11.0°'],
                                ['text' => 'Adjustability', 'value' => 'SureFit Hosel & SureFit Track Weight'],
                                ['text' => 'Spin Profile', 'value' => 'Low-Mid'],
                                ['text' => 'Stock Shafts', 'value' => 'Project X HZRDUS / Mitsubishi Tensei'],
                                ['text' => 'Head Size', 'value' => '460cc'],
                                ['text' => 'Target Handicap', 'value' => 'Low to Mid'],
                                ['text' => 'Price', 'value' => '£549']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Stunning, traditional clean look at address',
                                'Exceptional ball speed stability across the face',
                                'Highly adjustable sliding weight track for shot shaping',
                                'Wonderfully crisp, premium sound profile',
                                'Excellent aerodynamics'
                            ],
                            'cons' => [
                                'Premium price tag',
                                'Less built-in draw bias for chronic slicers',
                                'Requires a precise fitting to unlock full potential'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best for Forgiveness: Ping G430 Max 10K',
                            'subtitle' => 'Straightest driver on the market with unprecedented stability',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1587174486073-ae5e5cff23aa?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Ping G430 Max 10K'
                            ],
                            'url' => 'https://example.com/ping-g430-max-10k',
                            'specs' => [
                                ['text' => 'Loft Options', 'value' => '9.0°, 10.5°, 12.0°'],
                                ['text' => 'Adjustability', 'value' => 'Trajectory Tuning 2.0 Hosel'],
                                ['text' => 'Spin Profile', 'value' => 'Mid'],
                                ['text' => 'Stock Shafts', 'value' => 'Alta CB Black / Tour 2.0 Chrome'],
                                ['text' => 'Head Size', 'value' => '460cc (Oversized footprint)'],
                                ['text' => 'Target Handicap', 'value' => 'All abilities'],
                                ['text' => 'Price', 'value' => '£529']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Industry-leading forgiveness (10k combined MOI)',
                                'Inspires massive confidence behind the ball',
                                'Keeps dispersion incredibly tight',
                                'Highly durable face structure',
                                'Great high-launch characteristics'
                            ],
                            'cons' => [
                                'Acoustics are quite loud and distinct',
                                'Large profile may not appeal to traditionalists',
                                'Slightly higher spin rates for very fast swingers'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Value: Cobra Darkspeed X',
                            'subtitle' => 'Tour-level speeds and premium looks at a lower price point',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Cobra Darkspeed X'
                            ],
                            'url' => 'https://example.com/cobra-darkspeed-x',
                            'specs' => [
                                ['text' => 'Loft Options', 'value' => '9.0°, 10.5°, 12.0°'],
                                ['text' => 'Adjustability', 'value' => 'MyFly Adjustable Hosel'],
                                ['text' => 'Spin Profile', 'value' => 'Mid-Low'],
                                ['text' => 'Stock Shafts', 'value' => 'UST Lin-Q M40X / Mitsubishi Kai\'li'],
                                ['text' => 'Head Size', 'value' => '460cc'],
                                ['text' => 'Target Handicap', 'value' => 'Low to High'],
                                ['text' => 'Price', 'value' => '£399']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Outstanding value compared to main rivals',
                                'Sleek, matte-black stealth aesthetic',
                                'Very competitive ball speeds off the center',
                                'Adjustable front-to-back sole weights',
                                'Smooth, energetic feel'
                            ],
                            'cons' => [
                                'Matte finish can show smudges easily',
                                'Off-center distance drops slightly more than the Ping',
                                'Stock grip options are limited'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Launch Monitor Comparison',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Driver Model', 'Ball Speed (mph)', 'Launch Angle', 'Spin (rpm)', 'Carry (yds)', 'Price'],
                                ['Titleist GT3', '161.4', '12.8°', '2350', '268', '£549'],
                                ['Ping G430 Max 10K', '159.8', '14.1°', '2580', '262', '£529'],
                                ['Cobra Darkspeed X', '160.9', '13.2°', '2410', '266', '£399'],
                                ['TaylorMade Qi10 Max', '160.1', '13.9°', '2510', '263', '£499']
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Tip: Don\'t bypass a custom fitting. Simply getting the correct shaft weight and adapter loft setting can tighten your dispersion by up to 40%.'
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The best driver for your game isn\'t the one that hits your absolute longest shot on a perfect swing—it is the one that minimizes the damage on your worst swing.',
                            'attribution' => 'Joel Tadman, Technical Editor'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Best Golf Balls 2026: Complete Buyer\'s Guide',
                'slug' => 'best-golf-balls-2026',
                'tags' => ['buying-guide', 'balls', 'equipment', 'reviews'],
                'categories' => ['Golf Gear', 'Balls'],
                'author' => [
                    'name' => 'Dan Parker',
                    'bio' => 'Golf Monthly Staff Writer and ball-testing specialist.',
                ],
                'custom_fields' => [
                    'author_name' => 'Dan Parker',
                    'read_time' => 12,
                    'excerpt' => 'Match your ball to your swing. Our expert field-tests break down the best premium and value golf balls on the market.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Golf Balls 2026',
                            'subtitle' => 'Expert recommendations for control, distance, and green-side feel',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1622560480605-d83c853bc5c3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Your golf ball is the only piece of equipment you use on every single shot. From complex multi-layer urethane tour balls to soft-compression ionomer budget picks, choosing the correct construction can dramatically alter your short-game spin and driver distance.',
                                'We\'ve put the leading golf balls through thorough testing on the course and green-side to evaluate durability, alignment aids, wind stability, and feel.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Overall Premium: Titleist Pro V1',
                            'subtitle' => 'The gold standard for total performance and short-game control',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1592919505780-303950717480?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Titleist Pro V1'
                            ],
                            'url' => 'https://example.com/titleist-pro-v1',
                            'specs' => [
                                ['text' => 'Construction', 'value' => '3-piece'],
                                ['text' => 'Cover Material', 'value' => 'Cast Urethane Elastomer'],
                                ['text' => 'Compression', 'value' => 'Mid-High'],
                                ['text' => 'Flight Profile', 'value' => 'Mid, penetrating trajectory'],
                                ['text' => 'Short Game Spin', 'value' => 'High'],
                                ['text' => 'Price', 'value' => '£49.99 per dozen']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Unrivaled consistency ball-to-ball',
                                'Exceptional green-side checking power and soft feel',
                                'Extremely stable flight in heavy crosswinds',
                                'Excellent durability from the revised cover formula',
                                'Great feel off the putter face'
                            ],
                            'cons' => [
                                'Premium pricing is a heavy investment',
                                'Slower swing speeds may prefer the lower compression Pro V1x alternative'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Value Urethane: Srixon Q-Star Tour',
                            'subtitle' => 'Tour-level short game performance optimized for moderate swing speeds',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1587174486073-ae5e5cff23aa?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Srixon Q-Star Tour'
                            ],
                            'url' => 'https://example.com/srixon-q-star-tour',
                            'specs' => [
                                ['text' => 'Construction', 'value' => '3-piece'],
                                ['text' => 'Cover Material', 'value' => 'Urethane (Spin Skin with SeRM)'],
                                ['text' => 'Compression', 'value' => 'Mid-Low (72)'],
                                ['text' => 'Flight Profile', 'value' => 'Mid-High'],
                                ['text' => 'Short Game Spin', 'value' => 'Medium-High'],
                                ['text' => 'Price', 'value' => '£34.99 per dozen']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Superb value for a genuine urethane-covered ball',
                                'Incredibly soft feel on chips and pitches',
                                'Helps moderate swing speeds pick up driver distance',
                                'Excellent visual alignment options available (Divide)',
                                'Low driver spin reduces slice accentuation'
                            ],
                            'cons' => [
                                'Cover scuffs slightly faster than top-tier rivals',
                                'Fast swingers (105+ mph) may over-compress it and lose distance',
                                'Slightly lower spin on partial wedge shots than Pro V1'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Tour Ball Spin & Flight Comparison',
                            'productA' => 'Titleist Pro V1',
                            'productB' => 'Callaway Chrome Tour',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Driver Spin Rate',
                                    'items' => [
                                        ['value' => 'Low/Consistent (approx 2200 rpm)'],
                                        ['value' => 'Very Low (approx 2100 rpm)']
                                    ]
                                ],
                                [
                                    'subtitle' => '7-Iron Launch & Spin',
                                    'items' => [
                                        ['value' => 'Mid window, controlled stopping power'],
                                        ['value' => 'Slightly higher window, high apex']
                                    ]
                                ],
                                [
                                    'subtitle' => '50-Yard Wedge Spin',
                                    'items' => [
                                        ['value' => 'Excellent "one-hop-and-stop" reaction'],
                                        ['value' => 'Aggressive check, high spin retention']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Putter Feel',
                                    'items' => [
                                        ['value' => 'Muted, soft feedback'],
                                        ['value' => 'Slightly crisper click']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Important: Stop playing whatever "lake balls" or mixed models you find in the rough. Playing the exact same model of ball consistently stabilizes your distance control on approaches.'
                        ]
                    ]
                ]
            ]
        ];

        foreach ($guides as $guideData) {
            $this->createGuide($guideData, $siteId);
        }
    }

    private function createGuide(array $data, int $siteId): void
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'page_type' => 'buying-guide',
            'meta_title' => $data['title'],
            'meta_description' => $data['custom_fields']['excerpt'] ?? '',
            'site_id' => $siteId,
        ]);

        if (!empty($data['author'])) {
            $data['author']['slug'] = Str::slug($data['author']['name']);
            $author = Author::create($data['author']);
            PageAuthor::create([
                'page_id' => $page->id,
                'author_id' => $author->id
            ]);
        }

        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, $siteId);
            $page->tags(true)->attach($tag->id);
        }

        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, $siteId);
            $page->categories(true)->attach($category->id);
        }

        foreach ($data['custom_fields'] as $key => $value) {
            $fieldDef = CustomFieldDefinition::where('key', $key)
                ->where('site_id', $siteId)
                ->first();

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

    private function createVogueNoirGuides(): void
    {
        $siteId = 4;

        $guides = [
            [
                'title' => 'Best Designer Handbags 2025: Investment Pieces Worth Buying',
                'slug' => 'best-designer-handbags-investment-guide-2025',
                'tags' => ['buying-guide', 'luxury', 'handbags', 'investment'],
                'categories' => ['Fashion', 'Accessories', 'Bags'],
                'author' => [
                    'name' => 'Isabella Rossi',
                    'bio' => 'Luxury fashion expert with 20 years experience',
                ],
                'custom_fields' => [
                    'author_name' => 'Isabella Rossi',
                    'read_time' => 16,
                    'excerpt' => 'Invest wisely in timeless designer handbags that appreciate in value. Our expert guide to bags worth every penny.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Designer Handbags 2025',
                            'subtitle' => 'Investment-grade luxury bags that stand the test of time',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'A designer handbag is more than an accessory—it\'s an investment. The right bag can appreciate 10-15% annually, outperforming many traditional investments. But which bags hold value, and which lose it the moment you leave the boutique?',
                                'We\'ve analyzed resale data, auction results, and collector trends to identify the handbags truly worth your investment.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Investment: Hermès Birkin 30cm',
                            'subtitle' => 'The gold standard of handbag investments',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Hermès Birkin'
                            ],
                            'url' => 'https://example.com/hermes-birkin',
                            'specs' => [
                                ['text' => 'Size', 'value' => '30cm'],
                                ['text' => 'Material', 'value' => 'Togo Leather'],
                                ['text' => 'Retail Price', 'value' => '£10,000-12,000'],
                                ['text' => '5-Year Appreciation', 'value' => '+70%'],
                                ['text' => 'Resale Value', 'value' => '£17,000+'],
                                ['text' => 'Availability', 'value' => '2-6 year waitlist']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Unmatched resale value and appreciation',
                                'Handcrafted quality and durability',
                                'Ultimate status symbol',
                                'Limited supply ensures demand',
                                'Available in endless color combinations'
                            ],
                            'cons' => [
                                'Extremely difficult to purchase at retail',
                                'Very high initial investment',
                                'Requires careful authentication when buying secondhand',
                                'Heavy when fully loaded'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Classic: Chanel Classic Flap Medium',
                            'subtitle' => 'Timeless elegance with strong appreciation',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Chanel Classic Flap'
                            ],
                            'url' => 'https://example.com/chanel-classic-flap',
                            'specs' => [
                                ['text' => 'Size', 'value' => 'Medium (25cm)'],
                                ['text' => 'Material', 'value' => 'Caviar or Lambskin'],
                                ['text' => 'Retail Price', 'value' => '£7,800'],
                                ['text' => '5-Year Appreciation', 'value' => '+55%'],
                                ['text' => 'Resale Value', 'value' => '£12,000+'],
                                ['text' => 'Availability', 'value' => 'In-store purchase']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Iconic design by Coco Chanel',
                                'Strong value retention',
                                'Versatile for day and evening',
                                'Regular price increases protect investment',
                                'Available for immediate purchase'
                            ],
                            'cons' => [
                                'Significant annual price increases',
                                'Lambskin scratches easily',
                                'Limited capacity',
                                'Chain strap can be heavy'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Bag', '2020 Price', '2025 Price', 'Appreciation', 'Investment Grade'],
                                ['Hermès Birkin 30', '£7,000', '£12,000', '+71%', 'A+'],
                                ['Chanel Classic Flap', '£5,000', '£7,800', '+56%', 'A'],
                                ['Louis Vuitton Neverfull', '£1,000', '£1,450', '+45%', 'B+'],
                                ['Dior Lady Dior', '£3,800', '£5,200', '+37%', 'B'],
                                ['Gucci Dionysus', '£1,600', '£1,750', '+9%', 'C']
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Investment Tip: Black, navy, and neutral colors appreciate faster than seasonal shades. Classic hardware (gold or silver) outperforms trendy finishes.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Best Sustainable Fashion Brands 2025: Ethical Shopping Guide',
                'slug' => 'best-sustainable-fashion-brands-2025',
                'tags' => ['buying-guide', 'sustainable-fashion', 'ethical', 'eco-friendly'],
                'categories' => ['Fashion', 'Sustainable'],
                'author' => [
                    'name' => 'Emma Green',
                    'bio' => 'Sustainable fashion specialist',
                ],
                'custom_fields' => [
                    'author_name' => 'Emma Green',
                    'read_time' => 14,
                    'excerpt' => 'Shop sustainably without sacrificing style. Our guide to the best eco-conscious fashion brands.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Sustainable Fashion Brands 2025',
                            'subtitle' => 'Style with substance: eco-friendly brands leading the change',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Luxury Sustainable: Stella McCartney',
                            'subtitle' => 'Pioneering luxury fashion without animal products',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1558769132-cb1aea3c5f0c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Stella McCartney'
                            ],
                            'url' => 'https://example.com/stella-mccartney',
                            'specs' => [
                                ['text' => 'Founded', 'value' => '2001'],
                                ['text' => 'Materials', 'value' => '100% vegetarian'],
                                ['text' => 'Certifications', 'value' => 'B Corp, PETA Approved'],
                                ['text' => 'Price Range', 'value' => '£££-££££'],
                                ['text' => 'Sustainability Score', 'value' => '95/100']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Never uses leather, fur, or animal skins',
                                'Bio-fabricated materials innovation',
                                'Transparent supply chain',
                                'Carbon neutral operations',
                                'Circular fashion initiatives'
                            ],
                            'cons' => [
                                'Premium pricing',
                                'Limited retail locations',
                                'Some pieces require special care'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Sustainability Standards Comparison',
                            'productA' => 'Stella McCartney',
                            'productB' => 'Patagonia',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Materials',
                                    'items' => [
                                        ['value' => 'Innovative bio-fabrics, recycled materials'],
                                        ['value' => 'Organic cotton, recycled polyester']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Transparency',
                                    'items' => [
                                        ['value' => 'Full supply chain disclosure'],
                                        ['value' => 'Complete transparency + activism']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Price Point',
                                    'items' => [
                                        ['value' => 'Luxury (£500-3000)'],
                                        ['value' => 'Mid-range (£50-400)']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        foreach ($guides as $guideData) {
            $this->createGuide($guideData, $siteId);
        }
    }

    private function createGoCompareGuides(): void
    {
        $siteId = 10;

        $guides = [
            [
                'title' => 'Best Credit Cards 2025: Complete Comparison Guide',
                'slug' => 'best-credit-cards-2025-guide',
                'tags' => ['buying-guide', 'credit-cards', 'money', 'finance'],
                'categories' => ['Money', 'Credit Cards'],
                'author' => [
                    'name' => 'Andrew Foster',
                    'bio' => '15+ years in personal finance and credit expertise',
                ],
                'custom_fields' => [
                    'author_name' => 'Andrew Foster',
                    'read_time' => 12,
                    'excerpt' => 'Find the perfect credit card for your needs. Compare rates, rewards, and benefits across top UK providers.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Credit Cards 2025',
                            'subtitle' => 'Expert comparisons to help you choose the right card',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Choosing the right credit card can save you thousands in interest or earn valuable rewards. But with hundreds of options, how do you find the perfect match?',
                                'We\'ve compared rates, fees, rewards programs, and benefits to bring you definitive recommendations for every financial situation.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Balance Transfer: Virgin Money',
                            'subtitle' => 'Longest 0% period for existing debt',
                            'url' => 'https://example.com/virgin-balance-transfer',
                            'specs' => [
                                ['text' => '0% Period', 'value' => '29 months'],
                                ['text' => 'Transfer Fee', 'value' => '2.9%'],
                                ['text' => 'APR After 0%', 'value' => '24.9%'],
                                ['text' => 'Credit Score', 'value' => 'Good to Excellent'],
                                ['text' => 'Annual Fee', 'value' => '£0']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Market-leading 29-month 0% period',
                                'Low transfer fee of 2.9%',
                                'No annual fee',
                                'Online account management',
                                'Purchase protection included'
                            ],
                            'cons' => [
                                'High APR after promotional period',
                                'No rewards or cashback',
                                'Requires good credit score'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Rewards: American Express Platinum Cashback',
                            'subtitle' => 'Maximum cashback on everyday spending',
                            'url' => 'https://example.com/amex-platinum-cashback',
                            'specs' => [
                                ['text' => 'Cashback Rate', 'value' => '5% first 3 months, 1% ongoing'],
                                ['text' => 'Annual Fee', 'value' => '£25'],
                                ['text' => 'APR', 'value' => '22.9%'],
                                ['text' => 'Acceptance', 'value' => '95% of UK retailers']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                '5% cashback for first 3 months',
                                'No limit on cashback earnings',
                                'Excellent fraud protection',
                                'Purchase protection and insurance',
                                'Great customer service'
                            ],
                            'cons' => [
                                'Annual fee of £25',
                                'Not accepted everywhere',
                                'High APR if you carry balance'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Rewards vs Balance Transfer',
                            'productA' => 'Rewards Card',
                            'productB' => 'Balance Transfer Card',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Earning on spending'],
                                        ['value' => 'Paying off debt']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Typical APR',
                                    'items' => [
                                        ['value' => '20-30%'],
                                        ['value' => '0% then 22-27%']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Annual Fee',
                                    'items' => [
                                        ['value' => '£0-150'],
                                        ['value' => '£0']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Best Energy Deals 2025: How to Switch and Save',
                'slug' => 'best-energy-deals-2025',
                'tags' => ['buying-guide', 'energy', 'utilities', 'savings'],
                'categories' => ['Utilities', 'Energy'],
                'author' => [
                    'name' => 'Robert Davies',
                    'bio' => 'Energy market analyst',
                ],
                'custom_fields' => [
                    'author_name' => 'Robert Davies',
                    'read_time' => 10,
                    'excerpt' => 'Save hundreds on energy bills. Our guide to finding and switching to cheaper tariffs.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Energy Deals 2025',
                            'subtitle' => 'Cut your bills with our switching guide',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Fixed Rate: Octopus Energy Fixed 12M',
                            'subtitle' => 'Price certainty with green energy',
                            'url' => 'https://example.com/octopus-fixed',
                            'specs' => [
                                ['text' => 'Contract Length', 'value' => '12 months'],
                                ['text' => 'Average Annual Cost', 'value' => '£1,456'],
                                ['text' => 'Exit Fee', 'value' => '£0'],
                                ['text' => 'Green Energy', 'value' => '100%'],
                                ['text' => 'Customer Rating', 'value' => '4.7/5']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Price locked for 12 months',
                                '100% renewable electricity',
                                'No exit fees',
                                'Award-winning app and service',
                                'Smart tariff options'
                            ],
                            'cons' => [
                                'Slightly higher than variable',
                                'Limited time offer',
                                'Requires smart meter for best deals'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        foreach ($guides as $guideData) {
            $this->createGuide($guideData, $siteId);
        }
    }

    private function createGamesRadarGuides(): void
    {
        $siteId = 6;

        $guides = [
            [
                'title' => 'Best Gaming Monitors 2025: Ultimate Display Buying Guide',
                'slug' => 'best-gaming-monitors-2025',
                'tags' => ['buying-guide', 'gaming', 'monitors', 'hardware'],
                'categories' => ['Gaming', 'Hardware'],
                'author' => [
                    'name' => 'Jake Morrison',
                    'bio' => 'Gaming hardware specialist and esports enthusiast',
                ],
                'custom_fields' => [
                    'author_name' => 'Jake Morrison',
                    'read_time' => 14,
                    'excerpt' => 'Level up your gaming with the perfect monitor. Expert recommendations for every budget and gaming style.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Gaming Monitors 2025',
                            'subtitle' => 'Find your competitive edge with the perfect display',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Your monitor is the window to every gaming experience. Refresh rate, response time, panel type, and resolution all impact performance and immersion.',
                                'We\'ve tested dozens of gaming monitors to identify the best options for competitive FPS, immersive RPGs, and everything in between.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Overall: ASUS ROG Swift OLED PG27AQDM',
                            'subtitle' => 'OLED perfection for gaming',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'ASUS ROG Swift OLED'
                            ],
                            'url' => 'https://example.com/asus-oled',
                            'specs' => [
                                ['text' => 'Size', 'value' => '27"'],
                                ['text' => 'Resolution', 'value' => '2560x1440 (1440p)'],
                                ['text' => 'Refresh Rate', 'value' => '240Hz'],
                                ['text' => 'Response Time', 'value' => '0.03ms'],
                                ['text' => 'Panel', 'value' => 'OLED'],
                                ['text' => 'Price', 'value' => '£899']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Stunning OLED picture quality',
                                'Blazing fast 240Hz refresh rate',
                                'Near-instantaneous response time',
                                'Perfect blacks and infinite contrast',
                                'G-Sync compatible'
                            ],
                            'cons' => [
                                'OLED burn-in risk with static elements',
                                'Premium price',
                                'No HDR1000 brightness'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Budget: AOC 24G2',
                            'subtitle' => 'Competitive gaming on a budget',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1585792180666-f7347c490ee2?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'AOC Gaming Monitor'
                            ],
                            'url' => 'https://example.com/aoc-24g2',
                            'specs' => [
                                ['text' => 'Size', 'value' => '24"'],
                                ['text' => 'Resolution', 'value' => '1920x1080 (1080p)'],
                                ['text' => 'Refresh Rate', 'value' => '144Hz'],
                                ['text' => 'Response Time', 'value' => '1ms'],
                                ['text' => 'Panel', 'value' => 'IPS'],
                                ['text' => 'Price', 'value' => '£159']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Outstanding value for money',
                                'Solid 144Hz performance',
                                'IPS panel with good colors',
                                'FreeSync and G-Sync compatible',
                                'Height adjustable stand'
                            ],
                            'cons' => [
                                '1080p resolution shows age',
                                'Basic HDR implementation',
                                'Limited brightness'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Model', 'Size', 'Resolution', 'Refresh', 'Panel', 'Price'],
                                ['ASUS ROG Swift OLED', '27"', '1440p', '240Hz', 'OLED', '£899'],
                                ['LG 27GR95QE', '27"', '1440p', '240Hz', 'OLED', '£949'],
                                ['AOC 24G2', '24"', '1080p', '144Hz', 'IPS', '£159'],
                                ['Samsung Odyssey G7', '32"', '1440p', '165Hz', 'VA', '£449']
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Pro Tip: Match your monitor refresh rate to your GPU capability. A 360Hz monitor is wasted if your PC can\'t push those frame rates.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Best Gaming Headsets 2025: Audio Buying Guide',
                'slug' => 'best-gaming-headsets-2025',
                'tags' => ['buying-guide', 'gaming', 'audio', 'headsets'],
                'categories' => ['Gaming', 'Accessories'],
                'author' => [
                    'name' => 'Sarah Chen',
                    'bio' => 'Gaming peripherals expert',
                ],
                'custom_fields' => [
                    'author_name' => 'Sarah Chen',
                    'read_time' => 12,
                    'excerpt' => 'Hear every footstep with our gaming headset guide. Top picks for competitive and immersive gaming.',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Best Gaming Headsets 2025',
                            'subtitle' => 'Dominate with crystal-clear audio and comms',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1599669454699-248893623440?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Best Overall: SteelSeries Arctis Nova Pro',
                            'subtitle' => 'Premium wireless with swappable batteries',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1612208695882-02f2322b7fee?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'SteelSeries Arctis Nova Pro'
                            ],
                            'url' => 'https://example.com/arctis-nova-pro',
                            'specs' => [
                                ['text' => 'Connection', 'value' => 'Wireless 2.4GHz + Bluetooth'],
                                ['text' => 'Battery Life', 'value' => '44 hours (swappable)'],
                                ['text' => 'Driver', 'value' => '40mm neodymium'],
                                ['text' => 'Microphone', 'value' => 'Retractable boom'],
                                ['text' => 'Weight', 'value' => '338g'],
                                ['text' => 'Price', 'value' => '£349']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Hot-swappable battery system',
                                'Excellent sound quality',
                                'Premium build quality',
                                'Multi-platform compatibility',
                                'Active noise cancellation'
                            ],
                            'cons' => [
                                'Very expensive',
                                'Heavy for extended sessions',
                                'Base station required'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Wireless vs Wired',
                            'productA' => 'Wireless Headsets',
                            'productB' => 'Wired Headsets',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Latency',
                                    'items' => [
                                        ['value' => '5-15ms (acceptable)'],
                                        ['value' => '0ms (instant)']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Battery Concerns',
                                    'items' => [
                                        ['value' => 'Yes (15-40 hours typical)'],
                                        ['value' => 'No']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Freedom of Movement',
                                    'items' => [
                                        ['value' => 'Excellent'],
                                        ['value' => 'Limited by cable']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        foreach ($guides as $guideData) {
            $this->createGuide($guideData, $siteId);
        }
    }
}