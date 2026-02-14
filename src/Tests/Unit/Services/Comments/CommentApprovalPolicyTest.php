<?php

namespace App\Tests\Unit\Services\Comments;

use App\DTO\Comments\CreateCommentDTO;
use App\Enums\CommentStatus;
use App\Repositories\Members\CommentRepository;
use App\Services\Members\Comments\CommentApprovalPolicy;
use Mockery;
use PHPUnit\Framework\TestCase;

class CommentApprovalPolicyTest extends TestCase
{
    private $commentRepository;
    private CommentApprovalPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commentRepository = Mockery::mock(CommentRepository::class);
        $this->policy = new CommentApprovalPolicy($this->commentRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAutoApprovesAuthenticatedMembers()
    {
        $dto = new CreateCommentDTO(
            content: 'Great article!',
            pageId: 1,
            memberId: 123,
            name: 'John Doe',
            email: 'john@test.com',
            parentId: null,
            siteId: 1
        );

        $status = $this->policy->determineStatus($dto);

        $this->assertEquals(CommentStatus::APPROVED, $status);
    }

    public function testAutoApprovesTrustedUsers()
    {
        $dto = new CreateCommentDTO(
            content: 'Great article!',
            pageId: 1,
            memberId: null,
            name: 'Trusted User',
            email: 'trusted@test.com',
            parentId: null,
            siteId: 1
        );

        $this->commentRepository->shouldReceive('countApprovedCommentsByEmail')
            ->once()
            ->with('trusted@test.com')
            ->andReturn(3);

        $status = $this->policy->determineStatus($dto);

        $this->assertEquals(CommentStatus::APPROVED, $status);
    }

    public function testPendsNewUsers()
    {
        $dto = new CreateCommentDTO(
            content: 'First comment!',
            pageId: 1,
            memberId: null,
            name: 'New User',
            email: 'new@test.com',
            parentId: null,
            siteId: 1
        );

        $this->commentRepository->shouldReceive('countApprovedCommentsByEmail')
            ->once()
            ->with('new@test.com')
            ->andReturn(0);

        $status = $this->policy->determineStatus($dto);

        $this->assertEquals(CommentStatus::PENDING, $status);
    }

    public function testAutoApprovesWithExactlyOneApprovedComment()
    {
        $dto = new CreateCommentDTO(
            content: 'Second comment',
            pageId: 1,
            memberId: null,
            name: 'User',
            email: 'user@test.com',
            parentId: null,
            siteId: 1
        );

        $this->commentRepository->shouldReceive('countApprovedCommentsByEmail')
            ->once()
            ->with('user@test.com')
            ->andReturn(1);

        $status = $this->policy->determineStatus($dto);

        $this->assertEquals(CommentStatus::APPROVED, $status);
    }
}