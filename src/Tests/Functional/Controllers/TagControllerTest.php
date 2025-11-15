<?php

namespace App\Tests\Functional\Controllers;

use App\Models\PageTag;
use App\Models\Tag;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class TagControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsTags()
    {
        $this->createTag();
        $this->createTag();
        $response = $this->getForSite('/api/tags');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
    }

    public function testShowReturnsTagById()
    {
        $tag = $this->createTag(['name' => 'PHP', 'slug' => 'php']);
        $response = $this->getForSite("/api/tags/{$tag->id}");
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('PHP', $data['data']['tag']['name']);
    }

    public function testShowReturnsTagBySlug()
    {
        $tag = $this->createTag(['name' => 'PHP', 'slug' => 'php']);
        $response = $this->getForSite('/api/tags/php');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('PHP', $data['data']['tag']['name']);
    }

    public function testShowReturns404ForNonexistent()
    {
        $response = $this->getForSite('/api/tags/999');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testStoreCreatesTag()
    {
        $tagData = ['name' => 'PHP', 'description' => 'PHP programming language', 'color' => '#777BB4', 'is_featured' => true];
        $response = $this->postForSite('/api/tags', $tagData);
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('PHP', $data['data']['tag']['name']);
        $this->assertEquals('php', $data['data']['tag']['slug']);
    }

    public function testStoreAutoGeneratesSlug()
    {
        $response = $this->postForSite('/api/tags', ['name' => 'My New Tag']);
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('my-new-tag', $data['data']['tag']['slug']);
    }

    public function testStoreValidatesUniqueSlug()
    {
        $this->createTag(['name' => 'PHP', 'slug' => 'php']);
        $response = $this->postForSite('/api/tags', ['name' => 'New PHP', 'slug' => 'php']);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateModifiesTag()
    {
        $tag = $this->createTag();
        $response = $this->putForSite("/api/tags/{$tag->id}", ['name' => 'PHP 8', 'description' => 'Updated description', 'is_featured' => true]);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('PHP 8', $data['data']['tag']['name']);
    }

    public function testUpdateReturns404ForNonexistent()
    {
        $response = $this->putForSite('/api/tags/999', ['name' => 'Test']);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDestroyDeletesTag()
    {
        $tag = $this->createTag();
        $response = $this->deleteForSite("/api/tags/{$tag->id}");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(Tag::find($tag->id));
    }

    public function testPopularReturnsTopTags()
    {
        for ($i = 1; $i <= 40; $i++) {
           $this->createTag();
        }
        $response = $this->getForSite('/api/popular-tags');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(30, $data['data']['tags']);
    }

    public function testFeaturedReturnsFeaturedTags()
    {
        $this->createTag(['name' => 'Featured 1', 'slug' => 'featured-1', 'is_featured' => true]);
        $this->createTag(['name' => 'Regular', 'slug' => 'regular', 'is_featured' => false]);
        $this->createTag(['name' => 'Featured 2', 'slug' => 'featured-2', 'is_featured' => true]);

        $response = $this->getForSite('/api/featured-tags');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['data']['tags']);
    }

    public function testCloudReturnsTagCloud()
    {
        for ($i = 1; $i <= 120; $i++) {
            $this->createTag(['name' => "Tag $i", 'slug' => "tag-$i", 'usage_count' => 10, 'site_id' => $this->siteId]);;
        }
        $response = $this->getForSite('/api/tags/cloud');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(100, $data['data']['tags']);
    }

    public function testCleanupRemovesUnusedTags()
    {
        $this->createTag(['name' => 'Used Tag', 'slug' => 'used']);
        $response = $this->postForSite('/api/tags/cleanup');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Cleaned up', $data['message']);
    }

    public function testCheckDeleteTagReturnsCanDeleteWhenNoPagesExist()
    {
        // Arrange: create an author with no pages
        $category = $this->createTag();

        // Act
        $response = $this->getForSite("/api/tags/{$category->id}/check-delete");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('can_delete', $data['data']);
        $this->assertTrue($data['data']['can_delete']);
        $this->assertEquals(0, $data['data']['pages_count']);
        $this->assertFalse($data['data']['requires_reassignment']);
    }

    public function testCheckDeleteTagReturnsRequiresReassignmentWhenPagesExist()
    {
        // Arrange: create an author that has pages
        $category = $this->createTag();

        // Create one or more pages for this author
        $page = $this->createPage();
        $this->attachTagToPage($page, $category);

        // Act
        $response = $this->getForSite("/api/tags/{$category->id}/check-delete");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['requires_reassignment']);
        $this->assertGreaterThan(0, $data['data']['pages_count']);
        $this->assertArrayHasKey('alternatives', $data['data']);
        $this->assertIsArray($data['data']['alternatives']);
    }

    public function testCheckDeleteTagReturns404WhenAuthorNotFound()
    {
        // Act
        $response = $this->getForSite('/api/tags/9999/check-delete');

        // Assert
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Tag not found', $data['data']['message']);
    }

    public function testDuplicateTagSuccessfully(): void
    {
        $tag = $this->createTag(['name' => 'PHP', 'slug' => 'php', 'description' => 'PHP programming']);;

        $response = $this->postForSite("/api/tags/{$tag->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('PHP (Copy)', $data['data']['name']);
        $this->assertEquals('PHP programming', $data['data']['description']);
    }

    public function testDuplicateTagWithPages(): void
    {
        $tag = $this->createTag();

        // Create pages with this tag
        $page = $this->createPage();
        // Associate tag with page using the pivot table directly
        $this->attachTagToPage($page, $tag);

        $response = $this->postForSite("/api/tags/{$tag->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        // Verify original tag still has the page
        $originalTagPages = PageTag::where('tag_id', $tag->id)->count();
        $this->assertEquals(1, $originalTagPages);

        // Verify new tag has no pages
        $newTag = Tag::find($data['data']['id']);
        $newTagPages = PageTag::where('tag_id', $newTag->id)->count();
        $this->assertEquals(0, $newTagPages);
    }

    public function testDuplicateTagWithSeoFields(): void
    {
        $tag = $this->createTag([
            'name' => 'PHP',
            'seo_title' => 'PHP SEO Title',
            'seo_description' => 'PHP SEO Description',
            'no_index' => true,
            'canonical_url' => 'https://example.com/php',
        ]);

        $response = $this->postForSite("/api/tags/{$tag->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('PHP (Copy)', $data['data']['name']);
        $this->assertEquals('PHP SEO Title', $data['data']['seo_title']);
        $this->assertEquals('PHP SEO Description', $data['data']['seo_description']);
        $this->assertEquals(1, $data['data']['no_index']);
        $this->assertNull($data['data']['canonical_url']);
    }

    public function testMergeTagsSuccessfully(): void
    {
        // Arrange
        $fromTag = $this->createTag(['usage_count' => 10]);

        $toTag = $this->createTag(['usage_count' => 5]);

        // Create pages with fromTag
        $page1 = $this->createPage();

        $page2 = $this->createPage();
        $this->attachTagToPage($page1, $fromTag);
        $this->attachTagToPage($page2, $fromTag);

        // Act
        $response = $this->postForSite('/api/tags/merge', [
            'from_tag_id' => $fromTag->id,
            'to_tag_id' => $toTag->id,
        ]);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('merged successfully', $data['message']);

        // Verify fromTag is deleted
        $this->assertNull(Tag::find($fromTag->id));

        // Verify toTag has updated usage count
        $freshToTag = Tag::find($toTag->id);
        $this->assertEquals(15, $freshToTag->usage_count);

        // Verify pages are now associated with toTag
        $pageTags = PageTag::where('tag_id', $toTag->id)->count();
        $this->assertEquals(2, $pageTags);
    }

    public function testMergeTagsFailsForSameTag(): void
    {
        $tag = $this->createTag();

        $response = $this->postForSite('/api/tags/merge', [
            'from_tag_id' => $tag->id,
            'to_tag_id' => $tag->id,
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testMergeTagsFailsWhenSourceNotFound(): void
    {
        $toTag = $this->createTag();

        $response = $this->postForSite('/api/tags/merge', [
            'from_tag_id' => 9999,
            'to_tag_id' => $toTag->id,
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testMergeTagsFailsWhenTargetNotFound(): void
    {
        $fromTag = $this->createTag();

        $response = $this->postForSite('/api/tags/merge', [
            'from_tag_id' => $fromTag->id,
            'to_tag_id' => 9999,
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testBulkDeleteSuccessfully(): void
    {
        $tag1 = $this->createTag();
        $tag2 = $this->createTag();

        $response = $this->postForSite('/api/tags/bulk-delete', [
            'ids' => [$tag1->id, $tag2->id]
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['result']['deleted']);
        $this->assertCount(0, $data['result']['failed']);

        // Verify deletion
        $this->assertNull(Tag::find($tag1->id));
        $this->assertNull(Tag::find($tag2->id));
    }

    public function testBulkDeleteFailsWhenPagesExist(): void
    {
        $tag1 = $this->createTag();
        $tag2 = $this->createTag();

        $page = $this->createPage();
        $this->attachTagToPage($page, $tag2);

        $response = $this->postForSite('/api/tags/bulk-delete', [
            'ids' => [$tag1->id, $tag2->id]
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['result']['deleted']);
        $this->assertCount(1, $data['result']['failed']);
        $this->assertStringContainsString('associated pages', $data['result']['failed'][0]['reason']);

        // Verify tag1 deleted, tag2 still exists
        $this->assertNull(Tag::find($tag1->id));
        $this->assertNotNull(Tag::find($tag2->id));
    }

    public function testIndexFiltersForFeaturedTags()
    {
        $this->createTag(['name' => 'Featured 1', 'is_featured' => true]);
        $this->createTag(['name' => 'Regular 1', 'is_featured' => false]);
        $this->createTag(['name' => 'Featured 2', 'is_featured' => true]);

        $response = $this->getForSite('/api/tags?is_featured=true');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['items']);

        foreach ($data['items'] as $tag) {
            $this->assertTrue($tag['is_featured']);
        }
    }

    public function testIndexReturnsAllTagsWhenFeaturedFilterNotSet()
    {
        $this->createTag(['is_featured' => true]);
        $this->createTag(['is_featured' => false]);

        $response = $this->getForSite('/api/tags');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['items']);
    }

    public function testBulkDeleteValidation(): void
    {
        $response = $this->postForSite('/api/tags/bulk-delete', [
            'ids' => []
        ]);

        $this->assertResponseStatus(422, $response);
    }
}