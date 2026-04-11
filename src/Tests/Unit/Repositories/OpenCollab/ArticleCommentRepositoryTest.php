<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Models\ArticleComment;
use App\Models\Page;
use App\Models\User;
use App\Repositories\OpenCollab\ArticleCommentRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ArticleCommentRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ArticleCommentRepository $repository;
    private Page $page;
    private User $user;
    private User $user2;

    public function test_for_article_returns_top_level_comments_only(): void
    {
        // Arrange
        $parent = ArticleComment::create(['article_id' => $this->page->id, 'user_id' => $this->user->id, 'content' => 'Parent', 'parent_id' => null]);
        ArticleComment::create(['article_id' => $this->page->id, 'user_id' => $this->user2->id, 'content' => 'Reply', 'parent_id' => $parent->id]);

        // Act
        $comments = $this->repository->forArticle($this->page->id);

        // Assert
        $this->assertCount(1, $comments);
        $this->assertNull($comments->first()->parent_id);
    }

    public function test_for_article_eager_loads_replies(): void
    {
        $parent = ArticleComment::create(['article_id' => $this->page->id, 'user_id' => $this->user->id, 'content' => 'Parent', 'parent_id' => null]);
        ArticleComment::create(['article_id' => $this->page->id, 'user_id' => $this->user2->id, 'content' => 'Reply', 'parent_id' => $parent->id]);

        $comments = $this->repository->forArticle($this->page->id);

        $this->assertCount(1, $comments->first()->replies);
    }

    public function test_for_article_eager_loads_user_relationship(): void
    {
        ArticleComment::create([
            'article_id' => $this->page->id,
            'user_id' => $this->user->id,
            'content' => 'Parent',
            'parent_id' => null,
        ]);

        $comments = $this->repository->forArticle($this->page->id);

        $this->assertRelationLoaded($comments->first(), 'user');
    }

    public function test_for_article_does_not_return_comments_for_other_articles(): void
    {
        ArticleComment::create(['article_id' => $this->page->id, 'user_id' => $this->user->id, 'content' => 'Other article', 'parent_id' => null]);

        $comments = $this->repository->forArticle(99);

        $this->assertCount(0, $comments);
    }

    public function test_for_article_orders_by_created_at_asc(): void
    {
        $older = ArticleComment::create(['article_id' => $this->page->id, 'user_id' => $this->user->id, 'content' => 'Older', 'parent_id' => null]);
        $newer = ArticleComment::create(['article_id' => $this->page->id, 'user_id' => $this->user->id, 'content' => 'Newer', 'parent_id' => null]);

        $comments = $this->repository->forArticle($this->page->id);

        $this->assertEquals($newer->id, $comments->first()->id);
        $this->assertEquals($older->id, $comments->last()->id);
    }

    public function test_add_comment_creates_top_level_comment(): void
    {
        $comment = $this->repository->addComment($this->page->id, $this->user->id, 'Hello world');

        $this->assertInstanceOf(ArticleComment::class, $comment);
        $this->assertEquals($this->page->id, $comment->article_id);
        $this->assertEquals($this->user->id, $comment->user_id);
        $this->assertEquals('Hello world', $comment->content);
        $this->assertNull($comment->parent_id);
    }

    public function test_add_comment_creates_reply_when_parent_id_given(): void
    {
        $parent = ArticleComment::create(['article_id' => $this->page->id, 'user_id' => $this->user->id, 'content' => 'Parent', 'parent_id' => null]);

        $reply = $this->repository->addComment($this->page->id, $this->user->id, 'Reply text', parentId: $parent->id);

        $this->assertEquals($parent->id, $reply->parent_id);
    }

    public function test_add_comment_stores_position(): void
    {
        $comment = $this->repository->addComment($this->page->id, $this->user->id, 'Inline note', position: '{"line":5}');

        $this->assertEquals('{"line":5}', $comment->position);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ArticleCommentRepository();
        $this->user = $this->createUser();
        $this->user2 = $this->createUser();
        $this->page = $this->createPage([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
    }
}
