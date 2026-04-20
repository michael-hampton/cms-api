<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Events\ActivityTracking;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Repositories\Members\CommentRepository;

class MemberCommentsApiController extends Controller
{
    public function __construct(
        private readonly CommentRepository $commentRepository,
        private readonly ActivityTracking  $activityTracking
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/member/comments
     * Returns all comments for the authenticated member.
     */
    public function index(): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();

        $comments = $this->commentRepository->query()
            ->where('email', $member->email)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->resourceResponse([
            'success' => true,
            'data' => ['comments' => $comments],
        ]);
    }

    /**
     * DELETE /api/member/comments/{id}
     * Deletes a comment belonging to the authenticated member.
     */
    public function destroy(Request $request, int $commentId): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $comment = $this->commentRepository->findById($commentId);

        if (!$comment || $comment->email !== $member->email) {
            return $this->jsonResponse(['success' => false, 'message' => 'Comment not found'], 404);
        }

        if ($this->commentRepository->deleteComment($commentId)) {
            $this->activityTracking->trackComment($comment);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Comment deleted successfully',
            ]);
        }

        return $this->resourceResponse(['success' => false, 'message' => 'Failed to delete comment'], 500);
    }
}