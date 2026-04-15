<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\PageViewRepository;

class MemberReadingHistoryApiController extends Controller
{
    public function __construct(
        private readonly PageViewRepository $pageViewRepository
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/member/reading-history
     * Returns recently viewed pages and unique page read count.
     */
    public function index(): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $recentlyViewed = $this->pageViewRepository->getRecentlyViewedPages($member->id, 50);
        $totalPagesRead = $this->pageViewRepository->getUniquePagesViewedByMember($member->id, $siteId);

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'recently_viewed' => $recentlyViewed->map(function ($likedPage) {
                    return array_merge(
                        $likedPage->toArray(),
                        [
                            'viewed_at' => $likedPage->viewed_at?->format('Y-m-d H:i:s'),
                            'created_at' => $likedPage->created_at?->format('Y-m-d H:i:s'),
                            'page' => $likedPage->page ? array_merge(
                                $likedPage->page->toArray(),
                                [
                                    'created_at' => $likedPage->page->created_at?->format('Y-m-d H:i:s'),
                                ]
                            ) : null,
                        ]
                    );
                }),
                'total_pages_read' => $totalPagesRead,
            ],
        ]);
    }
}