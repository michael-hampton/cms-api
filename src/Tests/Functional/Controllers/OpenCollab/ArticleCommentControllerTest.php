<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Models\ArticleComment;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ArticleCommentControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $contributor;
    private User $otherUser;

    public function test_index_returns_top_level_comments_with_replies(): void
    {
        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'created_by' => $this->contributor->id,
            'updated_by' => $this->contributor->id,
        ]);

        $parent = ArticleComment::create([
            'article_id' => $page->id,
            'user_id' => $this->authenticatedUser->id,
            'content' => 'Parent comment',
            'parent_id' => null,
        ]);

        ArticleComment::create([
            'article_id' => $page->id,
            'user_id' => $this->otherUser->id,
            'content' => 'Reply comment',
            'parent_id' => $parent->id,
        ]);

        $response = $this->getForSite("/api/open-collab/pages/{$page->id}/comments");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['data']);
        $this->assertEquals('Parent comment', $data['data'][0]['content']);
        $this->assertCount(1, $data['data'][0]['replies']);
        $this->assertEquals('Reply comment', $data['data'][0]['replies'][0]['content']);
    }

    public function test_store_creates_comment_for_article(): void
    {
        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'created_by' => $this->contributor->id,
            'updated_by' => $this->contributor->id,
        ]);

        $response = $this->postForSite("/api/open-collab/pages/{$page->id}/comments", [
            'content' => 'New inline comment',
            'position' => '{"line":3}',
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('New inline comment', $data['data']['comment']['content']);
        $this->assertDatabaseHas('oc_article_comments', [
            'article_id' => $page->id,
            'user_id' => $this->authenticatedUser->id,
            'content' => 'New inline comment',
            'position' => '{"line":3}',
        ]);
    }

    public function test_store_returns_422_when_content_is_missing(): void
    {
        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'created_by' => $this->contributor->id,
            'updated_by' => $this->contributor->id,
        ]);

        $response = $this->postForSite("/api/open-collab/pages/{$page->id}/comments", [
            'content' => '   ',
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('Comment content is required.', $data['error']);
    }

    public function test_reply_creates_nested_comment(): void
    {
        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'created_by' => $this->contributor->id,
            'updated_by' => $this->contributor->id,
        ]);
        $parent = ArticleComment::create([
            'article_id' => $page->id,
            'user_id' => $this->authenticatedUser->id,
            'content' => 'Parent comment',
            'parent_id' => null,
        ]);

        $response = $this->postForSite("/api/open-collab/pages/{$page->id}/comments/{$parent->id}/reply", [
            'content' => 'Nested reply',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertDatabaseHas('oc_article_comments', [
            'article_id' => $page->id,
            'parent_id' => $parent->id,
            'content' => 'Nested reply',
        ]);
    }

    public function test_reply_returns_404_when_parent_comment_does_not_belong_to_page(): void
    {
        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'created_by' => $this->contributor->id,
            'updated_by' => $this->contributor->id,
        ]);
        $otherPage = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'created_by' => $this->contributor->id,
            'updated_by' => $this->contributor->id,
        ]);

        $parent = ArticleComment::create([
            'article_id' => $otherPage->id,
            'user_id' => $this->authenticatedUser->id,
            'content' => 'Wrong parent',
            'parent_id' => null,
        ]);

        $response = $this->postForSite("/api/open-collab/pages/{$page->id}/comments/{$parent->id}/reply", [
            'content' => 'Nested reply',
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_destroy_allows_author_to_delete_comment(): void
    {
        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'created_by' => $this->contributor->id,
            'updated_by' => $this->contributor->id,
        ]);
        $comment = ArticleComment::create([
            'article_id' => $page->id,
            'user_id' => $this->authenticatedUser->id,
            'content' => 'Delete me',
            'parent_id' => null,
        ]);

        $response = $this->deleteForSite("/api/open-collab/comments/{$comment->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseMissing('oc_article_comments', ['id' => $comment->id]);
    }

    public function test_destroy_returns_403_for_non_author_non_admin(): void
    {
        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'created_by' => $this->contributor->id,
            'updated_by' => $this->contributor->id,
        ]);
        $comment = ArticleComment::create([
            'article_id' => $page->id,
            'user_id' => $this->contributor->id,
            'content' => 'Protected comment',
            'parent_id' => null,
        ]);

        $this->actingAs($this->otherUser);

        $response = $this->deleteForSite("/api/open-collab/comments/{$comment->id}");

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertDatabaseHas('oc_article_comments', ['id' => $comment->id]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->contributor = $this->createUser([
            'email' => 'article-comment-owner@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);

        $this->otherUser = $this->createUser([
            'email' => 'article-comment-other@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
        ]);

        $this->actingAs($this->contributor);
    }
}
