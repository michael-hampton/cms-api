<?php

namespace App\Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;

class MenuSeeder
{

    public function run(): void
    {
        $items = [
            [
                'name' => 'Home',
                'slug' => 'home',
                'is_active' => true,
                'site_id' => 1
            ],
            [
                'name' => 'About',
                'slug' => 'about',
                'is_active' => true,
                'site_id' => 1
            ],
            [
                'name' => 'Blog',
                'slug' => 'first-time-buyers-guide-london',
                'is_active' => true,
                'site_id' => 1
            ],
            [
                'name' => 'Events',
                'slug' => 'property-investment-seminar',
                'is_active' => true,
                'site_id' => 1
            ],
            [
                'name' => 'Properties',
                'slug' => 'properties',
                'is_active' => true,
                'site_id' => 1
            ],
            [
                'name' => 'Shop',
                'slug' => 'shop',
                'is_active' => true,
                'site_id' => 1
            ],
            [
                'name' => 'Contact Us',
                'slug' => 'contact',
                'is_active' => true,
                'site_id' => ''
            ],
        ];

//        $menu = Menu::create([
//            'name' => 'Main Menu',
//            'slug' => 'main-menu',
//            'is_active' => true,
//            'site_id' => 1,
//        ]);
//
//        foreach ($items as $item) {
//            MenuItem::create([
//                'menu_id' => $menu->id,
//                'label' => $item['name'],
//                'slug' => $item['slug'],
//                'is_active' => $item['is_active'],
//            ]);
//        }
    }
}