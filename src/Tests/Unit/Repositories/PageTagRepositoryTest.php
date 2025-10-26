<?php

namespace App\Tests\Unit\Repositories;

use App\Models\PageTag;
use App\Models\Tag;
use App\Repositories\PageTagRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PageTagRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private PageTagRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PageTagRepository();
    }

    /** @test */
    public function test_sync_tags_removes_old_and_adds_new_tags(): void
    {
        // Arrange
        $page = $this->createPage();
        $oldTag = $this->createTag(['name' => 'Old Tag']);
        $this->attachTagToPage($page, $oldTag);

        // Act
        $this->repository->syncTags($page->id, ['New Tag 1', 'New Tag 2'], $this->siteId);

        // Assert
        $pageTags = PageTag::where('page_id', $page->id)->get();
        $this->assertCount(2, $pageTags);

        // Old tag should be removed
        $this->assertDatabaseMissing('page_tags', [
            'page_id' => $page->id,
            'tag_id' => $oldTag->id
        ]);

        // New tags should exist
        $newTag1 = Tag::where('name', 'New Tag 1')->first();
        $newTag2 = Tag::where('name', 'New Tag 2')->first();

        $this->assertNotNull($newTag1);
        $this->assertNotNull($newTag2);

        $this->assertDatabaseHas('page_tags', [
            'page_id' => $page->id,
            'tag_id' => $newTag1->id
        ]);
        $this->assertDatabaseHas('page_tags', [
            'page_id' => $page->id,
            'tag_id' => $newTag2->id
        ]);
    }

    /** @test */
    public function test_sync_tags_creates_new_tags_if_not_exist(): void
    {
        // Arrange
        $page = $this->createPage();

        // Act
        $this->repository->syncTags($page->id, ['Brand New Tag'], $this->siteId);

        // Assert
        $tag = Tag::where('name', 'Brand New Tag')
            ->where('site_id', $this->siteId)
            ->first();

        $this->assertNotNull($tag);
        $this->assertDatabaseHas('page_tags', [
            'page_id' => $page->id,
            'tag_id' => $tag->id
        ]);
    }

    /** @test */
    public function test_sync_tags_uses_existing_tags(): void
    {
        // Arrange
        $page = $this->createPage();
        $existingTag = $this->createTag(['name' => 'Existing Tag', 'slug' => 'existing-tag']);

        $initialTagCount = Tag::where('site_id', $this->siteId)->count();

        // Act
        $this->repository->syncTags($page->id, ['Existing Tag'], $this->siteId);

        // Assert - should not create duplicate tag
        $finalTagCount = Tag::where('site_id', $this->siteId)->count();
        $this->assertEquals($initialTagCount, $finalTagCount);

        $this->assertDatabaseHas('page_tags', [
            'page_id' => $page->id,
            'tag_id' => $existingTag->id
        ]);
    }

    /** @test */
    public function test_sync_tags_trims_whitespace_from_tag_names(): void
    {
        // Arrange
        $page = $this->createPage();

        // Act
        $this->repository->syncTags($page->id, ['  Spaced Tag  ', 'Normal Tag'], $this->siteId);

        // Assert
        $tag = Tag::where('name', 'Spaced Tag')->first();
        $this->assertNotNull($tag);
        $this->assertEquals('Spaced Tag', $tag->name); // Should be trimmed
    }

    /** @test */
    public function test_sync_tags_ignores_empty_tag_names(): void
    {
        // Arrange
        $page = $this->createPage();

        // Act
        $this->repository->syncTags($page->id, ['Valid Tag', '', '   ', 'Another Valid'], $this->siteId);

        // Assert
        $pageTags = PageTag::where('page_id', $page->id)->get();
        $this->assertCount(2, $pageTags); // Only 2 valid tags

        $tagIds = [];

        foreach ($pageTags as $pageTag) {
            $tagIds[] = $pageTag->tag_id;
        }


        $tags = Tag::whereIn('id', $tagIds)->get();
        $tagNames = $tags->pluck('name')->toArray();

        $this->assertContains('Valid Tag', $tagNames);
        $this->assertContains('Another Valid', $tagNames);
    }

    /** @test */
    public function test_sync_tags_decrements_usage_count_for_removed_tags(): void
    {
        // Arrange
        $page = $this->createPage();
        $tag = $this->createTag(['name' => 'Old Tag', 'usage_count' => 5]);
        $this->attachTagToPage($page, $tag);

        // Act
        $this->repository->syncTags($page->id, ['New Tag'], $this->siteId);

        // Assert
        $freshTag = $this->fresh($tag);
        $this->assertEquals(4, $freshTag->usage_count);
    }

    /** @test */
    public function test_sync_tags_increments_usage_count_for_new_tags(): void
    {
        // Arrange
        $page = $this->createPage();
        $existingTag = $this->createTag(['name' => 'Existing Tag', 'usage_count' => 3]);

        // Act
        $this->repository->syncTags($page->id, ['Existing Tag'], $this->siteId);

        // Assert
        $freshTag = $this->fresh($existingTag);
        $this->assertEquals(2, $freshTag->usage_count);
    }

    /** @test */
    public function test_sync_tags_handles_empty_tag_array(): void
    {
        // Arrange
        $page = $this->createPage();
        $tag = $this->createTag();
        $this->attachTagToPage($page, $tag);

        // Act
        $this->repository->syncTags($page->id, [], $this->siteId);

        // Assert
        $count = $this->countRecords('page_tags', ['page_id' => $page->id]);
        $this->assertEquals(0, $count);
    }

    /** @test */
    public function test_get_page_tags_returns_tags_for_page(): void
    {
        // Arrange
        $page = $this->createPage();
        $tag1 = $this->createTag(['name' => 'Alpha Tag']);
        $tag2 = $this->createTag(['name' => 'Beta Tag']);
        $tag3 = $this->createTag(['name' => 'Charlie Tag']);

        $this->attachTagToPage($page, $tag1);
        $this->attachTagToPage($page, $tag2);
        $this->attachTagToPage($page, $tag3);

        // Act
        $tags = $this->repository->getPageTags($page->id, $this->siteId);

        // Assert
        $this->assertCount(3, $tags);
        $this->assertContainsOnlyInstancesOf(Tag::class, $tags);
    }

    /** @test */
    public function test_get_page_tags_orders_by_name_ascending(): void
    {
        // Arrange
        $page = $this->createPage();
        $tagZ = $this->createTag(['name' => 'Zebra']);
        $tagA = $this->createTag(['name' => 'Apple']);
        $tagM = $this->createTag(['name' => 'Mango']);

        $this->attachTagToPage($page, $tagZ);
        $this->attachTagToPage($page, $tagA);
        $this->attachTagToPage($page, $tagM);

        // Act
        $tags = $this->repository->getPageTags($page->id, $this->siteId);

        // Assert
        $this->assertEquals('Apple', $tags[0]->name);
        $this->assertEquals('Mango', $tags[1]->name);
        $this->assertEquals('Zebra', $tags[2]->name);
    }

    /** @test */
    public function test_get_page_tags_returns_empty_array_when_no_tags(): void
    {
        // Arrange
        $page = $this->createPage();

        // Act
        $tags = $this->repository->getPageTags($page->id, $this->siteId);

        // Assert
        $this->assertIsArray($tags);
        $this->assertCount(0, $tags);
    }

    /** @test */
    public function test_get_page_tags_returns_hydrated_models(): void
    {
        // Arrange
        $page = $this->createPage();
        $tag = $this->createTag(['name' => 'Test Tag']);
        $this->attachTagToPage($page, $tag);

        // Act
        $tags = $this->repository->getPageTags($page->id, $this->siteId);;

        // Assert
        $this->assertCount(1, $tags);
        $returnedTag = $tags[0];

        $this->assertInstanceOf(Tag::class, $returnedTag);
        $this->assertEquals($tag->id, $returnedTag->id);
        $this->assertEquals('Test Tag', $returnedTag->name);
    }
}