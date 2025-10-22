<?php

namespace App\Tests\Unit\Services;

use App\Framework\Support\Str;
use App\Models\Menu;
use App\Models\Territory;
use App\Repositories\MenuRepository;
use App\Services\MenuService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\TestCase;

class MenuServiceTest extends FunctionalTestCase
{
    private $menuRepository;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->menuRepository = Mockery::mock(MenuRepository::class);
        $this->service = new MenuService($this->menuRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetMenuBySlugReturnsMenu()
    {
        $menu = Mockery::mock(Menu::class);

        $this->menuRepository->shouldReceive('findBySlug')
            ->with('main-menu')
            ->once()
            ->andReturn($menu);

        $result = $this->service->getMenuBySlug('main-menu');

        $this->assertNotNull($result);
    }

    public function testCreateMenuGeneratesSlugWhenNotProvided()
    {
        $data = ['name' => 'Main Menu', 'layout_config' => ['test']];

        // Mock Str::slug() call
        $str = Mockery::mock(Str::class);
        $str->shouldReceive('slug')
            ->with('Main Menu')
            ->andReturn('main-menu');


        // Mock repository uniqueness check
        $this->menuRepository->shouldReceive('findBySlug')
            ->with('main-menu')
            ->once()
            ->andReturn(null); // no duplicate found

        // Mock repository create call
        $menu = Mockery::mock(Menu::class);
        $this->menuRepository->shouldReceive('createMenu')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['slug'] === 'main-menu'
                    && $arg['name'] === 'Main Menu'
                    && $arg['layout_config'] === '["test"]';
            }))
            ->andReturn($menu);

        $result = $this->service->createMenu($data);

        $this->assertSame($menu, $result);
    }

    public function testCreateMenuEnsuresUniqueSlug()
    {
        $data = ['name' => 'Main Menu', 'slug' => 'main-menu', 'layout_config' => ['test']];

        $str = Mockery::mock(Str::class);
        $str->shouldReceive('slug')
            ->with('Main Menu')
            ->andReturn('main-menu');

        // First call: slug already exists
        $this->menuRepository->shouldReceive('findBySlug')
            ->with('main-menu')
            ->once()
            ->andReturn(Mockery::mock(Menu::class));

        // Second call: unique slug
        $this->menuRepository->shouldReceive('findBySlug')
            ->with('main-menu-1')
            ->once()
            ->andReturn(null);

        $menu = Mockery::mock(Menu::class);

        $this->menuRepository->shouldReceive('createMenu')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['slug'] === 'main-menu-1'
                    && $arg['name'] === 'Main Menu'
                    && $arg['layout_config'] === '["test"]';
            }))
            ->andReturn($menu);

        $result = $this->service->createMenu($data);

        $this->assertSame($menu, $result);
    }

    public function testUpdateMenuUpdatesSlugWhenChanged()
    {
        // Mock the Menu model
        $menu = Mockery::mock(Menu::class)->makePartial();
        $menu->slug = 'old-slug';
        $menu->id = 1;

        // Menu::findOrFail() should return our mocked menu
        $this->menuRepository->shouldReceive('getMenuById') // or your repo method
        ->with(1)
            ->andReturn($menu);

        $str = Mockery::mock(Str::class);
        $str->shouldReceive('slug')
            ->with('Main Menu')
            ->andReturn('main-menu');

        $this->menuRepository->shouldReceive('findBySlug')
            ->with('new-slug')
            ->once()
            ->andReturn(null);

        // Mock repository updateMenu call
        $this->menuRepository->shouldReceive('updateMenu')
            ->once()
            ->with(
                $menu,
                ['slug' => 'new-slug', 'layout_config' => null],
            )
            ->andReturn($menu);

        // Call the service
        $result = $this->service->updateMenu(1, ['slug' => 'new-slug']);

        $this->assertSame($menu, $result);
    }

    public function testDeleteMenuCallsRepository()
    {
        $menu = Mockery::mock(Menu::class);

        $this->menuRepository->shouldReceive('getMenuById') // or your repo method
        ->with(1)
            ->andReturn($menu);

        $this->menuRepository->shouldReceive('deleteMenu')
            ->with($menu)
            ->once()
            ->andReturn(true);

        $result = $this->service->deleteMenu(1);

        $this->assertTrue($result);
    }

    public function testReorderMenuItemsCallsRepository()
    {
        $items = [
            ['id' => 1, 'order' => 1],
            ['id' => 2, 'order' => 2]
        ];

        $this->menuRepository->shouldReceive('reorderMenuItems')
            ->with($items)
            ->once()
            ->andReturn(true);

        $result = $this->service->reorderMenuItems($items);

        $this->assertTrue($result);
    }

    public function testGetMenuHierarchyReturnsStructure()
    {
        $hierarchy = collect([]);

        $this->menuRepository->shouldReceive('getMenuHierarchy')
            ->with(1)
            ->once()
            ->andReturn($hierarchy);

        $result = $this->service->getMenuHierarchy(1);

        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $result);
    }

    public function testCreateFooterMenuWithLayoutConfig()
    {
        $data = [
            'name' => 'Footer Menu',
            'menu_type' => 'footer',
            'layout_config' => [
                'footer_style' => 'modern',
                'show_brand_section' => true,
                'logo_type' => 'icon',
                'logo_icon' => '🏠',
                'social_links' => [
                    'facebook' => 'https://facebook.com/test',
                    'twitter' => 'https://twitter.com/test'
                ],
                'show_newsletter' => true
            ]
        ];

        $this->menuRepository->shouldReceive('findBySlug')
            ->with('footer-menu')
            ->once()
            ->andReturn(null);

        $menu = Mockery::mock(Menu::class);
        $this->menuRepository->shouldReceive('createMenu')
            ->once()
            ->with(Mockery::on(function ($arg) use ($data) {
                $layoutConfig = json_decode($arg['layout_config'], true);
                return $arg['slug'] === 'footer-menu'
                    && $arg['menu_type'] === 'footer'
                    && $layoutConfig['footer_style'] === 'modern'
                    && $layoutConfig['show_newsletter'] === true;
            }))
            ->andReturn($menu);

        $result = $this->service->createMenu($data);

        $this->assertSame($menu, $result);
    }

    public function testUpdateMenuType()
    {
        $menu = Mockery::mock(Menu::class)->makePartial();
        $menu->id = 1;
        $menu->menu_type = 'header';

        $this->menuRepository->shouldReceive('getMenuById')
            ->with(1)
            ->andReturn($menu);

        $this->menuRepository->shouldReceive('updateMenu')
            ->once()
            ->with(
                $menu,
                ['menu_type' => 'footer', 'layout_config' => null]
            )
            ->andReturn($menu);

        $result = $this->service->updateMenu(1, ['menu_type' => 'footer']);

        $this->assertSame($menu, $result);
    }

    public function testUpdateFooterLayoutConfig()
    {
        $menu = Mockery::mock(Menu::class)->makePartial();
        $menu->id = 1;
        $menu->menu_type = 'footer';
        $menu->layout_config = ['footer_style' => 'default'];

        $newConfig = [
            'footer_style' => 'modern',
            'show_brand_section' => true,
            'social_links' => [
                'facebook' => 'https://facebook.com/updated'
            ]
        ];

        $this->menuRepository->shouldReceive('getMenuById')
            ->with(1)
            ->andReturn($menu);

        $this->menuRepository->shouldReceive('updateMenu')
            ->once()
            ->with(
                $menu,
                Mockery::on(function ($arg) use ($newConfig) {
                    $layoutConfig = json_decode($arg['layout_config'], true);
                    return $layoutConfig['footer_style'] === 'modern'
                        && $layoutConfig['show_brand_section'] === true;
                })
            )
            ->andReturn($menu);

        $result = $this->service->updateMenu(1, ['layout_config' => $newConfig]);

        $this->assertSame($menu, $result);
    }

    public function testCreateMenuWithTerritories()
    {
        $territory1 = Territory::create(['name' => 'Territory 1', 'code' => 'T1', 'is_active' => true, 'site_id' => $this->siteId, 'region_set_id' => null]);
        $territory2 = Territory::create(['name' => 'Territory 2', 'code' => 'T2', 'is_active' => true, 'site_id' => $this->siteId, 'region_set_id' => null]);

        $data = [
            'name' => 'Test Menu',
            'territory_ids' => [$territory1->id, $territory2->id]
        ];

        $this->menuRepository->shouldReceive('findBySlug')
            ->once()
            ->andReturn(null);

        $menu = Mockery::mock(Menu::class)->makePartial();
        $menu->id = 1;

        $this->menuRepository->shouldReceive('createMenu')
            ->once()
            ->andReturn($menu);

        $menu->shouldReceive('syncTerritories')
            ->once()
            ->with([$territory1->id, $territory2->id]);

        $result = $this->service->createMenu($data);

        $this->assertSame($menu, $result);
    }

    public function testUpdateMenuTerritories()
    {
        $menu = Mockery::mock(Menu::class)->makePartial();
        $menu->id = 1;
        $menu->slug = 'test-menu';

        $territory1 = Territory::create(['name' => 'Territory 1', 'code' => 'T1', 'is_active' => true, 'site_id' => 1]);

        $this->menuRepository->shouldReceive('getMenuById')
            ->with(1)
            ->andReturn($menu);

        $this->menuRepository->shouldReceive('updateMenu')
            ->once()
            ->andReturn($menu);

        $menu->shouldReceive('syncTerritories')
            ->once()
            ->with([$territory1->id]);

        $result = $this->service->updateMenu(1, [
            'territory_ids' => [$territory1->id]
        ]);

        $this->assertSame($menu, $result);
    }

}