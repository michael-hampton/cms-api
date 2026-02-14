<?php

namespace App\Tests\Unit\Services\Comments;

use App\DTO\Comments\CreateCommentDTO;
use App\Enums\CommentStatus;
use App\Exceptions\Comments\InvalidCommentStatusException;
use App\Models\Comment;
use App\Models\Member;
use App\Repositories\Members\CommentRepository;
use App\Repositories\Members\MemberRepository;
use App\Services\Members\Comments\CommentApprovalPolicy;
use App\Services\Members\Comments\CommentSanitizer;
use App\Services\Members\Comments\CommentService;
use App\Services\Members\Comments\Contracts\SpamDetectionInterface;
use App\Services\NotificationService;
use Mockery;
use PHPUnit\Framework\TestCase;

class CommentServiceTest extends TestCase
{
    private $commentRepository;
    private $notificationService;
    private $memberRepository;
    private $spamDetector;
    private $approvalPolicy;
    private $sanitizer;
    private CommentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commentRepository = Mockery::mock(CommentRepository::class);
        $this->notificationService = Mockery::mock(NotificationService::class);
        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->spamDetector = Mockery::mock(SpamDetectionInterface::class);
        $this->approvalPolicy = Mockery::mock(CommentApprovalPolicy::class);
        $this->sanitizer = Mockery::mock(CommentSanitizer::class);

