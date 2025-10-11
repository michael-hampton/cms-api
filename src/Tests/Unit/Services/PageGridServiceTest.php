<?php

namespace App\Tests\Unit\Services;

use App\Framework\Authorization\AuthenticationService;
use App\Models\PageGrid;
use App\Models\User;
use App\Repositories\PageGridRepository;
use App\Services\PageGridService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class PageGridServiceTest extends FunctionalTestCase
{
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
            ->with(1)
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

        $this->authenticationService->shouldReceive('check')->andReturn(false);

        $expectedGrid = new PageGrid(array_merge($data, ['slug' => 'test-grid']));

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
            ->andReturn(new PageGrid($data));

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
            ->shouldReceive('duplicate')
            ->with(1)
            ->once()
            ->andReturn($duplicate);

        $this->authenticationService->shouldReceive('check')->andReturn(false);

        $result = $this->service->duplicatePageGrid(1);

        $this->assertInstanceOf(PageGrid::class, $result);
        $this->assertEquals('Original (Copy)', $result->title);
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

    private function createUser()
    {
        return User::create([
            'name' => 'John Doe',
            'email' => '<EMAIL>',
            'password' => '<PASSWORD>',
        ]);
    }
}