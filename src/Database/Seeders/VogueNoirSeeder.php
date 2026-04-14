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
use App\Models\PageGrid;
use App\Models\Site;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class VogueNoirSeeder extends Seeder
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
        try {
            $this->createAdditionalArticles();
        } catch (\Exception $e) {
            echo $e->getMessage();
            die('error');
        }
    }

    private function createAdditionalArticles(): void
    {
        $articles = [
            // Article 1: Designer Spotlight with Products
            [
                'title' => 'Inside Stella McCartney\'s Zero-Waste Atelier: Fashion\'s Sustainable Future',
                'slug' => 'stella-mccartney-sustainable-atelier',
                'tags' => ['featured', 'designer-spotlight', 'sustainable-fashion', 'exclusive'],
                'categories' => ['Fashion', 'Designers', 'Sustainable'],
                'custom_fields' => [
                    'author_name' => 'Isabella Rossi',
                    'author_bio' => 'Editor-in-Chief with 20 years in fashion journalism',
                    'read_time' => 14,
                    'excerpt' => 'An exclusive look inside Stella McCartney\'s innovative studio where sustainability meets luxury fashion.'
                ],
                'author' => [
                    'name' => 'Isabella Rossi',
                    'bio' => 'Editor-in-Chief with 20 years in fashion journalism',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'The Future of Fashion is Circular',
                            'subtitle' => 'Stella McCartney proves luxury and sustainability are not mutually exclusive',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1558769132-cb1aea3c5f0c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'In a converted Victorian warehouse in East London, Stella McCartney is rewriting the rules of luxury fashion. Her atelier isn\'t filled with exotic leathers or fur—instead, you\'ll find bio-fabricated mushroom leather, recycled cashmere, and regenerated ocean plastic.',
                                '"We\'ve been saying for 20 years that you don\'t need to kill animals or destroy the planet to create beautiful clothes," Stella tells me as we walk through her studio. "Now the industry is finally catching up."'
                            ]
                        ]
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'layout' => 'grid',
                            'slides' => [
                                [
                                    'title' => 'Mylo Mushroom Leather',
                                    'description' => 'Bio-fabricated leather alternative made from mycelium',
                                    'image' => 'https://images.unsplash.com/photo-1617859047452-8510bcf207fd?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Mylo mushroom leather material'
                                ],
                                [
                                    'title' => 'Regenerated Cashmere',
                                    'description' => 'Recycled fibers that feel like new',
                                    'image' => 'https://images.unsplash.com/photo-1558769132-cb1aea3c5f0c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Regenerated cashmere fabric'
                                ],
                                [
                                    'title' => 'Ocean Plastic Collection',
                                    'description' => 'Turning waste into wearable art',
                                    'image' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                    'alt' => 'Ocean plastic fabric'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Falabella Fold-Over Tote',
                            'brand' => 'Stella McCartney',
                            'productName' => 'Falabella Vegetarian Leather Tote',
                            'image' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'price' => 895,
                            'currency' => '£',
                            'description' => 'The iconic Falabella bag crafted from vegetarian leather with signature chain trim. 100% animal-free and endlessly elegant.',
                            'link' => 'https://example.com/falabella-tote',
                            'linkText' => 'Shop Now',
                            'displayAs' => 'button',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 4.8,
                                'pros' => [
                                    'Timeless design',
                                    'Completely cruelty-free',
                                    'Durable construction',
                                    'Lightweight yet spacious'
                                ],
                                'cons' => [
                                    'Premium price point',
                                    'Chain can be heavy when fully loaded'
                                ]
                            ],
                            'noFollow' => false,
                            'sponsored' => true
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Stella McCartney\'s Impact',
                            'stats' => [
                                ['number' => '0', 'label' => 'Animals Harmed', 'icon' => '🐾'],
                                ['number' => '73%', 'label' => 'Less CO2 Than Industry Average', 'icon' => '🌍'],
                                ['number' => '100%', 'label' => 'Sustainable Materials by 2025', 'icon' => '♻️'],
                                ['number' => '20', 'label' => 'Years Pioneering Sustainable Luxury', 'icon' => '⭐']
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'If you think about it, choosing to wear something kind is one of the easiest decisions you can make in a day.',
                            'attribution' => 'Stella McCartney'
                        ]
                    ]
                ]
            ],

            // Article 2: Buying Guide with Comparisons
            [
                'title' => 'Investment Handbags 2025: Which Designer Bags Hold Their Value?',
                'slug' => 'investment-handbags-2025-guide',
                'tags' => ['buying-guide', 'luxury', 'accessories', 'bags'],
                'categories' => ['Fashion', 'Accessories', 'Bags'],
                'custom_fields' => [
                    'author_name' => 'Sophie Laurent',
                    'author_bio' => 'Paris fashion correspondent specializing in luxury goods',
                    'read_time' => 12,
                    'excerpt' => 'The definitive guide to designer handbags that appreciate in value—and those that don\'t.'
                ],
                'author' => [
                    'name' => 'Sophie Laurent',
                    'bio' => 'Paris fashion correspondent specializing in luxury goods',
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Luxury designer handbags',
                            'caption' => 'Some designer bags appreciate faster than stocks',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'A Hermès Birkin bought in 2000 for £5,000 now sells for £15,000—a 200% return that outperforms most stock portfolios. But not every designer bag is a wise investment.',
                                'We analyzed 10 years of auction data, interviewed collectors, and consulted with luxury resale experts to identify which handbags truly hold their value.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Hermès Birkin 30cm',
                            'subtitle' => 'The gold standard of investment handbags',
                            'image' => 'https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'url' => 'https://example.com/birkin-guide',
                            'specs' => [
                                ['text' => 'Average Retail', 'value' => '£8,000 - £12,000'],
                                ['text' => '5-Year Appreciation', 'value' => '14% annually'],
                                ['text' => 'Wait List', 'value' => '2-6 years'],
                                ['text' => 'Best Colors', 'value' => 'Black, Gold, Etoupe'],
                                ['text' => 'Best Leather', 'value' => 'Togo or Clemence']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Unmatched resale value',
                                'Handcrafted quality',
                                'Status symbol',
                                'Limited supply increases demand'
                            ],
                            'cons' => [
                                'Extremely difficult to acquire',
                                'Very high initial cost',
                                'Requires careful maintenance'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Classic Flap: Chanel vs. Hermès',
                            'productA' => 'Chanel Classic Flap Medium',
                            'productB' => 'Hermès Constance 24',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Retail Price',
                                    'items' => [
                                        ['value' => '£6,800'],
                                        ['value' => '£7,500']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Annual Appreciation',
                                    'items' => [
                                        ['value' => '11% per year'],
                                        ['value' => '12% per year']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Availability',
                                    'items' => [
                                        ['value' => 'Purchase in-store same day'],
                                        ['value' => '1-3 year waitlist']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Resale Market',
                                    'items' => [
                                        ['value' => 'Strong demand'],
                                        ['value' => 'Exceptional demand']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Accessible luxury investment'],
                                        ['value' => 'Maximum value retention']
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
                                ['Brand', 'Model', 'Retail Price', '5-Year Value Change', 'Investment Grade'],
                                ['Hermès', 'Birkin 30cm', '£10,000', '+70%', 'A+'],
                                ['Hermès', 'Kelly 28cm', '£9,500', '+65%', 'A+'],
                                ['Chanel', 'Classic Flap Medium', '£6,800', '+55%', 'A'],
                                ['Louis Vuitton', 'Speedy 30', '£1,200', '+10%', 'B'],
                                ['Gucci', 'Jackie 1961', '£2,400', '-5%', 'C']
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Investment Tips',
                            'paragraphs' => [
                                'Buy classic colors (black, tan, navy) over trendy shades',
                                'Keep all original packaging and authenticity cards',
                                'Store bags properly with dust bags and stuffing',
                                'Consider condition—pristine bags command premium prices',
                                'Buy from authorized dealers only to ensure authenticity'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ]
                ]
            ],

            // Article 3: Event Coverage
            [
                'title' => 'Milan Fashion Week SS25: The 10 Collections Everyone\'s Talking About',
                'slug' => 'milan-fashion-week-ss25-highlights',
                'tags' => ['featured', 'fashion-week', 'milan-fashion-week', 'spring-summer', 'runway'],
                'categories' => ['Fashion', 'Runway', 'Milan'],
                'custom_fields' => [
                    'author_name' => 'Marcus Chen',
                    'author_bio' => 'Fashion editor covering European fashion weeks',
                    'read_time' => 10,
                    'excerpt' => 'From Prada\'s intellectual minimalism to Versace\'s maximalist glamour, Milan delivered drama.'
                ],
                'author' => [
                    'name' => 'Marcus Chen',
                    'bio' => 'Fashion editor covering European fashion weeks',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Milan Does It Again',
                            'subtitle' => 'SS25 collections that define next season\'s trends',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Milan Fashion Week SS25 By The Numbers',
                            'stats' => [
                                ['number' => '68', 'label' => 'Shows & Presentations', 'icon' => '👗'],
                                ['number' => '15K', 'label' => 'Industry Attendees', 'icon' => '👥'],
                                ['number' => '42', 'label' => 'Countries Represented', 'icon' => '🌍'],
                                ['number' => '6', 'label' => 'Days of Fashion', 'icon' => '📅']
                            ]
                        ]
                    ],
                    [
                        'type' => 'page_grid',
                        'data' => [
                            'title' => 'Top 10 Collections',
                            'layout' => 'grid',
                            'columns' => 2,
                            'showExcerpt' => true,
                            'showImage' => true,
                            'pages' => [
                                [
                                    'title' => 'Prada: Intellectual Minimalism',
                                    'slug' => '#',
                                    'excerpt' => 'Miuccia Prada\'s ode to simplicity featured clean lines, muted tones, and impeccable tailoring.',
                                    'image' => [
                                        'src' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                        'alt' => 'Prada runway'
                                    ],
                                    'badge' => ['text' => 'Show of the Week', 'color' => 'primary']
                                ],
                                [
                                    'title' => 'Versace: Return to Glamour',
                                    'slug' => '#',
                                    'excerpt' => 'Donatella brought back the house codes: bold prints, body-con silhouettes, and unapologetic sexiness.',
                                    'image' => [
                                        'src' => 'https://images.unsplash.com/photo-1509631179647-0177331693ae?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                        'alt' => 'Versace runway'
                                    ],
                                    'badge' => ['text' => 'Trending', 'color' => 'warning']
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'event',
                        'data' => [
                            'title' => 'Vogue Noir Milan Fashion Week Viewing Party',
                            'description' => 'Join us for an exclusive screening of the best runway shows from Milan SS25. Champagne reception, live commentary from our editors, and networking with fashion insiders.',
                            'startDate' => '2025-03-15',
                            'startTime' => '7:00 PM',
                            'endTime' => '10:00 PM',
                            'location' => 'The Vogue Noir Studio',
                            'address' => '10 Vogue Street, London W1F 8GQ',
                            'ticketPrice' => 45,
                            'currency' => '£',
                            'capacity' => 80,
                            'organizerName' => 'Vogue Noir Magazine',
                            'category' => 'Fashion Event',
                            'showSignupForm' => true
                        ]
                    ]
                ]
            ],

            // Article 4: Trend Report
            [
                'title' => 'Color Trend Report 2025: Pantone\'s Mocha Mousse Takes Over Fashion',
                'slug' => 'color-trends-2025-mocha-mousse',
                'tags' => ['trends', 'color', 'fashion-forward', 'styling'],
                'categories' => ['Fashion', 'Trends'],
                'custom_fields' => [
                    'author_name' => 'Emma Nordström',
                    'author_bio' => 'Color theory expert and trend forecaster',
                    'read_time' => 8,
                    'excerpt' => 'How to wear 2025\'s Color of the Year and the other shades dominating runways and streets.'
                ],
                'author' => [
                    'name' => 'Emma Nordström',
                    'bio' => 'Color theory expert and trend forecaster',
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Mocha and earth tones fashion',
                            'caption' => 'Earth tones are having their moment',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Pantone has spoken: Mocha Mousse is the Color of the Year for 2025. This rich, warm brown represents comfort, earthiness, and sophistication.',
                                'But it\'s not alone. We\'re seeing a broader palette of earthy, grounded tones taking over—from terracotta to sage green to dusty rose.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'items' => [
                                'Mocha Mousse - The leading lady, perfect for everything from coats to accessories',
                                'Terracotta - Warm and inviting, especially striking in knitwear',
                                'Sage Green - The cool-toned earth shade that flatters everyone',
                                'Dusty Rose - Romantic without being precious',
                                'Camel - The eternal neutral having yet another moment',
                                'Burgundy - Deep, luxurious, and endlessly elegant'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'tip',
                            'description' => 'Style Tip: Earth tones work beautifully in monochromatic looks. Try varying shades of the same color family for a sophisticated, cohesive outfit.'
                        ]
                    ],
                    [
                        'type' => 'deal',
                        'data' => [
                            'title' => 'Editor\'s Pick',
                            'productName' => 'Mocha Cashmere Oversized Coat',
                            'brand' => 'Max Mara',
                            'image' => 'https://images.unsplash.com/photo-1539533018447-63fcce2678e3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'price' => 2500,
                            'salePrice' => 1875,
                            'currency' => '£',
                            'description' => 'The iconic 101801 coat in this season\'s must-have shade. Pure cashmere, timeless silhouette, 25% off for our readers.',
                            'link' => 'https://example.com/maxmara-mocha-coat',
                            'showDealButton' => true,
                            'starBlock' => true
                        ]
                    ]
                ]
            ],

            // Article 5: Shopping Guide
            [
                'title' => 'Build Your Capsule Wardrobe: 30 Essential Pieces for Every Closet',
                'slug' => 'capsule-wardrobe-essential-pieces-2025',
                'tags' => ['featured', 'shopping', 'wardrobe-essentials', 'style-guide'],
                'categories' => ['Fashion', 'Shopping'],
                'custom_fields' => [
                    'author_name' => 'Sophie Laurent',
                    'author_bio' => 'Personal stylist and wardrobe consultant',
                    'read_time' => 15,
                    'excerpt' => 'The only shopping guide you need: 30 versatile pieces that work for every occasion, season, and budget.'
                ],
                'author' => [
                    'name' => 'Sophie Laurent',
                    'bio' => 'Personal stylist and wardrobe consultant',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Less is More',
                            'subtitle' => 'Build a wardrobe that works smarter, not harder',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1558769132-cb1aea3c5f0c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The average person wears 20% of their wardrobe 80% of the time. A capsule wardrobe flips this equation: fewer pieces, all of which you love and wear regularly.',
                                'We\'ve curated 30 essential pieces that form the foundation of a versatile, stylish wardrobe. Mix and match these items for hundreds of outfit combinations.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'schema',
                        'data' => [
                            'schemaType' => 'how-to',
                            'title' => 'How to Build a Capsule Wardrobe',
                            'description' => 'A step-by-step guide to curating your perfect minimal wardrobe'
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Audit your current wardrobe - keep only what you wear and love',
                                'Define your personal style and lifestyle needs',
                                'Choose a cohesive color palette (3-5 colors maximum)',
                                'Invest in quality basics first: jeans, white tee, blazer',
                                'Add statement pieces that reflect your personality',
                                'Ensure each piece works with at least 3 other items',
                                'Shop mindfully - one in, one out rule'
                            ]
                        ]
                    ],
                    [
                        'type' => 'divider',
                        'data' => ['style' => 'solid']
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The 30 Essentials',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Perfect white t-shirt (buy 3)',
                                'Classic white button-down',
                                'Black turtleneck',
                                'Striped Breton shirt',
                                'Cashmere crewneck sweater (grey or navy)',
                                'Tailored blazer (black or navy)',
                                'Leather jacket',
                                'Trench coat',
                                'Winter coat (wool or down)',
                                'Perfect-fit jeans (dark wash)',
                                'Black trousers (tailored)',
                                'Wide-leg trousers (neutral)',
                                'Midi skirt (black or denim)',
                                'Little black dress',
                                'White dress (summer)',
                                'Jumpsuit (black or navy)',
                                'Leather belt',
                                'Structured handbag',
                                'Everyday tote bag',
                                'Crossbody bag',
                                'White sneakers',
                                'Black ankle boots',
                                'Nude heels',
                                'Leather flats',
                                'Sandals (summer)',
                                'Sunglasses',
                                'Simple gold jewelry',
                                'Watch',
                                'Silk scarf',
                                'Quality underwear & basics'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Budget Breakdown',
                            'paragraphs' => [
                                'Invest more: Coat, blazer, leather jacket, boots, handbag (these last years)',
                                'Mid-range: Jeans, trousers, knitwear, shoes',
                                'Save on: T-shirts, basics, trendy pieces you\'ll replace seasonally'
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
                                    'text' => 'I used to spend hours deciding what to wear. Now with my capsule wardrobe, I\'m dressed in 5 minutes and always feel put-together.',
                                    'author' => 'Emma Richardson',
                                    'role' => 'Marketing Director',
                                    'rating' => 5
                                ],
                                [
                                    'text' => 'Best investment I made was quality basics. My white tees from three years ago still look brand new.',
                                    'author' => 'Lisa Chen',
                                    'role' => 'Entrepreneur',
                                    'rating' => 5
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $pages = [];

        foreach ($articles as $articleData) {
            $result = $this->createArticle($articleData);
            $pages[] = $result;

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
            'site_id' => 4,
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
            $tag = $this->tagRepository->findOrCreateByName($tagName, 4);
            $page->tags(true)->attach($tag->id);
        }

        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, 4);
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
        $site = Site::find(4);

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
            'site_id' => $site->id
        ]);
    }
}