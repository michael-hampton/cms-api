<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Events\ActivityTracking;
use App\Events\Members\PageLikedByMember;
use App\Events\Members\PageUnlikedByMember;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Repositories\Members\PageLikeRepository;

class PageLikeController extends Controller
{
    public function __construct(
        private readonly PageLikeRepository $pageLikeRepository,
        private readonly ActivityTracking   $activityTracking
    )
    {
        parent::__construct();
    }

    public function toggle(int $pageId): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'You must be logged in to like pages'
            ], 401);
        }

        $page = Page::find($pageId);
        if (!$page) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Page not found'
            ], 404);
        }

        $member = MemberAuth::member();
        $siteId = SiteContext::getId();

        $result = $this->pageLikeRepository->toggleLike($pageId, $member->id, $siteId);

        if ($result['liked'] === true) {
            $this->activityTracking->trackLike($result['like']);
            event(new PageLikedByMember($member->id, $pageId, $siteId));
        } else {
            event(new PageUnlikedByMember($member->id, $pageId, $siteId));
        }

        return $this->resourceResponse([
            'success' => true,
            'data' => $result
        ]);
    }

    public function status(int $pageId): JsonResponse
    {
        $page = Page::find($pageId);
        if (!$page) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Page not found'
            ], 404);
        }

        $siteId = SiteContext::getId();
        $likeCount = $this->pageLikeRepository->getLikeCount($pageId);

        $isLiked = false;
        if (MemberAuth::check()) {
            $member = MemberAuth::member();
            $isLiked = $this->pageLikeRepository->isLikedBy($pageId, $member->id, $siteId);
        }

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'liked' => $isLiked,
                'like_count' => $likeCount
            ]
        ]);
    }
}