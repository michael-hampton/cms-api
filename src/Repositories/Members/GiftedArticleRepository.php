<?php

namespace App\Repositories\Members;

use App\Framework\Support\Collection;
use App\Models\GiftedArticle;
use App\Models\MemberGiftAllowance;
use App\Repositories\Repository;

class GiftedArticleRepository extends Repository
{
    public function getOrCreateAllowance(int $memberId, int $siteId): MemberGiftAllowance
    {
        $allowance = MemberGiftAllowance::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->first();

        if (!$allowance) {
            $allowance = MemberGiftAllowance::create([
                'member_id' => $memberId,
                'site_id' => $siteId,
                'annual_gift_limit' => 10, // Default limit
                'gifts_used_this_year' => 0,
                'year_start_date' => now_datetime()->format('Y-m-d')
            ]);
        } else {
            $allowance->resetIfNewYear();
        }

        return $allowance;
    }

    public function createGift(array $data): GiftedArticle
    {
        $data['gift_token'] = bin2hex(random_bytes(32));
        $data['gifted_at'] = now_datetime()->format('Y-m-d H:i:s');
        $data['status'] = 'pending';

        // Set expiration if not provided (default 90 days)
        if (!isset($data['expires_at'])) {
            $data['expires_at'] = now_datetime()->modify('+90 days')->format('Y-m-d H:i:s');
        }

        return GiftedArticle::create($data);
    }

    public function getGiftsByMember(int $memberId, int $siteId): Collection
    {
        return GiftedArticle::where('gifted_by_member_id', $memberId)
            ->where('site_id', $siteId)
            ->with(['page', 'recipient'])
            ->orderBy('gifted_at', 'desc')
            ->get();
    }

    public function getReceivedGifts(int $memberId, int $siteId): Collection
    {
        $member = \App\Models\Member::find($memberId);

        return GiftedArticle::where('site_id', $siteId)
            ->where(function ($q) use ($memberId, $member) {
                $q->where('recipient_member_id', $memberId)
                    ->orWhere('recipient_email', $member->email);
            })
            ->with(['page', 'giftedBy'])
            ->orderBy('gifted_at', 'desc')
            ->get();
    }

    public function findByToken(string $token): ?GiftedArticle
    {
        return GiftedArticle::where('gift_token', $token)
            ->with(['page', 'giftedBy'])
            ->first();
    }

    public function claimPendingGiftsForEmail(string $email, int $memberId): int
    {
        return GiftedArticle::where('recipient_email', $email)
            ->where('status', 'pending')
            ->whereNull('recipient_member_id')
            ->update([
                'recipient_member_id' => $memberId,
                'claimed_at' => now_datetime()->format('Y-m-d H:i:s'),
                'status' => 'claimed'
            ]);
    }

    public function markExpiredGifts(int $siteId): int
    {
        return GiftedArticle::where('site_id', $siteId)
            ->where('status', 'pending')
            ->where('expires_at', '<', now_datetime())
            ->update(['status' => 'expired']);
    }

    public function findExistingGift(int $pageId, int $gifterId, string $recipientEmail): ?GiftedArticle
    {
        return GiftedArticle::where('page_id', $pageId)
            ->where('gifted_by_member_id', $gifterId)
            ->where('recipient_email', $recipientEmail)
            ->where('status', '!=', 'expired')
            ->first();
    }

    public function findPendingGiftForMemberAndPage(int $memberId, string $email, int $pageId): ?GiftedArticle
    {
        return GiftedArticle::where('page_id', $pageId)
            ->where(function ($q) use ($memberId, $email) {
                $q->where('recipient_member_id', $memberId)
                    ->orWhere('recipient_email', strtolower(trim($email)));
            })
            ->where('status', 'pending')
            ->with(['page', 'giftedBy'])
            ->first();
    }

    public function getPendingGiftsForMember(mixed $id, mixed $email)
    {
        return GiftedArticle::where('recipient_email', strtolower(trim($email)))
            ->where('status', 'pending')
            ->with(['page', 'giftedBy'])
            ->first();
    }

    protected function getModelClass(): string
    {
        return GiftedArticle::class;
    }
}