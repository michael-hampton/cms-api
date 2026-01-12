<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;

class MemberBadgeController extends Controller
{
    public function getNewBadges(): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();

        // Check session for unshown badges
        $newBadgeId = $_SESSION['new_badge_earned'] ?? null;

        if (!$newBadgeId) {
            return $this->resourceResponse(['success' => true, 'badge' => null]);
        }

        $badge = \App\Models\MemberBadge::where('id', $newBadgeId)
            ->where('member_id', $member->id)
            ->with(['badge'])
            ->first();

        if (!$badge || !$badge->badge) {
            unset($_SESSION['new_badge_earned']);
            return $this->resourceResponse(['success' => true, 'badge' => null]);
        }

        return $this->resourceResponse([
            'success' => true,
            'badge' => [
                'id' => $badge->badge->id,
                'name' => $badge->badge->name,
                'description' => $badge->badge->description,
                'icon' => $badge->badge->icon,
                'points' => $badge->badge->points
            ]
        ]);
    }

    public function markBadgeShown(Request $request): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $badgeId = $request->input('badge_id');

        // Clear from session
        if (isset($_SESSION['new_badge_earned'])) {
            unset($_SESSION['new_badge_earned']);
        }

        return $this->jsonResponse(['success' => true]);
    }
}