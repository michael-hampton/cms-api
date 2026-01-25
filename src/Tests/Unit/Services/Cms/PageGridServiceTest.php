<?php

namespace App\Tests\Unit\Services\Cms;

use App\Framework\Authorization\AuthenticationService;
use App\Framework\Support\Collection;
use App\Models\PageGrid;
use App\Models\Territory;
use App\Models\User;
use App\Repositories\Cms\PageGridRepository;
use App\Services\Cms\PageGridService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class PageGridServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    protected PageGridService $service;
    protected $repositoryMock;
    private $authenticationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticationService = Mockery::mock(AuthenticationService::class);
        $this->repositoryMock = Mockery::mock(PageGridRepository::class);
        $this->service = new PageGridService($this->authenticationService, $this->repositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetAllPageGrids()
    {
        $expectedGrids = collect([
            new PageGrid(['id' => 1, 'title' => 'Grid 1']),
            new PageGrid(['id' => 2, 'title' => 'Grid 2']),
        ]);

        $this->repositoryMock
            ->shouldReceive('all')
            ->once()
            ->andReturn($expectedGrids);

        $result = $this->service->getAllPageGrids();

        $this->assertCount(2, $result);
        $this->assertEquals('Grid 1', $result->first()->title);
    }

    public function testGetPageGridById()
    {
        $expectedGrid = new PageGrid(['id' => 1, 'title' => 'Test Grid']);

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1, ['territories', 'pages', 'items'])
            ->once()
            ->andReturn($expectedGrid);

        $result = $this->service->getPageGrid(1);

        $this->assertInstanceOf(PageGrid::class, $result);
        $this->assertEquals('Test Grid', $result->title);
    }

    public function testGetPageGridBySlug()
    {
        $expectedGrid = new PageGrid(['id' => 1, 'slug' => 'test-grid']);

        $this->repositoryMock
            ->shouldReceive('findBySlug')
            ->with('test-grid')
            ->once()
            ->andReturn($expectedGrid);

        $result = $this->service->getPageGridBySlug('test-grid');

        $this->assertInstanceOf(PageGrid::class, $result);
        $this->assertEquals('test-grid', $result->slug);
    }

    public function testCreatePageGridGeneratesSlug()
    {
        $data = [
            'title' => 'Test Grid',
            'layout' => 'grid',
            'columns' => 3,
        ];

        $this->repositoryMock->expects('slugExists')->with('test-grid', null)->once()->andReturn(false);

        $this->authenticationService->shouldReceive('check')->andReturn(true);

        $expectedGrid = new PageGrid(array_merge($data, ['slug' => 'test-grid', 'id' => 1]));

        $this->authenticationService->shouldReceive('getUserId')->andReturn(1);

        $this->repositoryMock->shouldReceive('logHistory')
            ->once()
            ->with(1, 'created', 1, Mockery::type('array'))
            ->andReturn(true);

        $this->repositoryMock
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return isset($arg['slug']) && $arg['slug'] === 'test-grid';
            }))
            ->andReturn($expectedGrid);

        $result = $this->service->createPageGrid($data);

        $this->assertEquals('test-grid', $result->slug);
    }

    public function testCreatePageGridWithAuthenticatedUser()
    {
        $user = $this->createUser();
        $this->authenticationService->shouldReceive('check')->andReturn(true);
        $this->authenticationService->shouldReceive('getUserId')->andReturn($user->id);;

        $data = [
            'title' => 'Test Grid',
            'slug' => 'test-grid',
            'layout' => 'grid',
            'columns' => 3,
        ];

        $this->repositoryMock
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) use ($user) {
                return $arg['created_by'] === $user->id;
            }))
            ->andReturn(new PageGrid(array_merge($data, ['id' => 1])));;

        $this->repositoryMock->shouldReceive('logHistory')
            ->once()
            ->with(1, 'created', $user->id, Mockery::type('array'))
            ->andReturn(true);

        $result = $this->service->createPageGrid($data);

        $this->assertInstanceOf(PageGrid::class, $result);
    }

    public function testUpdatePageGrid()
    {
        $existingGrid = new PageGrid(['id' => 1, 'title' => 'Old Title']);
        $updatedData = ['title' => 'New Title'];

        $this->authenticationService->shouldReceive('check')->andReturn(false);

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->twice()
            ->andReturn($existingGrid);

        $this->repositoryMock
            ->shouldReceive('update')
            ->with(1, Mockery::type('array'))
            ->once()
            ->andReturn($existingGrid);

        // Mock refresh
        $existingGrid->title = 'New Title';

        $result = $this->service->updatePageGrid(1, $updatedData);

        $this->assertEquals('New Title', $result->title);
    }

    public function testUpdatePageGridNotFoundThrowsException()
    {
        $this->repositoryMock
            ->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Page grid not found');

        $this->service->updatePageGrid(999, ['title' => 'New Title']);
    }

    public function testDeletePageGrid()
    {
        $this->repositoryMock
            ->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);

        $result = $this->service->deletePageGrid(1);

        $this->assertTrue($result);
    }

    public function testDuplicatePageGrid()
    {
        $original = new PageGrid(['id' => 1, 'title' => 'Original']);
        $duplicate = new PageGrid(['id' => 2, 'title' => 'Original (Copy)']);

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($original);

        $this->repositoryMock
            ->shouldReceive('duplicate')
            ->with(1)
            ->once()
            ->andReturn($duplicate);

        $this->authenticationService->shouldReceive('check')->andReturn(false);

        // Expect history to be logged
        $this->repositoryMock->shouldReceive('logHistory')
            ->once()
            ->with(2, 'created', null, Mockery::on(function($arg) {
                return isset($arg['data']) && isset($arg['duplicated_from']) && $arg['duplicated_from'] === 1;
            }))
            ->andReturn(true);

        $result = $this->service->duplicatePageGrid(1);

        $this->assertInstanceOf(PageGrid::class, $result);
        $this->assertEquals('Original (Copy)', $result->title);
    }

    public function testDuplicatePageGridWithAuthenticatedUser()
    {
        $user = $this->createUser();
        $original = new PageGrid(['id' => 1, 'title' => 'Original']);
        $duplicate = Mockery::mock(PageGrid::class)->makePartial();
        $duplicate->id = 2;
        $duplicate->title = 'Original (Copy)';

        $this->authenticationService->shouldReceive('check')->andReturn(true);
        $this->authenticationService->shouldReceive('getUserId')->andReturn($user->id);

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($original);

        $this->repositoryMock
            ->shouldReceive('duplicate')
            ->with(1)
            ->once()
            ->andReturn($duplicate);

        $duplicate->shouldReceive('update')
            ->with(['created_by' => $user->id])
            ->once()
            ->andReturn(true);

        $duplicate->shouldReceive('toArray')
            ->once()
            ->andReturn(['id' => 2, 'title' => 'Original (Copy)']);

        $this->repositoryMock->shouldReceive('logHistory')
            ->once()
            ->with(2, 'created', $user->id, Mockery::on(function($arg) {
                return isset($arg['data']) && isset($arg['duplicated_from']) && $arg['duplicated_from'] === 1;
            }))
            ->andReturn(true);

        $result = $this->service->duplicatePageGrid(1);

        $this->assertInstanceOf(PageGrid::class, $result);
    }

    public function testAddPageToGrid()
    {
        $pageGrid = Mockery::mock(PageGrid::class)->makePartial();
        $pageData = ['title' => 'Test Page', 'slug' => 'test-page'];

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($pageGrid);

        $pageGrid->shouldReceive('addPage')
            ->with($pageData)
            ->once();

        $pageGrid->shouldReceive('save')
            ->once()
            ->andReturn(true);

        $result = $this->service->addPageToGrid(1, $pageData);

        $this->assertInstanceOf(PageGrid::class, $result);
    }

    public function testRemovePageFromGrid()
    {
        $pageGrid = Mockery::mock(PageGrid::class)->makePartial();

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($pageGrid);

        $pageGrid->shouldReceive('removePage')
            ->with(0)
            ->once();

        $pageGrid->shouldReceive('save')
            ->once()
            ->andReturn(true);

        $result = $this->service->removePageFromGrid(1, 0);

        $this->assertInstanceOf(PageGrid::class, $result);
    }

    public function testToggleActive()
    {
        $pageGrid = Mockery::mock(PageGrid::class)->makePartial();
        $pageGrid->is_active = true;

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($pageGrid);

        $pageGrid->shouldReceive('save')
            ->once()
            ->andReturn(true);

        $result = $this->service->toggleActive(1);

        $this->assertFalse($result->is_active);
    }

    public function testCreatePageGridWithDates()
    {
        $data = [
            'title' => 'Seasonal Grid',
            'layout' => 'grid',
            'columns' => 3,
            'start_date' => '2025-01-01 00:00:00',
            'end_date' => '2025-12-31 23:59:59'
        ];

        $this->authenticationService->shouldReceive('check')->andReturn(false);
        $this->repositoryMock->expects('slugExists')->andReturn(false);

        $this->repositoryMock->shouldReceive('logHistory')
            ->once()
            ->with(1, 'created', null, Mockery::type('array'))
            ->andReturn(true);

        $expectedGrid = new PageGrid(array_merge($data, ['slug' => 'seasonal-grid', 'id' => 1]));

        $this->repositoryMock
            ->shouldReceive('create')
            ->once()
            ->andReturn($expectedGrid);

        $result = $this->service->createPageGrid($data);

        $this->assertEquals('2025-01-01 00:00:00', $result->start_date->format('Y-m-d H:i:s'));
        $this->assertEquals('2025-12-31 23:59:59', $result->end_date->format('Y-m-d H:i:s'));;
    }

    public function testCreatePageGridRejectsInvalidDates()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Start date must be before end date');

        $data = [
            'title' => 'Invalid Grid',
            'layout' => 'grid',
            'columns' => 3,
            'start_date' => '2025-12-31 00:00:00',
            'end_date' => '2025-01-01 00:00:00'
        ];

        $this->authenticationService->shouldReceive('check')->andReturn(false);
        $this->repositoryMock->expects('slugExists')->andReturn(false);

        $this->service->createPageGrid($data);
    }

    private function createUser()
    {
        return User::create([
            'name' => 'John Doe',
            'email' => '<EMAIL>',
            'password' => '<PASSWORD>',
        ]);
    }

    public function testAddAuthorToGrid()
    {
        $pageGrid = Mockery::mock(PageGrid::class)->makePartial();
        $authorData = [
            'type' => 'author',
            'id' => 1,
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'bio' => 'Author bio'
        ];

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($pageGrid);

        $pageGrid->shouldReceive('addItem')
            ->with($authorData)
            ->once();

        $pageGrid->shouldReceive('save')
            ->once()
            ->andReturn(true);

        $result = $this->service->addItemToGrid(1, 'author', $authorData);

        $this->assertInstanceOf(PageGrid::class, $result);
    }

    public function testAddProductToGrid()
    {
        $pageGrid = Mockery::mock(PageGrid::class)->makePartial();
        $productData = [
            'type' => 'product',
            'id' => 1,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 99.99
        ];

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($pageGrid);

        $pageGrid->shouldReceive('addItem')
            ->with($productData)
            ->once();

        $pageGrid->shouldReceive('save')
            ->once()
            ->andReturn(true);

        $result = $this->service->addItemToGrid(1, 'product', $productData);

        $this->assertInstanceOf(PageGrid::class, $result);
    }

    public function testRemoveItemFromGrid()
    {
        $pageGrid = Mockery::mock(PageGrid::class)->makePartial();

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($pageGrid);

        $pageGrid->shouldReceive('removeItem')
            ->with(0)
            ->once();

        $pageGrid->shouldReceive('save')
            ->once()
            ->andReturn(true);

        $result = $this->service->removeItemFromGrid(1, 0);

        $this->assertInstanceOf(PageGrid::class, $result);
    }

    public function testUpdateItemInGrid()
    {
        $pageGrid = Mockery::mock(PageGrid::class)->makePartial();
        $updateData = ['name' => 'Updated Name'];

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($pageGrid);

        $pageGrid->shouldReceive('updateItem')
            ->with(0, $updateData)
            ->once();

        $pageGrid->shouldReceive('save')
            ->once()
            ->andReturn(true);

        $pageGrid->shouldReceive('fresh')->once()->andReturn($pageGrid);

        $result = $this->service->updateItemInGrid(1, 0, $updateData);

        $this->assertInstanceOf(PageGrid::class, $result);
    }

    public function testMixedContentTypes()
    {
        $data = [
            'title' => 'Mixed Content Grid',
            'layout' => 'grid',
            'columns' => 3,
            'items' => [
                ['type' => 'page', 'title' => 'Page 1', 'slug' => 'page-1'],
                ['type' => 'author', 'name' => 'Author 1', 'slug' => 'author-1'],
                ['type' => 'product', 'name' => 'Product 1', 'slug' => 'product-1']
            ]
        ];

        $this->authenticationService->shouldReceive('check')->andReturn(false);
        $this->repositoryMock->expects('slugExists')->andReturn(false);

        $expectedGrid = new PageGrid(array_merge($data, ['slug' => 'mixed-content-grid', 'id' => 1]));

        $this->repositoryMock->shouldReceive('logHistory')
            ->once()
            ->andReturn(true);

        $this->repositoryMock
            ->shouldReceive('create')
            ->once()
            ->andReturn($expectedGrid);

        $result = $this->service->createPageGrid($data);

        $this->assertCount(3, $result->items);
        $this->assertEquals('page', $result->items[0]['type']);
        $this->assertEquals('author', $result->items[1]['type']);
        $this->assertEquals('product', $result->items[2]['type']);
    }

    public function testCreatePageGridWithMixedItems()
    {
        $data = [
            'title' => 'Mixed Content Grid',
            'layout' => 'grid',
            'columns' => 3,
            'items' => [
                [
                    'type' => 'page',
                    'title' => 'Test Page',
                    'slug' => 'test-page',
                    'excerpt' => 'Page excerpt'
                ],
                [
                    'type' => 'author',
                    'name' => 'John Doe',
                    'slug' => 'john-doe',
                    'bio' => 'Author bio'
                ],
                [
                    'type' => 'product',
                    'name' => 'Test Product',
                    'slug' => 'test-product',
                    'price' => '99.99',
                    'description' => 'Product description'
                ]
            ]
        ];

        $this->authenticationService->shouldReceive('check')->andReturn(false);
        $this->repositoryMock->expects('slugExists')->andReturn(false);

        $expectedGrid = new PageGrid(array_merge($data, ['slug' => 'mixed-content-grid', 'id' => 1]));

        $this->repositoryMock->shouldReceive('logHistory')
            ->once()
            ->andReturn(true);

        $this->repositoryMock
            ->shouldReceive('create')
            ->once()
            ->andReturn($expectedGrid);

        $result = $this->service->createPageGrid($data);

        $this->assertCount(3, $result->items);
        $this->assertEquals('page', $result->items[0]['type']);
        $this->assertEquals('author', $result->items[1]['type']);
        $this->assertEquals('product', $result->items[2]['type']);
    }

    public function testCreatePageGridWithAuthorsOnly()
    {
        $data = [
            'title' => 'Authors Grid',
            'layout' => 'grid',
            'columns' => 3,
            'items' => [
                [
                    'type' => 'author',
                    'name' => 'Author 1',
                    'slug' => 'author-1',
                    'bio' => 'Bio 1'
                ],
                [
                    'type' => 'author',
                    'name' => 'Author 2',
                    'slug' => 'author-2',
                    'bio' => 'Bio 2'
                ]
            ]
        ];

        $this->authenticationService->shouldReceive('check')->andReturn(false);
        $this->repositoryMock->expects('slugExists')->andReturn(false);

        $expectedGrid = new PageGrid(array_merge($data, ['slug' => 'authors-grid', 'id' => 1]));

        $this->repositoryMock->shouldReceive('logHistory')
            ->once()
            ->andReturn(true);

        $this->repositoryMock
            ->shouldReceive('create')
            ->once()
            ->andReturn($expectedGrid);

        $result = $this->service->createPageGrid($data);

        $this->assertCount(2, $result->items);
        $this->assertEquals('author', $result->items[0]['type']);
        $this->assertEquals('author', $result->items[1]['type']);
    }

    public function testCreatePageGridWithProductsOnly()
    {
        $data = [
            'title' => 'Products Grid',
            'layout' => 'grid',
            'columns' => 4,
            'items' => [
                [
                    'type' => 'product',
                    'name' => 'Product 1',
                    'slug' => 'product-1',
                    'price' => '49.99'
                ],
                [
                    'type' => 'product',
                    'name' => 'Product 2',
                    'slug' => 'product-2',
                    'price' => '99.99'
                ]
            ]
        ];

        $this->authenticationService->shouldReceive('check')->andReturn(false);
        $this->repositoryMock->expects('slugExists')->andReturn(false);

        $expectedGrid = new PageGrid(array_merge($data, ['slug' => 'products-grid', 'id' => 1]));

        $this->repositoryMock->shouldReceive('logHistory')
            ->once()
            ->andReturn(true);

        $this->repositoryMock
            ->shouldReceive('create')
            ->once()
            ->andReturn($expectedGrid);

        $result = $this->service->createPageGrid($data);

        $this->assertCount(2, $result->items);
        $this->assertEquals('product', $result->items[0]['type']);
        $this->assertEquals('product', $result->items[1]['type']);
    }

    public function testCreatePageGridRejectsInvalidItemType()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid item type: invalid');

        $data = [
            'title' => 'Invalid Grid',
            'layout' => 'grid',
            'columns' => 3,
            'items' => [
                [
                    'type' => 'invalid',
                    'name' => 'Test'
                ]
            ]
        ];

        $this->authenticationService->shouldReceive('check')->andReturn(false);
        $this->repositoryMock->expects('slugExists')->andReturn(false);

        $this->service->createPageGrid($data);
    }

    public function testCreatePageGridRequiresNameForAuthor()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Authors require name');

        $data = [
            'title' => 'Invalid Author Grid',
            'layout' => 'grid',
            'columns' => 3,
            'items' => [
                [
                    'type' => 'author',
                    'slug' => 'author-1'
                ]
            ]
        ];

        $this->authenticationService->shouldReceive('check')->andReturn(false);
        $this->repositoryMock->expects('slugExists')->andReturn(false);

        $this->service->createPageGrid($data);
    }

    public function testCreatePageGridRequiresNameForProduct()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Products require name');

        $data = [
            'title' => 'Invalid Product Grid',
            'layout' => 'grid',
            'columns' => 3,
            'items' => [
                [
                    'type' => 'product',
                    'price' => '99.99'
                ]
            ]
        ];

        $this->authenticationService->shouldReceive('check')->andReturn(false);
        $this->repositoryMock->expects('slugExists')->andReturn(false);

        $this->service->createPageGrid($data);
    }

    public function testUpdatePageGridWithMixedItems()
    {
        $existingGrid = new PageGrid([
            'id' => 1,
            'title' => 'Old Title',
            'items' => [
                ['type' => 'page', 'title' => 'Page 1', 'slug' => 'page-1']
            ]
        ]);

        $updatedData = [
            'title' => 'New Title',
            'items' => [
                ['type' => 'page', 'title' => 'Page 1', 'slug' => 'page-1'],
                ['type' => 'author', 'name' => 'John Doe', 'slug' => 'john-doe'],
                ['type' => 'product', 'name' => 'Product 1', 'slug' => 'product-1']
            ]
        ];

        $this->authenticationService->shouldReceive('check')->andReturn(false);

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->twice()
            ->andReturn($existingGrid);

        $this->repositoryMock
            ->shouldReceive('update')
            ->with(1, Mockery::type('array'))
            ->once()
            ->andReturn($existingGrid);

        $existingGrid->title = 'New Title';
        $existingGrid->items = $updatedData['items'];

        $result = $this->service->updatePageGrid(1, $updatedData);

        $this->assertEquals('New Title', $result->title);
        $this->assertCount(3, $result->items);
    }

    public function testDuplicatePageGridWithMixedItems()
    {
        $original = new PageGrid([
            'id' => 1,
            'title' => 'Original',
            'items' => [
                ['type' => 'page', 'title' => 'Page 1', 'slug' => 'page-1'],
                ['type' => 'author', 'name' => 'Author 1', 'slug' => 'author-1'],
                ['type' => 'product', 'name' => 'Product 1', 'slug' => 'product-1']
            ]
        ]);

        $duplicate = Mockery::mock(PageGrid::class)->makePartial();
        $duplicate->id = 2;
        $duplicate->title = 'Original (Copy)';
        $duplicate->items = $original->items;

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($original);

        $this->repositoryMock
            ->shouldReceive('duplicate')
            ->with(1)
            ->once()
            ->andReturn($duplicate);

        $this->authenticationService->shouldReceive('check')->andReturn(false);

        $duplicate->shouldReceive('toArray')
            ->once()
            ->andReturn([
                'id' => 2,
                'title' => 'Original (Copy)',
                'items' => $duplicate->items
            ]);

        $this->repositoryMock->shouldReceive('logHistory')
            ->once()
            ->with(2, 'created', null, Mockery::on(function($arg) {
                return isset($arg['data'])
                    && isset($arg['duplicated_from'])
                    && $arg['duplicated_from'] === 1
                    && isset($arg['items_count'])
                    && isset($arg['item_types'])
                    && $arg['items_count'] === 3;
            }))
            ->andReturn(true);

        $result = $this->service->duplicatePageGrid(1);

        $this->assertInstanceOf(PageGrid::class, $result);
        $this->assertCount(3, $result->items);
    }

    public function testBackwardsCompatibilityWithPagesField()
    {
        $data = [
            'title' => 'Backwards Compatible Grid',
            'layout' => 'grid',
            'columns' => 3,
            'pages' => [ // Using old 'pages' field
                [
                    'title' => 'Page 1',
                    'slug' => 'page-1'
                ]
            ]
        ];

        $this->authenticationService->shouldReceive('check')->andReturn(false);
        $this->repositoryMock->expects('slugExists')->andReturn(false);

        $this->repositoryMock->shouldReceive('logHistory')
            ->once()
            ->andReturn(true);

        $expectedGrid = new PageGrid([
            'id' => 1,
            'title' => 'Backwards Compatible Grid',
            'slug' => 'backwards-compatible-grid',
            'layout' => 'grid',
            'columns' => 3,
            'items' => [
                [
                    'type' => 'page',
                    'title' => 'Page 1',
                    'slug' => 'page-1'
                ]
            ]
        ]);

        $this->repositoryMock
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($arg) {
                // Should convert 'pages' to 'items'
                return isset($arg['items']) && !isset($arg['pages']);
            }))
            ->andReturn($expectedGrid);

        $result = $this->service->createPageGrid($data);

        $this->assertArrayNotHasKey('pages', $result->toArray());
        $this->assertArrayHasKey('items', $result->toArray());
    }

    public function testCreatePageGridWithTerritories()
    {
        $territory1 = Territory::create(['name' => 'Territory 1', 'code' => 'T1', 'is_active' => true, 'site_id' => 1, 'slug' => 'territory-1']);
        $territory2 = Territory::create(['name' => 'Territory 2', 'code' => 'T2', 'is_active' => true, 'site_id' => 1, 'slug' => 'territory-2']);;

        $data = [
            'title' => 'Test Grid',
            'layout' => 'grid',
            'columns' => 3,
            'territory_ids' => [$territory1->id, $territory2->id]
        ];

        $this->authenticationService->shouldReceive('check')->andReturn(false);
        $this->repositoryMock->expects('slugExists')->andReturn(false);

        $expectedGrid = Mockery::mock(PageGrid::class)->makePartial();
        $expectedGrid->id = 1;

        $this->repositoryMock->shouldReceive('create')
            ->once()
            ->andReturn($expectedGrid);

        $expectedGrid->shouldReceive('syncTerritories')
            ->once()
            ->with([$territory1->id, $territory2->id]);

        $this->repositoryMock->shouldReceive('logHistory')
            ->once()
            ->andReturn(true);

        $result = $this->service->createPageGrid($data);

        $this->assertInstanceOf(PageGrid::class, $result);
    }

    public function testUpdatePageGridTerritories()
    {
        $existingGrid = Mockery::mock(PageGrid::class)->makePartial();
        $existingGrid->id = 1;
        $existingGrid->title = 'Test Grid';

        $territory1 = Territory::create(['name' => 'Territory 1', 'code' => 'T1', 'is_active' => true, 'site_id' => 1, 'slug' => 'territory-1']);;

        $this->authenticationService->shouldReceive('check')->andReturn(false);

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->twice()
            ->andReturn($existingGrid);

        $this->repositoryMock
            ->shouldReceive('update')
            ->once()
            ->andReturn($existingGrid);

        $existingGrid->shouldReceive('syncTerritories')
            ->once()
            ->with([$territory1->id]);

        $result = $this->service->updatePageGrid(1, [
            'territory_ids' => [$territory1->id]
        ]);

        $this->assertInstanceOf(PageGrid::class, $result);
    }

    public function testCreatePageGridWithPages()
    {
        $page = $this->createPage(['title' => 'Test Page']);

        $data = [
            'title' => 'Test Grid',
            'layout' => 'grid',
            'columns' => 3,
            'page_ids' => [$page->id]
        ];

        $this->authenticationService->shouldReceive('check')->andReturn(false);
        $this->repositoryMock->expects('slugExists')->andReturn(false);

        $expectedGrid = Mockery::mock(PageGrid::class)->makePartial();
        $expectedGrid->id = 1;

        $this->repositoryMock->shouldReceive('create')
            ->once()
            ->andReturn($expectedGrid);

        $expectedGrid->shouldReceive('pages')
            ->once()
            ->with(true)
            ->andReturnSelf();

        $expectedGrid->shouldReceive('sync')
            ->once()
            ->with([$page->id]);

        $this->repositoryMock->shouldReceive('logHistory')
            ->once()
            ->andReturn(true);

        $result = $this->service->createPageGrid($data);

        $this->assertInstanceOf(PageGrid::class, $result);
    }

    public function testUpdatePageGridPages()
    {
        $existingGrid = Mockery::mock(PageGrid::class)->makePartial();
        $existingGrid->id = 1;
        $existingGrid->title = 'Test Grid';

        $page = $this->createPage(['title' => 'Test Page']);

        $this->authenticationService->shouldReceive('check')->andReturn(false);

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->twice()
            ->andReturn($existingGrid);

        $this->repositoryMock
            ->shouldReceive('update')
            ->once()
            ->andReturn($existingGrid);

        $existingGrid->shouldReceive('pages')
            ->once()
            ->with(true)
            ->andReturnSelf();

        $existingGrid->shouldReceive('sync')
            ->once()
            ->with([$page->id]);

        $result = $this->service->updatePageGrid(1, [
            'page_ids' => [$page->id]
        ]);

        $this->assertInstanceOf(PageGrid::class, $result);
    }

    public function testDuplicatePageGridCopiesPageAssignments()
    {
        $page = $this->createPage(['title' => 'Test Page']);

        $original = Mockery::mock(PageGrid::class)->makePartial();
        $original->id = 1;
        $original->title = 'Original';

        $duplicate = Mockery::mock(PageGrid::class)->makePartial();
        $duplicate->id = 2;
        $duplicate->title = 'Original (Copy)';

        $pagesRelation = Mockery::mock();
        $pagesRelation->shouldReceive('pluck')
            ->with('id')
            ->andReturn(collect([$page->id]));

        $original->shouldReceive('pages')
            ->once()
            ->andReturn($pagesRelation);

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($original);

        $this->repositoryMock
            ->shouldReceive('duplicate')
            ->with(1)
            ->once()
            ->andReturn($duplicate);

        $this->authenticationService->shouldReceive('check')->andReturn(false);

        $duplicate->shouldReceive('pages')
            ->once()
            ->with(true)
            ->andReturnSelf();

        $duplicate->shouldReceive('sync')
            ->once()
            ->with([$page->id]);

        $duplicate->shouldReceive('toArray')
            ->once()
            ->andReturn(['id' => 2, 'title' => 'Original (Copy)']);

        $this->repositoryMock->shouldReceive('logHistory')
            ->once()
            ->andReturn(true);

        $result = $this->service->duplicatePageGrid(1);

        $this->assertInstanceOf(PageGrid::class, $result);
    }

    public function testAssignPages()
    {
        $page1 = $this->createPage(['title' => 'Page 1']);
        $page2 = $this->createPage(['title' => 'Page 2']);

        $pageGrid = Mockery::mock(PageGrid::class)->makePartial();
        $pageGrid->id = 1;

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($pageGrid);

        $pageGrid->shouldReceive('pages')
            ->once()
            ->with(true)
            ->andReturnSelf();

        $pageGrid->shouldReceive('sync')
            ->once()
            ->with([$page1->id, $page2->id]);

        $pageGrid->shouldReceive('fresh')
            ->once()
            ->andReturn($pageGrid);

        $this->repositoryMock->shouldReceive('logHistory')
            ->once()
            ->andReturn(true);

        $this->authenticationService->shouldReceive('check')->andReturn(true);

        $this->authenticationService->shouldReceive('getUserId')->andReturn(1);

        $result = $this->service->assignPages(1, [$page1->id, $page2->id]);

        $this->assertInstanceOf(PageGrid::class, $result);
    }

    public function testGetAssignedPages()
    {
        $page = $this->createPage(['title' => 'Test Page']);

        $pageGrid = Mockery::mock(PageGrid::class)->makePartial();
        $pageGrid->id = 1;

        $collection = Mockery::mock(Collection::class);
        $collection->items = [$page];


        $collection->shouldReceive('count')
            ->once()
            ->andReturn(1);

        $pageGrid->shouldReceive('pages')
            ->once()
            ->andReturn($collection);

        $this->repositoryMock
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($pageGrid);

        $result = $this->service->getAssignedPages(1);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
    }

}