<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Repositories\OpenCollab\ArticleCommentRepository;

/**
 * Inline threaded comment management for contributor articles.
 *
 * Routes:
 *   GET    /api/{site}/open-collab/pages/{pageId}/comments         — list all threads
 *   POST   /api/{site}/open-collab/pages/{pageId}/comments         — add top-level comment
 *   POST   /api/{site}/open-collab/pages/{pageId}/comments/{id}/reply — reply to a comment
 *   DELETE /api/{site}/open-collab/comments/{id}                   — delete (author or admin only)
 */
class ArticleCommentController extends Controller
{
    public function __construct(
        private readonly ArticleCommentRepository $commentRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/open-collab/pages/{pageId}/comments
     */
    public function index(int $pageId): JsonResponse
    {
        $comments = $this->commentRepository->forArticle($pageId);

        $comments = $comments
            ->map(fn($c) => $this->formatComment($c))
            ->toArray();

        return $this->jsonResponse($comments);
    }

    private function formatComment(\App\Models\ArticleComment $comment): array
    {
        return [
            'id' => $comment->id,
            'article_id' => $comment->article_id,
            'user_id' => $comment->user_id,
            'user_name' => is_array($comment->user) ? $comment->user['name'] : $comment->user->name ?? 'Unknown',
            'parent_id' => $comment->parent_id,
            'position' => $comment->position,
            'content' => $comment->content,
            'created_at' => $comment->created_at,
            'replies' => $comment->relationLoaded('replies')
                ? $comment->replies->map(fn($r) => $this->formatComment($r))->toArray()
                : [],
        ];
    }

    /**
     * POST /api/{site}/open-collab/pages/{pageId}/comments
     * Body: { content: string, position?: string }
     */
    public function store(Request $request, int $pageId): JsonResponse
    {
        $userId = Auth::id();
        $content = trim($request->input('content', ''));

        if (empty($content)) {
            return $this->errorResponse('Comment content is required.', 422);
        }

        if (mb_strlen($content) > 5000) {
            return $this->errorResponse('Comment must not exceed 5000 characters.', 422);
        }

        $position = $request->input('position');

        $comment = $this->commentRepository->addComment(
            articleId: $pageId,
            userId: $userId,
            content: $content,
            parentId: null,
            position: $position,
        );

        return $this->jsonResponse(
            ['comment' => $this->formatComment($comment)],
            201
        );
    }

    /**
     * POST /api/{site}/open-collab/pages/{pageId}/comments/{id}/reply
     * Body: { content: string }
     */
    public function reply(Request $request, int $pageId, int $id): JsonResponse
    {
        $userId = Auth::id();
        $content = trim($request->input('content', ''));

        if (empty($content)) {
            return $this->errorResponse('Reply content is required.', 422);
        }

        if (mb_strlen($content) > 5000) {
            return $this->errorResponse('Reply must not exceed 5000 characters.', 422);
        }

        // Verify parent belongs to this page
        $parent = $this->commentRepository->find($id);

        if (!$parent || (int)$parent->article_id !== $pageId) {
            return $this->errorResponse('Parent comment not found.', 404);
        }

        $comment = $this->commentRepository->addComment(
            articleId: $pageId,
            userId: $userId,
            content: $content,
            parentId: $id,
        );

        return $this->jsonResponse(
            ['comment' => $this->formatComment($comment)],
            201
        );
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * DELETE /api/{site}/open-collab/comments/{id}
     * Only the comment author or an admin may delete.
     */
    public function destroy(int $id): JsonResponse
    {
        $userId = Auth::id();
        $comment = $this->commentRepository->find($id);

        if (!$comment) {
            return $this->errorResponse('Comment not found.', 404);
        }

        $user = Auth::user();
        $isAdmin = in_array($user?->role ?? '', ['admin', 'agent'], true);

        if ((int)$comment->user_id !== $userId && !$isAdmin) {
            return $this->errorResponse('You may only delete your own comments.', 403);
        }

        $this->commentRepository->delete($id);

        return $this->successResponse('Comment deleted.');
    }
}