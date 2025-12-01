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
use App\Models\Site;
use App\Repositories\BlockRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;
use App\Services\BlockParserService;

class VogueNoirAccessoriesSeeder extends Seeder
{
    private $pageRepository;
    private $blockRepository;
    private $tagRepository;
    private $categoryRepository;
    private $blockParserService;
    private \App\Models\Model $site;

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
        $this->site = Site::find(6); // Vogue Noir site ID

        if (!$this->site) {
            echo "Vogue Noir site not found.\n";
            return;
        }

        $this->createAccessoriesPages();
        echo "Vogue Noir accessories pages created successfully!\n";
    }

    private function createAccessoriesPages(): void
    {
        $pages = [
            // Luxury Handbags Guide
            [
                'title' => 'The Ultimate Guide to Luxury Handbags: Investment Pieces Worth Every Penny',
                'slug' => 'luxury-handbags-investment-guide',
                'tags' => ['featured', 'accessories', 'bags', 'luxury', 'buying-guide'],
                'categories' => ['Fashion', 'Accessories', 'Bags'],
                'custom_fields' => [
                    'author_name' => 'Charlotte Beaumont',
                    'author_bio' => 'Luxury accessories expert with 15 years at Christie\'s auction house',
                    'read_time' => 18,
                    'excerpt' => 'From Birkins to Boy Bags, discover which designer handbags appreciate in value and how to build a collection that lasts generations.'
                ],
                'author' => [
                    'name' => 'Charlotte Beaumont',
                    'bio' => 'Luxury accessories expert with 15 years at Christie\'s auction house',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Investment Handbags',
                            'subtitle' => 'The designer bags that appreciate faster than stocks',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'A Hermès Birkin 30 purchased in 2010 for £6,000 now sells for £18,000—a 200% return that outperforms most traditional investments. But the world of investment handbags extends far beyond Hermès, with savvy collectors building portfolios that combine passion with profit.',
                                'Not every designer bag is worth the investment. We analyzed decade-long auction data, interviewed luxury resale experts, and consulted with collectors to identify which handbags truly hold their value—and which are better left on the boutique shelf.',
                                'Whether you\'re building your first collection or adding to an existing wardrobe, this comprehensive guide reveals everything you need to know about investing in luxury handbags.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'stats',
                        'data' => [
                            'title' => 'Luxury Handbag Market 2025',
                            'stats' => [
                                ['number' => '14%', 'label' => 'Average Annual Appreciation', 'icon' => '📈'],
                                ['number' => '£42B', 'label' => 'Global Market Value', 'icon' => '💰'],
                                ['number' => '97%', 'label' => 'Hermès Birkin Resale Rate', 'icon' => '👜'],
                                ['number' => '3-6yr', 'label' => 'Average Birkin Waitlist', 'icon' => '⏰']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'The Holy Grail: Hermès Birkin', 'level' => 2]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Hermès Birkin 30cm',
                            'subtitle' => 'The ultimate investment handbag',
                            'image' => 'https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'url' => 'https://example.com/hermes-birkin',
                            'specs' => [
                                ['text' => 'Average Retail Price', 'value' => '£8,000 - £15,000'],
                                ['text' => 'Annual Appreciation', 'value' => '14.2%'],
                                ['text' => 'Best Colors for Investment', 'value' => 'Black, Gold, Etoupe, Bleu'],
                                ['text' => 'Best Leather', 'value' => 'Togo or Clemence'],
                                ['text' => 'Waitlist Time', 'value' => '2-6 years']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Unmatched resale value and appreciation',
                                'Handcrafted by single artisan (18+ hours)',
                                'Limited production maintains exclusivity',
                                'Timeless design never goes out of style',
                                'Strong demand across all markets'
                            ],
                            'cons' => [
                                'Extremely difficult to acquire at retail',
                                'High initial investment required',
                                'Requires careful maintenance',
                                'Risk of counterfeits in resale market'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Hermès Birkin vs. Kelly: Which to Choose?',
                            'productA' => 'Birkin 30cm',
                            'productB' => 'Kelly 28cm',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Retail Price',
                                    'items' => [
                                        ['value' => '£10,000 average'],
                                        ['value' => '£9,500 average']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Annual Appreciation',
                                    'items' => [
                                        ['value' => '14.2% per year'],
                                        ['value' => '13.8% per year']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Practicality',
                                    'items' => [
                                        ['value' => 'Easier access, two handles'],
                                        ['value' => 'More formal, single handle']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Resale Demand',
                                    'items' => [
                                        ['value' => 'Exceptional - most sought'],
                                        ['value' => 'Excellent - classic choice']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Everyday luxury & investment'],
                                        ['value' => 'Formal occasions & elegance']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Chanel: Timeless French Elegance', 'level' => 2]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Chanel Classic Flap Medium',
                            'brand' => 'Chanel',
                            'productName' => 'Classic Double Flap Medium',
                            'image' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'price' => 7200,
                            'currency' => '£',
                            'description' => 'The iconic quilted design with interlocking CC closure. Available at retail with regular price increases that boost resale value.',
                            'link' => 'https://example.com/chanel-classic-flap',
                            'linkText' => 'Find Stockists',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 4.7,
                                'pros' => [
                                    'Available to purchase without waitlist',
                                    'Annual price increases boost investment',
                                    '11% average annual appreciation',
                                    'Multiple color and hardware options',
                                    'Iconic, instantly recognizable design'
                                ],
                                'cons' => [
                                    'Regular price increases (£500+ annually)',
                                    'Vintage pieces may need costly repairs',
                                    'Caviar leather more durable than lambskin'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Brand', 'Model', 'Retail Price', '5-Year Appreciation', 'Accessibility', 'Investment Grade'],
                                ['Hermès', 'Birkin 30', '£10,000', '+71%', 'Very Difficult', 'A+'],
                                ['Hermès', 'Kelly 28', '£9,500', '+69%', 'Very Difficult', 'A+'],
                                ['Chanel', 'Classic Flap Medium', '£7,200', '+55%', 'Moderate', 'A'],
                                ['Louis Vuitton', 'Neverfull MM', '£1,400', '+12%', 'Easy', 'B'],
                                ['Dior', 'Lady Dior Medium', '£4,800', '+18%', 'Moderate', 'B+'],
                                ['Bottega Veneta', 'Jodie', '£2,600', '-8%', 'Easy', 'C'],
                                ['Gucci', 'Jackie 1961', '£2,400', '-5%', 'Easy', 'C']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Building Your Collection: Expert Strategy', 'level' => 2]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Start with one investment piece - A classic Hermès or Chanel in black or neutral',
                                'Focus on classics over trendy designs - Timeless silhouettes retain value',
                                'Choose neutral colors first - Black, tan, navy appreciate better than seasonal shades',
                                'Keep all packaging and authenticity cards - These add 10-15% to resale value',
                                'Buy from authorized dealers only - Counterfeits are worthless',
                                'Maintain condition meticulously - Store with stuffing, use dust bags',
                                'Build relationships with SAs - Key to accessing Hermès pieces',
                                'Consider vintage for discontinued styles - Some appreciate faster than new',
                                'Diversify across brands - Don\'t put all resources into one house',
                                'Think long-term - Best returns come after 5+ years'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Authentication is Critical',
                            'paragraphs' => [
                                'The luxury handbag market is flooded with sophisticated counterfeits. Before purchasing any pre-owned bag, have it authenticated by professionals like Entrupy, Authenticate First, or luxury consignment experts.',
                                'Red flags include: significantly below-market pricing, sellers unwilling to provide detailed photos, missing serial numbers, poor stitching quality, and incorrect hardware.',
                                'For Hermès bags, check the craftsman\'s stamp, date code format, stitching (should be perfectly even), and hardware weight. When in doubt, walk away.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Where to Buy & Sell', 'level' => 2]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'For new purchases, authorized boutiques offer peace of mind but limited access to coveted pieces. Building a purchase history with regular visits and smaller purchases can open doors to exclusive offerings.',
                                'The resale market offers wider selection but requires caution. Reputable platforms like Vestiaire Collective, The RealReal, and Fashionphile authenticate items and offer guarantees. Expect to pay 10-30% above retail for in-demand pieces.',
                                'When selling, timing matters. Hermès pieces sell best in autumn/winter, while colorful bags move faster in spring/summer. Factor in platform fees (typically 15-25%) when pricing.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'testimonial',
                        'data' => [
                            'layout' => 'grid',
                            'testimonials' => [
                                [
                                    'text' => 'I bought my first Birkin in 2015 for £7,500. Today it\'s worth £19,000. It\'s the best investment I ever made, and I get to carry it!',
                                    'author' => 'Victoria Chen',
                                    'role' => 'Collector',
                                    'rating' => 5
                                ],
                                [
                                    'text' => 'My Chanel Classic Flap has increased £3,000 in value in just four years. I wear it weekly and still consider it an investment piece.',
                                    'author' => 'Amelia Thompson',
                                    'role' => 'Fashion Editor',
                                    'rating' => 5
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Buy less, choose well, make it last. A quality bag is an investment in yourself and your future.',
                            'attribution' => 'Vivienne Westwood'
                        ]
                    ]
                ]
            ],

            // Designer Jewelry Guide
            [
                'title' => 'Fine Jewelry Investment Guide: Cartier, Tiffany & Beyond',
                'slug' => 'designer-jewelry-investment-guide',
                'tags' => ['featured', 'accessories', 'jewelry', 'luxury', 'buying-guide'],
                'categories' => ['Fashion', 'Accessories', 'Jewelry'],
                'custom_fields' => [
                    'author_name' => 'Arabella Sterling',
                    'author_bio' => 'Former Sotheby\'s jewelry specialist and gemologist',
                    'read_time' => 16,
                    'excerpt' => 'From Cartier Love bracelets to Van Cleef Alhambra necklaces, discover which designer jewelry pieces are worth the investment.'
                ],
                'author' => [
                    'name' => 'Arabella Sterling',
                    'bio' => 'Former Sotheby\'s jewelry specialist and gemologist',
                ],
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Investment-Grade Jewelry',
                            'subtitle' => 'Designer pieces that transcend trends and appreciate with time',
                            'backgroundImage' => 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Unlike costume jewelry that loses value the moment you leave the boutique, certain designer pieces appreciate steadily over time. A Cartier Love bracelet purchased in 2010 for £3,500 now retails for £6,750—and vintage pieces command even higher prices.',
                                'The fine jewelry market combines craftsmanship, precious materials, and brand heritage to create pieces that serve as both adornment and investment. But not all designer jewelry is created equal when it comes to retaining value.',
                                'We\'ve analyzed market trends, auction results, and resale data to identify the designer jewelry pieces worth your investment—and those better suited for enjoyment than appreciation.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'The Icon: Cartier Love Bracelet', 'level' => 2]
                    ],
                    [
                        'type' => 'buying-guide',
                        'data' => [
                            'title' => 'Cartier Love Bracelet',
                            'subtitle' => 'The most recognizable piece of modern jewelry',
                            'image' => 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'url' => 'https://example.com/cartier-love',
                            'specs' => [
                                ['text' => 'Yellow Gold (no diamonds)', 'value' => '£6,750'],
                                ['text' => 'With 4 Diamonds', 'value' => '£8,000'],
                                ['text' => 'With 10 Diamonds', 'value' => '£11,800'],
                                ['text' => 'Average Appreciation', 'value' => '6-8% annually'],
                                ['text' => 'Material', 'value' => '18k gold']
                            ],
                            'showReviewPanel' => true,
                            'pros' => [
                                'Iconic design recognized globally',
                                'Steady price increases boost value',
                                'Strong resale market',
                                'Unisex appeal broadens buyer base',
                                'Timeless aesthetic never dates'
                            ],
                            'cons' => [
                                'Requires screwdriver to remove',
                                'Scratches easily with daily wear',
                                'Counterfeits common in resale market',
                                'High retail price for entry'
                            ]
                        ]
                    ],
                    [
                        'type' => 'product',
                        'data' => [
                            'name' => 'Van Cleef & Arpels Vintage Alhambra',
                            'brand' => 'Van Cleef & Arpels',
                            'productName' => 'Vintage Alhambra 20 Motif Necklace',
                            'image' => 'https://images.unsplash.com/photo-1599643477877-530eb83abc8e?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                            'price' => 4950,
                            'currency' => '£',
                            'description' => 'The iconic four-leaf clover motif in onyx, carnelian, or mother-of-pearl. A Van Cleef signature since 1968.',
                            'link' => 'https://example.com/vca-alhambra',
                            'linkText' => 'Find at Van Cleef',
                            'showReviewPanel' => true,
                            'review' => [
                                'rating' => 4.9,
                                'pros' => [
                                    'Timeless design nearly 60 years old',
                                    'Versatile - works day to evening',
                                    'Multiple length and material options',
                                    'Strong brand heritage',
                                    'Excellent resale value'
                                ],
                                'cons' => [
                                    'Delicate - requires careful handling',
                                    'Mother-of-pearl can chip',
                                    'Often waitlisted for popular versions'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Brand', 'Piece', 'Entry Price', '10-Yr Appreciation', 'Investment Grade'],
                                ['Cartier', 'Love Bracelet', '£6,750', '+90%', 'A+'],
                                ['Van Cleef', 'Alhambra Necklace', '£4,950', '+65%', 'A'],
                                ['Tiffany', 'T Wire Bracelet', '£3,200', '+15%', 'B+'],
                                ['Bulgari', 'B.zero1 Ring', '£1,890', '+25%', 'B'],
                                ['Cartier', 'Juste un Clou', '£5,950', '+75%', 'A'],
                                ['Tiffany', 'Return to Tiffany', '£240', '-10%', 'C']
                            ]
                        ]
                    ],
                    [
                        'type' => 'product-comparison',
                        'data' => [
                            'title' => 'Gold vs. Diamond Versions: Which to Choose?',
                            'productA' => 'Plain Gold',
                            'productB' => 'Diamond-Set',
                            'comparisons' => [
                                [
                                    'subtitle' => 'Initial Cost',
                                    'items' => [
                                        ['value' => 'Lower entry point'],
                                        ['value' => '20-70% premium for diamonds']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Appreciation',
                                    'items' => [
                                        ['value' => 'Tracks gold prices + brand'],
                                        ['value' => 'Slower - diamonds depreciate']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Versatility',
                                    'items' => [
                                        ['value' => 'More casual, everyday'],
                                        ['value' => 'Dressier, special occasions']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Resale',
                                    'items' => [
                                        ['value' => 'Stronger market demand'],
                                        ['value' => 'Smaller buyer pool']
                                    ]
                                ],
                                [
                                    'subtitle' => 'Best For',
                                    'items' => [
                                        ['value' => 'Investment + daily wear'],
                                        ['value' => 'Personal enjoyment']
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Investment Strategy for Fine Jewelry',
                            'paragraphs' => [
                                'Focus on iconic pieces from heritage houses: Cartier, Van Cleef & Arpels, and Bulgari consistently outperform newer designers in the resale market.',
                                'Choose yellow gold over white gold or platinum: Yellow gold is timeless and easier to resell. Rose gold trends come and go.',
                                'Plain versions appreciate better than diamond-set: Diamonds add cost but rarely add equivalent value in resale.',
                                'Buy the smallest size that fits comfortably: Smaller sizes are more versatile and have broader resale appeal.',
                                'Keep all original packaging and certificates: These can add 15-20% to resale value.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => ['text' => 'Emerging Investment Pieces', 'level' => 2]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Cartier Panthère Collection - Especially vintage pieces from the 1980s',
                                'Bulgari Serpenti watches and jewelry - Strong brand heritage driving demand',
                                'Early Van Cleef Alhambra pieces - Pre-2010 versions command premiums',
                                'Cartier Trinity rings in larger sizes - Three-band design increasingly collectible',
                                'Tiffany Elsa Peretti pieces - Especially Bone Cuff and Open Heart'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Jewelry is like the perfect spice - it always complements what\'s there. But it should also be an investment piece that appreciates over time.',
                            'attribution' => 'Diane von Furstenberg'
                        ]
                    ]
                ]
            ]
        ];

        foreach ($pages as $pageData) {
            $page = $this->createArticle($pageData);
            echo "Created: {$page->title}\n";
        }
    }

    private function createArticle(array $data): Page
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

        if (!empty($data['author'])) {
            $data['author']['slug'] = Str::slug($data['author']['name']);
            $author = Author::create($data['author']);
            PageAuthor::create([
                'page_id' => $page->id,
                'author_id' => $author->id
            ]);
        }

        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
            $page->tags(true)->attach($tag->id);
        }

        foreach ($data['categories'] as $categoryName) {
            echo $categoryName;
            $category = $this->categoryRepository->findOrCreateByName($categoryName, $this->site->id);
            $page->categories(true)->attach($category->id);
        }

        foreach ($data['custom_fields'] as $key => $value) {
            $fieldDef = CustomFieldDefinition::where('key', $key)
                ->where('site_id', $this->site->id)
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

        return $page;
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