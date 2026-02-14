<?php

namespace App\Services\Members\Comments;

use App\DTO\Comments\CommentStatsDTO;
use App\DTO\Comments\CreateCommentDTO;
use App\Enums\CommentStatus;
use App\Exceptions\Comments\InvalidCommentStatusException;
use App\Framework\Support\Collection;
use App\Models\Comment;
use App\Repositories\Members\CommentRepository;
use App\Repositories\Members\MemberRepository;
use App\Services\Members\Comments\Contracts\SpamDetectionInterface;
use App\Services\NotificationService;

class CommentService
{
    public function __construct(
        private readonly CommentRepository      $commentRepository,
        private readonly NotificationService $notificationService,
        private readonly MemberRepository       $memberRepository,
        private readonly SpamDetectionInterface $spamDetector,
        private readonly CommentApprovalPolicy  $approvalPolicy,
        private readonly CommentSanitizer       $sanitizer
    ) {}

    public function createComment(CreateCommentDTO $dto): Comment
    {
        // Hydrate member information if authenticated
        if ($dto->memberId !== null) {
            $dto = $this->hydrateMemberInfo($dto);
        }

        // Sanitize content
        $sanitizedContent = $this->sanitizer->sanitize($dto->content);
        $dto = new CreateCommentDTO(
            content: $sanitizedContent,
            pageId: $dto->pageId,
            memberId: $dto->memberId,
            name: $dto->name,
            email: $dto->email,
            parentId: $dto->parentId,
            siteId: $dto->siteId,
            ipAddress: $dto->ipAddress,
            userAgent: $dto->userAgent
        );

        // Determine status: check spam first, then approval policy
        $status = $this->spamDetector->isSpam($dto)
            ? CommentStatus::SPAM
            : $this->approvalPolicy->determineStatus($dto);

        // Create comment
        $comment = $this->commentRepository->createComment($dto, $status);

        // Send notification if approved
        if ($status === CommentStatus::APPROVED) {
            $this->notificationService->notifyNewComment($comment);
        }

        return $comment;
    }

    public function moderateComment(int $commentId, string $statusString): bool
    {
        try {
            $status = CommentStatus::fromString($statusString);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidCommentStatusException($statusString);
        }

        $success = $this->commentRepository->updateStatus($commentId, $status);

        // Send notification if newly approved
        if ($success && $status === CommentStatus::APPROVED) {
            $comment = $this->commentRepository->findById($commentId);
            if ($comment) {
                $this->notificationService->notifyNewComment($comment);
            }
        }

        return $success;
    }

    public function getCommentsForPage(int $pageId, bool $onlyApproved = true): Collection
    {
        return $this->commentRepository->getCommentsForPage($pageId, $onlyApproved);
    }

    public function deleteComment(int $commentId): bool
    {
        return $this->commentRepository->deleteComment($commentId);
    }

    public function getCommentStats(int $pageId): CommentStatsDTO
    {
        return new CommentStatsDTO(
            total: $this->commentRepository->countCommentsByPage($pageId),
            approved: $this->commentRepository->countCommentsByPage($pageId, 'approved'),
            pending: $this->commentRepository->countCommentsByPage($pageId, 'pending'),
            spam: $this->commentRepository->countCommentsByPage($pageId, 'spam')
        );
    }

    private function hydrateMemberInfo(CreateCommentDTO $dto): CreateCommentDTO
    {
        $member = $this->memberRepository->find($dto->memberId);

        if (!$member) {
            return $dto;
        }

        return new CreateCommentDTO(
            content: $dto->content,
            pageId: $dto->pageId,
            memberId: $member->id,
            name: $member->first_name . ' ' . $member->last_name,
            email: $member->email,
            parentId: $dto->parentId,
            siteId: $dto->siteId,
            ipAddress: $dto->ipAddress,
            userAgent: $dto->userAgent
        );
    }
}