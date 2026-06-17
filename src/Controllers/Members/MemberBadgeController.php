<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\MemberBadge;

class MemberBadgeController extends Controller
{
    public function getNewBadges(): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $memberBadge = $this->latestUnviewedBadgeForCurrentSite();

        if (!$memberBadge?->badge) {
            return $this->resourceResponse(['success' => true, 'badge' => null]);
        }

        return $this->resourceResponse([
            'success' => true,
            'badge' => [
                'member_badge_id' => (int) $memberBadge->id,
                'id' => (int) $memberBadge->badge->id,
                'name' => (string) $memberBadge->badge->name,
                'description' => (string) ($memberBadge->badge->description ?? ''),
                'icon' => $memberBadge->badge->icon ?? '🏆',
                'points' => (int) $memberBadge->badge->points,
            ],
        ]);
    }

    public function markBadgeShown(Request $request): JsonResponse
    {
        return $this->markAsShown($request);
    }

    public function markAsShown(Request $request): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false], 401);
        }

        $memberBadgeId = (int) $request->input('member_badge_id');
        if ($memberBadgeId <= 0) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'A member badge ID is required.',
            ], 422);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();
        $selectedBadge = MemberBadge::where('id', $memberBadgeId)
            ->where('member_id', (int) $member->id)
            ->with(['badge'])
            ->first();

        if (!$selectedBadge?->badge || (int) $selectedBadge->badge->site_id !== $siteId) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Badge not found.',
            ], 404);
        }

        $viewedAt = now_datetime();
        $unviewedBadges = MemberBadge::where('member_id', (int) $member->id)
            ->whereNull('modal_viewed_at')
            ->with(['badge'])
            ->get();

        foreach ($unviewedBadges as $memberBadge) {
            if (
                $memberBadge->badge
                && (int) $memberBadge->badge->site_id === $siteId
                && $memberBadge->earned_at <= $selectedBadge->earned_at
            ) {
                $memberBadge->modal_viewed_at = $viewedAt;
                $memberBadge->save();
            }
        }

        unset(
            $_SESSION['show_badge_modal'],
            $_SESSION['new_badge_data'],
            $_SESSION['badge_modal_ever_shown']
        );

        return $this->jsonResponse(['success' => true]);
    }

    private function latestUnviewedBadgeForCurrentSite(): ?MemberBadge
    {
        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();
        $memberBadges = MemberBadge::where('member_id', (int) $member->id)
            ->whereNull('modal_viewed_at')
            ->with(['badge'])
            ->orderByDesc('earned_at')
            ->get();

        foreach ($memberBadges as $memberBadge) {
            if ($memberBadge->badge && (int) $memberBadge->badge->site_id === $siteId) {
                return $memberBadge;
            }
        }

        return null;
    }
}
