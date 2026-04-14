<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Page;

class SetPagesPrivateForSite10Seeder extends Seeder
{
    public function run(): void
    {
        $page = Page::where('site_id', 3)
            ->where('slug', 'home')
            ->first();

        if (!$page) {
            return;
        }

        foreach ($page->blocks as $block) {
            if ($block->type !== 'page_grid') {
                continue;
            }

            $pages = $block->data['pages'] ?? [];

            foreach ($pages as $index => $childPage) {
                if (empty($childPage['price'] ?? null)) {
                    continue;
                }

                $pages[$index]['is_private'] = true;
            }


            // Fix: modify data safely
            $data = $block->data;
            $data['pages'] = $pages;
            $block->data = $data;

            $block->save();
        }
    }


}