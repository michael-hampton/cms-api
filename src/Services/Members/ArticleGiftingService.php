<?php

namespace App\Services\Members;

use App\Events\ArticleGifting\GiftClaimedEvent;
use App\Events\ArticleGifting\GiftCreatedEvent;
use App\Framework\Database\Database;
use App\Models\GiftedArticle;
use App\Models\Member;
use App\Models\Page;
use App\Repositories\Members\GiftedArticleRepository;
use App\Services\RateLimiting\RateLimiter;
use App\Services\ValueObjects\Email;

class ArticleGiftingService
{
    public function __construct(
        private readonly GiftedArticleRepository $giftRepository,
        private readonly RateLimiter             $rateLimiter,
        private readonly Database                $database
    )
    {
    }

    public function canMemberGift(Member $member, int $siteId): array
    {
        $allowance = $this->giftRepository->getOrCreateAllowance($member->id, $siteId);

        return [
            'can_gift' => $allowance->canGift(),
            'remaining_gifts' => $allowance->getRemainingGifts(),
            'annual_limit' => $allowance->annual_gift_limit,
            'used_this_year' => $allowance->gifts_used_this_year
        ];
    }

    public function giftArticle(
        Member  $gifter,
        Page    $page,
        string  $recipientEmail,
        int     $siteId,
        ?string $personalMessage = null
    ): array
    {
        // Rate limiting
        $rateLimitKey = "gift_article:{$gifter->id}";
        if ($this->rateLimiter->tooManyAttempts($rateLimitKey, 10)) {
            return [
                'success' => false,
                'message' => 'Too many gift attempts. Please try again later.'
            ];
        }

        $allowance = $this->giftRepository->getOrCreateAllowance($gifter->id, $siteId);

        if (!$allowance->canGift()) {
            return [
                'success' => false,
                'message' => 'You have reached your annual gift limit'
            ];
        }

        $recipientEmailObj = new Email($recipientEmail);
        $gifterEmailObj = new Email($gifter->email);

        // Prevent self-gifting
        if ($recipientEmailObj->equals($gifterEmailObj)) {
            return [
                'success' => false,
                'message' => 'You cannot gift an article to yourself'
            ];
        }

        // Check if this article was already gifted to this email by this member
        $existing = $this->giftRepository->findExistingGift(
            $page->id,
            $gifter->id,
            $recipientEmail
        );

        if ($existing) {
            return [
                'success' => false,
                'message' => 'You have already gifted this article to this email address'
            ];
        }

        return $this->database->transaction(function () use ($gifter, $page, $recipientEmailObj, $siteId, $personalMessage, $allowance, $rateLimitKey) {
            $gift = $this->giftRepository->createGift([
                'page_id' => $page->id,
                'gifted_by_member_id' => $gifter->id,
                'site_id' => $siteId,
                'recipient_email' => $recipientEmailObj->getValue(),
                'personal_message' => $personalMessage
            ]);

            $allowance->incrementUsage();

            $this->rateLimiter->attempt($rateLimitKey, 10, 3600);

            // Dispatch event instead of directly calling email service
            event(new GiftCreatedEvent($gift));

            return [
                'success' => true,
                'gift' => $gift,
                'message' => 'Article gifted successfully'
            ];

        });
    }

    public function generateShareLink(GiftedArticle $gift): string
    {
        return url("/gift/{$gift->gift_token}");
    }

    public function claimGift(string $token, Member $member): array
    {
        $gift = $this->giftRepository->findByToken($token);

        if (!$gift) {
            return [
                'success' => false,
                'message' => 'Invalid gift link'
            ];
        }

        if ($gift->isExpired()) {
            return [
                'success' => false,
                'message' => 'This gift has expired'
            ];
        }

        if ($gift->isClaimed()) {
            // Check if claimed by this member
            if ($gift->recipient_member_id === $member->id) {
                return [
                    'success' => true,
                    'already_claimed' => true,
                    'gift' => $gift,
                    'message' => 'You already have access to this article'
                ];
            }

            return [
                'success' => false,
                'message' => 'This gift has already been claimed'
            ];
        }

        $recipientEmailObj = new Email($gift->recipient_email);
        $memberEmailObj = new Email($member->email);

        if (!$recipientEmailObj->equals($memberEmailObj)) {
            return [
                'success' => false,
                'message' => 'This gift was sent to a different email address'
            ];
        }

        $gift->claim($member->id);

        event(new GiftClaimedEvent($gift, $member));

        return [
            'success' => true,
            'gift' => $gift,
            'message' => 'Gift claimed successfully! You now have access to this article.'
        ];
    }

    public function getGiftedArticlesForMember(Member $member, int $siteId): array
    {
        $received = $this->giftRepository->getReceivedGifts($member->id, $siteId);
        $given = $this->giftRepository->getGiftsByMember($member->id, $siteId);

        return [
            'received' => $received,
            'given' => $given
        ];
    }

    public function autoClaimGiftsOnSignup(Member $member): int
    {
        return $this->giftRepository->claimPendingGiftsForEmail($member->email, $member->id);
    }

    public function checkAndClaimGiftForPage(Member $member, Page $page): ?GiftedArticle
    {
        $gift = $this->giftRepository->findPendingGiftForMemberAndPage(
            $member->id,
            $member->email,
            $page->id
        );

        if ($gift && !$gift->isClaimed()) {
            $gift->claim($member->id);
            //event(new GiftClaimedEvent($gift, $member));
            return $gift;
        }

        return null;
    }
}