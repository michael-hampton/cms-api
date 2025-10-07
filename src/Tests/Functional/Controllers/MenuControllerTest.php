<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Menu;

class MenuControllerTest extends FunctionalTestCase
{
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
}