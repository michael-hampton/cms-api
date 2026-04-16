<?php
namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\DTO\Comments\CreateCommentDTO;
use App\Events\ActivityTracking;
use App\Events\Members\CommentPostedByMember;
use App\Exceptions\Comments\InvalidCommentStatusException;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Requests\CreateCommentRequest;
use App\Services\Members\Comments\CommentService;

class CommentController extends Controller
{
    public function __construct(
        private readonly CommentService $commentService,
        private readonly ActivityTracking $activityTracking
    ) {
        parent::__construct();
    }

    public function store(CreateCommentRequest $request)
    {
        try {
            $validated = $request->validated();

            $dto = CreateCommentDTO::fromArray([
                ...$validated,
                'site_id' => SiteContext::getId(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            $comment = $this->commentService->createComment($dto);

            $this->activityTracking->trackComment($comment);

            if ($comment->member_id) {
                event(new CommentPostedByMember($comment->member_id, SiteContext::getId(), $request->get('page_id')));
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => $this->getStatusMessage($comment->status),
                'comment' => [
                    'id' => $comment->id,
                    'name' => $comment->name,
                    'email' => $comment->email,
                    'member_id' => $comment->member_id,
                    'content' => $comment->content,
                    'created_at' => $comment->created_at,
                    'status' => $comment->status
                ],
                'status' => $comment->status
            ]);

        } catch (ValidationException|\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->getErrors());
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to post comment. Please try again.'
            ], 500);
        }
    }

    public function moderate(int $commentId, Request $request)
    {
        try {
            $status = $request->get('status');

            if (!$status) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Status is required'
                ], 422);
            }

            $success = $this->commentService->moderateComment($commentId, $status);

            if ($success) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Comment status updated successfully'
                ]);
            }

            return $this->jsonResponse([
                'success' => false,
                'message' => 'Comment not found'
            ], 404);

        } catch (InvalidCommentStatusException $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update comment status'
            ], 500);
        }
    }

    public function index(int $pageId)
    {
        try {
            $comments = $this->commentService->getCommentsForPage($pageId);

            $stats = $this->commentService->getCommentStats($pageId);

            return $this->resourceResponse([
                'success' => true,
                'comments' => $comments->toArray(),
                'stats' => $stats->toArray()
            ]);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to fetch comments'
            ], 500);
        }
    }

    public function destroy(int $commentId)
    {
        try {
            $success = $this->commentService->deleteComment($commentId);

            if ($success) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Comment deleted successfully'
                ]);
            }

            return $this->jsonResponse([
                'success' => false,
                'message' => 'Comment not found'
            ], 404);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to delete comment'
            ], 500);
        }
    }

    private function getStatusMessage(string $status): string
    {
        return match($status) {
            'approved' => 'Comment posted successfully!',
            'pending' => 'Your comment is awaiting moderation.',
            'spam' => 'Your comment was flagged as spam.',
            default => 'Comment submitted.'
        };
    }
}