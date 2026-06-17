<?php

namespace App\Controllers\Api\V1;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Services\PublicContent\Badges\PublicContentBadgeModalService;

final class PublicContentBadgeModalController extends Controller
{
    public function __construct(
        private readonly PublicContentBadgeModalService $badgeModals,
    ) {
        parent::__construct();
    }

    public function markViewed(string $memberBadgeId): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $memberBadgeId = (int) $memberBadgeId;
        if ($memberBadgeId <= 0) {
            return $this->errorResponse('Invalid member badge ID.', 422);
        }

        $member = MemberAuth::getMember();
        $updated = $this->badgeModals->markViewed(
            $memberBadgeId,
            (int) $member->id,
            SiteContext::getId(),
        );

        if (!$updated) {
            return $this->errorResponse('Badge not found.', 404);
        }

        return $this->resourceResponse([
            'data' => ['viewed' => true],
        ]);
    }
}
