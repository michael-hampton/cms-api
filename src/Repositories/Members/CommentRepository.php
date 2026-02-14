<?php

namespace App\Repositories\Members;

use App\DTO\Comments\CreateCommentDTO;
use App\Enums\CommentStatus;
use App\Framework\Support\Collection;
use App\Models\Comment;
use App\Models\Model;
use App\Repositories\Repository;

class CommentRepository extends Repository
{
    protected function getModelClass(): string
    {
        return Comment::class;
    }

    public function getPageComments(int $pageId, string $status = 'approved'): Collection
    {
        return Comment::topLevel()->forPage($pageId)
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getCommentsWithReplies(int $pageId, string $status = 'approved'): array
    {
        $comments = $this->getPageComments($pageId, $status);
        $result = [];

        foreach ($comments as $comment) {
            $commentData = $comment->toArray();
            $commentData['replies'] = $this->getCommentReplies($comment->id, $status);
            $result[] = $commentData;
        }

        return $result;
    }

    private function getCommentReplies(int $parentId, string $status = 'approved'): array
    {
        return Comment::where('parent_id', $parentId)
            ->where('status', $status)
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }

    public function getRecentComments(int $limit = 10): Collection
    {
        return Comment::approved()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getPendingComments(): Collection
    {
        return Comment::pending()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getCommentsByStatus(string $status): Collection
    {
        return Comment::where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function moderateComment(int $commentId, string $status): bool
    {
        $comment = $this->find($commentId);
        if (!$comment) {
            return false;
        }

        $comment->status = $status;
        return $comment->save();
    }

    private function determineCommentStatus(array $data): string
    {
        // Simple spam detection - in production you'd want more sophisticated filtering
        $content = strtolower($data['content'] ?? '');
        $spamKeywords = ['viagra', 'casino', 'lottery', 'click here', 'free money'];

        foreach ($spamKeywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return 'spam';
            }
        }

        // Check for suspicious patterns
        if (substr_count($content, 'http') > 2) {
            return 'pending'; // Multiple links = suspicious
        }

        return 'approved'; // Auto-approve for now
    }

    public function getCommentStats(): array
    {
        return [
            'total' => Comment::count(),
            'approved' => Comment::approved()->count(),
            'pending' => Comment::pending()->count(),
            'spam' => Comment::where('status', 'spam')->count(),
            'rejected' => Comment::where('status', 'rejected')->count()
        ];
    }
    public function findById(int $id): Model
    {
        return Comment::find($id);
    }

    public function countApprovedCommentsByEmail(string $email): int
    {
        return $this->applySiteFilter(Comment::where('email', $email)
            ->where('status', 'approved'))->count();
    }

    public function countCommentsByPage(int $pageId, ?string $status = null): int
    {
        $query = Comment::where('page_id', $pageId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->count();
    }

    public function deleteComment(int $commentId): bool
    {
        $comment = Comment::find($commentId);
        return $comment ? $comment->delete() : false;
    }

    public function getCommentsForPage(int $pageId, bool $onlyApproved = true): Collection
    {
        $query = Comment::where('page_id', $pageId)
            ->orderBy('created_at', 'DESC');

        if ($onlyApproved) {
            $query->where('status', 'approved');
        }

        return $query->get();
    }

    public function createComment(CreateCommentDTO $dto, CommentStatus $status): Model
    {
        return Comment::create([
            'page_id' => $dto->pageId,
            'member_id' => $dto->memberId,
            'name' => $dto->name,
            'email' => $dto->email,
            'content' => $dto->content,
            'parent_id' => $dto->parentId,
            'site_id' => $dto->siteId,
            'status' => $status->value,
            'ip_address' => $dto->ipAddress,
            'user_agent' => $dto->userAgent,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function updateStatus(int $commentId, CommentStatus $status): bool
    {
        $comment = $this->findById($commentId);

        if (!$comment) {
            return false;
        }

        $comment->status = $status->value;
        return $comment->save();
    }
}