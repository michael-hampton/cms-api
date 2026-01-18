<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Brief;
use App\Repositories\Cms\Briefs\BriefRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class BriefRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private BriefRepository $repository;

    public function test_it_can_search_briefs(): void
    {
        // Arrange
        $user = $this->createUser();
        $this->createBrief(['title' => 'First Brief', 'owner_id' => $user->id]);
        $this->createBrief(['title' => 'Second Brief', 'owner_id' => $user->id]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->setPerPage(10);
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertGreaterThanOrEqual(2, count($result->getData()));
    }

    public function test_search_filters_by_status(): void
    {
        // Arrange
        $user = $this->createUser();
        $this->createBrief(['status' => 'active', 'owner_id' => $user->id]);
        $this->createBrief(['status' => 'converted', 'owner_id' => $user->id]);
        $this->createBrief(['status' => 'archived', 'owner_id' => $user->id]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('status', 'active');
        $criteria->setPerPage(10);
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertCount(1, $result->getData());
        $this->assertEquals('active', $result->getData()[0]['status']);
    }

    public function test_search_filters_by_owner_id(): void
    {
        // Arrange
        $user1 = $this->createUser();
        $user2 = $this->createUser();

        $this->createBrief(['owner_id' => $user1->id, 'title' => 'User 1 Brief']);
        $this->createBrief(['owner_id' => $user2->id, 'title' => 'User 2 Brief']);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('owner_id', $user1->id);
        $criteria->setPerPage(10);
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertCount(1, $result->getData());
        $this->assertEquals('User 1 Brief', $result->getData()[0]['title']);
    }

    public function test_search_filters_by_category_id(): void
    {
        // Arrange
        $user = $this->createUser();
        $category1 = $this->createCategory(['name' => 'Tech']);
        $category2 = $this->createCategory(['name' => 'News']);

        $this->createBrief(['category_id' => $category1->id, 'owner_id' => $user->id]);
        $this->createBrief(['category_id' => $category2->id, 'owner_id' => $user->id]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('category_id', $category1->id);
        $criteria->setPerPage(10);
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertCount(1, $result->getData());
    }

    public function test_search_loads_all_relationships(): void
    {
        // Arrange
        $user = $this->createUser();
        $category = $this->createCategory();
        $brief = $this->createBrief([
            'owner_id' => $user->id,
            'category_id' => $category->id
        ]);

        $this->createBriefAttachment($brief->id, ['type' => 'image']);
        $this->createBriefComment($brief->id, $user->id, ['content' => 'Test comment']);

        // Act
        $criteria = new SearchCriteria();
        $criteria->setPerPage(10);
        $result = $this->repository->search($criteria);

        // Assert
        $foundBrief = $result->getData()[0];
        $this->assertNotEmpty($foundBrief['attachments']);
        $this->assertNotEmpty($foundBrief['comments']);
        $this->assertNotEmpty($foundBrief['owner']);
        $this->assertNotEmpty($foundBrief['category']);
    }

    public function test_get_complete_brief_data_loads_all_relations(): void
    {
        // Arrange
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $attachment = $this->createBriefAttachment($brief->id);
        $comment = $this->createBriefComment($brief->id, $user->id);

        // Create reply
        $this->createBriefComment($brief->id, $user->id, [
            'parent_comment_id' => $comment->id,
            'content' => 'Reply'
        ]);

        // Act
        $result = $this->repository->getCompleteBriefData($brief->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertRelationLoaded($result, 'attachments');
        $this->assertRelationLoaded($result, 'comments');
        $this->assertRelationLoaded($result, 'owner');
    }

    public function test_add_attachment_creates_attachment(): void
    {
        // Arrange
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $attachmentData = [
            'type' => 'image',
            'file_url' => 'http://example.com/image.jpg',
            'file_name' => 'image.jpg',
            'sort_order' => 0
        ];

        // Act
        $attachment = $this->repository->addAttachment($brief->id, $attachmentData);

        // Assert
        $this->assertNotNull($attachment);
        $this->assertEquals('image', $attachment->type);
        $this->assertEquals($brief->id, $attachment->brief_id);
    }

    public function test_delete_attachment_removes_attachment(): void
    {
        // Arrange
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $attachment = $this->createBriefAttachment($brief->id);

        // Act
        $result = $this->repository->deleteAttachment($brief->id, $attachment->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('brief_attachments', ['id' => $attachment->id]);
    }

    public function test_delete_attachment_returns_false_for_nonexistent(): void
    {
        // Arrange
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        // Act
        $result = $this->repository->deleteAttachment($brief->id, 999);

        // Assert
        $this->assertFalse($result);
    }

    public function test_add_comment_creates_comment(): void
    {
        // Arrange
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $commentData = [
            'user_id' => $user->id,
            'content' => 'Test comment'
        ];

        // Act
        $comment = $this->repository->addComment($brief->id, $commentData);

        // Assert
        $this->assertNotNull($comment);
        $this->assertEquals('Test comment', $comment->content);
        $this->assertEquals($brief->id, $comment->brief_id);
    }

    public function test_delete_comment_removes_comment(): void
    {
        // Arrange
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);
        $comment = $this->createBriefComment($brief->id, $user->id);

        // Act
        $result = $this->repository->deleteComment($brief->id, $comment->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('brief_comments', ['id' => $comment->id]);
    }

    public function test_mark_as_converted_updates_status_and_sets_converted_data(): void
    {
        // Arrange
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id, 'status' => 'active']);
        $page = $this->createPage();

        // Act
        $result = $this->repository->markAsConverted($brief->id, $page->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('briefs', [
            'id' => $brief->id,
            'status' => 'converted',
            'converted_page_id' => $page->id
        ]);

        $updated = Brief::find($brief->id);
        $this->assertNotNull($updated->converted_at);
    }

    public function test_mark_as_converted_returns_false_for_nonexistent_brief(): void
    {
        // Arrange
        $page = $this->createPage();

        // Act
        $result = $this->repository->markAsConverted(999, $page->id);

        // Assert
        $this->assertFalse($result);
    }

    public function test_archive_updates_status(): void
    {
        // Arrange
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id, 'status' => 'active']);

        // Act
        $result = $this->repository->archive($brief->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('briefs', [
            'id' => $brief->id,
            'status' => 'archived'
        ]);
    }

    public function test_archive_returns_false_for_nonexistent_brief(): void
    {
        // Act
        $result = $this->repository->archive(999);

        // Assert
        $this->assertFalse($result);
    }

    public function test_search_orders_by_created_at_desc_by_default(): void
    {
        // Arrange
        $user = $this->createUser();
        $oldest = $this->createBrief([
            'owner_id' => $user->id,
            'created_at' => '2024-01-01 00:00:00',
            'title' => 'Oldest'
        ]);
        $newest = $this->createBrief([
            'owner_id' => $user->id,
            'created_at' => '2024-12-31 23:59:59',
            'title' => 'Newest'
        ]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->setPerPage(10);
        $result = $this->repository->search($criteria);

        // Assert
        $data = $result->getData();
        $this->assertEquals('Newest', $data[0]['title']);
    }

    public function test_search_filters_by_site_id(): void
    {
        // Arrange
        $user = $this->createUser();
        $site2 = $this->createSite();

        $this->createBrief(['owner_id' => $user->id, 'site_id' => $this->siteId]);
        $this->createBrief(['owner_id' => $user->id, 'site_id' => $site2->id]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('site_id', $this->siteId);
        $criteria->setPerPage(10);
        $result = $this->repository->search($criteria);

        // Assert
        foreach ($result->getData() as $brief) {
            $this->assertEquals($this->siteId, $brief['site_id']);
        }
    }

    public function test_search_respects_per_page_limit(): void
    {
        // Arrange
        $user = $this->createUser();
        for ($i = 0; $i < 15; $i++) {
            $this->createBrief(['owner_id' => $user->id, 'title' => "Brief $i"]);
        }

        // Act
        $criteria = new SearchCriteria();
        $criteria->setPerPage(5);
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertLessThanOrEqual(5, count($result->getData()));
        $this->assertGreaterThanOrEqual(15, $result->getTotal());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new BriefRepository();
    }
}