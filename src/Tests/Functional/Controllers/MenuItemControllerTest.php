<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Menu;
use App\Models\MenuItem;

class MenuItemControllerTest extends FunctionalTestCase
{
    private $footerMenu;
    private $headerMenu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->headerMenu = Menu::create([
            'name' => 'Header Menu',
            'slug' => 'header-menu',
            'menu_type' => 'header',
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        $this->footerMenu = Menu::create([
            'name' => 'Footer Menu',
            'slug' => 'footer-menu',
            'menu_type' => 'footer',
            'layout_config' => [
                'footer_style' => 'modern',
                'show_newsletter' => true
            ],
            'is_active' => true,
            'site_id' => $this->siteId
        ]);
    }

    public function testCreateFooterMenuItemWithColumnGroup()
    {
        $itemData = [
            'menu_id' => $this->footerMenu->id,
            'label' => 'Products',
            'target_type' => 'custom',
            'custom_url' => '#',
            'column_group' => 1,
            'sort_order' => 1,
            'is_active' => true
        ];

        $response = $this->post('/api/menu-items', $itemData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['data']['column_group']);
        $this->assertEquals('Products', $data['data']['label']);
    }

    public function testCreateMultipleItemsInSameColumn()
    {
        // Column header
        $header = MenuItem::create([
            'menu_id' => $this->footerMenu->id,
            'label' => 'Company',
            'target_type' => 'custom',
            'custom_url' => '#',
            'column_group' => 2,
            'sort_order' => 1,
            'is_active' => true
        ]);

        // Column items
        $item1 = MenuItem::create([
            'menu_id' => $this->footerMenu->id,
            'label' => 'About Us',
            'target_type' => 'custom',
            'custom_url' => '/about',
            'column_group' => 2,
            'sort_order' => 2,
            'is_active' => true
        ]);

        $item2 = MenuItem::create([
            'menu_id' => $this->footerMenu->id,
            'label' => 'Contact',
            'target_type' => 'custom',
            'custom_url' => '/contact',
            'column_group' => 2,
            'sort_order' => 3,
            'is_active' => true
        ]);

        // Fetch menu with items
        $response = $this->getForSite("/api/menu/{$this->footerMenu->id}");
        $data = json_decode($response->getContent(), true);

        $items = $data['data']['menu']['items'];

        // Filter items in column 2
        $column2Items = array_filter($items, fn($item) => $item['column_group'] == 2);

        $this->assertCount(3, $column2Items);
    }

    public function testUpdateMenuItemColumnGroup()
    {
        $data = [
            'menu_id' => $this->footerMenu->id,
            'label' => 'Test Item',
            'target_type' => 'custom',
            'custom_url' => '/test',
            'column_group' => 1,
            'is_active' => true
        ];

        $item = MenuItem::create($data);

        $response = $this->put("/api/menu-items/{$item->id}", array_merge($data, ['column_group' => 3]));;

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(3, $data['data']['column_group']);
    }

    public function testHeaderMenuItemsDefaultToColumnGroupZero()
    {
        $itemData = [
            'menu_id' => $this->headerMenu->id,
            'label' => 'Home',
            'target_type' => 'custom',
            'custom_url' => '/',
            'is_active' => true
        ];

        $response = $this->post('/api/menu-items', $itemData);
        $data = json_decode($response->getContent(), true);

        // Header items should default to column_group 0
        $this->assertEquals(0, $data['data']['column_group']);
    }

    public function testReorderItemsWithinColumn()
    {
        // Create items in same column
        $item1 = MenuItem::create([
            'menu_id' => $this->footerMenu->id,
            'label' => 'Item 1',
            'target_type' => 'custom',
            'custom_url' => '/1',
            'column_group' => 1,
            'sort_order' => 1,
            'is_active' => true
        ]);

        $item2 = MenuItem::create([
            'menu_id' => $this->footerMenu->id,
            'label' => 'Item 2',
            'target_type' => 'custom',
            'custom_url' => '/2',
            'column_group' => 1,
            'sort_order' => 2,
            'is_active' => true
        ]);

        // Reorder
        $reorderData = [
            'items' => [
                ['id' => $item2->id, 'sort_order' => 1, 'column_group' => 1],
                ['id' => $item1->id, 'sort_order' => 2, 'column_group' => 1]
            ]
        ];

        $response = $this->post('/api/menu-items/reorder', $reorderData);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify order
        $item1Fresh = MenuItem::find($item1->id);
        $item2Fresh = MenuItem::find($item2->id);

        $this->assertEquals(2, $item1Fresh->sort_order);
        $this->assertEquals(1, $item2Fresh->sort_order);
    }

    public function testMoveItemToDifferentColumn()
    {
        $data = [
            'menu_id' => $this->footerMenu->id,
            'label' => 'Test Item',
            'target_type' => 'custom',
            'custom_url' => '/test',
            'column_group' => 1,
            'sort_order' => 1,
            'is_active' => true
        ];

        $item = MenuItem::create($data);

        $response = $this->put("/api/menu-items/{$item->id}", array_merge($data, [
            'column_group' => 2,
            'sort_order' => 1
        ]));

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(2, $data['data']['column_group']);
    }

    public function testDeleteMenuItemFromFooterColumn()
    {
        $item = MenuItem::create([
            'menu_id' => $this->footerMenu->id,
            'label' => 'To Delete',
            'target_type' => 'custom',
            'custom_url' => '/delete',
            'column_group' => 1,
            'is_active' => true
        ]);

        $response = $this->delete("/api/menu-items/{$item->id}");

        $this->assertEquals(200, $response->getStatusCode());

        $deleted = MenuItem::find($item->id);
        $this->assertNull($deleted);
    }
}