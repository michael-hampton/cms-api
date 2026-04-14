<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Site;

class GoCompareMenuUpdateSeeder extends Seeder
{
    public function run(): void
    {
        $this->updateGoCompareMenuLinks();
    }

    private function updateGoCompareMenuLinks(): void
    {
        $site = Site::find(10);
        if (!$site) {
            echo "GoCompare site not found\n";
            return;
        }

        $menu = Menu::where('site_id', $site->id)->where('slug', 'main-menu')->first();
        if (!$menu) {
            echo "Main menu not found\n";
            return;
        }

        // Get the Insurance parent item
        $insuranceParent = MenuItem::where('menu_id', $menu->id)
            ->where('label', 'Insurance')
            ->whereNull('parent_id')
            ->first();

        if ($insuranceParent) {
            $insuranceItems = [
                ['label' => 'Car Insurance', 'page_id' => 269],
                ['label' => 'Home Insurance', 'page_id' => 270],
                ['label' => 'Travel Insurance', 'page_id' => 271],
                ['label' => 'Pet Insurance', 'page_id' => 275],
                ['label' => 'Life Insurance', 'page_id' => 276],
                ['label' => 'Health Insurance', 'page_id' => 276] // Using Life Insurance page as reference
            ];

            foreach ($insuranceItems as $item) {
                $menuItem = MenuItem::where('menu_id', $menu->id)
                    ->where('parent_id', $insuranceParent->id)
                    ->where('label', $item['label'])
                    ->first();

                if ($menuItem) {
                    $menuItem->update([
                        'target_type' => 'page',
                        'target_id' => $item['page_id'],
                        'custom_url' => null
                    ]);
                    echo "Updated: {$item['label']}\n";
                }
            }
        }

        // Get the Money parent item
        $moneyParent = MenuItem::where('menu_id', $menu->id)
            ->where('label', 'Money')
            ->whereNull('parent_id')
            ->first();

        if ($moneyParent) {
            $moneyItems = [
                ['label' => 'Credit Cards', 'page_id' => 273],
                ['label' => 'Loans', 'page_id' => 273], // Using Credit Cards as financial products reference
                ['label' => 'Mortgages', 'page_id' => 270], // Using Home Insurance for property finance
                ['label' => 'Bank Accounts', 'page_id' => 273], // Using Credit Cards for banking
                ['label' => 'Savings', 'page_id' => 276] // Using Life Insurance for financial planning
            ];

            foreach ($moneyItems as $item) {
                $menuItem = MenuItem::where('menu_id', $menu->id)
                    ->where('parent_id', $moneyParent->id)
                    ->where('label', $item['label'])
                    ->first();

                if ($menuItem) {
                    $menuItem->update([
                        'target_type' => 'page',
                        'target_id' => $item['page_id'],
                        'custom_url' => null
                    ]);
                    echo "Updated: {$item['label']}\n";
                }
            }
        }

        // Get the Utilities parent item
        $utilitiesParent = MenuItem::where('menu_id', $menu->id)
            ->where('label', 'Utilities')
            ->whereNull('parent_id')
            ->first();

        if ($utilitiesParent) {
            $utilitiesItems = [
                ['label' => 'Energy', 'page_id' => 272],
                ['label' => 'Broadband', 'page_id' => 274],
                ['label' => 'Mobile Phones', 'page_id' => 274], // Using Broadband as telecoms reference
                ['label' => 'TV & Streaming', 'page_id' => 274] // Using Broadband for home entertainment
            ];

            foreach ($utilitiesItems as $item) {
                $menuItem = MenuItem::where('menu_id', $menu->id)
                    ->where('parent_id', $utilitiesParent->id)
                    ->where('label', $item['label'])
                    ->first();

                if ($menuItem) {
                    $menuItem->update([
                        'target_type' => 'page',
                        'target_id' => $item['page_id'],
                        'custom_url' => null
                    ]);
                    echo "Updated: {$item['label']}\n";
                }
            }
        }

        // Get the Guides & Advice parent item
        $guidesParent = MenuItem::where('menu_id', $menu->id)
            ->where('label', 'Guides & Advice')
            ->whereNull('parent_id')
            ->first();

        if ($guidesParent) {
            // For guides, we'll keep category links since they aggregate multiple pages
            // But let's update them to use proper category URLs
            $guideItems = [
                ['label' => 'How-To Guides', 'url' => '/category/how-to-guides'],
                ['label' => 'Money Saving Tips', 'url' => '/category/money-saving-tips'],
                ['label' => 'Comparison Guides', 'url' => '/category/comparison-guides']
            ];

            foreach ($guideItems as $item) {
                $menuItem = MenuItem::where('menu_id', $menu->id)
                    ->where('parent_id', $guidesParent->id)
                    ->where('label', $item['label'])
                    ->first();

                if ($menuItem) {
                    $menuItem->update([
                        'target_type' => 'custom',
                        'target_id' => null,
                        'custom_url' => $item['url']
                    ]);
                    echo "Updated: {$item['label']}\n";
                }
            }
        }

        echo "\nGoCompare menu successfully updated!\n";
    }
}