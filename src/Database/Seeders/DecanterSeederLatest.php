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

class DecanterSeederLatest extends Seeder
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
        $this->site = Site::where('slug', 'wine-chronicle')->first();
        $this->createArticles();
    }


    // ... within the createArticles() method of DecanterSeeder.php
    private function createArticles(): void
    {
        $articles = [
            // ... (Existing Bordeaux, Burgundy, Champagne articles go here) ...

            // --- START OF NEW ARTICLES ---
            // 1. Vineyard Tours: Bordeaux First Growths
            [
                'title' => 'Château Exploration: A Tour of Bordeaux\'s First Growths',
                'slug' => 'bordeaux-first-growth-tours',
                'tags' => ['luxury', 'wine-travel', 'bordeaux', 'vineyard-visit', 'premium'],
                'categories' => ['Wine Lifestyle', 'Wine Travel', 'Vineyard Tours', 'Wine Reviews', 'By Region', 'Bordeaux'],
                'custom_fields' => [
                    'author_name' => 'James Thornton MW',
                    'author_bio' => 'Master of Wine and Bordeaux specialist.',
                    'read_time' => 10,
                    'wine_region' => 'Bordeaux',
                    'wine_country' => 'France',
                    'price_range' => 'luxury',
                    'excerpt' => 'An insider\'s guide to visiting and tasting at the most prestigious châteaux in Bordeaux: Lafite, Margaux, Latour, Haut-Brion, and Mouton Rothschild.'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'The Five First Growths',
                            'subtitle' => 'Exclusive tours of Bordeaux\'s most legendary estates',
                            'ctaText' => 'Book Your Visit',
                            'ctaUrl' => '#booking',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1579701047463-2287f3b8f52f?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Visiting a First Growth Bordeaux château is a bucket-list experience for any serious wine lover. These estates—Château Lafite Rothschild, Château Margaux, Château Latour, Château Haut-Brion, and Château Mouton Rothschild—are icons of the wine world. Securing a private tour and tasting requires planning, but the insight into history, terroir, and winemaking is unparalleled.',
                                'Our guide details the best way to book, what to expect, and the signature wine of each legendary estate.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Château Latour: Power and Purity',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Château Latour Pavilion',
                            'brand' => 'Pauillac',
                            'productName' => 'Private Tasting Tour',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1547595628-c61a29f496f0?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'price' => 250.00,
                            'currency' => '€',
                            'description' => 'A two-hour private tour focusing on their winemaking philosophy and barrel room, culminating in a tasting of the third wine and the Forts de Latour.',
                            'link' => 'https://example.com/chateau-latour-visit',
                            'linkText' => 'Inquire to Book',
                            'displayAs' => 'button',
                            'layout' => 'standard',
                            'showReviewPanel' => false,
                            'noFollow' => true,
                            'sponsored' => false,
                            'openInNewTab' => true
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Château', 'Appellation', 'Signature Style', 'Best Time to Visit'],
                                ['Lafite Rothschild', 'Pauillac', 'Elegance & Finesse', 'May-September'],
                                ['Margaux', 'Margaux', 'Aromatic & Silky', 'October (Harvest)'],
                                ['Latour', 'Pauillac', 'Power & Longevity', 'Year-round (appointment only)'],
                                ['Haut-Brion', 'Pessac-Léognan', 'Smoky & Refined', 'April-May (Pre-En Primeur)'],
                                ['Mouton Rothschild', 'Pauillac', 'Opulence & Intensity', 'October-April']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'To stand in the cellar of a First Growth is to feel the weight of centuries of winemaking history.',
                            'attribution' => 'James Thornton MW'
                        ]
                    ],
                    [
                        'type' => 'contact-form',
                        'data' => [
                            'title' => 'Plan Your Trip',
                            'subtitle' => 'Contact us for a curated Bordeaux wine tour itinerary.',
                            'showName' => true,
                            'showEmail' => true,
                            'showSubject' => true,
                            'showMessage' => true,
                            'submitButtonText' => 'Request Itinerary'
                        ]
                    ],
                ]
            ],
            // 2. Vineyard Tours: Napa Valley Sustainable Pioneers
            [
                'title' => 'Napa Valley\'s Sustainable Pioneers: A Vineyard Guide',
                'slug' => 'napa-valley-sustainable-tours',
                'tags' => ['napa-valley', 'wine-travel', 'organic', 'biodynamic', 'vineyard-visit', 'cabernet-sauvignon'],
                'categories' => ['Wine Lifestyle', 'Wine Travel', 'Vineyard Tours', 'Wine Knowledge', 'Grape Varieties', 'Red Grapes'],
                'custom_fields' => [
                    'author_name' => 'David Chen',
                    'author_bio' => 'New World Wine Editor and former Napa Valley winemaker.',
                    'read_time' => 7,
                    'wine_region' => 'Napa Valley',
                    'wine_country' => 'USA',
                    'excerpt' => 'Discover the innovators making Napa Valley green. A guide to the best organic and biodynamic vineyards offering public and private tours.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1534065538171-866415655a68?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Napa Valley vineyard in the morning light',
                            'caption' => 'Sustainable farming is becoming the standard across Napa Valley.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Green Wineries in the Golden State',
                            'subtitle' => 'Farming for the future',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Napa Valley is leading the charge for sustainable and eco-conscious winemaking. Over 80% of Napa’s wineries and 85% of its vineyard acres are certified under the Napa Green program, exceeding California state requirements. For travelers, this means a chance to experience beautiful vineyards and taste world-class wines while supporting a healthy planet.',
                                'Here are our top three picks for public tours at wineries committed to organic and biodynamic practices.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                '**Frog’s Leap (Rutherford):** Certified organic since 1988, dry-farmed. Famous for their playful but serious Cabernet Sauvignon. **Tour Type:** Casual, reservation recommended.',
                                '**Spottswoode Estate Vineyard & Winery (St. Helena):** Fully certified organic and biodynamic. Their tour offers deep insight into their farming philosophy. **Tour Type:** Highly exclusive, reservation required months in advance.',
                                '**Robert Sinskey Vineyards (Carneros):** Features an extensive culinary garden and offers a delicious food-and-wine pairing experience. **Tour Type:** Culinary focused, daily availability.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Napa Green Impact',
                            'stats' => [
                                ['number' => '80%', 'label' => 'Certified Wineries', 'icon' => '✅'],
                                ['number' => '85%', 'label' => 'Certified Acres', 'icon' => '🍇'],
                                ['number' => '200M', 'label' => 'Gallons Saved Annually', 'icon' => '💧'],
                                ['number' => '1988', 'label' => 'First Organic Certification', 'icon' => '🌱']
                            ]
                        ]
                    ],
                ]
            ],
            // 3. Wine Routes: Mosel
            [
                'title' => 'The Mosel Wine Route: Germany\'s Steepest Vineyards and Finest Rieslings',
                'slug' => 'mosel-wine-route-guide',
                'tags' => ['wine-travel', 'destinations', 'riesling', 'germany', 'expert-advice', 'best-value'],
                'categories' => ['Wine Lifestyle', 'Wine Travel', 'Wine Routes', 'Wine Knowledge', 'Grape Varieties', 'White Grapes'],
                'custom_fields' => [
                    'author_name' => 'Emma Thompson',
                    'author_bio' => 'WSET educator and wine writer focused on accessible wine knowledge.',
                    'read_time' => 10,
                    'wine_region' => 'Mosel',
                    'wine_country' => 'Germany',
                    'excerpt' => 'A driver\'s and cyclist\'s guide to the Mosel Wine Route—exploring world-famous vineyards, charming villages, and the greatest Rieslings on earth.'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Driving the Mosel Route',
                            'subtitle' => 'Riesling, Castles, and Cliffs in Germany',
                            'ctaText' => 'View Map',
                            'ctaUrl' => '#map',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1579471120281-2c0693a7434a?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Mosel (Moselle) river carves a spectacular, twisting path through Western Germany, flanked by some of the steepest vineyards in the world. This wine route is a journey through history, with medieval castles and charming Roman towns like Trier dotting the landscape. The star, of course, is Riesling—from dry and racy to lusciously sweet, all defined by slate terroir.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Route Highlights',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'items' => [
                                '**Trier:** Start at Germany\'s oldest city for a historical context.',
                                '**Piesport:** Home to the famous Goldtröpfchen vineyard. Excellent for an overnight stay.',
                                '**Bernkastel-Kues:** A picturesque medieval town on the river, perfect for walking and wine bars.',
                                '**Bremm:** The location of the *Calmont*, the steepest vineyard in Europe. Incredible hiking opportunities.',
                                '**Cochem:** A romantic town featuring the Reichsburg Cochem castle.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Mosel Wine Buying Guide',
                            'subtitle' => 'Understanding the German Prädikat System',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1547595628-c61a29f496f0?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'url' => '/guides/german-wine-labels',
                            'linkText' => 'Read Our Full Guide',
                            'displayAs' => 'button',
                            'specs' => [
                                ['text' => 'Kabinett', 'value' => 'Lightest, often off-dry'],
                                ['text' => 'Spätlese', 'value' => 'Late harvest, more intensity'],
                                ['text' => 'Auslese', 'value' => 'Select harvest, rich and sweet'],
                                ['text' => 'Trocken', 'value' => 'Dry']
                            ],
                            'showReviewPanel' => false,
                            'noFollow' => false,
                            'sponsored' => false,
                            'openInNewTab' => false
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'There is no wine more expressive of its place than Mosel Riesling, capturing the slate, the sun, and the mist in every glass.',
                            'attribution' => 'Emma Thompson'
                        ]
                    ],
                ]
            ],
            // 4. Destinations: Tuscany Wine & Culinary Guide
            [
                'title' => 'Tuscany: A Wine & Culinary Travel Guide to Chianti and Brunello',
                'slug' => 'tuscany-wine-culinary-guide',
                'tags' => ['tuscany', 'wine-travel', 'destinations', 'sangiovese', 'wine-pairing', 'grape-variety'],
                'categories' => ['Wine Lifestyle', 'Wine Travel', 'Destinations', 'Wine Knowledge', 'Food Pairing'],
                'custom_fields' => [
                    'author_name' => 'Isabella Romano MW',
                    'author_bio' => 'Master of Wine specializing in Italian wine regions.',
                    'read_time' => 12,
                    'wine_region' => 'Tuscany',
                    'wine_country' => 'Italy',
                    'grape_variety' => 'Sangiovese',
                    'excerpt' => 'Beyond Florence: an essential guide to exploring the greatest wine regions of Tuscany, from the rolling hills of Chianti Classico to the majesty of Brunello di Montalcino.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1549725807-6a4270f23f66?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Rolling hills of the Tuscan countryside with a villa',
                            'caption' => 'The heart of Chianti Classico is the ultimate blend of beauty and gastronomy.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Sangiovese\'s Kingdom',
                            'subtitle' => 'From Florence to the Mediterranean',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Tuscany is the romantic heart of Italy, a region famed for its art, history, and the noble Sangiovese grape. The wine journey here is as much about the food and the landscape as the wine itself. The region offers two distinct world-class red wines: **Chianti Classico**, known for its lively acidity and cherry notes, and **Brunello di Montalcino**, a single-varietal, highly structured wine built for decades of aging.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Wine', 'Grape', 'Aging Requirement', 'Food Pairing'],
                                ['Chianti Classico', 'Min 80% Sangiovese', 'Min 12 months', 'Bistecca alla Fiorentina'],
                                ['Brunello di Montalcino', '100% Sangiovese', 'Min 5 years (2 in wood)', 'Aged Pecorino Cheese'],
                                ['Vino Nobile di Montepulciano', 'Min 70% Sangiovese', 'Min 2 years', 'Pappardelle al Ragù']
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Must-Try Culinary Experience',
                            'paragraphs' => [
                                'No trip to Tuscany is complete without visiting a local *macelleria* (butcher) for a true Florentine steak (*Bistecca alla Fiorentina*), paired perfectly with a high-tannin Chianti Classico Riserva.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Florence',
                                    'description' => 'The Renaissance capital and gateway to Chianti',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1511210137746-879857d81a93?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Florence cityscape'
                                ],
                                [
                                    'title' => 'Montalcino',
                                    'description' => 'Home of the powerful Brunello wine',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1520288863690-ca40a373673d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Montalcino hillside'
                                ],
                                [
                                    'title' => 'Siena',
                                    'description' => 'Medieval jewel near Chianti Classico',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1557997384-257a0753d0e2?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Siena town square'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            // 5. Destinations: Rías Baixas
            [
                'title' => 'Exploring the Rías Baixas: Albariño and the Coast of Galicia',
                'slug' => 'rias-baixas-albariño-guide',
                'tags' => ['wine-travel', 'destinations', 'white-wine', 'albariño', 'seafood', 'spain'],
                'categories' => ['Wine Lifestyle', 'Wine Travel', 'Destinations', 'Wine Knowledge', 'Grape Varieties', 'White Grapes'],
                'custom_fields' => [
                    'author_name' => 'Sophie Beaumont',
                    'author_bio' => 'Burgundy specialist with a passion for coastal white wines.',
                    'read_time' => 9,
                    'wine_region' => 'Rías Baixas',
                    'wine_country' => 'Spain',
                    'grape_variety' => 'Albariño',
                    'excerpt' => 'A comprehensive guide to the Spanish region of Rías Baixas, the home of crisp Albariño, and its perfect pairing with Galician seafood.'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Albariño\'s Coastal Home',
                            'subtitle' => 'The Salty, Sunny Rías Baixas',
                            'ctaText' => 'Discover the Wines',
                            'ctaUrl' => '#wines',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1616769064789-9a707a0f7c23?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Nestled on the rugged northwestern coast of Spain, Rías Baixas is a land defined by the Atlantic Ocean, misty weather, and the singular **Albariño** grape. The wine here is distinctively aromatic, with high acidity and a characteristic saline, refreshing finish—a wine born for seafood. This guide will walk you through the key sub-regions and the unforgettable local cuisine.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Five Sub-Zones',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Sub-Zone Comparison',
                            'productA' => 'Val do Salnés',
                            'productB' => 'Condado do Tea',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Proximity to Ocean',
                                    'items' => [
                                        ['value' => 'Very close; most coastal'],
                                        ['value' => 'Inland, along the Miño River']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Soil',
                                    'items' => [
                                        ['value' => 'Granite, sandy'],
                                        ['value' => 'Granite, slate']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Wine Style',
                                    'items' => [
                                        ['value' => 'Crisp, mineral, saline'],
                                        ['value' => 'Riper fruit, fuller body']
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
                                '**Must-Try:** *Pulpo a la Gallega* (Galician Octopus) with a youthful, unoaked Albariño.',
                                '**Key Producer:** Bodegas Martín Códax for classic, reliable style.',
                                '**Travel Tip:** Visit the historic town of Santiago de Compostela nearby.'
                            ]
                        ]
                    ],
                ]
            ],
            // 6. Tasting Guide: Host a Blind Tasting
            [
                'title' => 'How to Host a Blind Wine Tasting: A Step-by-Step Guide',
                'slug' => 'host-blind-wine-tasting',
                'tags' => ['wine-tasting', 'beginner-guide', 'expert-advice', 'wine-knowledge'],
                'categories' => ['Wine Knowledge', 'Tasting Guides', 'Beginner', 'Wine Lifestyle', 'Events'],
                'custom_fields' => [
                    'author_name' => 'Emma Thompson',
                    'author_bio' => 'WSET educator and wine writer focused on accessible wine knowledge.',
                    'read_time' => 8,
                    'excerpt' => 'Elevate your wine night: a practical guide on how to organize and execute a fun and educational blind wine tasting for friends.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1547595628-c61a29f496f0?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Wine glasses in a line for tasting with small labels',
                            'caption' => 'Blind tasting sharpens your senses and removes bias.',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'schema',
                        'data' => [
                            'schemaType' => 'how-to',
                            'title' => 'Host an Effective Blind Tasting',
                            'description' => 'A six-step process for a successful and fun blind tasting event.',
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                '**Choose a Theme:** Select 4-6 wines with a common thread (e.g., "Pinot Noir from around the world" or "Bordeaux blends").',
                                '**Prepare the Wines:** Cover bottles with foil bags and number them. Chill white and sparkling wines appropriately.',
                                '**Set the Scene:** Provide clear tasting sheets, water, and palate cleansers (plain crackers, water).',
                                '**The Pour & Assessment:** Pour samples; guests should taste, make notes, and discuss without revealing their guesses.',
                                '**The Big Reveal:** Uncover the bottles, discuss the wines, and compare notes. This is the fun part!',
                                '**Award a Prize:** Give a small prize (a bottle of wine, bragging rights) to the person who guessed the most correctly.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Tasting Note Template',
                            'level' => 3
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Stage', 'Observation', 'Tasting Terms'],
                                ['Appearance', 'Color, Clarity', 'Ruby, Pale Gold, Clear'],
                                ['Nose', 'Aromas, Intensity', 'Blackcurrant, Vanilla, Wet Stone, Pronounced'],
                                ['Palate', 'Flavors, Structure (Tannin, Acidity, Body)', 'Red Cherry, Creamy, High Acidity, Full Body'],
                                ['Conclusion', 'Quality, Identity Guess', 'Outstanding, Napa Cab Sauv 2018']
                            ]
                        ]
                    ]
                ]
            ],
            // 7. Tasting Guide: Mastering Tannins
            [
                'title' => 'Mastering Tannins: How to Judge Structure in Red Wine',
                'slug' => 'mastering-tannins-guide',
                'tags' => ['wine-tasting', 'expert-advice', 'red-wine', 'tasting-guide', 'vintage'],
                'categories' => ['Wine Knowledge', 'Tasting Guides', 'Intermediate', 'Grape Varieties', 'Red Grapes'],
                'custom_fields' => [
                    'author_name' => 'Pierre Dubois',
                    'author_bio' => 'Certified Sommelier specializing in age-worthy reds.',
                    'read_time' => 7,
                    'excerpt' => 'Tannins are the backbone of red wine. Learn where they come from, how to identify their quality, and how they determine a wine\'s aging potential and food-pairing versatility.'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Tannins: The Skeleton of Wine',
                            'subtitle' => 'Understanding Structure, Aging, and Texture',
                            'ctaText' => 'View Infographic',
                            'ctaUrl' => '#infographic',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Tannins are naturally occurring compounds found in the skins, seeds, and stems of grapes, and they are also extracted from oak barrels during aging. They are what gives red wine its **astringency**—that mouth-drying, puckering sensation you feel on your gums. Judging their quantity and quality is critical to assessing any red wine.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Where Do Tannins Come From?',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                '**Grape Skins:** The primary source, imparting high levels of tannin to thick-skinned grapes like Cabernet Sauvignon and Nebbiolo.',
                                '**Seeds & Stems:** Less desirable source; seeds release bitter tannins, while stems (used in whole-bunch fermentation) release coarse, herbaceous tannins.',
                                '**Oak Barrels:** New oak barrels introduce wood tannins, which are typically softer, rounder, and contribute flavors like vanilla and spice.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => 'Tannin Comparison Kit',
                            'productName' => 'Grape Variety Box',
                            'brand' => 'The Wine Chronicle',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1547595628-c61a29f496f0?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'price' => 199.00,
                            'currency' => '£',
                            'description' => 'A 6-bottle box featuring wines of varying tannin levels: Pinot Noir (Low), Merlot (Medium), Cabernet Sauvignon (High), Nebbiolo (Very High).',
                            'link' => 'https://example.com/tannin-sampler',
                            'showDealButton' => true,
                            'starBlock' => true
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'High-quality tannins are like polished velvet; low-quality tannins are like sandpaper. Both dry the mouth, but one is pleasurable, the other is not.',
                            'attribution' => 'Pierre Dubois'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Burgundy Grand Cru Vineyard Tour: A Journey Through Legendary Terroir',
                'slug' => 'burgundy-grand-cru-vineyard-tour',
                'tags' => ['vineyard-visit', 'burgundy', 'wine-travel', 'expert-advice'],
                'categories' => ['Wine Lifestyle', 'Wine Travel', 'Vineyard Tours'],
                'custom_fields' => [
                    'author_name' => 'Sophie Beaumont',
                    'author_bio' => 'Burgundy specialist and champion of emerging producers.',
                    'read_time' => 10,
                    'wine_region' => 'Burgundy',
                    'wine_country' => 'France',
                    'excerpt' => 'Explore the hallowed vineyards of Burgundy\'s Côte d\'Or, from Gevrey-Chambertin to Puligny-Montrachet, with our expert guide to the region\'s most prestigious domaines.'
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Walking Among the Vines of Burgundy',
                            'subtitle' => 'A privileged journey through Grand Cru vineyards',
                            'ctaText' => 'Plan Your Visit',
                            'ctaUrl' => '#planning',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1547595628-c61a29f496f0?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The narrow strip of land known as the Côte d\'Or has produced some of the world\'s most sought-after wines for over a thousand years. Walking through these Grand Cru vineyards is like stepping into a living museum of winemaking history.',
                                'We spent a week visiting family-owned domaines, learning about the subtle differences between climats, and discovering why Burgundy remains the benchmark for Pinot Noir and Chardonnay worldwide.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Book vineyard visits at least 3 months in advance. Many prestigious domaines only accept visitors by appointment and have limited availability.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Côte de Nuits: Home of Legendary Reds',
                            'subtitle' => 'From Gevrey-Chambertin to Vosne-Romanée',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1547595628-c61a29f496f0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80',
                            'alt' => 'Burgundy vineyard rows',
                            'caption' => 'The precisely delineated parcels of Burgundy\'s Grand Cru vineyards',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The Côte de Nuits is where Burgundy\'s most powerful and age-worthy Pinot Noirs are born. The villages of Gevrey-Chambertin, Chambolle-Musigny, Vougeot, Vosne-Romanée, and Nuits-Saint-Georges read like a who\'s who of fine wine.',
                                'Our first stop was Domaine Armand Rousseau in Gevrey-Chambertin, where we walked through their holdings in Chambertin and Clos de Bèze. The limestone-rich soils and eastern exposure create wines of remarkable depth and longevity.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Clos de Vougeot',
                                    'description' => 'The iconic 50-hectare walled vineyard, divided among 80 owners',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1506377247377-2a5b3b417ebb?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Clos de Vougeot stone wall'
                                ],
                                [
                                    'title' => 'Romanée-Conti',
                                    'description' => 'The world\'s most expensive vineyard - just 1.8 hectares',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1568213816046-0ee1c42bd559?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Romanée-Conti vineyard marker'
                                ],
                                [
                                    'title' => 'Traditional Cellars',
                                    'description' => 'Ancient caves where wines age in French oak barrels',
                                    'image' => ['src' => 'https://images.unsplash.com/photo-1566754534506-d94ff9d39c38?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                                    'alt' => 'Wine cellar with barrels'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Essential Domaines to Visit',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Domaine de la Romanée-Conti - By invitation only, but their monopole vineyards can be viewed from the road',
                                'Domaine Leroy - Biodynamic pioneer with holdings across top Grand Crus',
                                'Domaine Dujac - Modern cellars and gracious hosts in Morey-Saint-Denis',
                                'Domaine Méo-Camuzet - Excellent tour explaining Burgundian viticulture',
                                'Domaine Faiveley - Large négociant with extensive vineyard holdings'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Tasting Etiquette',
                            'paragraphs' => [
                                'Burgundy vignerons take their craft seriously. Arrive on time, dress smart-casual, and avoid wearing perfume or cologne which can interfere with wine aromas.',
                                'Many domaines charge tasting fees (€20-50), which are often waived with purchase. Be prepared to buy at least one bottle - these are working businesses, not tourist attractions.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Côte de Beaune: White Wine Paradise',
                            'subtitle' => 'Meursault, Puligny, and Chassagne',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'While the Côte de Beaune produces excellent reds (Pommard, Volnay, Corton), it\'s the white wines that truly shine. The trio of Meursault, Puligny-Montrachet, and Chassagne-Montrachet represents the pinnacle of Chardonnay production.',
                                'At Domaine Leflaive in Puligny-Montrachet, we experienced biodynamic viticulture at its finest. Walking through their Chevalier-Montrachet parcel, you can see why these wines command such high prices - the combination of limestone soils, perfect drainage, and ideal sun exposure is unreplicable.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'testimonial',
                        'data' => [
                            'layout' => 'grid',
                            'testimonials' => [
                                [
                                    'text' => 'The Burgundy tour opened my eyes to what great Pinot Noir should be. Walking the vineyards with the winemakers themselves was unforgettable.',
                                    'author' => 'James Mitchell',
                                    'role' => 'Wine Collector',
                                    'rating' => 5
                                ],
                                [
                                    'text' => 'Sophie\'s connections got us into domaines we\'d never have accessed alone. Worth every penny.',
                                    'author' => 'Sarah Chen',
                                    'role' => 'Sommelier',
                                    'rating' => 5
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'event',
                        'data' => [
                            'title' => 'Burgundy Grand Cru Tour 2025',
                            'description' => 'Join Sophie Beaumont for an exclusive 5-day tour of Burgundy\'s most prestigious domaines. Limited to 8 participants for intimate tastings and cellar visits. Includes accommodation, all tastings, and daily lunches paired with exceptional wines.',
                            'startDate' => '2025-09-15',
                            'endDate' => '2025-09-19',
                            'startTime' => '9:00 AM',
                            'endTime' => '6:00 PM',
                            'location' => 'Beaune, Burgundy',
                            'address' => 'Meeting point: Hôtel Le Cep, 27 Rue Maufoux, 21200 Beaune, France',
                            'ticketPrice' => 3500.00,
                            'currency' => '£',
                            'ticketUrl' => 'https://example.com/burgundy-tour',
                            'capacity' => 8,
                            'organizerName' => 'The Wine Chronicle Travel',
                            'organizerEmail' => 'travel@winechronicle.com',
                            'category' => 'Wine Tour',
                            'image' => 'https://images.unsplash.com/photo-1547595628-c61a29f496f0?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'showSignupForm' => true
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Practical Information',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'accordion',
                        'data' => [
                            'title' => 'Planning Your Visit',
                            'items' => [
                                [
                                    'question' => 'When is the best time to visit?',
                                    'answer' => 'May-June (flowering) and September-October (harvest) offer the most vineyard activity. Avoid August when many domaines close for summer holidays.'
                                ],
                                [
                                    'question' => 'How do I book domaine visits?',
                                    'answer' => 'Email domaines directly 2-3 months in advance. Include your preferred dates, group size, and wine knowledge level. Some require professional credentials or wine trade membership.'
                                ],
                                [
                                    'question' => 'What should I budget per day?',
                                    'answer' => '€150-300 per person including accommodation, tastings, and meals. Top domaines may charge €30-50 per tasting. Buying wine will significantly increase costs.'
                                ],
                                [
                                    'question' => 'Do I need a car?',
                                    'answer' => 'Essential. Villages are close together but public transport is limited. Consider hiring a driver if planning serious tastings. Many hotels offer bike rentals for exploring between villages.'
                                ]
                            ],
                            'allowMultipleOpen' => false
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'In Burgundy, the vigneron is not just a farmer but a guardian of centuries of tradition. Each climat tells a story written in the soil.',
                            'attribution' => 'Sophie Beaumont'
                        ]
                    ]
                ]
            ],

// VINEYARD TOURS ARTICLE 2
            [
                'title' => 'Napa Valley Winery Hopping: The Ultimate Guide to California Wine Country',
                'slug' => 'napa-valley-winery-hopping-guide',
                'tags' => ['vineyard-visit', 'napa-valley', 'wine-travel', 'beginner-guide'],
                'categories' => ['Wine Lifestyle', 'Wine Travel', 'Vineyard Tours'],
                'custom_fields' => [
                    'author_name' => 'David Chen',
                    'author_bio' => 'Expert in wines from California, Australia, New Zealand, and South America.',
                    'read_time' => 12,
                    'wine_region' => 'Napa Valley',
                    'wine_country' => 'USA',
                    'excerpt' => 'From iconic Cabernet Sauvignon estates to cutting-edge sustainable wineries, discover the best of Napa Valley with our insider guide to the region\'s must-visit producers.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1506377247377-2a5b3b417ebb?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Napa Valley vineyard landscape',
                            'caption' => 'The rolling vineyards of Napa Valley stretch from valley floor to mountain peaks',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Napa Valley may be just 30 miles long and a few miles wide, but it packs more world-class wineries per square mile than anywhere else on Earth. With over 400 wineries producing everything from powerful Cabernet Sauvignon to delicate Chardonnay, planning your visit can feel overwhelming.',
                                'This guide breaks down the valley into distinct regions, recommends our favorite wineries, and provides insider tips for making the most of your wine country adventure.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'banner',
                        'data' => [
                            'bannerType' => 'promo-header',
                            'title' => '🍷 Download Our Free Napa Valley Map',
                            'subtitle' => 'Interactive map with 50+ recommended wineries, restaurants, and hotels',
                            'ctaText' => 'Get Map',
                            'ctaUrl' => '/downloads/napa-map',
                            'backgroundColor' => '#8B0000',
                            'textColor' => '#ffffff',
                            'dismissible' => false
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Understanding Napa\'s Sub-Regions',
                            'subtitle' => 'From Carneros to Calistoga',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Napa Valley encompasses 16 distinct AVAs (American Viticultural Areas), each with unique microclimates and soil types. Temperature can vary by 10-15°F between cool Carneros in the south and warm Calistoga in the north.',
                                'Understanding these regions helps you plan your route and manage your palate - generally, start with lighter wines in cooler regions and work your way toward bigger, bolder wines.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Napa Valley By The Numbers',
                            'stats' => [
                                ['number' => '400+', 'label' => 'Wineries', 'icon' => '🏰'],
                                ['number' => '45,000', 'label' => 'Acres of Vineyards', 'icon' => '🍇'],
                                ['number' => '16', 'label' => 'Distinct AVAs', 'icon' => '📍'],
                                ['number' => '$50-75', 'label' => 'Average Tasting Fee', 'icon' => '💰']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Carneros: Cool Climate Specialists',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The southernmost region of Napa, cooled by fog and breezes from San Pablo Bay, specializes in Chardonnay and Pinot Noir. It\'s also where many of Napa\'s best sparkling wines originate.',
                                'Start your day here - the wines are refreshing, the tasting rooms welcoming, and you\'ll beat the crowds that sleep in and head straight to Oakville or Rutherford.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'page_grid',
                        'data' => [
                            'title' => 'Must-Visit Carneros Wineries',
                            'layout' => 'grid',
                            'columns' => 3,
                            'showExcerpt' => true,
                            'showImage' => true,
                            'pages' => [
                                [
                                    'title' => 'Domaine Carneros',
                                    'slug' => '#',
                                    'excerpt' => 'Stunning château-style winery specializing in sparkling wines. The terrace views are magnificent.',
                                    'image' => [
                                        'src' => 'https://images.unsplash.com/photo-1558346648-9757f2fa4474?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                        'alt' => 'Sparkling wine glasses'
                                    ],
                                    'features' => ['Sparkling Wine', 'Beautiful Terrace', 'No Reservation Needed'],
                                    'price' => '$40 tasting'
                                ],
                                [
                                    'title' => 'Artesa Vineyards',
                                    'slug' => '#',
                                    'excerpt' => 'Modern architecture set into hillside with panoramic valley views and excellent Pinot Noir.',
                                    'image' => [
                                        'src' => 'https://images.unsplash.com/photo-1547595628-c61a29f496f0?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                        'alt' => 'Modern winery building'
                                    ],
                                    'features' => ['Pinot Noir', 'Architecture', 'Sculpture Garden'],
                                    'price' => '$45 tasting'
                                ],
                                [
                                    'title' => 'Etude Wines',
                                    'slug' => '#',
                                    'excerpt' => 'Serious winemaking focused on Pinot Noir and Cabernet. Intimate tastings with knowledgeable staff.',
                                    'image' => [
                                        'src' => 'https://images.unsplash.com/photo-1568213816046-0ee1c42bd559?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                        'alt' => 'Wine tasting room'
                                    ],
                                    'features' => ['Educational', 'Small Groups', 'Reserve Wines'],
                                    'price' => '$50 tasting'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Oakville & Rutherford: Cabernet Country',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The heart of Napa Valley, where the most prestigious Cabernet Sauvignon producers are concentrated. The famous "Rutherford Dust" tannins and Oakville\'s gravelly soils produce wines of remarkable power and elegance.',
                                'These are the big names - Opus One, Silver Oak, Caymus, and dozens more cult producers. Expect higher tasting fees ($75-150) and mandatory reservations.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Napa Styles: Oakville vs Rutherford Cabernet',
                            'productA' => 'Oakville Cabernet',
                            'productB' => 'Rutherford Cabernet',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Tannin Structure',
                                    'items' => [
                                        ['value' => 'Polished, refined tannins'],
                                        ['value' => 'Powerful "Rutherford Dust" tannins']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Flavor Profile',
                                    'items' => [
                                        ['value' => 'Black cherry, cassis, violet'],
                                        ['value' => 'Dark chocolate, espresso, black fruit']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Aging Potential',
                                    'items' => [
                                        ['value' => '15-25 years'],
                                        ['value' => '20-30 years']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Price Range',
                                    'items' => [
                                        ['value' => '$75-300'],
                                        ['value' => '$100-500']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Opus One - Napa\'s Icon',
                            'subtitle' => 'Joint venture between Robert Mondavi and Baron Philippe de Rothschild',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1566754534506-d94ff9d39c38?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'url' => 'https://example.com/opus-one-tour',
                            'linkText' => 'Book Tour',
                            'displayAs' => 'button',
                            'specs' => [
                                ['text' => 'Tasting Fee', 'value' => '$150 per person'],
                                ['text' => 'Duration', 'value' => '90 minutes'],
                                ['text' => 'Reservation', 'value' => 'Required (3+ months ahead)'],
                                ['text' => 'Group Size', 'value' => 'Maximum 6 people']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Iconic winery with exceptional hospitality',
                                'World-class architecture and grounds',
                                'Educational tour of winemaking process',
                                'Current vintage tasting included'
                            ],
                            'cons' => [
                                'Very expensive tasting fee',
                                'Books up months in advance',
                                'Formal atmosphere may intimidate beginners'
                            ],
                            'noFollow' => false,
                            'sponsored' => false
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'St. Helena & Calistoga: Mountain Vineyards',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The northern end of the valley gets warmer and more dramatic. Mountain vineyards on the eastern and western hillsides produce intensely concentrated wines, while the valley floor around St. Helena offers more approachable, fruit-forward styles.',
                                'Calistoga, at the very north, is known for its hot springs, mud baths, and rustic charm. After a day of tasting, a spa treatment is the perfect ending.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Pride Mountain Vineyards - Straddles Napa/Sonoma border with spectacular mountain views',
                                'Spottswoode Estate - Historic Victorian estate producing elegant Cabernet',
                                'Schramsberg - Historic caves and exceptional sparkling wines',
                                'Château Montelena - Site of the 1976 Judgment of Paris Chardonnay victory',
                                'Castello di Amorosa - 121-room Tuscan castle (touristy but fun)'
                            ]
                        ]
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'style' => 'decorative'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Planning Your Perfect Day',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'schema',
                        'data' => [
                            'schemaType' => 'how-to',
                            'title' => 'How to Plan Your Napa Valley Day',
                            'description' => 'Follow this proven itinerary structure for the best winery hopping experience.',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80']
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                '9:00 AM - Start in Carneros with sparkling wine or Pinot Noir',
                                '11:00 AM - Second stop in Yountville or Oak Knoll for Chardonnay',
                                '1:00 PM - Lunch at a winery restaurant (book ahead)',
                                '3:00 PM - Oakville or Rutherford Cabernet tasting',
                                '5:00 PM - Final stop in St. Helena, ideally at a hilltop winery for sunset',
                                'Evening - Dinner in Yountville or St. Helena (The French Laundry requires months of advance booking)'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Transportation Tips',
                            'paragraphs' => [
                                'Do NOT drive yourself between tastings. Options include hiring a driver ($500-800/day), joining a group tour ($150-250/person), or using ride-sharing services.',
                                'Many hotels offer complimentary town car service to nearby wineries. The Napa Valley Wine Train is an excellent option for those who want to avoid driving entirely.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => 'Napa Valley Wine Pass',
                            'productName' => '3-Day All-Access Winery Pass',
                            'brand' => 'Napa Valley Vintners',
                            'image' => ['src' => 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'],
                            'price' => 149.00,
                            'salePrice' => 99.00,
                            'currency' => '$',
                            'description' => 'Access to 15 participating wineries over 3 consecutive days. Includes one tasting at each location plus 10% discount on purchases. Valid weekdays only.',
                            'link' => 'https://example.com/napa-pass',
                            'showDealButton' => true,
                            'voucherId' => 'NAPA2025',
                            'noFollow' => false,
                            'sponsored' => true
                        ]
                    ],
                    [
                        'type' => 'contact-form',
                        'data' => [
                            'title' => 'Request Custom Napa Itinerary',
                            'subtitle' => 'Tell us your preferences and we\'ll create a personalized day plan',
                            'showName' => true,
                            'showEmail' => true,
                            'showPhone' => false,
                            'showSubject' => true,
                            'showMessage' => true,
                            'submitButtonText' => 'Get My Itinerary',
                            'requireName' => true,
                            'requireEmail' => true,
                            'requireMessage' => true
                        ]
                    ]
                ]
            ],
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
            'meta_title' => $data['title'] . ' - The Wine Chronicle',
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