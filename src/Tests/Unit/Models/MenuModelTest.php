<?php

namespace App\Tests\Unit\Models;

use App\Models\Menu;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class MenuModelTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    public function testCreateMenu()
    {
        $menu = Menu::create([
            'name' => 'Main Menu',
            'slug' => 'main-menu',
            'description' => 'Primary navigation',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Menu::class, $menu);
        $this->assertEquals('Main Menu', $menu->name);
    }

    public function testLayoutConfigCast()
    {
        $config = ['theme' => 'dark', 'alignment' => 'center'];
        $menu = Menu::create([
            'name' => 'Menu',
            'slug' => 'menu',
            'layout_config' => json_encode($config),
            'is_active' => true,
        ]);

        $this->assertIsArray($menu->layout_config);
        $this->assertEquals($config, $menu->layout_config);
    }

    public function testIsActiveCast()
    {
        $menu = Menu::create([
            'name' => 'Menu',
            'slug' => 'menu',
            'is_active' => 1,
        ]);

        $this->assertIsBool($menu->is_active);
        $this->assertTrue($menu->is_active);
    }
}

