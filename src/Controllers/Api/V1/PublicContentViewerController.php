<?php

namespace App\Controllers\Api\V1;

use App\Controllers\Controller;
use App\DTO\Comments\CreateCommentDTO;
use App\Events\ActivityTracking;
use App\Events\Members\CommentPostedByMember;
use App\Events\Members\PageLikedByMember;
use App\Events\Members\PageUnlikedByMember;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\PageLikeRepository;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Requests\PublicContent\CreatePublicCommentRequest;
use App\Services\Members\Comments\CommentService;
use App\Services\PublicContent\Comments\PublicCommentRateLimiter;
use App\Services\PublicContent\PublicContentViewerStateService;
use App\Services\PublicContent\Views\PublicPageViewRecorder;
use InvalidArgumentException;
use Throwable;

final class PublicContentViewerController extends Controller
{
    public function __construct(
        private readonly PublicContentViewerStateService $viewerState,
        private readonly PublicContentPageRepository $pages,
        private readonly PageLikeRepository $likes,
        private readonly CommentService $comments,
        private readonly ActivityTracking $activityTracking,
        private readonly PublicCommentRateLimiter $commentRateLimiter,
        private readonly PublicPageViewRecorder $pageViewRecorder,
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
        $result = $this->pageViewRecorder->record(
            pageId: $pageId,
            siteId: SiteContext::getId(),
            memberId: $member?->id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            referer: $request->header('Referer'),
        );

        if ($result['limited']) {
            return $this->errorResponse(
                'Too many page view requests. Please try again later.',
                429,
            )->setHeader('Retry-After', (string) $result['retry_after']);
        }

        return $this->resourceResponse([
            'data' => [
                'recorded' => $result['recorded'],
                'duplicate' => $result['duplicate'],
            ],
        ], 201);
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

    public function storeComment(int $pageId, CreatePublicCommentRequest $request): JsonResponse
    {
        try {
            if (!$this->findPage($pageId)) {
                return $this->errorResponse('Content not found.', 404);
            }

            $validated = $request->validated();
            $member = MemberAuth::check() ? MemberAuth::getMember() : null;
            $siteId = SiteContext::getId();
            $limit = $this->commentRateLimiter->consume(
                $siteId,
                $member?->id,
                $request->ip(),
            );

            if (!$limit['allowed']) {
                return $this->errorResponse(
                    'Too many comments submitted. Please try again later.',
                    429,
                )->setHeader('Retry-After', (string) $limit['retry_after']);
            }

            $name = $member
                ? ($member->display_name
                    ?: trim((string) $member->first_name . ' ' . (string) $member->last_name)
                    ?: 'Member')
                : trim((string) ($validated['name'] ?? ''));

            $comment = $this->comments->createComment(CreateCommentDTO::fromArray([
                'page_id' => $pageId,
                'parent_id' => $validated['parent_id'] ?? null,
                'member_id' => $member?->id,
                'name' => $name,
                'email' => $member?->email ?? (string) ($validated['email'] ?? ''),
                'content' => trim((string) $validated['content']),
                'site_id' => $siteId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]));

            $this->activityTracking->trackComment($comment);

            if ($comment->member_id) {
                event(new CommentPostedByMember($comment->member_id, $siteId, $pageId));
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => $this->commentStatusMessage($comment->status),
                'comment' => [
                    'id' => $comment->id,
                    'name' => $comment->name,
                    'member_id' => $comment->member_id,
                    'content' => $comment->content,
                    'created_at' => $comment->created_at,
                    'status' => $comment->status,
                ],
                'status' => $comment->status,
                'rate_limit' => [
                    'remaining' => $limit['remaining'],
                ],
            ], 201);
        } catch (ValidationException|InvalidArgumentException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                422,
                method_exists($exception, 'getErrors') ? $exception->getErrors() : [],
            );
        } catch (Throwable) {
            return $this->errorResponse('Failed to post comment. Please try again.', 500);
        }
    }

    private function commentStatusMessage(string $status): string
    {
        return match ($status) {
            'approved' => 'Comment posted successfully!',
            'pending' => 'Your comment is awaiting moderation.',
            'spam' => 'Your comment was flagged as spam.',
            default => 'Comment submitted.',
        };
    }

    private function findPage(int $pageId)
    {
        return $this->pages->findPublishedById($pageId, SiteContext::getId());
    }
}
