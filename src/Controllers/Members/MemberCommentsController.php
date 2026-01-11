<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\BadgeRepository;
use App\Repositories\Members\CommentRepository;

class MemberCommentsController extends Controller
{
    public function __construct(private CommentRepository $commentRepository)
    {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::member();

        $comments = $this->commentRepository->where('email', $member->email)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->view('member/comments/index', [
            'member' => $member,
            'site' => SiteContext::get(),
            'comments' => $comments
        ]);
    }

    public function destroy(Request $request, int $commentId)
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

            $activityTracking = new \App\Events\ActivityTracking(
                new \App\Services\Members\BadgeService(new BadgeRepository())
            );
            $activityTracking->trackComment($comment);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);
        }

        return $this->jsonResponse(['success' => false, 'message' => 'Failed to delete comment'], 500);
    }
}