<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Site;
use App\Repositories\MenuRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MenuRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private MenuRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MenuRepository();
    }

    protected function createMenu(array $overrides = []): Menu
    {
        return Menu::create(array_merge([
            'site_id' => $this->siteId,
            'name' => 'Test Menu',
            'slug' => 'test-menu-' . uniqid(),
            'menu_type' => 'header',
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    protected function createMenuItem(array $overrides = []): MenuItem
    {
        if (!isset($overrides['menu_id'])) {
            $menu = $this->createMenu();
            $overrides['menu_id'] = $menu->id;
        }

        return MenuItem::create(array_merge([
            'label' => 'Test Menu Item',
            'url' => '/test',
            'target' => '_self',
            'sort_order' => 0,
            'parent_id' => null,
            'column_group' => 0,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    public function test_create_menu_saves_new_menu(): void
    {
        // Arrange
        $data = [
            'site_id' => $this->siteId,
            'name' => 'New Menu',
            'slug' => 'new-menu',
            'menu_type' => 'footer',
            'is_active' => true,
        ];

        // Act
        $menu = $this->repository->createMenu($data);

        // Assert
        $this->assertNotNull($menu);
        $this->assertEquals('New Menu', $menu->name);
        $this->assertEquals('new-menu', $menu->slug);

        $this->assertDatabaseHas('menus', [
            'name' => 'New Menu',
            'slug' => 'new-menu',
        ]);
    }

    public function test_update_menu_modifies_menu(): void
    {
        // Arrange
        $menu = $this->createMenu(['name' => 'Original Name']);

        // Act
        $updated = $this->repository->updateMenu($menu, ['name' => 'Updated Name']);

        // Assert
        $this->assertEquals('Updated Name', $updated->name);

        $fresh = $this->fresh($menu);
        $this->assertEquals('Updated Name', $fresh->name);
    }

    public function test_get_all_menus_returns_active_menus_with_relations(): void
    {
        // Arrange
        $activeSite = Site::find($this->siteId);
        $activeMenu = $this->createMenu(['name' => 'Active Menu', 'is_active' => true]);
        $inactiveMenu = $this->createMenu(['name' => 'Inactive Menu', 'is_active' => false]);

        $menuItem = $this->createMenuItem(['menu_id' => $activeMenu->id]);

        // Act
        $menus = $this->repository->getAllMenus($activeSite->slug);

        // Assert
        $this->assertGreaterThanOrEqual(1, $menus->count());

        $foundActive = false;
        $foundInactive = false;

        foreach ($menus as $menu) {
            $this->assertEquals(1, $menu->is_active);
            if ($menu->id === $activeMenu->id) {
                $foundActive = true;
                $this->assertRelationLoaded($menu, 'items');
                $this->assertRelationLoaded($menu, 'territories');
            }
            if ($menu->id === $inactiveMenu->id) {
                $foundInactive = true;
            }
        }

        $this->assertTrue($foundActive);
        $this->assertFalse($foundInactive);
    }

    public function test_create_menu_item_saves_new_item(): void
    {
        // Arrange
        $menu = $this->createMenu();
        $data = [
            'menu_id' => $menu->id,
            'label' => 'New Item',
            'custom_url' => '/new-item',
            'target' => '_self',
            'parent_id' => null,
            'column_group' => 0,
        ];

        // Act
        $menuItem = $this->repository->createMenuItem($data);

        // Assert
        $this->assertNotNull($menuItem);
        $this->assertEquals('New Item', $menuItem->label);
        $this->assertEquals('/new-item', $menuItem->custom_url);

        $this->assertDatabaseHas('menu_items', [
            'menu_id' => $menu->id,
            'label' => 'New Item',
        ]);
    }

    public function test_create_menu_item_auto_assigns_sort_order(): void
    {
        // Arrange
        $menu = $this->createMenu();

        // Create first item
        $item1 = $this->createMenuItem([
            'menu_id' => $menu->id,
            'sort_order' => 1,
        ]);

        // Create second item
        $item2 = $this->createMenuItem([
            'menu_id' => $menu->id,
            'sort_order' => 2,
        ]);

        // Act - create third item without sort_order
        $data = [
            'menu_id' => $menu->id,
            'label' => 'Auto Sort Item',
            'url' => '/auto-sort',
            'parent_id' => null,
            'column_group' => 0,
        ];

        $item3 = $this->repository->createMenuItem($data);

        // Assert
        $this->assertEquals(3, $item3->sort_order);
    }

    public function test_create_menu_item_respects_parent_id_for_sort_order(): void
    {
        // Arrange
        $menu = $this->createMenu();
        $parent = $this->createMenuItem([
            'menu_id' => $menu->id,
            'sort_order' => 1,
        ]);

        $child1 = $this->createMenuItem([
            'menu_id' => $menu->id,
            'parent_id' => $parent->id,
            'sort_order' => 1,
        ]);

        // Act - create second child without sort_order
        $data = [
            'menu_id' => $menu->id,
            'label' => 'Second Child',
            'url' => '/second-child',
            'parent_id' => $parent->id,
            'column_group' => 0,
        ];

        $child2 = $this->repository->createMenuItem($data);

        // Assert
        $this->assertEquals(2, $child2->sort_order);
    }

    public function test_update_menu_item_modifies_item(): void
    {
        // Arrange
        $menuItem = $this->createMenuItem(['label' => 'Original Title']);

        // Act
        $updated = $this->repository->updateMenuItem($menuItem, ['label' => 'Updated Title']);

        // Assert
        $this->assertEquals('Updated Title', $updated->label);

        $fresh = $this->fresh($menuItem);
        $this->assertEquals('Updated Title', $fresh->label);
    }

    public function test_delete_menu_item_removes_item(): void
    {
        // Arrange
        $menuItem = $this->createMenuItem();

        // Act
        $result = $this->repository->deleteMenuItem($menuItem);

        // Assert
        $this->assertTrue($result);
        $this->assertNull(MenuItem::find($menuItem->id));
    }

    public function test_reorder_menu_items_updates_sort_order_and_parent(): void
    {
        // Arrange
        $menu = $this->createMenu();
        $item1 = $this->createMenuItem(['menu_id' => $menu->id, 'sort_order' => 1]);
        $item2 = $this->createMenuItem(['menu_id' => $menu->id, 'sort_order' => 2]);
        $item3 = $this->createMenuItem(['menu_id' => $menu->id, 'sort_order' => 3]);

        // Act - reverse the order and make item3 a child of item1
        $result = $this->repository->reorderMenuItems([
            ['id' => $item3->id, 'sort_order' => 1, 'parent_id' => null],
            ['id' => $item2->id, 'sort_order' => 2, 'parent_id' => null],
            ['id' => $item1->id, 'sort_order' => 1, 'parent_id' => $item3->id],
        ]);

        // Assert
        $this->assertTrue($result);

        $fresh1 = $this->fresh($item1);
        $fresh2 = $this->fresh($item2);
        $fresh3 = $this->fresh($item3);

        $this->assertEquals(1, $fresh1->sort_order);
        $this->assertEquals($item3->id, $fresh1->parent_id);
        $this->assertEquals(2, $fresh2->sort_order);
        $this->assertNull($fresh2->parent_id);
        $this->assertEquals(1, $fresh3->sort_order);
        $this->assertNull($fresh3->parent_id);
    }

    public function test_delete_menu_removes_menu(): void
    {
        // Arrange
        $menu = $this->createMenu();

        // Act
        $result = $this->repository->deleteMenu($menu);

        // Assert
        $this->assertTrue($result);
        $this->assertNull(Menu::find($menu->id));
    }

    public function test_get_menu_by_id_loads_relationships(): void
    {
        // Arrange
        $menu = $this->createMenu();
        $item = $this->createMenuItem(['menu_id' => $menu->id]);
        $child = $this->createMenuItem([
            'menu_id' => $menu->id,
            'parent_id' => $item->id,
            'is_active' => true,
        ]);

        // Act
        $found = $this->repository->getMenuById($menu->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($menu->id, $found->id);
        $this->assertRelationLoaded($found, 'items');
        $this->assertRelationLoaded($found, 'territories');
    }

    public function test_get_menu_by_id_returns_null_when_not_found(): void
    {
        // Act
        $found = $this->repository->getMenuById(99999);

        // Assert
        $this->assertNull($found);
    }

    public function test_find_by_slug_returns_menu(): void
    {
        // Arrange
        $menu = $this->createMenu(['slug' => 'unique-slug']);

        // Act
        $found = $this->repository->findBySlug('unique-slug');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($menu->id, $found->id);
        $this->assertEquals('unique-slug', $found->slug);
    }

    public function test_find_by_slug_returns_null_when_not_found(): void
    {
        // Act
        $found = $this->repository->findBySlug('non-existent-slug');

        // Assert
        $this->assertNull($found);
    }

    public function test_get_menu_item_by_id_returns_item(): void
    {
        // Arrange
        $menuItem = $this->createMenuItem(['label' => 'Find Me']);

        // Act
        $found = $this->repository->getMenuItemById($menuItem->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($menuItem->id, $found->id);
        $this->assertEquals('Find Me', $found->label);
    }

    public function test_get_menu_item_by_id_throws_exception_when_not_found(): void
    {
        $this->expectException(\Exception::class);

        $this->repository->getMenuItemById(99999);
    }

//    public function test_get_menu_hierarchy_returns_tree_structure(): void
//    {
//        // Arrange
//        $menu = $this->createMenu();
//
//        $parent1 = $this->createMenuItem([
//            'menu_id' => $menu->id,
//            'title' => 'Parent 1',
//            'sort_order' => 1,
//            'parent_id' => null,
//        ]);
//
//        $child1 = $this->createMenuItem([
//            'menu_id' => $menu->id,
//            'title' => 'Child 1',
//            'sort_order' => 1,
//            'parent_id' => $parent1->id,
//        ]);
//
//        $parent2 = $this->createMenuItem([
//            'menu_id' => $menu->id,
//            'label' => 'Parent 2',
//            'sort_order' => 2,
//            'parent_id' => null,
//        ]);
//
//        // Act
//        $hierarchy = $this->repository->getMenuHierarchy($menu->id);
//
//        // Assert
//        $this->assertCount(2, $hierarchy);
//
//        $hierarchyArray = $hierarchy->toArray();
//        $this->assertEquals('Parent 1', $hierarchyArray[0]['label']);
//        $this->assertEquals('Parent 2', $hierarchyArray[1]['label']);
//    }

//    public function test_get_menu_hierarchy_orders_by_sort_order(): void
//    {
//        // Arrange
//        $menu = $this->createMenu();
//
//        $item2 = $this->createMenuItem([
//            'menu_id' => $menu->id,
//            'title' => 'Second',
//            'sort_order' => 2,
//            'parent_id' => null,
//        ]);
//
//        $item1 = $this->createMenuItem([
//            'menu_id' => $menu->id,
//            'title' => 'First',
//            'sort_order' => 1,
//            'parent_id' => null,
//        ]);
//
//        // Act
//        $hierarchy = $this->repository->getMenuHierarchy($menu->id);
//
//        // Assert
//        $hierarchyArray = $hierarchy->toArray();
//        $this->assertEquals('First', $hierarchyArray[0]['title']);
//        $this->assertEquals('Second', $hierarchyArray[1]['title']);
//    }

    public function test_get_menus_by_type_returns_menus_of_type(): void
    {
        // Arrange
        $site = Site::find($this->siteId);
        $headerMenu1 = $this->createMenu(['menu_type' => 'header', 'is_active' => true]);
        $headerMenu2 = $this->createMenu(['menu_type' => 'header', 'is_active' => true]);
        $footerMenu = $this->createMenu(['menu_type' => 'footer', 'is_active' => true]);
        $inactiveHeader = $this->createMenu(['menu_type' => 'header', 'is_active' => false]);

        // Act
        $menus = $this->repository->getMenusByType('header', $site->slug);

        // Assert
        $this->assertGreaterThanOrEqual(2, $menus->count());

        foreach ($menus as $menu) {
            $this->assertEquals('header', $menu->menu_type);
            $this->assertEquals(1, $menu->is_active);
        }
    }

    public function test_get_menus_by_type_loads_items_relation(): void
    {
        // Arrange
        $site = Site::find($this->siteId);
        $menu = $this->createMenu(['menu_type' => 'header', 'is_active' => true]);
        $item = $this->createMenuItem(['menu_id' => $menu->id]);

        // Act
        $menus = $this->repository->getMenusByType('header', $site->slug);

        // Assert
        $found = null;
        foreach ($menus as $m) {
            if ($m->id === $menu->id) {
                $found = $m;
                break;
            }
        }

        $this->assertNotNull($found);
        $this->assertRelationLoaded($found, 'items');
    }

    public function test_get_menus_by_type_filters_by_site(): void
    {
        // Arrange
        $site = Site::find($this->siteId);
        $otherSite = $this->createSite();

        $siteMenu = $this->createMenu([
            'site_id' => $this->siteId,
            'menu_type' => 'header',
            'is_active' => true,
        ]);

        $otherSiteMenu = Menu::create([
            'site_id' => $otherSite->id,
            'name' => 'Other Site Menu',
            'slug' => 'other-site-menu',
            'menu_type' => 'header',
            'is_active' => true,
        ]);

        // Act
        $menus = $this->repository->getMenusByType('header', $site->slug);

        // Assert
        $foundOtherSiteMenu = false;
        foreach ($menus as $menu) {
            if ($menu->id === $otherSiteMenu->id) {
                $foundOtherSiteMenu = true;
                break;
            }
        }

        $this->assertFalse($foundOtherSiteMenu);
    }
}