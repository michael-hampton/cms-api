<?php

namespace App\Tests\Unit\Models;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class MenuItemModelTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    public function testCreateMenuItem()
    {
        $menu = Menu::create([
            'name' => 'Main Menu',
            'slug' => 'main-menu',
            'is_active' => true,
        ]);

        $item = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Home',
            'target_type' => 'custom',
            'custom_url' => '/',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(MenuItem::class, $item);
        $this->assertEquals('Home', $item->label);
    }

    public function testGetUrlAttributeCustom()
    {
        $menu = Menu::create(['name' => 'Menu', 'slug' => 'menu', 'is_active' => true]);

        $item = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Home',
            'target_type' => 'custom',
            'custom_url' => '/home',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->assertEquals('/home', $item->getUrlAttribute());
    }

    public function testGetDepthAttribute()
    {
        $menu = Menu::create(['name' => 'Menu', 'slug' => 'menu', 'is_active' => true]);

        $parent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Parent',
            'target_type' => 'custom',
            'custom_url' => '/parent',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $child = MenuItem::create([
            'menu_id' => $menu->id,
            'parent_id' => $parent->id,
            'label' => 'Child',
            'target_type' => 'custom',
            'custom_url' => '/child',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->assertEquals(0, $parent->getDepthAttribute());
        $this->assertEquals(1, $child->getDepthAttribute());
    }
}