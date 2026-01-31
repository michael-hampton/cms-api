<?php

namespace App\Services\Members;

use App\Framework\Support\Collection;
use App\Models\Comment;
use App\Repositories\Members\CommentRepository;
use App\Repositories\Members\MemberRepository;
use App\Services\NotificationService;

class CommentService
{
    public function __construct(
        private readonly CommentRepository   $commentRepository,
        private readonly NotificationService $notificationService,
        private readonly MemberRepository    $memberRepository
    ) {}

    /**
     * Create a new comment
     */
    public function createComment(array $data): Comment
    {
        // If member is authenticated, get their info
        if (isset($data['member_id']) && !empty($data['member_id'])) {

            $member = $this->memberRepository->find($data['member_id']);

            if ($member) {
                $data['name'] = $member->first_name . ' ' . $member->last_name;
                $data['email'] = $member->email;
                $data['member_id'] = $member->id;
            }
        } else {
            // Ensure member_id is null if not authenticated
            $data['member_id'] = null;
        }

        // Validate and sanitize
        $data['content'] = $this->sanitizeContent($data['content']);
        $data['status'] = $this->determineInitialStatus($data);

        // Create comment
        $comment = $this->commentRepository->createComment($data);

        // Send notification if approved automatically
        if ($comment->isApproved()) {
            $this->notificationService->notifyNewComment($comment);
        }

        return $comment;
    }

    /**
     * Moderate a comment
     */
    public function moderateComment(int $commentId, string $status): bool
    {
        if (!in_array($status, ['approved', 'rejected', 'spam'])) {
            throw new \InvalidArgumentException('Invalid comment status');
        }

        $success = $this->commentRepository->moderateComment($commentId, $status);

        if ($success && $status === 'approved') {
            $comment = $this->commentRepository->findById($commentId);
            if ($comment) {
                $this->notificationService->notifyNewComment($comment);
            }
        }

        return $success;
    }

    /**
     * Get comments for a page
     */
    public function getCommentsForPage(int $pageId, bool $onlyApproved = true): Collection
    {
        return $this->commentRepository->getCommentsForPage($pageId, $onlyApproved);
    }

    /**
     * Sanitize comment content
     */
    private function sanitizeContent(string $content): string
    {
        // Remove scripts and potentially harmful HTML
        $content = strip_tags($content, '<p><br><strong><em><a>');

        // Sanitize URLs in links
        $content = preg_replace_callback(
            '/<a\s+href="([^"]+)"/',
            function($matches) {
                $url = filter_var($matches[1], FILTER_SANITIZE_URL);
                return '<a href="' . $url . '"';
            },
            $content
        );

        return trim($content);
    }

    /**
     * Determine initial comment status based on rules
     */
    private function determineInitialStatus(array $data): string
    {
        // Check for spam patterns
        if ($this->isLikelySpam($data)) {
            return 'spam';
        }

        // Auto-approve authenticated members
        if (isset($data['member_id']) && !empty($data['member_id'])) {
            return 'approved';
        }

        // Auto-approve trusted users or based on settings
        if ($this->shouldAutoApprove($data)) {
            return 'approved';
        }

        return 'pending';
    }

    /**
     * Check if comment is likely spam
     */
    private function isLikelySpam(array $data): bool
    {
        $content = strtolower($data['content']);

        // Check for spam keywords
        $spamKeywords = ['viagra', 'cialis', 'casino', 'lottery', 'prize'];
        foreach ($spamKeywords as $keyword) {
            if (str_contains($content, $keyword)) {
                return true;
            }
        }

        // Check for excessive links
        $linkCount = substr_count($content, 'http://') + substr_count($content, 'https://');
        if ($linkCount > 3) {
            return true;
        }

        return false;
    }

    /**
     * Determine if comment should be auto-approved
     */
    private function shouldAutoApprove(array $data): bool
    {
        // Check if user has previously approved comments
        if (isset($data['email'])) {
            $previousApproved = $this->commentRepository->countApprovedCommentsByEmail($data['email']);
            if ($previousApproved >= 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete a comment
     */
    public function deleteComment(int $commentId): bool
    {
        return $this->commentRepository->deleteComment($commentId);
    }

    /**
     * Reply to a comment
     */
    public function replyToComment(int $parentId, array $data): Comment
    {
        $data['parent_id'] = $parentId;
        return $this->createComment($data);
    }

    /**
     * Get comment statistics for a page
     */
    public function getCommentStats(int $pageId): array
    {
        return [
            'total' => $this->commentRepository->countCommentsByPage($pageId),
            'approved' => $this->commentRepository->countCommentsByPage($pageId, 'approved'),
            'pending' => $this->commentRepository->countCommentsByPage($pageId, 'pending'),
            'spam' => $this->commentRepository->countCommentsByPage($pageId, 'spam'),
        ];
    }
}