<?php

namespace App\Controllers;

use App\Framework\Http\Request;
use App\Repositories\CommentRepository;
use App\Requests\CreateCommentRequest;
use App\Services\NotificationService;

class CommentController extends Controller
{
    public function __construct(
        private CommentRepository $commentRepository,
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    public function store(CreateCommentRequest $request)
    {
        try {
            $comment = $this->commentRepository->createComment($request->validated());

            // Send notification if comment is approved automatically
            if ($comment->isApproved()) {
                $this->notificationService->notifyNewComment($comment);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Comment posted successfully',
                'comment' => $comment->toArray(),
                'status' => $comment->status
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to post comment. Please try again.'
            ], 400);
        }
    }

    public function moderate(int $commentId, Request $request)
    {
//        $data = $this->validateRequest([
//            'status' => 'required|in:approved,rejected,spam'
//        ]); //todo

        $success = $this->commentRepository->moderateComment($commentId, $request->get('status') );;

        if ($success) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Comment status updated successfully'
            ]);
        }

        return $this->jsonResponse([
            'success' => false,
            'message' => 'Failed to update comment status'
        ], 400);
    }
}