        $this->service = new CommentService(
            $this->commentRepository,
            $this->notificationService,
            $this->memberRepository,
            $this->spamDetector,
            $this->approvalPolicy,
            $this->sanitizer
        );
    }

    public function testCreateCommentWithAutoApproval()
    {
        $dto = new CreateCommentDTO(
            content: 'Great article!',
            pageId: 1,
            memberId: null,
            name: 'John Doe',
            email: 'john@example.com',
            parentId: null,
            siteId: 1
        );

        $comment = Mockery::mock(Comment::class)->makePartial();
        $comment->id = 1;
        $comment->status = 'approved';

        $this->sanitizer->shouldReceive('sanitize')
            ->once()
            ->with('Great article!')
            ->andReturn('Great article!');

        $this->spamDetector->shouldReceive('isSpam')
            ->once()
            ->andReturn(false);

        $this->approvalPolicy->shouldReceive('determineStatus')
            ->once()
            ->andReturn(CommentStatus::APPROVED);

        $this->commentRepository->shouldReceive('createComment')
            ->once()
            ->andReturn($comment);

        $this->notificationService->shouldReceive('notifyNewComment')
            ->once()
            ->with($comment);

        $result = $this->service->createComment($dto);

        $this->assertInstanceOf(Comment::class, $result);
        $this->assertEquals('approved', $result->status);
    }

    public function testCreateCommentAsAuthenticatedMember()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 5;
        $member->first_name = 'Test';
        $member->last_name = 'Member';
        $member->email = 'member@example.com';

        $dto = new CreateCommentDTO(
            content: 'Great article!',
            pageId: 1,
            memberId: 5,
            name: null,
            email: null,
            parentId: null,
            siteId: 1
        );

        $comment = Mockery::mock(Comment::class)->makePartial();
        $comment->id = 10;
        $comment->status = 'approved';
        $comment->member_id = 5;

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(5)
            ->andReturn($member);

        $this->sanitizer->shouldReceive('sanitize')
            ->once()
            ->andReturn('Great article!');

        $this->spamDetector->shouldReceive('isSpam')
            ->once()
            ->andReturn(false);

        $this->approvalPolicy->shouldReceive('determineStatus')
            ->once()
            ->andReturn(CommentStatus::APPROVED);

        $this->commentRepository->shouldReceive('createComment')
            ->once()
            ->andReturn($comment);

        $this->notificationService->shouldReceive('notifyNewComment')
            ->once();

        $result = $this->service->createComment($dto);

        $this->assertEquals('approved', $result->status);
        $this->assertEquals(5, $result->member_id);
    }

    public function testCreateCommentWithPendingStatus()
    {
        $dto = new CreateCommentDTO(
            content: 'Nice post!',
            pageId: 1,
            memberId: null,
            name: 'Jane Smith',
            email: 'jane@example.com',
            parentId: null,
            siteId: 1
        );

        $comment = Mockery::mock(Comment::class)->makePartial();
        $comment->id = 2;
        $comment->status = 'pending';

        $this->sanitizer->shouldReceive('sanitize')
            ->once()
            ->andReturn('Nice post!');

        $this->spamDetector->shouldReceive('isSpam')
            ->once()
            ->andReturn(false);

        $this->approvalPolicy->shouldReceive('determineStatus')
            ->once()
            ->andReturn(CommentStatus::PENDING);

        $this->commentRepository->shouldReceive('createComment')
            ->once()
            ->andReturn($comment);

        $this->notificationService->shouldNotReceive('notifyNewComment');

        $result = $this->service->createComment($dto);

        $this->assertEquals('pending', $result->status);
    }


    public function testCreateCommentDetectsSpam()
    {
        $dto = new CreateCommentDTO(
            content: 'Buy cheap viagra now!',
            pageId: 1,
            memberId: null,
            name: 'Spammer',
            email: 'spam@example.com',
            parentId: null,
            siteId: 1
        );

        $comment = Mockery::mock(Comment::class)->makePartial();
        $comment->id = 3;
        $comment->status = 'spam';

        $this->sanitizer->shouldReceive('sanitize')
            ->once()
            ->andReturn('Buy cheap viagra now!');

        $this->spamDetector->shouldReceive('isSpam')
            ->once()
            ->andReturn(true);

        $this->commentRepository->shouldReceive('createComment')
            ->once()
            ->andReturn($comment);

        $this->notificationService->shouldNotReceive('notifyNewComment');

        $result = $this->service->createComment($dto);

        $this->assertEquals('spam', $result->status);
    }


    public function testModerateCommentApproves()
    {
        $commentId = 1;
        $comment = Mockery::mock(Comment::class)->makePartial();
        $comment->id = $commentId;
        $comment->status = 'approved';

        $this->commentRepository->shouldReceive('updateStatus')
            ->once()
            ->with($commentId, CommentStatus::APPROVED)
            ->andReturn(true);

        $this->commentRepository->shouldReceive('findById')
            ->once()
            ->with($commentId)
            ->andReturn($comment);

        $this->notificationService->shouldReceive('notifyNewComment')
            ->once()
            ->with($comment);

        $result = $this->service->moderateComment($commentId, 'approved');

        $this->assertTrue($result);
    }

    public function testModerateCommentRejects()
    {
        $commentId = 1;

        $this->commentRepository->shouldReceive('updateStatus')
            ->once()
            ->with($commentId, CommentStatus::REJECTED)
            ->andReturn(true);

        $this->notificationService->shouldNotReceive('notifyNewComment');

        $result = $this->service->moderateComment($commentId, 'rejected');

        $this->assertTrue($result);
    }

    public function testModerateCommentThrowsExceptionForInvalidStatus()
    {
        $this->expectException(InvalidCommentStatusException::class);

        $this->service->moderateComment(1, 'invalid');
    }

    public function testSanitizeContentRemovesScripts()
    {
        $dto = new CreateCommentDTO(
            content: 'Hello <script>alert("xss")</script> world!',
            pageId: 1,
            memberId: null,
            name: 'Test User',
            email: 'test@example.com',
            parentId: null,
            siteId: 1
        );

        $comment = Mockery::mock(Comment::class)->makePartial();

        $this->sanitizer->shouldReceive('sanitize')
            ->once()
            ->with('Hello <script>alert("xss")</script> world!')
            ->andReturn('Hello  world!');

        $this->spamDetector->shouldReceive('isSpam')->andReturn(false);
        $this->approvalPolicy->shouldReceive('determineStatus')->andReturn(CommentStatus::PENDING);
        $this->commentRepository->shouldReceive('createComment')->andReturn($comment);

        $this->service->createComment($dto);
        $this->assertTrue(true);
    }


    public function testGetCommentStats()
    {
        $pageId = 1;

        $this->commentRepository->shouldReceive('countCommentsByPage')
            ->once()
            ->with($pageId)
            ->andReturn(10);

        $this->commentRepository->shouldReceive('countCommentsByPage')
            ->once()
            ->with($pageId, 'approved')
            ->andReturn(7);

        $this->commentRepository->shouldReceive('countCommentsByPage')
            ->once()
            ->with($pageId, 'pending')
            ->andReturn(2);

        $this->commentRepository->shouldReceive('countCommentsByPage')
            ->once()
            ->with($pageId, 'spam')
            ->andReturn(1);

        $stats = $this->service->getCommentStats($pageId);

        $this->assertEquals(10, $stats->total);
        $this->assertEquals(7, $stats->approved);
        $this->assertEquals(2, $stats->pending);
        $this->assertEquals(1, $stats->spam);
    }

    public function testDeleteComment()
    {
        $commentId = 1;

        $this->commentRepository->shouldReceive('deleteComment')
            ->once()
            ->with($commentId)
            ->andReturn(true);

        $result = $this->service->deleteComment($commentId);

        $this->assertTrue($result);
    }

    private function createMockComment(int $id, string $status, ?int $memberId = null): Comment
    {
        $comment = Mockery::mock(Comment::class)->makePartial();
        $comment->id = $id;
        $comment->status = $status;
        $comment->member_id = $memberId;

        $comment->shouldReceive('isApproved')->andReturn($status === 'approved');

        return $comment;
    }

    public function testGetCommentsForPage()
    {
        $pageId = 1;
        $comments = collect([]);

        $this->commentRepository->shouldReceive('getCommentsForPage')
            ->once()
            ->with($pageId, true)
            ->andReturn($comments);

        $result = $this->service->getCommentsForPage($pageId);

        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $result);
    }

    public function testGetCommentsForPageIncludingUnapproved()
    {
        $pageId = 1;
        $comments = collect([]);

        $this->commentRepository->shouldReceive('getCommentsForPage')
            ->once()
            ->with($pageId, false)
            ->andReturn($comments);

        $result = $this->service->getCommentsForPage($pageId, false);

        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $result);
    }

    public function testCreateCommentHydratesMemberInfoWhenNotFound()
    {
        $dto = new CreateCommentDTO(
            content: 'Test',
            pageId: 1,
            memberId: 999,
            name: null,
            email: null,
            parentId: null,
            siteId: 1
        );

        $comment = Mockery::mock(Comment::class)->makePartial();

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->sanitizer->shouldReceive('sanitize')->andReturn('Test');
        $this->spamDetector->shouldReceive('isSpam')->andReturn(false);
        $this->approvalPolicy->shouldReceive('determineStatus')->andReturn(CommentStatus::PENDING);
        $this->commentRepository->shouldReceive('createComment')->andReturn($comment);

        $this->service->createComment($dto);
        $this->assertTrue(true);
    }

    private function createMockMember(int $id, string $name, string $email): Member
    {
        $nameParts = explode(' ', $name);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = $id;
        $member->first_name = $firstName;
        $member->last_name = $lastName;
        $member->email = $email;

        return $member;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}