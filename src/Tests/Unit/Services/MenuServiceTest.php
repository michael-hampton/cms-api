<?php

namespace App\Tests\Unit\Services;

use App\Framework\Support\Str;
use App\Models\Menu;
use App\Repositories\MenuRepository;
use App\Services\MenuService;
use Mockery;
use PHPUnit\Framework\TestCase;

class MenuServiceTest extends TestCase
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
}