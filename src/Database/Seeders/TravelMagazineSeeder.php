<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\CustomFieldDefinition;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\Site;
use App\Models\Territory;
use App\Parsers\BlockParserService;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\TagRepository;

class TravelMagazineSeeder extends Seeder
{
    private $pageRepository;
    private $blockRepository;
    private $tagRepository;
    private $categoryRepository;
    private $blockParserService;
    private \App\Models\Model $site;
    private array $territories = [];
    private array $menus = [];
    private array $articlePages = []; // Store created article pages by region

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
        $this->createSite();
        $this->createTerritories();
        $this->createMenus();
        $this->createTags();
        $this->createCategories();
        $this->createCustomFields();
        $this->createRegionArticles(); // Create articles FIRST
        $this->createRegionHomepages(); // Then create homepages with page_grid
    }

    private function createSite(): void
    {
        $this->site = Site::create([
            'name' => 'Global Wanderlust - Travel Magazine',
            'is_active' => true,
            'slug' => 'global-wanderlust',
        ]);
    }

    private function createTerritories(): void
    {
        $regions = [
            ['name' => 'Asia Pacific', 'slug' => 'asia-pacific', 'code' => 'APAC'],
            ['name' => 'Europe', 'slug' => 'europe', 'code' => 'EU'],
            ['name' => 'Americas', 'slug' => 'americas', 'code' => 'AMER']
        ];

        foreach ($regions as $region) {
            $territory = Territory::create([
                'name' => $region['name'],
                'slug' => $region['slug'],
                'code' => $region['code'],
                'is_active' => true,
                'site_id' => $this->site->id
            ]);
            $this->territories[$region['slug']] = $territory;
            $this->articlePages[$region['slug']] = []; // Initialize array for each region
        }
    }

    private function createMenus(): void
    {
        foreach ($this->territories as $slug => $territory) {
            $menu = Menu::create([
                'name' => ucfirst($territory->name) . ' Menu',
                'slug' => $slug . '-menu',
                'site_id' => $this->site->id,
                'menu_type' => 'header',
                'is_active' => true,
            ]);

            $menu->territories(true)->attach($territory->id);
            $this->menus[$slug] = $menu;
        }
    }

    private function createTags(): void
    {
        $tags = [
            'featured', 'adventure', 'luxury', 'budget-travel', 'cultural',
            'food-travel', 'beach', 'mountains', 'city-break', 'wildlife',
            'photography', 'solo-travel', 'family-travel', 'backpacking',
            'cruise', 'road-trip', 'hidden-gems', 'sustainable-travel'
        ];

        foreach ($tags as $tagName) {
            $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
        }
    }

    private function createCategories(): void
    {
        $categories = [
            'Destinations' => [
                'Cities' => null,
                'Beaches' => null,
                'Mountains' => null,
                'Islands' => null
            ],
            'Travel Tips' => [
                'Planning' => null,
                'Packing' => null,
                'Budget' => null,
                'Safety' => null
            ],
            'Experiences' => [
                'Adventure' => null,
                'Culture' => null,
                'Food' => null,
                'Wellness' => null
            ],
            'Accommodation' => [
                'Hotels' => null,
                'Hostels' => null,
                'Resorts' => null,
                'Unique Stays' => null
            ]
        ];

        $this->createCategoriesRecursively($categories);
    }

    private function createCategoriesRecursively(array $categories, ?int $parentId = null): void
    {
        foreach ($categories as $name => $children) {
            $category = $this->categoryRepository->findOrCreateByName($name, $this->site->id);
            if ($parentId) {
                $category->parent_id = $parentId;
                $category->save();
            }

            if (is_array($children) && !empty($children)) {
                $this->createCategoriesRecursively($children, $category->id);
            }
        }
    }

    private function createCustomFields(): void
    {
        $fields = [
            ['key' => 'author_name', 'name' => 'Author Name', 'type' => 'text'],
            ['key' => 'author_bio', 'name' => 'Author Bio', 'type' => 'textarea'],
            ['key' => 'read_time', 'name' => 'Read Time (minutes)', 'type' => 'number'],
            ['key' => 'excerpt', 'name' => 'Article Excerpt', 'type' => 'textarea'],
            ['key' => 'destination', 'name' => 'Destination', 'type' => 'text'],
            ['key' => 'budget_range', 'name' => 'Budget Range', 'type' => 'select', 'options' => '{"budget":"Budget","mid":"Mid-Range","luxury":"Luxury"}'],
        ];

        foreach ($fields as $field) {
            CustomFieldDefinition::create([
                'key' => $field['key'],
                'name' => $field['name'],
                'type' => $field['type'],
                'is_active' => true,
                'sort_order' => 10,
                'options' => $field['options'] ?? null,
                'site_id' => $this->site->id
            ]);
        }
    }

    private function createRegionArticles(): void
    {
        $articlesByRegion = [
            'asia-pacific' => [
                [
                    'title' => 'Tokyo After Dark: A Neon-Lit Adventure',
                    'slug' => 'tokyo-after-dark',
                    'excerpt' => 'Experience the electric energy of Tokyo\'s nightlife, from traditional izakayas to futuristic robot restaurants.',
                    'author' => 'Yuki Tanaka',
                    'destination' => 'Tokyo, Japan',
                    'budget_range' => 'mid',
                    'hero_image' => 'https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?w=800',
                    'badge' => ['text' => 'Featured', 'color' => 'primary']
                ],
                [
                    'title' => 'Bali\'s Hidden Beach Paradise',
                    'slug' => 'bali-hidden-beaches',
                    'excerpt' => 'Escape the crowds and discover Bali\'s secluded coastlines and pristine waters.',
                    'author' => 'Sarah Mitchell',
                    'destination' => 'Bali, Indonesia',
                    'budget_range' => 'budget',
                    'hero_image' => 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?w=800',
                    'badge' => ['text' => 'Adventure', 'color' => 'success']
                ],
                [
                    'title' => 'The Great Barrier Reef: An Underwater Wonder',
                    'slug' => 'great-barrier-reef-guide',
                    'excerpt' => 'Dive into the world\'s largest coral reef system and witness nature\'s masterpiece.',
                    'author' => 'Jack Robinson',
                    'destination' => 'Queensland, Australia',
                    'budget_range' => 'luxury',
                    'hero_image' => 'https://images.unsplash.com/photo-1582967788606-a171c1080cb0?w=800',
                    'badge' => ['text' => 'Luxury', 'color' => 'warning']
                ]
            ],
            'europe' => [
                [
                    'title' => 'Paris Beyond the Eiffel Tower',
                    'slug' => 'paris-hidden-gems',
                    'excerpt' => 'Discover the secret gardens, hidden cafés, and local haunts that Parisians love.',
                    'author' => 'Marie Dubois',
                    'destination' => 'Paris, France',
                    'budget_range' => 'mid',
                    'hero_image' => 'https://images.unsplash.com/photo-1502602898657-3e91760cbb34?w=800',
                    'badge' => ['text' => 'Featured', 'color' => 'primary']
                ],
                [
                    'title' => 'Scottish Highlands Road Trip',
                    'slug' => 'scottish-highlands-adventure',
                    'excerpt' => 'Drive through misty mountains, ancient castles, and legendary lochs.',
                    'author' => 'Duncan MacLeod',
                    'destination' => 'Scottish Highlands, UK',
                    'budget_range' => 'mid',
                    'hero_image' => 'https://images.unsplash.com/photo-1605092676920-8ac5ae40c7c8?w=800',
                    'badge' => ['text' => 'Road Trip', 'color' => 'success']
                ],
                [
                    'title' => 'Mediterranean Magic: Island Hopping in Greece',
                    'slug' => 'greek-island-hopping',
                    'excerpt' => 'Sail between whitewashed villages and turquoise waters in the Cyclades.',
                    'author' => 'Elena Papadopoulos',
                    'destination' => 'Greek Islands',
                    'budget_range' => 'luxury',
                    'hero_image' => 'https://images.unsplash.com/photo-1533104816931-20fa691ff6ca?w=800',
                    'badge' => ['text' => 'Luxury', 'color' => 'warning']
                ]
            ],
            'americas' => [
                [
                    'title' => 'Patagonia: At the End of the World',
                    'slug' => 'patagonia-wilderness',
                    'excerpt' => 'Trek through dramatic landscapes where glaciers meet mountains in South America\'s wild frontier.',
                    'author' => 'Carlos Rodriguez',
                    'destination' => 'Patagonia, Argentina/Chile',
                    'budget_range' => 'mid',
                    'hero_image' => 'https://images.unsplash.com/photo-1523531294919-4bcd7c65e216?w=800',
                    'badge' => ['text' => 'Featured', 'color' => 'primary']
                ],
                [
                    'title' => 'New York City on a Shoestring Budget',
                    'slug' => 'nyc-budget-guide',
                    'excerpt' => 'Experience the Big Apple without breaking the bank with insider tips and free attractions.',
                    'author' => 'Maya Johnson',
                    'destination' => 'New York City, USA',
                    'budget_range' => 'budget',
                    'hero_image' => 'https://images.unsplash.com/photo-1496442226666-8d4d0e62e6e9?w=800',
                    'badge' => ['text' => 'Budget', 'color' => 'success']
                ],
                [
                    'title' => 'Costa Rica: Pura Vida Adventure',
                    'slug' => 'costa-rica-adventure',
                    'excerpt' => 'Zip through rainforests, surf pristine beaches, and encounter incredible wildlife.',
                    'author' => 'Diego Martinez',
                    'destination' => 'Costa Rica',
                    'budget_range' => 'mid',
                    'hero_image' => 'https://images.unsplash.com/photo-1621337460762-44c136c4c6f5?w=800',
                    'badge' => ['text' => 'Adventure', 'color' => 'warning']
                ]
            ]
        ];

        foreach ($this->territories as $slug => $territory) {
            $articles = $articlesByRegion[$slug];

            foreach ($articles as $articleData) {
                $page = $this->createArticle($articleData, $territory, $slug);

                // Store article data for page_grid
                $this->articlePages[$slug][] = [
                    'title' => $articleData['title'],
                    'slug' => $articleData['slug'],
                    'excerpt' => $articleData['excerpt'],
                    'image' => [
                        'src' => $articleData['hero_image'],
                        'alt' => $articleData['title']
                    ],
                    'badge' => $articleData['badge'],
                    'meta' => [
                        'author' => $articleData['author'],
                        'date' => date('F j, Y'),
                        'readTime' => '8 min read'
                    ]
                ];
            }
        }
    }

    private function createRegionHomepages(): void
    {
        $homepageData = [
            'asia-pacific' => [
                'title' => 'Asia Pacific Travel Guide',
                'subtitle' => 'Discover Ancient Temples, Modern Cities & Tropical Paradise',
                'hero_image' => 'https://images.unsplash.com/photo-1528181304800-259b08848526?w=2340'
            ],
            'europe' => [
                'title' => 'European Adventures',
                'subtitle' => 'From Historic Capitals to Mediterranean Coastlines',
                'hero_image' => 'https://images.unsplash.com/photo-1467269204594-9661b134dd2b?w=2340'
            ],
            'americas' => [
                'title' => 'Americas Explorer',
                'subtitle' => 'Journey Through Diverse Landscapes & Vibrant Cultures',
                'hero_image' => 'https://images.unsplash.com/photo-1501594907352-04cda38ebc29?w=2340'
            ]
        ];

        foreach ($this->territories as $slug => $territory) {
            $data = $homepageData[$slug];

            $page = $this->createPage([
                'title' => $data['title'],
                'slug' => $slug,
                'meta_title' => $data['title'] . ' - Global Wanderlust',
                'meta_description' => $data['subtitle'],
            ], $territory);

            $this->createMenuItem([
                'label' => 'Home',
                'menu_id' => $this->menus[$slug]->id,
                'target_id' => $page->id,
                'sort_order' => 1
            ]);

            $blocks = [
                $this->buildHeroBlock($data['title'], $data['subtitle'], $data['hero_image'], 1),
                $this->buildHeadingBlock('Featured Stories', 'Latest from ' . $territory->name, 2),
                $this->buildPageGridBlock($this->articlePages[$slug], 3) // Pass actual article data
            ];

            $this->createBlocksForPage($page->id, $blocks);
        }
    }

    private function createArticle(array $data, Territory $territory, string $regionSlug): Page
    {
        $page = $this->createPage([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'meta_title' => $data['title'] . ' - Global Wanderlust',
            'meta_description' => $data['excerpt'],
        ], $territory);

        $this->createMenuItem([
            'label' => $data['title'],
            'menu_id' => $this->menus[$regionSlug]->id,
            'target_id' => $page->id,
            'sort_order' => 10
        ]);

        $this->attachTags($page, ['featured', 'adventure', 'cultural']);
        $this->attachCategories($page, ['Destinations']);
        $this->createCustomFieldsForPage($page, [
            'author_name' => $data['author'],
            'read_time' => 8,
            'excerpt' => $data['excerpt'],
            'destination' => $data['destination'],
            'budget_range' => $data['budget_range']
        ]);

        $blocks = $this->buildArticleBlocks($data);
        $this->createBlocksForPage($page->id, $blocks);

        return $page;
    }

    private function buildArticleBlocks(array $data): array
    {
        return [
            $this->buildImageBlock(
                $data['hero_image'],
                $data['title'],
                'Exploring ' . $data['destination'],
                1
            ),

            $this->buildTextBlock([
                $data['excerpt'],
                'Travel is not just about the destination—it\'s about the journey, the people you meet, and the experiences that transform you.',
                'Join us as we explore the wonders of ' . $data['destination'] . ' and uncover what makes this place truly special.'
            ], 2),

            $this->buildHeadingBlock('Why Visit ' . $data['destination'] . '?', 'Discover what makes this destination unforgettable', 3),

            $this->buildListBlock([
                'Unique cultural experiences that can\'t be found anywhere else',
                'Stunning natural beauty and breathtaking landscapes',
                'Delicious local cuisine and authentic dining experiences',
                'Friendly locals who welcome travelers with open arms',
                'Incredible photo opportunities at every turn'
            ], 4),

            $this->buildQuoteBlock(
                'The world is a book, and those who do not travel read only one page.',
                'Saint Augustine',
                5
            )
        ];
    }

    // ============================================
    // Block Builder Methods
    // ============================================

    private function buildHeroBlock(string $title, string $subtitle, string $image, int $order): array
    {
        return [
            'type' => 'hero',
            'data' => [
                'title' => $title,
                'subtitle' => $subtitle,
                'ctaText' => 'Explore Destinations',
                'ctaUrl' => '#articles',
                'showSearch' => false,
                'backgroundImage' => $image
            ],
            'order' => $order
        ];
    }

    private function buildHeadingBlock(string $text, string $subtitle, int $order, int $level = 2): array
    {
        return [
            'type' => 'heading',
            'data' => [
                'text' => $text,
                'subtitle' => $subtitle,
                'level' => $level
            ],
            'order' => $order
        ];
    }

    private function buildPageGridBlock(array $pages, int $order): array
    {
        return [
            'type' => 'page_grid',
            'data' => [
                'title' => '',
                'layout' => 'grid',
                'columns' => 3,
                'showExcerpt' => true,
                'showImage' => true,
                'showMeta' => true,
                'pages' => $pages // Actual page data from articlePages
            ],
            'order' => $order
        ];
    }

    private function buildImageBlock(string $src, string $alt, string $caption, int $order): array
    {
        return [
            'type' => 'image',
            'data' => [
                'src' => $src,
                'alt' => $alt,
                'caption' => $caption,
                'layout' => 'full',
                'alignment' => 'fullscreen'
            ],
            'order' => $order
        ];
    }

    private function buildTextBlock(array $paragraphs, int $order): array
    {
        return [
            'type' => 'text',
            'data' => [
                'paragraphs' => $paragraphs
            ],
            'order' => $order
        ];
    }

    private function buildListBlock(array $items, int $order, string $listType = 'ul'): array
    {
        return [
            'type' => 'list',
            'data' => [
                'listType' => $listType,
                'items' => $items
            ],
            'order' => $order
        ];
    }

    private function buildQuoteBlock(string $text, string $attribution, int $order): array
    {
        return [
            'type' => 'quote',
            'data' => [
                'text' => $text,
                'attribution' => $attribution
            ],
            'order' => $order
        ];
    }

    // ============================================
    // Helper Methods
    // ============================================

    private function createPage(array $data, Territory $territory): Page
    {
        $page = Page::create(array_merge([
            'page_type' => 'content',
            'status' => 'published',
            'site_id' => $this->site->id,
        ], $data));

        $page->territories(true)->attach($territory->id);

        return $page;
    }

    private function createMenuItem(array $data): void
    {
        MenuItem::create(array_merge([
            'target_type' => 'page',
            'is_active' => true,
        ], $data));
    }

    private function attachTags(Page $page, array $tagNames): void
    {
        foreach ($tagNames as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, $this->site->id);
            $page->tags(true)->attach($tag->id);
        }
    }

    private function attachCategories(Page $page, array $categoryNames): void
    {
        foreach ($categoryNames as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, $this->site->id);
            $page->categories(true)->attach($category->id);
        }
    }

    private function createCustomFieldsForPage(Page $page, array $fields): void
    {
        foreach ($fields as $key => $value) {
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