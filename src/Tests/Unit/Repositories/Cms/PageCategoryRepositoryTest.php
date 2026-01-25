<?php

namespace App\Tests\Unit\Repositories\Cms;

use App\Models\Category;
use App\Repositories\Cms\PageCategoryRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class PageCategoryRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private PageCategoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PageCategoryRepository();
    }

    public function test_sync_categories_removes_existing_categories(): void
    {
        $page = $this->createPage();
        $oldCategory = $this->createCategory(['name' => 'Old Category']);

        $this->attachCategoryToPage($page, $oldCategory);

        $this->repository->syncCategories($page->id, ['New Category'], $this->siteId);

        $count = $this->countRecords('page_categories', [
            'page_id' => $page->id,
            'category_id' => $oldCategory->id
        ]);

        $this->assertEquals(0, $count);
    }

    public function test_sync_categories_creates_new_category_if_not_exists(): void
    {
        $page = $this->createPage();

        $this->repository->syncCategories($page->id, ['Technology'], $this->siteId);

        $category = Category::where('name', 'Technology')
            ->where('site_id', $this->siteId)
            ->first();

        $this->assertNotNull($category);
        $this->assertDatabaseHas('page_categories', [
            'page_id' => $page->id,
            'category_id' => $category->id
        ]);
    }

    public function test_sync_categories_uses_existing_category(): void
    {
        $page = $this->createPage();
        $existingCategory = $this->createCategory(['name' => 'Existing']);

        $initialCount = Category::where('site_id', $this->siteId)->count();

        $this->attachCategoryToPage($page, $existingCategory);

        $finalCount = Category::where('site_id', $this->siteId)->count();

        $this->assertEquals($initialCount, $finalCount);
        $this->assertDatabaseHas('page_categories', [
            'page_id' => $page->id,
            'category_id' => $existingCategory->id
        ]);
    }

    public function test_sync_categories_handles_multiple_categories(): void
    {
        $page = $this->createPage();

        $this->repository->syncCategories(
            $page->id,
            ['Tech', 'News', 'Reviews'],
            $this->siteId
        );

        $count = $this->countRecords('page_categories', ['page_id' => $page->id]);
        $this->assertEquals(3, $count);

        $categories = Category::where('site_id', $this->siteId)->get();
        $this->assertCount(3, $categories);
    }

    public function test_sync_categories_trims_whitespace(): void
    {
        $page = $this->createPage();

        $this->repository->syncCategories(
            $page->id,
            ['  Technology  ', 'News'],
            $this->siteId
        );

        $category = Category::where('name', 'Technology')
            ->where('site_id', $this->siteId)
            ->first();

        $this->assertNotNull($category);
        $this->assertEquals('Technology', $category->name);
    }

    public function test_sync_categories_skips_empty_strings(): void
    {
        $page = $this->createPage();

        $this->repository->syncCategories(
            $page->id,
            ['Technology', '', '  ', 'News'],
            $this->siteId
        );

        $count = $this->countRecords('page_categories', ['page_id' => $page->id]);
        $this->assertEquals(2, $count);
    }

    public function test_sync_categories_with_empty_array_removes_all(): void
    {
        $page = $this->createPage();
        $category = $this->createCategory();
        $this->attachCategoryToPage($page, $category);

        $this->repository->syncCategories($page->id, [], $this->siteId);

        $count = $this->countRecords('page_categories', ['page_id' => $page->id]);
        $this->assertEquals(0, $count);
    }

    public function test_sync_categories_generates_slug_from_name(): void
    {
        $page = $this->createPage();

        $this->repository->syncCategories(
            $page->id,
            ['Web Development'],
            $this->siteId
        );

        $category = Category::where('site_id', $this->siteId)->first();

        $this->assertNotNull($category->slug);
        $this->assertStringContainsString('web', strtolower($category->slug));
    }
}