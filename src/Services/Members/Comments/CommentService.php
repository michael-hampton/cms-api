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
        private readonly CommentRepository $commentRepository,
        private readonly NotificationService $notificationService,
        private readonly MemberRepository $memberRepository,
        private readonly SpamDetectionInterface $spamDetector,
        private readonly CommentApprovalPolicy $approvalPolicy,
        private readonly CommentSanitizer $sanitizer
    ) {}

    public function createComment(CreateCommentDTO $dto): Comment
    {
        if ($dto->memberId !== null) {
            $dto = $this->hydrateMemberInfo($dto);
        }

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

        $status = $this->spamDetector->isSpam($dto)
            ? CommentStatus::SPAM
            : $this->approvalPolicy->determineStatus($dto);

        $comment = $this->commentRepository->createComment($dto, $status);

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

    public function getPublicThread(int $pageId, int $page, int $perPage): array
    {
        $count = $this->commentRepository->countCommentsByPage($pageId, CommentStatus::APPROVED->value);
        $lastPage = max(1, (int) ceil($count / $perPage));
        $page = min($page, $lastPage);

        return [
            'count' => $count,
            'thread' => $this->commentRepository
                ->getPaginatedApprovedCommentsForPage($pageId, $page, $perPage)
                ->toArray(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'has_previous' => $page > 1,
                'has_next' => $page < $lastPage,
            ],
        ];
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
