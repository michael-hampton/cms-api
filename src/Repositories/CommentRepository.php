<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Comment;
use App\Models\Model;

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

    public function createComment(array $data): Model
    {
        // Auto-detect spam or require moderation
        $data['status'] = $this->determineCommentStatus($data);
        $data['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
        $data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;

        return $this->create($data);
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
}