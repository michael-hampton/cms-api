<?php

namespace App\Controllers\Api\V1;

use App\Controllers\Controller;
use App\DTO\Comments\CreateCommentDTO;
use App\Events\ActivityTracking;
use App\Events\Members\CommentPostedByMember;
use App\Events\Members\PageLikedByMember;
use App\Events\Members\PageUnlikedByMember;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\PageLikeRepository;
use App\Repositories\Members\PageViewRepository;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\Members\Comments\CommentService;
use App\Services\PublicContent\PublicContentViewerStateService;

final class PublicContentViewerController extends Controller
{
    public function __construct(
        private readonly PublicContentViewerStateService $viewerState,
        private readonly PublicContentPageRepository $pages,
        private readonly PageLikeRepository $likes,
        private readonly PageViewRepository $views,
        private readonly CommentService $comments,
        private readonly ActivityTracking $activityTracking,
    ) {
        parent::__construct();
    }

    public function show(int $pageId): JsonResponse
    {
        $page = $this->findPage($pageId);
        if (!$page) {
            return $this->errorResponse('Content not found.', 404);
        }

        return $this->resourceResponse([
            'data' => $this->viewerState->for(
                $page,
                SiteContext::getId(),
                SiteContext::slug(),
                MemberAuth::check() ? MemberAuth::getMember() : null,
            ),
        ]);
    }

    public function like(int $pageId): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->errorResponse('Authentication required.', 401);
        }

        $page = $this->findPage($pageId);
        if (!$page) {
            return $this->errorResponse('Content not found.', 404);
        }

        $member = MemberAuth::member();
        $siteId = SiteContext::getId();

        if (!$this->likes->isLikedBy($pageId, $member->id, $siteId)) {
            $result = $this->likes->toggleLike($pageId, $member->id, $siteId);
            if (($result['liked'] ?? false) === true) {
                $this->activityTracking->trackLike($result['like']);
                event(new PageLikedByMember($member->id, $pageId, $siteId));
            }
        }

        return $this->show($pageId);
    }

    public function unlike(int $pageId): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->errorResponse('Authentication required.', 401);
        }

        if (!$this->findPage($pageId)) {
            return $this->errorResponse('Content not found.', 404);
        }

        $member = MemberAuth::member();
        $siteId = SiteContext::getId();

        if ($this->likes->isLikedBy($pageId, $member->id, $siteId)) {
            $this->likes->toggleLike($pageId, $member->id, $siteId);
            event(new PageUnlikedByMember($member->id, $siteId, $pageId));
        }

        return $this->show($pageId);
    }

    public function recordView(int $pageId, Request $request): JsonResponse
    {
        if (!$this->findPage($pageId)) {
            return $this->errorResponse('Content not found.', 404);
        }

        $member = MemberAuth::check() ? MemberAuth::member() : null;
        $this->views->recordView(
            $pageId,
            $member?->id,
            SiteContext::getId(),
            $request->ip(),
            $request->userAgent(),
            $request->header('Referer'),
        );

        return $this->resourceResponse(['data' => ['recorded' => true]], 201);
    }

    public function comments(int $pageId): JsonResponse
    {
        if (!$this->findPage($pageId)) {
            return $this->errorResponse('Content not found.', 404);
        }

        return $this->resourceResponse([
            'data' => [
                'comments' => $this->comments->getCommentsForPage($pageId)->toArray(),
                'stats' => $this->comments->getCommentStats($pageId)->toArray(),
            ],
        ]);
    }

    public function storeComment(int $pageId, Request $request): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->errorResponse('Authentication required.', 401);
        }

        if (!$this->findPage($pageId)) {
            return $this->errorResponse('Content not found.', 404);
        }

        $member = MemberAuth::getMember();
        if (!$member) {
            return $this->errorResponse('Authenticated member not found.', 401);
        }

        $content = trim((string)$request->get('content'));
        if ($content === '') {
            return $this->errorResponse('Comment content is required.', 422);
        }

        $name = $member->display_name
            ?: trim((string)$member->first_name . ' ' . (string)$member->last_name)
            ?: 'Member';

        $comment = $this->comments->createComment(CreateCommentDTO::fromArray([
            'page_id' => $pageId,
            'member_id' => $member->id,
            'name' => $name,
            'email' => $member->email ?? '',
            'content' => $content,
            'site_id' => SiteContext::getId(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]));

        $this->activityTracking->trackComment($comment);
        event(new CommentPostedByMember($member->id, SiteContext::getId(), $pageId));

        return $this->resourceResponse([
            'data' => [
                'id' => $comment->id,
                'name' => $comment->name,
                'content' => $comment->content,
                'status' => $comment->status,
                'created_at' => $comment->created_at,
            ],
        ], 201);
    }

    private function findPage(int $pageId)
    {
        return $this->pages->findPublishedById($pageId, SiteContext::getId());
    }
}
