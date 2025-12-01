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
use App\Repositories\BlockRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;
use App\Services\BlockParserService;

class WineChronicleReviewSeeder extends Seeder
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
        $this->site = Site::find(10);

        if (!$this->site) {
            echo "Wine Chronicle site not found.\n";
            return;
        }

        $reviewPages = $this->createReviewPages();
        $this->addReviewSectionToHomepage($reviewPages);
    }

    private function createReviewPages(): array
    {
        $reviews = [
            [
                'title' => 'Château Margaux 2015 Review: Bordeaux Perfection',
                'slug' => 'chateau-margaux-2015-review',
                'image' => 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?w=800',
                'wine' => 'Château Margaux 2015',
                'rating' => 5.0,
                'region' => 'Bordeaux, France',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The 2015 Château Margaux stands as one of the greatest expressions of Bordeaux in recent memory. This legendary First Growth demonstrates why Margaux has maintained its reputation for elegance and complexity for centuries. The vintage benefits from near-perfect growing conditions, resulting in a wine of remarkable balance and aging potential.',
                            'On the nose, the wine opens with layers of blackcurrant, violets, cedar, and graphite, evolving to reveal subtle notes of tobacco and dark chocolate with aeration. The aromatic complexity is extraordinary, with new nuances emerging throughout the tasting experience. The bouquet exemplifies the refined character that Margaux is celebrated for worldwide.',
                            'The palate delivers silky tannins that belie the wine\'s power and concentration. Flavors of black fruits, cassis, and plum integrate seamlessly with oak-derived notes of vanilla and spice. The texture is simultaneously rich and graceful, with perfect acidity providing structure and ensuring longevity. The finish extends for minutes, leaving impressions of minerals and dark berries.',
                            'Overall, the 2015 Château Margaux is a masterpiece that will reward cellaring for decades while offering immense pleasure even in its youth. It represents the pinnacle of Bordeaux winemaking and justifies its position among the world\'s most sought-after wines.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Screaming Eagle Cabernet Sauvignon 2018 Review',
                'slug' => 'screaming-eagle-2018-review',
                'image' => 'https://images.unsplash.com/photo-1566754436011-9c5e20aee4d7?w=800',
                'wine' => 'Screaming Eagle Cabernet Sauvignon 2018',
                'rating' => 4.9,
                'region' => 'Napa Valley, California',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Screaming Eagle\'s 2018 Cabernet Sauvignon exemplifies the power and precision that Napa Valley can achieve at its highest level. This cult wine from Oakville demonstrates impeccable balance between New World fruit intensity and Old World structure. The vintage showcases the estate\'s meticulous vineyard management and winemaking philosophy perfectly.',
                            'The aromatics are immediately captivating, with concentrated blackberry, cassis, and dark cherry supported by notes of espresso, dark chocolate, and sweet tobacco. The oak integration is seamless, with French barrels adding complexity without overwhelming the extraordinary fruit quality. Floral hints of violet and lavender add elegance to the powerful core.',
                            'On the palate, the wine displays remarkable density and concentration while maintaining freshness and elegance. Tannins are fine-grained and perfectly ripe, providing structure for decades of aging. The mid-palate richness is exceptional, with layers of dark fruit, baking spices, and mineral notes. Acidity is well-integrated, ensuring the wine never feels heavy despite its power.',
                            'In conclusion, the 2018 Screaming Eagle is a monumental California Cabernet that demonstrates why this wine commands such attention from collectors worldwide. While approachable now, it will develop additional complexity and nuance over the next twenty to thirty years.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Domaine de la Romanée-Conti Montrachet 2019 Review',
                'slug' => 'drc-montrachet-2019-review',
                'image' => 'https://images.unsplash.com/photo-1547595628-c61a29f496f0?w=800',
                'wine' => 'DRC Montrachet 2019',
                'rating' => 5.0,
                'region' => 'Burgundy, France',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The 2019 Montrachet from Domaine de la Romanée-Conti represents white Burgundy at its absolute apex. This wine from the legendary Grand Cru vineyard showcases what Chardonnay can achieve in the hands of master vintners working with exceptional terroir. The vintage delivered ideal conditions, resulting in a wine of breathtaking complexity and longevity.',
                            'Aromatically, the wine is a revelation, offering layers of white flowers, citrus zest, hazelnut, and wet stones. Beneath the surface lie notes of honey, brioche, and subtle tropical fruit that emerge with time in the glass. The aromatic precision and intensity are extraordinary, with each element perfectly delineated yet harmoniously integrated.',
                            'The palate showcases remarkable concentration balanced by vibrant acidity and mineral tension. Flavors of lemon, white peach, and pear are complemented by nutty complexity and a distinct chalky minerality. The texture is simultaneously rich and refined, with a silky weight that coats the palate. The finish seems to go on forever, leaving impressions of citrus, minerals, and subtle oak spice.',
                            'Overall, this Montrachet is a transcendent wine that demonstrates why DRC remains the benchmark for Burgundy. It will evolve magnificently over decades, rewarding patient collectors with one of the world\'s most profound white wine experiences.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Penfolds Grange 2017 Review: Australian Icon',
                'image' => 'https://images.unsplash.com/photo-1585553616435-2dc0a54e271d?w=800',
                'slug' => 'penfolds-grange-2017-review',
                'wine' => 'Penfolds Grange 2017',
                'rating' => 4.8,
                'region' => 'South Australia',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The 2017 Penfolds Grange continues the legacy of Australia\'s most iconic wine with a vintage that balances power and elegance beautifully. This Shiraz-dominant blend from multiple premium South Australian vineyards demonstrates the house style of concentrated fruit, American oak influence, and extraordinary aging potential. The vintage showcases freshness alongside the richness Grange is famous for.',
                            'The nose is immediately compelling, with blackberry, plum, and dark cherry fruit complemented by notes of mocha, vanilla, and sweet spice from new American oak. Underlying complexity emerges with aeration, revealing hints of licorice, leather, and dried herbs. The aromatic profile is bold yet refined, capturing attention without overwhelming the senses.',
                            'On the palate, the wine delivers the concentration and structure expected from Grange while maintaining surprising elegance. Tannins are firm but ripe, providing framework for decades of cellaring. The flavor profile mirrors the aromatics, with additional notes of dark chocolate and coffee adding depth. Acidity is well-integrated, keeping the wine fresh despite its richness and power.',
                            'In summary, the 2017 Grange is a worthy addition to this legendary wine\'s history. It will reward both medium-term drinking and long-term cellaring, developing additional complexity while maintaining the essential character that has made Grange an Australian icon for over sixty years.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Sassicaia 2016 Review: Super Tuscan Excellence',
                'image' => 'https://images.unsplash.com/photo-1564760055775-d63b17a55c44?w=800',
                'slug' => 'sassicaia-2016-review',
                'wine' => 'Tenuta San Guido Sassicaia 2016',
                'rating' => 4.9,
                'region' => 'Bolgheri, Tuscany',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The 2016 Sassicaia exemplifies why this wine revolutionized Italian winemaking and remains the benchmark for Super Tuscan wines. This Cabernet Sauvignon and Cabernet Franc blend from the Bolgheri region demonstrates remarkable finesse and complexity. The vintage benefited from excellent weather, producing a wine of classic proportions and exceptional balance.',
                            'Aromatically, the wine offers blackcurrant, cherry, and plum fruit layered with cedar, tobacco, graphite, and Mediterranean herbs. The bouquet evokes both Bordeaux elegance and Italian character, with subtle notes of leather and earth adding complexity. French oak is perfectly integrated, contributing structure without dominating the pure fruit expression.',
                            'The palate showcases Sassicaia\'s signature combination of power and refinement. Tannins are fine and well-integrated, providing structure without astringency. Flavors of dark berries, cassis, and herbs are complemented by mineral notes that reflect the estate\'s unique terroir. The wine shows remarkable length, with flavors evolving and persisting through an extended finish.',
                            'Overall, the 2016 Sassicaia is a brilliant expression of this legendary wine. It can be enjoyed with some decanting now but will reward cellaring for two decades or more, developing additional complexity while maintaining its essential elegance and balance.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Cristal Champagne 2013 Review: Prestige Cuvée Perfection',
                'image' => 'https://images.unsplash.com/photo-1598224531512-2d5d9c11c408?w=800',
                'slug' => 'cristal-champagne-2013-review',
                'wine' => 'Louis Roederer Cristal 2013',
                'rating' => 4.8,
                'region' => 'Champagne, France',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'The 2013 Cristal showcases Louis Roederer\'s mastery of prestige cuvée production with a vintage that balances richness with remarkable freshness. This iconic Champagne from predominantly Chardonnay grapes demonstrates why Cristal has maintained its position at the pinnacle of luxury sparkling wines. The challenging vintage year resulted in wines of exceptional tension and aging potential.',
                            'The mousse is fine and persistent, with delicate bubbles creating a creamy texture. Aromatically, the wine offers white flowers, citrus zest, white peach, and subtle notes of brioche and hazelnut. Underlying minerality adds complexity and precision, while hints of honey and almond emerge with time. The bouquet is refined and multifaceted, revealing new dimensions throughout the tasting.',
                            'On the palate, the wine displays remarkable concentration balanced by vibrant acidity and chalky minerality. Flavors of lemon, apple, and pear are complemented by notes of toast and butter from extended lees aging. The texture is simultaneously rich and energetic, with the acidity providing lift and ensuring the wine never feels heavy. The finish is long and pure, leaving impressions of citrus and minerals.',
                            'In conclusion, the 2013 Cristal is an exceptional vintage that will develop beautifully over the next decade or longer. It demonstrates the heights that Champagne can reach when meticulous viticulture meets masterful winemaking and ideal terroir.'
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
                'page_type' => 'content',
                'meta_title' => $reviewData['title'] . ' - Vogue Noir',
                'site_id' => 10,
            ]);

            $category = Category::where('slug', strtolower($reviewData['region']))->where('site_id', 10)->first();
            if ($category) {
                PageCategory::create(['page_id' => $page->id, 'category_id' => $category->id]);
            }

            $tag = Tag::where('slug', $reviewData['wine'])->where('site_id', 10)->first();
            if ($tag) {
                PageTag::create(['page_id' => $page->id, 'tag_id' => $tag->id]);
            }

            $blocks = [
                [
                    'type' => 'image',
                    'data' => [
                        'src' => $reviewData['image'],
                        'alt' => $reviewData['wine'],
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
                        'productName' => ($reviewData['region'] ?? '') . ' by ' . $reviewData['wine'],
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
        $homepage = Page::where('slug', 'home')->where('site_id', 10)->first();
        if (!$homepage) return;

        echo "Adding reviews to homepage.\n";

        $reviewItems = [];
        foreach ($reviewPages as $item) {
            $page = $item['page'];
            $data = $item['data'];

            $reviewItems[] = [
                'title' => $page->title,
                'slug' => $page->slug,
                'image' => ['src' => $data['image'], 'alt' => $data['collection'] ?? $data['wine'] ?? ''],
                'badge' => ['text' => '⭐ ' . $data['rating'] . '/5', 'color' => 'success'],
                'meta' => [
                    'region' => $data['region'] ?? '',
                    'wine' => $data['wine'] ?? '',
                    'readTime' => '10 min read'
                ]
            ];
        }

        $reviewBlock = [
            'type' => 'page_grid',
            'data' => [
                'title' => 'Latest Reviews',
                'subtitle' => 'In-depth expert reviews of the latest tech products',
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