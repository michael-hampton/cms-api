<?php

namespace App\Tests\Unit\Models;

use App\Models\PageGrid;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class PageGridTest extends FunctionalTestCase
{
    protected PageGrid $pageGrid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pageGrid = new PageGrid([
            'title' => 'Test Grid',
            'subtitle' => 'Test subtitle',
            'slug' => 'test-grid',
            'layout' => 'grid',
            'columns' => 3,
            'show_excerpt' => true,
            'show_image' => true,
            'show_features' => false,
            'show_actions' => true,
            'pages' => json_encode([
                ['id' => 1, 'title' => 'Page 1'],
                ['id' => 2, 'title' => 'Page 2']
            ]),
            'is_active' => true,
            'created_by' => 1,
            'updated_by' => 1
        ]);
    }

    public function testPageGridCanBeInstantiated()
    {
        $this->assertInstanceOf(PageGrid::class, $this->pageGrid);
    }

    public function testPageGridHasCorrectTableName()
    {
        $this->assertEquals('page_grids', $this->pageGrid->getTable());
    }

    public function testGetPagesCountAttributeReturnsCorrectCount()
    {
        $this->pageGrid->pages = [
            ['id' => 1, 'title' => 'Page 1'],
            ['id' => 2, 'title' => 'Page 2'],
            ['id' => 3, 'title' => 'Page 3']
        ];

        $count = $this->pageGrid->getPagesCountAttribute();
        $this->assertEquals(3, $count);
    }

    public function testGetPagesCountAttributeReturnsZeroForNull()
    {
        $this->pageGrid->pages = null;
        $count = $this->pageGrid->getPagesCountAttribute();
        $this->assertEquals(0, $count);
    }

    public function testGetPagesCountAttributeReturnsZeroForNonArray()
    {
        $this->pageGrid->pages = 'not an array';
        $count = $this->pageGrid->getPagesCountAttribute();
        $this->assertEquals(0, $count);
    }

    public function testAddPageAddsNewPage()
    {
        $this->pageGrid->pages = [['id' => 1, 'title' => 'Page 1']];
        $this->pageGrid->addPage(['id' => 2, 'title' => 'Page 2']);

        $this->assertCount(2, $this->pageGrid->pages);
        $this->assertEquals(['id' => 2, 'title' => 'Page 2'], $this->pageGrid->pages[1]);
    }

    public function testAddPageHandlesNullPages()
    {
        $this->pageGrid->pages = null;
        $this->pageGrid->addPage(['id' => 1, 'title' => 'Page 1']);

        $this->assertCount(1, $this->pageGrid->pages);
        $this->assertEquals(['id' => 1, 'title' => 'Page 1'], $this->pageGrid->pages[0]);
    }

    public function testRemovePageRemovesCorrectPage()
    {
        $this->pageGrid->pages = [
            ['id' => 1, 'title' => 'Page 1'],
            ['id' => 2, 'title' => 'Page 2'],
            ['id' => 3, 'title' => 'Page 3']
        ];

        $this->pageGrid->removePage(1);

        $this->assertCount(2, $this->pageGrid->pages);
        $this->assertEquals(['id' => 1, 'title' => 'Page 1'], $this->pageGrid->pages[0]);
        $this->assertEquals(['id' => 3, 'title' => 'Page 3'], $this->pageGrid->pages[1]);
    }

    public function testRemovePageDoesNothingForInvalidIndex()
    {
        $this->pageGrid->pages = [
            ['id' => 1, 'title' => 'Page 1'],
            ['id' => 2, 'title' => 'Page 2']
        ];

        $this->pageGrid->removePage(5);

        $this->assertCount(2, $this->pageGrid->pages);
    }

    public function testUpdatePageUpdatesCorrectPage()
    {
        $this->pageGrid->pages = [
            ['id' => 1, 'title' => 'Page 1'],
            ['id' => 2, 'title' => 'Page 2']
        ];

        $this->pageGrid->updatePage(1, ['title' => 'Updated Page 2']);

        $this->assertEquals('Updated Page 2', $this->pageGrid->pages[1]['title']);
        $this->assertEquals(2, $this->pageGrid->pages[1]['id']);
    }

    public function testUpdatePageDoesNothingForInvalidIndex()
    {
        $this->pageGrid->pages = [
            ['id' => 1, 'title' => 'Page 1']
        ];

        $originalPages = $this->pageGrid->pages;
        $this->pageGrid->updatePage(5, ['title' => 'Should not update']);

        $this->assertEquals($originalPages, $this->pageGrid->pages);
    }

    public function testReorderPagesReordersCorrectly()
    {
        $this->pageGrid->pages = [
            ['id' => 1, 'title' => 'Page 1'],
            ['id' => 2, 'title' => 'Page 2'],
            ['id' => 3, 'title' => 'Page 3']
        ];

        $this->pageGrid->reorderPages([2, 0, 1]);

        $this->assertEquals(['id' => 3, 'title' => 'Page 3'], $this->pageGrid->pages[0]);
        $this->assertEquals(['id' => 1, 'title' => 'Page 1'], $this->pageGrid->pages[1]);
        $this->assertEquals(['id' => 2, 'title' => 'Page 2'], $this->pageGrid->pages[2]);
    }

    public function testReorderPagesSkipsInvalidIndices()
    {
        $this->pageGrid->pages = [
            ['id' => 1, 'title' => 'Page 1'],
            ['id' => 2, 'title' => 'Page 2']
        ];

        $this->pageGrid->reorderPages([1, 5, 0]);

        $this->assertCount(2, $this->pageGrid->pages);
        $this->assertEquals(['id' => 2, 'title' => 'Page 2'], $this->pageGrid->pages[0]);
        $this->assertEquals(['id' => 1, 'title' => 'Page 1'], $this->pageGrid->pages[1]);
    }

    public function testScopeActiveAddsCorrectWhereClause()
    {
        $query = $this->createMock(\App\Framework\Database\QueryBuilder::class);
        $query->expects($this->once())
            ->method('where')
            ->with('is_active', true)
            ->willReturnSelf();

        $result = $this->pageGrid->scopeActive($query);
        $this->assertInstanceOf(\App\Framework\Database\QueryBuilder::class, $result);
    }

    public function testScopeByLayoutAddsCorrectWhereClause()
    {
        $query = $this->createMock(\App\Framework\Database\QueryBuilder::class);
        $query->expects($this->once())
            ->method('where')
            ->with('layout', 'grid')
            ->willReturnSelf();

        $result = $this->pageGrid->scopeByLayout($query, 'grid');
        $this->assertInstanceOf(\App\Framework\Database\QueryBuilder::class, $result);
    }

    public function testScopeSearchWithSearchTerm()
    {
        $query = $this->createMock(\App\Framework\Database\QueryBuilder::class);
        $query->expects($this->once())
            ->method('where')
            ->willReturnCallback(function($callback) use ($query) {
                $this->assertIsCallable($callback);
                return $query;
            });

        $result = $this->pageGrid->scopeSearch($query, 'test');
        $this->assertInstanceOf(\App\Framework\Database\QueryBuilder::class, $result);
    }

    public function testScopeSearchWithoutSearchTerm()
    {
        $query = $this->createMock(\App\Framework\Database\QueryBuilder::class);
        $query->expects($this->never())->method('where');

        $result = $this->pageGrid->scopeSearch($query, null);
        $this->assertInstanceOf(\App\Framework\Database\QueryBuilder::class, $result);
    }

    public function testCreatorRelationReturnsCorrectType()
    {
        $relation = $this->pageGrid->creator();
        $this->assertInstanceOf(User::class, $relation);
    }

    public function testUpdaterRelationReturnsCorrectType()
    {
        $relation = $this->pageGrid->updater();
        $this->assertInstanceOf(User::class, $relation);
    }

    public function testSetAndGetTitle()
    {
        $this->pageGrid->title = 'New Grid Title';
        $this->assertEquals('New Grid Title', $this->pageGrid->title);
    }

    public function testSetAndGetSubtitle()
    {
        $this->pageGrid->subtitle = 'New subtitle';
        $this->assertEquals('New subtitle', $this->pageGrid->subtitle);
    }

    public function testSetAndGetSlug()
    {
        $this->pageGrid->slug = 'new-grid-slug';
        $this->assertEquals('new-grid-slug', $this->pageGrid->slug);
    }

    public function testSetAndGetLayout()
    {
        $this->pageGrid->layout = 'list';
        $this->assertEquals('list', $this->pageGrid->layout);
    }

    public function testSetAndGetColumns()
    {
        $this->pageGrid->columns = 4;
        $this->assertEquals(4, $this->pageGrid->columns);
    }

    public function testSetAndGetShowExcerpt()
    {
        $this->pageGrid->show_excerpt = false;
        $this->assertFalse($this->pageGrid->show_excerpt);

        $this->pageGrid->show_excerpt = true;
        $this->assertTrue($this->pageGrid->show_excerpt);
    }

    public function testSetAndGetShowImage()
    {
        $this->pageGrid->show_image = false;
        $this->assertFalse($this->pageGrid->show_image);
    }

    public function testSetAndGetShowFeatures()
    {
        $this->pageGrid->show_features = true;
        $this->assertTrue($this->pageGrid->show_features);
    }

    public function testSetAndGetShowActions()
    {
        $this->pageGrid->show_actions = false;
        $this->assertFalse($this->pageGrid->show_actions);
    }

    public function testSetAndGetPages()
    {
        $pages = [
            ['id' => 5, 'title' => 'Page 5'],
            ['id' => 6, 'title' => 'Page 6']
        ];
        $this->pageGrid->pages = $pages;
        $this->assertEquals($pages, $this->pageGrid->pages);
    }

    public function testSetAndGetIsActive()
    {
        $this->pageGrid->is_active = false;
        $this->assertFalse($this->pageGrid->is_active);
    }

    public function testSetAndGetCreatedBy()
    {
        $this->pageGrid->created_by = 5;
        $this->assertEquals(5, $this->pageGrid->created_by);
    }

    public function testSetAndGetUpdatedBy()
    {
        $this->pageGrid->updated_by = 10;
        $this->assertEquals(10, $this->pageGrid->updated_by);
    }
    public function testBooleanAttributesAreCastedCorrectly()
    {
        $this->pageGrid->show_excerpt = 1;
        $this->assertIsBool($this->pageGrid->show_excerpt);
        $this->assertTrue($this->pageGrid->show_excerpt);

        $this->pageGrid->show_image = 0;
        $this->assertIsBool($this->pageGrid->show_image);
        $this->assertFalse($this->pageGrid->show_image);
    }

    public function testPagesArrayIsCastedCorrectly()
    {
        $pages = [['id' => 1], ['id' => 2]];
        $this->pageGrid->pages = $pages;

        $retrieved = $this->pageGrid->pages;
        $this->assertIsArray($retrieved);
        $this->assertCount(2, $retrieved);
    }

    public function testToArrayIncludesAllAttributes()
    {
        $array = $this->pageGrid->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('subtitle', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('layout', $array);
        $this->assertArrayHasKey('columns', $array);
        $this->assertArrayHasKey('is_active', $array);
    }

    public function testCreatePageGrid()
    {
        $pageGrid = PageGrid::create([
            'title' => 'Featured Pages',
            'slug' => 'featured-pages',
            'layout' => 'grid',
            'columns' => 4,
            'is_active' => true
        ]);

        $this->assertInstanceOf(PageGrid::class, $pageGrid);
        $this->assertEquals('Featured Pages', $pageGrid->title);
        $this->assertEquals('featured-pages', $pageGrid->slug);
        $this->assertEquals(4, $pageGrid->columns);
    }

    public function testFillMethodPopulatesAttributes()
    {
        $pageGrid = new PageGrid();
        $pageGrid->fill([
            'title' => 'New Grid',
            'layout' => 'list',
            'show_excerpt' => true
        ]);

        $this->assertEquals('New Grid', $pageGrid->title);
        $this->assertEquals('list', $pageGrid->layout);
        $this->assertTrue($pageGrid->show_excerpt);
    }

    public function testAddMultiplePages()
    {
        $this->pageGrid->pages = [];

        $this->pageGrid->addPage(['id' => 1, 'title' => 'Page 1']);
        $this->pageGrid->addPage(['id' => 2, 'title' => 'Page 2']);
        $this->pageGrid->addPage(['id' => 3, 'title' => 'Page 3']);

        $this->assertCount(3, $this->pageGrid->pages);
        $this->assertEquals('Page 1', $this->pageGrid->pages[0]['title']);
        $this->assertEquals('Page 3', $this->pageGrid->pages[2]['title']);
    }

    public function testUpdatePagePreservesOtherFields()
    {
        $this->pageGrid->pages = [
            ['id' => 1, 'title' => 'Page 1', 'featured' => true]
        ];

        $this->pageGrid->updatePage(0, ['title' => 'Updated Page 1']);

        $this->assertEquals('Updated Page 1', $this->pageGrid->pages[0]['title']);
        $this->assertTrue($this->pageGrid->pages[0]['featured']);
        $this->assertEquals(1, $this->pageGrid->pages[0]['id']);
    }
}