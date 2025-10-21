<?php
// tests/Unit/Services/CommentServiceTest.php

namespace App\Tests\Unit\Services;

use App\Models\Comment;
use App\Repositories\CommentRepository;
use App\Services\CommentService;
use App\Services\NotificationService;
use Mockery;
use PHPUnit\Framework\TestCase;

class CommentServiceTest extends TestCase
{
    private $commentRepository;
    private $notificationService;
    private $commentService;

    protected function setUp(): void
    {
        $this->commentRepository = $this->createMock(CommentRepository::class);
        $this->notificationService = $this->createMock(NotificationService::class);

        $this->commentService = new CommentService(
            $this->commentRepository,
            $this->notificationService
        );
    }

    public function testCreateCommentWithAutoApproval()
    {
        $data = [
            'page_id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'content' => 'Great article!'
        ];

        $comment = $this->createMockComment(1, 'approved');

        $this->commentRepository
            ->expects($this->once())
            ->method('countApprovedCommentsByEmail')
            ->with('john@example.com')
            ->willReturn(1);

        $this->commentRepository
            ->expects($this->once())
            ->method('createComment')
            ->willReturn($comment);

        $this->notificationService
            ->expects($this->once())
            ->method('notifyNewComment')
            ->with($comment);

        $result = $this->commentService->createComment($data);

        $this->assertInstanceOf(Comment::class, $result);
        $this->assertEquals('approved', $result->status);
    }

    public function testCreateCommentWithPendingStatus()
    {
        $data = [
            'page_id' => 1,
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'content' => 'Nice post!'
        ];

        $comment = $this->createMockComment(2, 'pending');

        $this->commentRepository
            ->expects($this->once())
            ->method('countApprovedCommentsByEmail')
            ->with('jane@example.com')
            ->willReturn(0);

        $this->commentRepository
            ->expects($this->once())
            ->method('createComment')
            ->willReturn($comment);

        $this->notificationService
            ->expects($this->never())
            ->method('notifyNewComment');

        $result = $this->commentService->createComment($data);

        $this->assertEquals('pending', $result->status);
    }

    public function testCreateCommentDetectsSpam()
    {
        $data = [
            'page_id' => 1,
            'name' => 'Spammer',
            'email' => 'spam@example.com',
            'content' => 'Buy cheap viagra now! http://spam1.com http://spam2.com http://spam3.com http://spam4.com'
        ];

        $comment = $this->createMockComment(3, 'spam');

        $this->commentRepository
            ->expects($this->once())
            ->method('createComment')
            ->willReturn($comment);

        $this->notificationService
            ->expects($this->never())
            ->method('notifyNewComment');

        $result = $this->commentService->createComment($data);

        $this->assertEquals('spam', $result->status);
    }

    public function testModerateCommentApproves()
    {
        $commentId = 1;
        $comment = $this->createMockComment($commentId, 'approved');

        $this->commentRepository
            ->expects($this->once())
            ->method('moderateComment')
            ->with($commentId, 'approved')
            ->willReturn(true);

        $this->commentRepository
            ->expects($this->once())
            ->method('findById')
            ->with($commentId)
            ->willReturn($comment);

        $this->notificationService
            ->expects($this->once())
            ->method('notifyNewComment')
            ->with($comment);

        $result = $this->commentService->moderateComment($commentId, 'approved');

        $this->assertTrue($result);
    }

    public function testModerateCommentRejects()
    {
        $commentId = 1;

        $this->commentRepository
            ->expects($this->once())
            ->method('moderateComment')
            ->with($commentId, 'rejected')
            ->willReturn(true);

        $this->notificationService
            ->expects($this->never())
            ->method('notifyNewComment');

        $result = $this->commentService->moderateComment($commentId, 'rejected');

        $this->assertTrue($result);
    }

    public function testModerateCommentThrowsExceptionForInvalidStatus()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid comment status');

        $this->commentService->moderateComment(1, 'invalid');
    }

    public function testSanitizeContentRemovesScripts()
    {
        $data = [
            'page_id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'content' => 'Hello <script>alert("xss")</script> world!'
        ];

        $this->commentRepository
            ->expects($this->once())
            ->method('countApprovedCommentsByEmail')
            ->willReturn(0);

        $this->commentRepository
            ->expects($this->once())
            ->method('createComment')
            ->with($this->callback(function($arg) {
                return !str_contains($arg['content'], '<script>');
            }))
            ->willReturn($this->createMockComment(1, 'pending'));

        $this->commentService->createComment($data);
    }

    public function testGetCommentStats()
    {
        $pageId = 1;

        $this->commentRepository
            ->expects($this->exactly(4))
            ->method('countCommentsByPage')
            ->willReturnCallback(function($pageId, $status = null) {
                return match($status) {
                    null => 10,
                    'approved' => 7,
                    'pending' => 2,
                    'spam' => 1,
                };
            });

        $stats = $this->commentService->getCommentStats($pageId);

        $this->assertEquals(10, $stats['total']);
        $this->assertEquals(7, $stats['approved']);
        $this->assertEquals(2, $stats['pending']);
        $this->assertEquals(1, $stats['spam']);
    }

    public function testDeleteComment()
    {
        $commentId = 1;

        $this->commentRepository
            ->expects($this->once())
            ->method('deleteComment')
            ->with($commentId)
            ->willReturn(true);

        $result = $this->commentService->deleteComment($commentId);

        $this->assertTrue($result);
    }

    private function createMockComment(int $id, string $status): Comment
    {
        $comment = Mockery::mock(Comment::class)->makePartial();
        $comment->id = $id;
        $comment->status = $status;

        $comment->shouldReceive('isApproved')->andReturn($status === 'approved');

        return $comment;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}