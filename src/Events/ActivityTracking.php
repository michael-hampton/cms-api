<?php

namespace App\Events;

use App\Framework\Authorization\MemberAuth;
use App\Models\Member;
use App\Services\BadgeService;

class ActivityTracking
{
    private BadgeService $badgeService;

    public function __construct(BadgeService $badgeService)
    {
        $this->badgeService = $badgeService;
    }

    public function trackComment($comment)
    {
        if (!MemberAuth::check()) {
            return;
        }

        $member = MemberAuth::getMember();

        $this->badgeService->trackActivity(
            $member,
            'comment',
            'page',
            $comment->page_id,
            ['comment_id' => $comment->id],
            10 // 10 points for commenting
        );
    }

    public function trackPageView($page)
    {
        if (!MemberAuth::check()) {
            return;
        }

        $member = MemberAuth::member();

        // Only track once per page per day
        $today = now_datetime()->startOfDay();
        $exists = \App\Models\MemberActivity::where('member_id', $member->id)
            ->where('activity_type', 'read')
            ->where('entity_type', 'page')
            ->where('entity_id', $page->id)
            ->where('activity_date', '>=', $today->toDateTimeString())
            ->exists();

        if (!$exists) {
            $this->badgeService->trackActivity(
                MemberAuth::getMember(),
                'read',
                'page',
                $page->id,
                ['page_slug' => $page->slug],
                5 // 5 points for reading
            );
        }
    }

    public function trackLike($like)
    {
        if (!MemberAuth::check()) {
            return;
        }

        $member = MemberAuth::getMember();

        $this->badgeService->trackActivity(
            $member,
            'like',
            'page',
            $like->page_id,
            ['like_id' => $like->id],
            3 // 3 points for liking
        );
    }

    public function trackOrder($order)
    {
        $member = Member::find($order->user_id);
        if (!$member) {
            return;
        }

        $this->badgeService->trackActivity(
            $member,
            'purchase',
            'order',
            $order->id,
            ['order_total' => $order->total],
            (int)($order->total) // 1 point per currency unit spent
        );
    }
}