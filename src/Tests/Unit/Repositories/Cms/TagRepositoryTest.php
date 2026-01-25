<?php

namespace App\Tests\Unit\Repositories\Cms;

use App\Models\Tag;
use App\Repositories\Cms\TagRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class TagRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private TagRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TagRepository();
    }

    public function test_it_can_find_tag_by_slug(): void
    {
        // Arrange
        $tag = $this->createTag(['slug' => 'unique-test-slug', 'name' => 'Unique Tag']);

        // Act
        $found = $this->repository->findBySlug('unique-test-slug');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($tag->id, $found->id);
        $this->assertEquals('unique-test-slug', $found->slug);
    }

    public function test_it_returns_null_when_slug_not_found(): void
    {
        // Act
        $found = $this->repository->findBySlug('non-existent-slug');

        // Assert
        $this->assertNull($found);
    }

    public function test_it_filters_tags_by_site(): void
    {
        // Arrange
        $this->createTag(['slug' => 'site-1-tag', 'site_id' => $this->siteId]);
        $otherSite = $this->createSite();
        $this->createTag(['slug' => 'site-2-tag', 'site_id' => $otherSite->id]);

        // Act
        $found = $this->repository->findBySlug('site-1-tag');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($this->siteId, $found->site_id);
    }

    public function test_find_or_create_by_name_creates_new_tag(): void
    {
        // Act
        $tag = $this->repository->findOrCreateByName('New Tag', $this->siteId);

        // Assert
        $this->assertNotNull($tag);
        $this->assertEquals('New Tag', $tag->name);
        $this->assertEquals('new-tag', $tag->slug);
        $this->assertEquals(1, $tag->usage_count);
    }

    public function test_find_or_create_by_name_increments_existing_tag(): void
    {
        // Arrange
        $existing = $this->createTag(['name' => 'Existing Tag', 'slug' => 'existing-tag', 'usage_count' => 5]);

        // Act
        $tag = $this->repository->findOrCreateByName('Existing Tag', $this->siteId);

        // Assert
        $this->assertEquals($existing->id, $tag->id);
        $freshTag = $this->fresh($existing);
        $this->assertEquals(6, $freshTag->usage_count);
    }

    public function test_get_popular_tags_returns_ordered_by_usage(): void
    {
        // Arrange
        $tag1 = $this->createTag(['name' => 'Tag 1', 'usage_count' => 10]);
        $tag2 = $this->createTag(['name' => 'Tag 2', 'usage_count' => 50]);
        $tag3 = $this->createTag(['name' => 'Tag 3', 'usage_count' => 25]);

        // Act
        $tags = $this->repository->getPopularTags(10);

        // Assert
        $this->assertCount(3, $tags);
        $tagsArray = $tags->toArray();
        $this->assertEquals($tag2->id, $tagsArray[0]['id']);
        $this->assertEquals($tag3->id, $tagsArray[1]['id']);
        $this->assertEquals($tag1->id, $tagsArray[2]['id']);
    }

    public function test_get_popular_tags_respects_limit(): void
    {
        // Arrange
        for ($i = 1; $i <= 10; $i++) {
            $this->createTag(['name' => "Tag $i", 'usage_count' => $i]);
        }

        // Act
        $tags = $this->repository->getPopularTags(5);

        // Assert
        $this->assertCount(5, $tags);
    }

    public function test_get_popular_tags_filters_by_search_query(): void
    {
        // Arrange
        $this->createTag(['name' => 'PHP Programming', 'usage_count' => 10]);
        $this->createTag(['name' => 'JavaScript', 'usage_count' => 20]);
        $this->createTag(['name' => 'PHP Framework', 'usage_count' => 15]);

        // Act
        $tags = $this->repository->getPopularTags(10, 'PHP');

        // Assert
        $this->assertCount(2, $tags);
        foreach ($tags as $tag) {
            $this->assertStringContainsString('PHP', $tag->name);
        }
    }

    public function test_get_featured_tags_returns_only_featured(): void
    {
        // Arrange
        $featured1 = $this->createTag(['name' => 'Featured 1', 'is_featured' => true]);
        $regular = $this->createTag(['name' => 'Regular', 'is_featured' => false]);
        $featured2 = $this->createTag(['name' => 'Featured 2', 'is_featured' => true]);

        // Act
        $tags = $this->repository->getFeaturedTags();

        // Assert
        $this->assertCount(2, $tags);
        $this->assertCollectionContains($tags, ['name' => 'Featured 1']);
        $this->assertCollectionContains($tags, ['name' => 'Featured 2']);
        $this->assertCollectionDoesNotContain($tags, ['name' => 'Regular']);
    }

    public function test_search_tags_filters_by_query(): void
    {
        // Arrange
        $this->createTag(['name' => 'Laravel', 'usage_count' => 10]);
        $this->createTag(['name' => 'PHP', 'usage_count' => 20]);
        $this->createTag(['name' => 'Laravel Framework', 'usage_count' => 15]);

        // Act
        $tags = $this->repository->searchTags('Laravel', 10);

        // Assert
        $this->assertCount(2, $tags);
        foreach ($tags as $tag) {
            $this->assertStringContainsString('Laravel', $tag->name);
        }
    }

    public function test_get_tag_cloud_calculates_relative_sizes(): void
    {
        // Arrange
        $this->createTag(['name' => 'Tag 1', 'usage_count' => 100]);
        $this->createTag(['name' => 'Tag 2', 'usage_count' => 50]);
        $this->createTag(['name' => 'Tag 3', 'usage_count' => 25]);

        // Act
        $tags = $this->repository->getTagCloud(10, $this->siteId);

        // Assert
        $tagsArray = $tags->toArray();

        $this->assertEquals(100, $tagsArray[0]['relative_size']);
        $this->assertEquals(50, $tagsArray[1]['relative_size']);
        $this->assertEquals(25, $tagsArray[2]['relative_size']);
    }

    public function test_cleanup_unused_tags_removes_zero_usage(): void
    {
        // Arrange
        $used = $this->createTag(['name' => 'Used', 'usage_count' => 5]);
        $unused = $this->createTag(['name' => 'Unused', 'usage_count' => 0]);

        // Act
        $deleted = $this->repository->cleanupUnusedTags();

        // Assert
        $this->assertGreaterThan(0, $deleted);
        $this->assertNotNull(Tag::find($used->id));
        $this->assertNull(Tag::find($unused->id));
    }

    public function test_merge_tags_updates_page_associations(): void
    {
        // Arrange
        $fromTag = $this->createTag(['name' => 'From Tag', 'usage_count' => 5]);
        $toTag = $this->createTag(['name' => 'To Tag', 'usage_count' => 10]);

        $page1 = $this->createPage();
        $page2 = $this->createPage();

        $this->attachTagToPage($page1, $fromTag);
        $this->attachTagToPage($page2, $fromTag);

        // Act
        $result = $this->repository->mergeTags($fromTag->id, $toTag->id);

        // Assert
        $this->assertTrue($result);

        // Verify pages are now associated with toTag
        $this->assertDatabaseHas('page_tags', [
            'page_id' => $page1->id,
            'tag_id' => $toTag->id
        ]);
        $this->assertDatabaseHas('page_tags', [
            'page_id' => $page2->id,
            'tag_id' => $toTag->id
        ]);

        // Verify fromTag no longer exists
        $this->assertNull(Tag::find($fromTag->id));
    }

    public function test_merge_tags_updates_usage_count(): void
    {
        // Arrange
        $fromTag = $this->createTag(['name' => 'From Tag', 'usage_count' => 5]);
        $toTag = $this->createTag(['name' => 'To Tag', 'usage_count' => 10]);

        // Act
        $this->repository->mergeTags($fromTag->id, $toTag->id);

        // Assert
        $freshToTag = $this->fresh($toTag);
        $this->assertEquals(15, $freshToTag->usage_count);
    }

    public function test_get_alternatives_excludes_specified_tag(): void
    {
        // Arrange
        $tag1 = $this->createTag(['name' => 'Tag 1']);
        $tag2 = $this->createTag(['name' => 'Tag 2']);
        $tag3 = $this->createTag(['name' => 'Tag 3']);

        // Act
        $alternatives = $this->repository->getAlternatives($tag2->id);

        // Assert
        $this->assertGreaterThanOrEqual(2, $alternatives->count());
        $this->assertCollectionDoesNotContain($alternatives, ['id' => $tag2->id]);
    }

    public function test_get_pages_by_tag_id_returns_correct_pages(): void
    {
        // Arrange
        $tag = $this->createTag();
        $page1 = $this->createPage(['title' => 'Page 1']);
        $page2 = $this->createPage(['title' => 'Page 2']);
        $page3 = $this->createPage(['title' => 'Page 3']);

        $this->attachTagToPage($page1, $tag);
        $this->attachTagToPage($page2, $tag);

        // Act
        $pages = $this->repository->getPagesByTagId($tag->id);

        // Assert
        $this->assertCount(2, $pages);
    }

    public function test_get_pages_by_tag_id_respects_limit(): void
    {
        // Arrange
        $tag = $this->createTag();
        $pages = $this->createPages(5);

        foreach ($pages as $page) {
            $this->attachTagToPage($page, $tag);
        }

        // Act
        $result = $this->repository->getPagesByTagId($tag->id, 2);

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_search_returns_paginated_results(): void
    {
        // Arrange
        $this->createTag(['name' => 'Tag 1']);
        $this->createTag(['name' => 'Tag 2']);
        $this->createTag(['name' => 'Tag 3']);

        // Act
        $criteria = new SearchCriteria();
        $criteria->setPerPage(2);
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertInstanceOf(\App\Search\PaginatedResult::class, $result);
        $this->assertGreaterThan(0, count($result->getData()));
    }
}