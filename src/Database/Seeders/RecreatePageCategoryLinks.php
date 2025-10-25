<?php

namespace App\Database\Seeders;

use App\Models\Category;
use App\Models\Page;
use App\Models\PageCategory;
use App\Models\Site;

class RecreatePageCategoryLinks
{

    public function run(): void
    {
        $this->recreateComputerWeeklyCategories();
        $this->recreateDecanterCategories();
        $this->recreateFashionCategories();
        $this->recreateFoodRecipeCategories();
        $this->recreateHomeGardenCategories();
        $this->recreateMusicCategories();
    }

    private function recreateComputerWeeklyCategories(): void
    {
        $site = Site::where('slug', 'tech-weekly')->first();
        if (!$site) return;

        $articles = [
            [
                'slug' => 'quantum-computing-cybersecurity',
                'categories' => ['Security', 'Cybersecurity', 'Threats']
            ],
            [
                'slug' => 'microservices-go-kubernetes',
                'categories' => ['Development', 'Programming', 'Tools']
            ],
            [
                'slug' => 'ai-code-generation-comparison',
                'categories' => ['Reviews', 'Software']
            ]
        ];

        $this->attachCategoriesToPages($site->id, $articles);
    }

    private function recreateDecanterCategories(): void
    {
        $site = Site::where('slug', 'wine-chronicle')->first();
        if (!$site) return;

        $articles = [
            [
                'slug' => 'bordeaux-2020-vintage-guide',
                'categories' => ['Wine Reviews', 'By Region', 'Bordeaux']
            ],
            [
                'slug' => 'burgundy-value-wines-guide',
                'categories' => ['Wine Reviews', 'By Region', 'Burgundy']
            ],
            [
                'slug' => 'champagne-tasting-masterclass',
                'categories' => ['Wine Knowledge', 'Tasting Guides', 'Advanced']
            ]
        ];

        $this->attachCategoriesToPages($site->id, $articles);
    }

    private function recreateFashionCategories(): void
    {
        $site = Site::where('slug', 'vogue-noir')->first();
        if (!$site) return;

        $articles = [
            [
                'slug' => 'paris-fashion-week-spring-2025',
                'categories' => ['Fashion', 'Runway', 'Paris']
            ],
            [
                'slug' => 'sustainable-luxury-designers',
                'categories' => ['Fashion', 'Designers', 'Sustainable']
            ],
            [
                'slug' => 'nyfw-street-style',
                'categories' => ['Fashion', 'Street Style']
            ]
        ];

        $this->attachCategoriesToPages($site->id, $articles);
    }

    private function recreateFoodRecipeCategories(): void
    {
        $site = Site::where('slug', 'taste-table')->first();
        if (!$site) return;

        $articles = [
            [
                'slug' => 'ultimate-italian-cooking-guide',
                'categories' => ['Recipes', 'By Cuisine', 'Italian']
            ],
            [
                'slug' => 'mexican-street-tacos-guide',
                'categories' => ['Recipes', 'By Cuisine', 'Mexican']
            ],
            [
                'slug' => 'asian-noodle-bowls-quick-recipes',
                'categories' => ['Recipes', 'By Cuisine', 'Asian']
            ],
            [
                'slug' => 'perfect-chocolate-cake-recipe',
                'categories' => ['Recipes', 'By Meal', 'Dessert']
            ],
            [
                'slug' => 'mediterranean-diet-complete-guide',
                'categories' => ['Cooking Guides', 'Meal Prep']
            ],
            [
                'slug' => 'kitchen-knife-buying-guide-2025',
                'categories' => ['Product Reviews', 'Kitchen Tools']
            ]
        ];

        $this->attachCategoriesToPages($site->id, $articles);
    }

    private function recreateHomeGardenCategories(): void
    {
        $site = Site::where('slug', 'haven-hearth')->first();
        if (!$site) return;

        $articles = [
            [
                'slug' => 'scandinavian-interior-design-guide',
                'categories' => ['Interior Design', 'Design Styles', 'Scandinavian']
            ],
            [
                'slug' => 'smart-lighting-comparison-2025',
                'categories' => ['Product Reviews', 'Lighting']
            ],
            [
                'slug' => 'spring-garden-makeover-ideas',
                'categories' => ['Garden & Outdoor', 'Outdoor Living']
            ]
        ];

        $this->attachCategoriesToPages($site->id, $articles);
    }

    private function recreateMusicCategories(): void
    {
        $site = Site::where('slug', 'soundwave')->first();
        if (!$site) return;

        $articles = [
            [
                'slug' => 'luna-eclipse-cover-story',
                'categories' => ['Features', 'Interviews']
            ],
            [
                'slug' => 'synthwave-neon-dreams-review',
                'categories' => ['Reviews', 'Albums']
            ],
            [
                'slug' => 'coachella-2025-guide',
                'categories' => ['News', 'Festivals']
            ],
            [
                'slug' => 'zara-quinn-interview',
                'categories' => ['Features', 'Interviews']
            ],
            [
                'slug' => 'vinyl-renaissance-feature',
                'categories' => ['Culture', 'Industry']
            ],
            [
                'slug' => 'arctic-monkeys-msg-review',
                'categories' => ['Reviews', 'Live Shows']
            ]
        ];

        $this->attachCategoriesToPages($site->id, $articles);
    }

    private function attachCategoriesToPages(int $siteId, array $articles): void
    {
        foreach ($articles as $articleData) {
            $page = Page::where('slug', $articleData['slug'])
                ->where('site_id', $siteId)
                ->first();

            if (!$page) {
                echo "Page not found: {$articleData['slug']}\n";
                continue;
            }

            foreach ($articleData['categories'] as $categoryName) {
                $category = Category::where('name', $categoryName)
                    ->where('site_id', $siteId)
                    ->first();

                if (!$category) {
                    echo "Category not found: {$categoryName} for site {$siteId}\n";
                    continue;
                }

                // Check if the link already exists before creating it
                $exists = PageCategory::where('page_id', $page->id)
                    ->where('category_id', $category->id)
                    ->exists();

                if (!$exists) {
                    $page->categories(true)->attach($category->id);
                    echo "Attached category '{$categoryName}' to page '{$page->slug}'\n";
                } else {
                    echo "Link already exists: '{$categoryName}' -> '{$page->slug}'\n";
                }
            }
        }
    }
}