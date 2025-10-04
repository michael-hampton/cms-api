<?php

namespace App\Tests\Unit\Controllers;

use App\Controllers\MenuItemController;
use App\Framework\Database\QueryBuilder;
use App\Models\MenuItem;
use App\Requests\CreateMenuItemRequest;
use App\Requests\ReorderMenuItemsRequest;
use App\Requests\UpdateMenuItemRequest;
use App\Services\MenuService;
use Mockery;
use PHPUnit\Framework\TestCase;

class MenuItemControllerTest extends TestCase
{
    private $menuService;
    private $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->menuService = Mockery::mock(MenuService::class);
        $this->controller = new MenuItemController($this->menuService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testStoreCreatesMenuItem()
    {
        $request = Mockery::mock(CreateMenuItemRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'menu_id' => 1,
            'title' => 'New Item',
            'url' => '/new-item'
        ]);

        $menuItem = Mockery::mock(MenuItem::class);
        $menuItem->shouldReceive('with')->with(['children'])->andReturn($this->createMock(QueryBuilder::class));
        $menuItem->shouldReceive('get')->andReturn([]);

        $this->menuService->shouldReceive('createMenuItem')
            ->once()
            ->andReturn($menuItem);

        $response = $this->controller->store($request);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testUpdateUpdatesMenuItem()
    {
        $request = Mockery::mock(UpdateMenuItemRequest::class);
        $request->shouldReceive('validated')->andReturn(['title' => 'Updated Item']);

        $menuItem = Mockery::mock(MenuItem::class);
        $menuItem->shouldReceive('with')->with(['children'])->andReturn($this->createMock(QueryBuilder::class));
        $menuItem->shouldReceive('get')->andReturn([]);

        $this->menuService->shouldReceive('updateMenuItem')
            ->with(1, Mockery::any())
            ->once()
            ->andReturn($menuItem);

        $response = $this->controller->update($request, 1);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDestroyDeletesMenuItem()
    {
        $this->menuService->shouldReceive('deleteMenuItem')
            ->with(1)
            ->once();

        $response = $this->controller->destroy(1);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testReorderReordersMenuItems()
    {
        $request = Mockery::mock(ReorderMenuItemsRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'items' => [
                ['id' => 1, 'order' => 1],
                ['id' => 2, 'order' => 2]
            ]
        ]);

        $this->menuService->shouldReceive('reorderMenuItems')
            ->once()
            ->andReturn(true);

        $response = $this->controller->reorder($request);

        $this->assertEquals(200, $response->getStatusCode());
    }
}