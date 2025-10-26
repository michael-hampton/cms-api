<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Menu;
use App\Models\MenuTerritory;
use App\Models\Territory;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MenuControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsAllMenus()
    {
        Menu::create(['name' => 'Main Menu', 'slug' => 'main-menu', 'is_active' => true]);
        Menu::create(['name' => 'Footer Menu', 'slug' => 'footer-menu', 'is_active' => true]);
        $response = $this->getForSite('/api/menu');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
    }

    public function testShowReturnsMenuById()
    {
        $menu = Menu::create(['name' => 'Main Menu', 'slug' => 'main-menu', 'is_active' => true]);
        $response = $this->getForSite("/api/menu/{$menu->id}");
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Main Menu', $data['data']['menu']['name']);
    }

    public function testShowReturns404ForNonexistent()
    {
        $response = $this->getForSite('/api/menu/999');
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
    }

    public function testGetMenuBySlug()
    {
        Menu::create(['name' => 'Main Menu', 'slug' => 'main-menu', 'is_active' => true]);
        $response = $this->getForSite('/api/menu/slug/main-menu');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Main Menu', $data['data']['menu']['name']);
    }

    public function testStoreCreatesNewMenu()
    {
        $menuData = ['name' => 'New Menu', 'description' => 'A test menu', 'layout_config' => ['type' => 'horizontal', 'show_icons' => true]];
        $response = $this->postForSite('/api/menu', $menuData);
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('New Menu', $data['data']['menu']['name']);
        $this->assertEquals('new-menu', $data['data']['menu']['slug']);
    }

    public function testStoreAutoGeneratesSlug()
    {
        $response = $this->postForSite('/api/menu', ['name' => 'My Custom Menu']);
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('my-custom-menu', $data['data']['menu']['slug']);
    }

    public function testStoreValidatesUniqueSlug()
    {
        Menu::create(['name' => 'Existing', 'slug' => 'existing', 'is_active' => true]);
        $response = $this->postForSite('/api/menu', ['name' => 'New', 'slug' => 'existing']);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateModifiesMenu()
    {
        $menu = Menu::create(['name' => 'Original', 'slug' => 'original', 'is_active' => true]);
        $response = $this->putForSite("/api/menu/{$menu->id}", ['name' => 'Updated Menu', 'description' => 'Updated']);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated Menu', $data['data']['menu']['name']);
    }

    public function testDestroyDeletesMenu()
    {
        $menu = Menu::create(['name' => 'To Delete', 'slug' => 'to-delete', 'is_active' => true]);
        $response = $this->deleteForSite("/api/menu/{$menu->id}");
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testHierarchyReturnsMenuStructure()
    {
        $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'is_active' => true]);
        $response = $this->getForSite("/api/menu/{$menu->id}/hierarchy");
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    public function testStoreCreatesFooterMenuWithConfiguration()
    {
        $menuData = [
            'name' => 'Footer Menu',
            'menu_type' => 'footer',
            'layout_config' => [
                'footer_style' => 'modern',
                'show_brand_section' => true,
                'logo_type' => 'icon',
                'logo_icon' => '🏠',
                'brand_name' => 'Test Company',
                'footer_description' => 'Test description',
                'social_links' => [
                    'facebook' => 'https://facebook.com/test',
                    'twitter' => 'https://twitter.com/test'
                ],
                'show_newsletter' => true,
                'newsletter_title' => 'Newsletter',
                'newsletter_description' => 'Get updates'
            ]
        ];

        $response = $this->postForSite('/api/menu', $menuData);
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Footer Menu', $data['data']['menu']['name']);
        $this->assertEquals('footer', $data['data']['menu']['menu_type']);
        $this->assertArrayHasKey('layout_config', $data['data']['menu']);

        $config = json_decode($data['data']['menu']['layout_config'], true);
        $this->assertEquals('modern', $config['footer_style']);
        $this->assertTrue($config['show_brand_section']);
        $this->assertEquals('🏠', $config['logo_icon']);
    }

    public function testUpdateMenuTypeFromHeaderToFooter()
    {
        $data = [
            'name' => 'Test Menu',
            'slug' => 'test-menu',
            'menu_type' => 'header',
            'is_active' => true,
            'site_id' => $this->siteId
        ];

        $menu = Menu::create($data);

        $response = $this->putForSite("/api/menu/{$menu->id}", array_merge($data, ['menu_type' => 'footer']));;

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('footer', $data['data']['menu']['menu_type']);
    }

    public function testUpdateFooterLayoutConfiguration()
    {
        $data = [
            'name' => 'Footer Menu',
            'slug' => 'footer-menu',
            'menu_type' => 'footer',
            'layout_config' => [
                'footer_style' => 'default',
                'show_newsletter' => false
            ],
            'is_active' => true,
            'site_id' => $this->siteId
        ];

        $menu = Menu::create($data);

        $newConfig = [
            'footer_style' => 'modern',
            'show_brand_section' => true,
            'logo_type' => 'text',
            'logo_main' => 'BRAND',
            'social_links' => [
                'facebook' => 'https://facebook.com/updated',
                'twitter' => 'https://twitter.com/updated'
            ],
            'show_newsletter' => true,
            'newsletter_title' => 'Subscribe Now'
        ];

        $response = $this->putForSite("/api/menu/{$menu->id}", array_merge($data, ['layout_config' => $newConfig]));;;

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $config = json_decode($data['data']['menu']['layout_config'], true);
        $this->assertEquals('modern', $config['footer_style']);
        $this->assertTrue($config['show_brand_section']);
        $this->assertTrue($config['show_newsletter']);
        $this->assertEquals('Subscribe Now', $config['newsletter_title']);
    }

    public function testGetMenusByType()
    {
        Menu::create([
            'name' => 'Header 1',
            'slug' => 'header-1',
            'menu_type' => 'header',
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        Menu::create([
            'name' => 'Footer 1',
            'slug' => 'footer-1',
            'menu_type' => 'footer',
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        Menu::create([
            'name' => 'Footer 2',
            'slug' => 'footer-2',
            'menu_type' => 'footer',
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/menu?type=footer');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);

        foreach ($data['data']['data'] as $menu) {
            $this->assertEquals('footer', $menu['menu_type']);
        }
    }

    public function testStoreCreatesMenuWithTerritories()
    {
        $territory1 = $this->createTerritory();
        $territory2 = $this->createTerritory();

        $menuData = [
            'name' => 'Multi-Territory Menu',
            'territory_ids' => [$territory1->id, $territory2->id]
        ];

        $response = $this->postForSite('/api/menu', $menuData);
        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $menu = Menu::find($data['data']['menu']['id']);
        $territoryIds = MenuTerritory::where('menu_id', $menu->id)
            ->get()
            ->pluck('territory_id');;

        $this->assertCount(2, $territoryIds);
        $this->assertContains($territory1->id, $territoryIds);
        $this->assertContains($territory2->id, $territoryIds);
    }

    public function testUpdateMenuTerritories()
    {
        $territory1 = $this->createTerritory();
        $territory2 = $this->createTerritory();

        $data = [
            'name' => 'Test Menu',
            'slug' => 'test-menu',
            'is_active' => true,
            'site_id' => $this->siteId
        ];

        $menu = Menu::create($data);

        $response = $this->putForSite("/api/menu/{$menu->id}",
            array_merge($data, ['territory_ids' => [$territory1->id, $territory2->id]])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $territoryIds = MenuTerritory::where('menu_id', $menu->id)
            ->get()
            ->pluck('territory_id');

        $this->assertCount(2, $territoryIds);
    }
}