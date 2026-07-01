<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\Category;
use App\Models\Page;
use App\Models\PageCategory;
use App\Models\PageTag;
use App\Models\Site;
use App\Models\Tag;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\BlockParserService;

class GolfMonthlyReviewSeeder extends Seeder
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
        $this->site = Site::find(11);

        if (!$this->site) {
            echo "Golf Monthly site not found.\n";
            return;
        }

        $reviewPages = $this->createReviewPages();
        $this->addReviewSectionToHomepage($reviewPages);
    }

    private function createReviewPages(): array
    {
        $reviews = [
            [
                'title' => 'TaylorMade Stealth 2 Driver Review',
                'slug' => 'taylormade-stealth-2-driver-review',
                'category' => 'Equipment Reviews',
                'image' => 'https://images.unsplash.com/photo-1587174486073-ae5e5cff23aa?w=800',
                'brand' => 'TaylorMade',
                'product' => 'Stealth 2 Driver',
                'rating' => 4.8,
                'category_type' => 'Drivers',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The TaylorMade Stealth 2 Driver represents a significant evolution in carbon face technology, delivering exceptional ball speed and forgiveness across the face. The 60X Carbon Twist Face creates a larger sweet spot while maintaining the structural integrity needed for maximum distance. The refined aerodynamic shape reduces drag through the impact zone, promoting faster swing speeds.',
                            'Performance testing revealed impressive distance gains of 8-12 yards compared to previous generation drivers, with remarkably consistent dispersion patterns. The Thru-Slot Speed Pocket on the sole enhances flexibility in the lower face area, particularly beneficial for players who struggle with low-face strikes. Sound and feel have been refined from the original Stealth, offering a more satisfying acoustic experience at impact.',
                            'Adjustability features include a 4-degree loft sleeve and sliding weight track that allows players to fine-tune launch conditions and shot shape bias. The stock shaft options cater to a wide range of swing speeds, though custom fitting is highly recommended to maximize performance benefits. Build quality is exceptional with premium materials throughout.',
                            'Overall, the TaylorMade Stealth 2 Driver is a tour-proven performer that delivers measurable improvements in both distance and accuracy. It suits golfers from mid-handicappers to professionals seeking cutting-edge technology with proven results on course.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Titleist T200 Irons Review',
                'slug' => 'titleist-t200-irons-review',
                'category' => 'Equipment Reviews',
                'brand' => 'Titleist',
                'image' => 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?w=800',
                'product' => 'T200 Irons',
                'rating' => 4.7,
                'category_type' => 'Irons',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The Titleist T200 Irons masterfully blend distance technology with the precision and feel that better players demand. Featuring a hollow-body construction with a forged face insert, these irons deliver impressive ball speeds while maintaining a compact, tour-inspired profile. The Max Impact technology positions tungsten weights strategically to optimize launch and enhance forgiveness on off-center hits.',
                            'Launch monitor testing showed consistent 5-7 yard distance improvements over traditional cavity back irons, with exceptional apex heights that promote soft landings on greens. The progressive design throughout the set sees longer irons optimized for distance and launch, while short irons prioritize precision and workability. Turf interaction is excellent with the narrow sole design promoting clean contact from various lies.',
                            'Feel and feedback are outstanding for a distance iron, with the forged L-Face insert providing the soft sensation skilled players prefer. Shot-shaping capabilities exceed expectations, allowing players to work the ball both directions with confidence. The aesthetic package is understated and elegant, featuring minimal offset and a thin topline that inspires confidence at address.',
                            'In conclusion, the Titleist T200 Irons are ideal for accomplished players seeking to add distance without sacrificing control or feel. They represent a sweet spot in Titleist\'s lineup, bridging the gap between game-improvement and pure players irons with remarkable effectiveness.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Callaway Paradym X Fairway Wood Review',
                'slug' => 'callaway-paradym-x-fairway-wood-review',
                'category' => 'Equipment Reviews',
                'image' => 'https://images.unsplash.com/photo-1713728920047-45c7d1a51f1c?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'brand' => 'Callaway',
                'product' => 'Paradym X Fairway Wood',
                'rating' => 4.6,
                'category_type' => 'Fairway Woods',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Callaway\'s Paradym X Fairway Wood introduces groundbreaking 360 Carbon Chassis technology that redistributes weight with unprecedented efficiency. This design allows for a significantly lower center of gravity while maintaining structural integrity, resulting in higher launch angles and increased forgiveness. The Jailbreak Batwing structure stiffens the body to promote faster ball speeds across a wider area of the face.',
                            'On-course performance exceeded expectations, with the fairway wood excelling from both the deck and off the tee. The draw-biased design helps players who struggle with a slice, though not so aggressively that it limits workability. Launch conditions are optimal for most swing speeds, producing penetrating ball flights that hold their line in wind. The adjustable hosel provides additional loft and lie customization options.',
                            'The Tungsten Speed Cartridge positioned low and deep enhances stability and forgiveness on mishits, particularly those struck low on the face. Sound and feel are solid and powerful without being overly loud or harsh. The crown design features subtle alignment aids that help with proper setup without being visually distracting.',
                            'Overall, the Callaway Paradym X Fairway Wood is an excellent choice for golfers seeking maximum forgiveness and consistent performance. It\'s particularly well-suited for mid-to-high handicappers who need help getting the ball airborne but will also appeal to better players who value versatility and reliability.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Ping G430 Max Driver Review',
                'slug' => 'ping-g430-max-driver-review',
                'category' => 'Equipment Reviews',
                'brand' => 'Ping',
                'image' => 'https://plus.unsplash.com/premium_photo-1680346492290-f8069cbbc9ab?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                'product' => 'G430 Max Driver',
                'rating' => 4.9,
                'category_type' => 'Drivers',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The Ping G430 Max Driver sets a new benchmark for forgiveness and consistency in the premium driver category. Featuring Ping\'s largest head profile to date at 460cc, combined with a 25-gram tungsten backweight positioned extremely rearward, this driver offers exceptional stability and MOI. The Carbonfly Wrap technology saves weight in the crown, allowing for optimal CG placement and improved launch characteristics.',
                            'Testing revealed remarkable consistency across various impact locations, with minimal distance and accuracy loss on off-center hits. Ball speeds remain competitive with the market\'s fastest drivers, while the combination of high launch and low spin produces optimal carry distances for players across the handicap spectrum. The sound at impact is satisfyingly muted, and feel provides excellent feedback on strike quality.',
                            'Adjustability features include Ping\'s proven 8-setting hosel that allows ±1.5 degrees of loft adjustment and lie angle modifications to optimize ball flight. The Trajectory Tuning 2.0 sleeve enables further customization when paired with professional fitting. Stock shaft options from Alta, Ping Tour, and Project X cater to different swing profiles, though custom options significantly expand fitting possibilities.',
                            'In summary, the Ping G430 Max Driver is an outstanding choice for golfers prioritizing forgiveness and consistency without sacrificing distance. Its combination of proven technology, build quality, and performance makes it suitable for everyone from improving amateurs to competitive players seeking ultimate reliability.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Cobra King Tour MIM Wedges Review',
                'slug' => 'cobra-king-tour-mim-wedges-review',
                'brand' => 'Cobra',
                'category' => 'Equipment Reviews',
                'image' => 'https://images.unsplash.com/photo-1566577134770-3d85bb3a9cc4?w=800',
                'product' => 'King Tour MIM Wedges',
                'rating' => 4.7,
                'category_type' => 'Wedges',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Cobra\'s King Tour MIM Wedges utilize Metal Injection Molding technology to create grooves with unprecedented precision and consistency. This manufacturing process allows for tighter tolerances than traditional forging, resulting in grooves that meet USGA conforming limits while maximizing spin generation. The 304 stainless steel construction provides excellent durability and a premium feel at impact.',
                            'Short game performance is exceptional, with these wedges generating impressive spin rates on full shots, partial swings, and delicate pitches around the greens. The Progressive Spin Technology features different groove configurations optimized for each loft, with tighter spacing in higher lofts for maximum control on finesse shots. Versatility from various lies and turf conditions is outstanding, with the cambered sole promoting clean turf interaction.',
                            'Feel and feedback are exemplary, providing players with the tactile information needed to execute precise distance control. The tour-inspired shaping features minimal offset and a compact profile that appeals to accomplished players. Multiple grind options allow for customization based on swing type and course conditions, from the versatile V-Grind to the high-bounce S-Grind for softer turf.',
                            'Overall, the Cobra King Tour MIM Wedges are premium scoring clubs that deliver tour-level performance and craftsmanship. They\'re ideal for skilled players who demand maximum spin, control, and consistency in their short game, though the price point reflects their elite positioning.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Odyssey Tri-Hot 5K Putter Review',
                'slug' => 'odyssey-tri-hot-5k-putter-review',
                'category' => 'Equipment Reviews',
                'image' => 'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?w=800',
                'brand' => 'Odyssey',
                'product' => 'Tri-Hot 5K Putter',
                'rating' => 4.8,
                'category_type' => 'Putters',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The Odyssey Tri-Hot 5K Putter combines classic design aesthetics with modern performance technology to create an exceptional putting experience. The iconic three-dot alignment system returns in updated form, providing clear visual guidance while maintaining the clean look that made the original Tri-Hot series so popular. The multi-material construction optimizes weight distribution for enhanced stability and forgiveness.',
                            'The White Hot insert delivers Odyssey\'s legendary soft feel while producing consistent roll characteristics across the face. Forward press technology in the insert promotes immediate topspin for improved accuracy and distance control on varying green speeds. Testing on multiple surfaces confirmed excellent performance on both fast and slow greens, with predictable behavior on off-center strikes.',
                            'Balance and weight feel are precisely calibrated, with head weights available from 350 to 370 grams to accommodate different stroke tempos. The refined hosel options include heel-shafted, center-shafted, and short slant configurations to match various putting strokes and preferences. The premium finish resists wear and maintains its appearance through extensive use.',
                            'In conclusion, the Odyssey Tri-Hot 5K Putter is a masterful blend of nostalgia and innovation that performs at the highest level. It suits golfers who appreciate traditional aesthetics combined with proven modern technology, making it an excellent choice for players across all skill levels seeking improved putting performance.'
                        ]
                    ]
                ]
            ]
        ];

        $pages = [];
        foreach ($reviews as $reviewData) {
            $page = Page::create([
                'title' => $reviewData['title'],
                'slug' => $reviewData['slug'],
                'status' => 'published',
                'page_type' => 'review',
                'meta_title' => $reviewData['title'] . ' - Golf Monthly',
                'site_id' => 11,
            ]);

            $category = Category::where('slug', strtolower(str_replace(' ', '-', $reviewData['category'])))->where('site_id', 11)->first();
            if ($category) {
                PageCategory::create(['page_id' => $page->id, 'category_id' => $category->id]);
            }

            $tag = Tag::where('slug', strtolower(str_replace(' ', '-', $reviewData['brand'])))->where('site_id', 11)->first();
            if ($tag) {
                PageTag::create(['page_id' => $page->id, 'tag_id' => $tag->id]);
            }

            $blocks = [
                [
                    'type' => 'image',
                    'data' => [
                        'src' => $reviewData['image'],
                        'alt' => $reviewData['product'],
                        'layout' => 'full',
                        'alignment' => 'fullscreen'
                    ]
                ],
                [
                    'type' => 'heading',
                    'data' => ['text' => $reviewData['title'], 'level' => 1]
                ],
                [
                    'type' => 'award',
                    'data' => [
                        'subcategory' => 'Expert Review',
                        'productName' => $reviewData['product'] . ' by ' . $reviewData['brand'],
                        'winner' => true,
                        'rating' => $reviewData['rating'],
                    ]
                ],
                [
                    'type' => 'text',
                    'data' => $reviewData['review']['data']
                ]
            ];

            $this->createBlocksForPage($page->id, $blocks);
            $pages[] = ['page' => $page, 'data' => $reviewData];
        }

        return $pages;
    }

    private function createBlocksForPage(int $pageId, array $blocks): void
    {
        foreach ($blocks as $blockData) {
            $this->blockRepository->create([
                'page_id' => $pageId,
                'type' => $blockData['type'],
                'data' => json_encode($blockData['data']),
                'order' => $blockData['order'] ?? 1
            ]);
        }
    }

    private function addReviewSectionToHomepage(array $reviewPages): void
    {
        $homepage = Page::where('slug', 'home')->where('site_id', 11)->first();
        if (!$homepage) return;

        echo "Adding reviews to homepage.\n";

        $reviewItems = [];
        foreach ($reviewPages as $item) {
            $page = $item['page'];
            $data = $item['data'];

            $reviewItems[] = [
                'title' => $page->title,
                'slug' => $page->slug,
                'image' => ['src' => $data['image'], 'alt' => $data['product'] ?? $data['brand'] ?? ''],
                'badge' => ['text' => '⭐ ' . $data['rating'] . '/5', 'color' => 'success'],
                'meta' => [
                    'product' => $data['product'] ?? '',
                    'brand' => $data['brand'] ?? '',
                    'readTime' => '8 min read'
                ]
            ];
        }

        $reviewBlock = [
            'type' => 'page_grid',
            'data' => [
                'title' => 'Latest Equipment Reviews',
                'subtitle' => 'Expert reviews of the newest golf equipment',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'pages' => $reviewItems
            ]
        ];

        // Get current max order
        $maxOrder = $homepage->blocks()->max('order') ?? 0;

        $this->blockRepository->create([
            'page_id' => $homepage->id,
            'type' => $reviewBlock['type'],
            'data' => json_encode($reviewBlock['data']),
            'order' => $maxOrder + 1
        ]);
    }
}