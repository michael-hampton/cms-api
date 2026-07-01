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

class VogueNoirReviewSeeder extends Seeder
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
        $this->site = Site::find(4);

        if (!$this->site) {
            echo "Vogue Noir site not found.\n";
            return;
        }

        $reviewPages = $this->createReviewPages();
        $this->addReviewSectionToHomepage($reviewPages);
    }

    private function createReviewPages(): array
    {
        $reviews = [
            [
                'title' => 'Chanel Fall/Winter 2024 Collection Review',
                'slug' => 'chanel-fall-winter-2024-review',
                'image' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=800',
                'designer' => 'Chanel',
                'collection' => 'Fall/Winter 2024',
                'rating' => 4.9,
                'category' => 'Ready-to-Wear',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Chanel\'s Fall/Winter 2024 collection honors the house\'s heritage while pushing forward with contemporary relevance. The collection balances classic Chanel codes—tweed suits, quilted bags, pearls—with unexpected modern touches that feel fresh and wearable. It\'s a masterclass in evolution without abandonment of identity.',
                            'The craftsmanship throughout is impeccable, with hand-finished details and luxurious materials that justify the investment. Tweeds are woven in innovative colorways and textures, while silhouettes range from sharply tailored to fluid and romantic. The color palette moves from neutral tones to rich jewel tones, offering versatility and drama in equal measure.',
                            'Standout pieces include reimagined bouclé jackets with exaggerated shoulders, elegant evening gowns with intricate beading, and a series of practical yet luxurious separates perfect for the modern woman\'s lifestyle. Accessories continue the theme of classic-meets-contemporary, with updated versions of iconic bags and bold costume jewelry.',
                            'Overall, this collection demonstrates Chanel\'s enduring ability to remain relevant while staying true to its DNA. It offers pieces that will be treasured for decades, combining timeless elegance with just enough modernity to feel exciting and new.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Balenciaga Spring 2024 Couture Review',
                'slug' => 'balenciaga-spring-2024-couture-review',
                'image' => 'https://images.unsplash.com/photo-1509631179647-0177331693ae?w=800',
                'designer' => 'Balenciaga',
                'collection' => 'Spring 2024 Couture',
                'rating' => 4.7,
                'category' => 'Haute Couture',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Demna\'s Spring 2024 couture collection for Balenciaga redefines the boundaries between high fashion and streetwear, creating garments that are simultaneously avant-garde and wearable. The collection challenges conventional notions of luxury while showcasing the extraordinary craftsmanship that defines couture. It\'s a bold statement about fashion\'s future direction.',
                            'The silhouettes are dramatic and architectural, featuring exaggerated proportions and unexpected volumes. Tailoring is sharp and precise, with garments that deconstruct and reconstruct traditional forms. Materials range from the most luxurious silks and satins to technical fabrics more commonly associated with sportswear, all executed with couture-level attention to detail.',
                            'Particularly striking are the sculptural evening pieces that transform the body into living architecture. Innovative draping techniques and structural elements create three-dimensional forms that challenge the viewer\'s perception. The color palette is predominantly monochromatic, allowing form and texture to take center stage, with occasional bursts of vibrant color providing dramatic punctuation.',
                            'In conclusion, this collection positions Balenciaga at the forefront of contemporary fashion innovation. While polarizing, it undeniably pushes the conversation forward about what couture can mean in the twenty-first century, making it essential viewing for anyone interested in fashion\'s evolution.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Valentino Spring/Summer 2024 Review: Romantic Rebellion',
                'slug' => 'valentino-spring-summer-2024-review',
                'image' => 'https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?w=800',
                'designer' => 'Valentino',
                'collection' => 'Spring/Summer 2024',
                'rating' => 4.8,
                'category' => 'Ready-to-Wear',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Pierpaolo Piccioli\'s Spring/Summer 2024 collection for Valentino is a masterful exploration of romance, femininity, and strength. The collection celebrates beauty and craftsmanship while making powerful statements about modern femininity. It\'s fashion as poetry, with each look telling a story about confidence, grace, and individuality.',
                            'The palette is breathtaking, moving from soft pastels to vibrant fuchsias and deep blacks, with each color chosen for maximum emotional impact. Fabrics range from the weightless and ethereal to richly textured and substantial, often combined within single looks. The attention to detail in embellishments, from featherwork to hand-applied crystals, is extraordinary.',
                            'Silhouettes balance volume and structure, with billowing sleeves paired with tailored bodices, and flowing skirts grounded by strong shoulders. The collection manages to feel simultaneously timeless and of-the-moment, with pieces that could work equally well in different decades. Accessories, including statement bags and dramatic shoes, complete looks without overwhelming them.',
                            'Overall, this collection reaffirms Valentino\'s position as a house that understands both craft and emotion. It offers clothes that make women feel beautiful and powerful, demonstrating that romance and strength are not opposing forces but complementary aspects of modern femininity.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'The Row Fall 2024 Collection Review: Quiet Luxury Perfected',
                'slug' => 'the-row-fall-2024-review',
                'designer' => 'The Row',
                'image' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=800',
                'collection' => 'Fall 2024',
                'rating' => 4.9,
                'category' => 'Ready-to-Wear',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Mary-Kate and Ashley Olsen\'s Fall 2024 collection for The Row exemplifies the "quiet luxury" aesthetic at its finest. Every piece demonstrates an obsessive attention to detail, material quality, and proportion. This is fashion stripped to its essentials, where excellence speaks louder than logos or obvious branding.',
                            'The collection focuses on impeccable tailoring and luxurious materials, from the finest cashmeres to perfectly weighted wools and buttery leathers. Silhouettes are clean and modern, with a focus on how clothes move and drape on the body. Colors are predominantly neutral—camel, black, cream, charcoal—allowing the cut and quality to take center stage.',
                            'Standout pieces include oversized coats with perfect shoulder lines, slouchy trousers that somehow look both relaxed and refined, and knitwear so luxurious it redefines casual elegance. The styling is minimal and sophisticated, showing how each piece can integrate into a discerning wardrobe. Every button, seam, and hem receives the same meticulous consideration.',
                            'In conclusion, The Row continues to prove that true luxury lies in quality, fit, and thoughtful design rather than flash or trends. This collection offers investment pieces that will remain relevant and beautiful for decades, rewarding those who appreciate craft and understated elegance.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Gucci Ancora Collection Review: Maximalist Magic',
                'slug' => 'gucci-ancora-collection-review',
                'image' => 'https://images.unsplash.com/photo-1469334031218-e382a71b716b?w=800',
                'designer' => 'Gucci',
                'collection' => 'Ancora Collection',
                'rating' => 4.6,
                'category' => 'Ready-to-Wear',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Sabato De Sarno\'s Ancora collection for Gucci marks a fresh chapter for the house, balancing Gucci\'s heritage of maximalism with a more refined, sophisticated approach. The collection celebrates Italian craftsmanship and sensuality while offering pieces that feel both special and wearable. It\'s a recalibration that honors the past while looking confidently to the future.',
                            'The aesthetic leans into luxurious materials and rich colors, with particular emphasis on tactile experiences—silks that flow, leathers that mold, cashmeres that caress. Silhouettes emphasize the body in flattering ways, with attention to proportion and movement. The Gucci codes are present but refined, with the iconic double-G logo used sparingly and thoughtfully.',
                            'Particularly successful are the evening pieces, which combine Old Hollywood glamour with contemporary sensibility. Flowing gowns, sharp tuxedos, and statement coats demonstrate versatility and range. Accessories maintain Gucci\'s tradition of desirability while feeling fresh, with updated versions of classic bags and new silhouettes that are likely to become future icons.',
                            'Overall, the Ancora collection successfully navigates the challenge of honoring Gucci\'s identity while moving forward. It offers luxury that feels grown-up and confident, appealing to customers seeking investment pieces with both heritage and modern relevance.'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Dior Haute Couture Spring 2024 Review',
                'slug' => 'dior-haute-couture-spring-2024-review',
                'image' => 'https://images.unsplash.com/photo-1558769132-cb1aea3c3e75?w=800',
                'designer' => 'Dior',
                'collection' => 'Spring 2024 Haute Couture',
                'rating' => 5.0,
                'category' => 'Haute Couture',
                'review' => [
                    'type' => 'text',
                    'data' => [
                        'paragraphs' => [
                            'Maria Grazia Chiuri\'s Spring 2024 Haute Couture collection for Dior is a breathtaking celebration of the atelier\'s savoir-faire and feminine power. The collection draws inspiration from both Dior\'s archives and contemporary artistic movements, creating garments that exist at the intersection of art and fashion. It\'s couture in the truest sense—one-of-a-kind pieces that push the boundaries of what\'s possible.',
                            'The craftsmanship on display is extraordinary, with thousands of hours of hand work evident in each piece. Embroidery techniques range from traditional haute couture methods to innovative contemporary approaches. Silks are pleated by hand, tulle is layered with precision, and embellishments are applied with meticulous care. The atelier\'s mastery of volume and structure creates sculptural forms that defy gravity.',
                            'The collection balances Dior\'s heritage of ultra-feminine silhouettes with modern elements of strength and fluidity. Bar jackets are reimagined with contemporary proportions, while flowing gowns incorporate unexpected structural elements. The color palette ranges from delicate pastels to bold primary colors, each chosen to enhance the emotional impact of individual pieces.',
                            'In summary, this collection demonstrates why Dior remains at the apex of haute couture. It combines historical reverence with forward-thinking innovation, technical mastery with artistic vision, resulting in garments that are true works of wearable art destined to become part of fashion history.'
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
                'meta_title' => $reviewData['title'] . ' - Vogue Noir',
                'site_id' => 4,
            ]);

            $category = Category::where('slug', strtolower($reviewData['category']))->where('site_id', 6)->first();
            if ($category) {
                PageCategory::create(['page_id' => $page->id, 'category_id' => $category->id]);
            }

            $tag = Tag::where('slug', $reviewData['designer'])->where('site_id', 6)->first();
            if ($tag) {
                PageTag::create(['page_id' => $page->id, 'tag_id' => $tag->id]);
            }

            $blocks = [
                [
                    'type' => 'image',
                    'data' => [
                        'src' => $reviewData['image'],
                        'alt' => $reviewData['designer'],
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
                        'productName' => ($reviewData['collection'] ?? '') . ' by ' . $reviewData['designer'],
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
        $homepage = Page::where('slug', 'home')->where('site_id', 6)->first();
        if (!$homepage) return;

        echo "Adding reviews to homepage.\n";

        $reviewItems = [];
        foreach ($reviewPages as $item) {
            $page = $item['page'];
            $data = $item['data'];

            $reviewItems[] = [
                'title' => $page->title,
                'slug' => $page->slug,
                'image' => ['src' => $data['image'], 'alt' => $data['collection'] ?? $data['designer'] ?? ''],
                'badge' => ['text' => '⭐ ' . $data['rating'] . '/5', 'color' => 'success'],
                'meta' => [
                    'collection' => $data['collection'] ?? '',
                    'designer' => $data['designer'] ?? '',
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
