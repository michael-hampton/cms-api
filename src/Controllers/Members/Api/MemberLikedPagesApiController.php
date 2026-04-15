<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\PageLikeRepository;

class MemberLikedPagesApiController extends Controller
{
    public function __construct(
        private readonly PageLikeRepository $pageLikeRepository
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/member/liked-pages
     * Returns all liked pages and the total like count for the member.
     */
    public function index(): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $siteId = SiteContext::getId();

        $likedPages = $this->pageLikeRepository->getMemberLikedPages($member->id, $siteId);
        $totalLikes = $this->pageLikeRepository->getMemberLikeCount($member->id, $siteId);

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'liked_pages' => $likedPages->map(function ($likedPage) {
                    return array_merge(
                        $likedPage->toArray(),
                        [
                            'created_at' => $likedPage->created_at?->format('Y-m-d H:i:s'),
                            'liked_at' => $likedPage->liked_at?->format('Y-m-d H:i:s'),
                        ]
                    );
                }),
                'total_likes' => $totalLikes,
            ],
        ]);
    }
}