<?php

namespace App\Services\Members\Comments;

use App\DTO\Comments\CreateCommentDTO;
use App\Enums\CommentStatus;
use App\Repositories\Members\CommentRepository;

class CommentApprovalPolicy
{
    public function __construct(
        private readonly CommentRepository $commentRepository
    )
    {
    }

    public function determineStatus(CreateCommentDTO $dto): CommentStatus
    {
        // Auto-approve authenticated members
        if ($dto->memberId !== null) {
            return CommentStatus::APPROVED;
        }

        // Auto-approve trusted users (those with previously approved comments)
        if ($dto->email && $this->hasPreviousApprovedComments($dto->email)) {
            return CommentStatus::APPROVED;
        }

        return CommentStatus::PENDING;
    }

    private function hasPreviousApprovedComments(string $email): bool
    {
        return $this->commentRepository->countApprovedCommentsByEmail($email) >= 1;
    }
}