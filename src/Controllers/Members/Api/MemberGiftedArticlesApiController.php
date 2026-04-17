<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Mail\GiftedArticleMail;
use App\Models\Page;
use App\Services\Members\ArticleGiftingService;

class MemberGiftedArticlesApiController extends Controller
{
    public function __construct(
        private ArticleGiftingService $giftingService
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/member/gifted-articles
     * Returns received + given gifts and allowance for the authenticated member.
     */
    public function index(): mixed
    {
        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $gifts = $this->giftingService->getGiftedArticlesForMember($member, $siteId);
        $allowance = $this->giftingService->canMemberGift($member, $siteId);

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'received' => $gifts['received']->map(function ($received) {
                    return array_merge(
                        $received->toArray(),
                        [
                            'created_at' => $received->created_at?->format('Y-m-d H:i:s'),
                            'gifted_at' => $received->gifted_at?->format('Y-m-d H:i:s'),
                            'claimed_at' => $received->claimed_at?->format('Y-m-d H:i:s'),
                            'expires_at' => $received->expires_at?->format('Y-m-d H:i:s'),
                        ]
                    );
                }),
                'given' => $gifts['given']->map(function ($given) {
                    return array_merge(
                        $given->toArray(),
                        [
                            'created_at' => $given->created_at?->format('Y-m-d H:i:s'),
                            'gifted_at' => $given->gifted_at?->format('Y-m-d H:i:s'),
                            'claimed_at' => $given->claimed_at?->format('Y-m-d H:i:s'),
                            'expires_at' => $given->expires_at?->format('Y-m-d H:i:s'),
                        ]
                    );
                }),
                'allowance' => $allowance,
            ],
        ]);
    }

    /**
     * GET /api/{site}/member/gift-modal/{pageSlug}
     * Returns page details and allowance for the gift modal.
     */
    public function modal(Request $request, string $pageSlug): mixed
    {
        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $page = Page::where('slug', $pageSlug)
            ->where('site_id', $siteId)
            ->where('status', 'published')
            ->first();

        if (!$page) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Article not found',
            ], 404);
        }

        $allowance = $this->giftingService->canMemberGift($member, $siteId);

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'page' => [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                ],
                'allowance' => $allowance,
            ],
        ]);
    }

    /**
     * POST /api/{site}/gift-article/{pageSlug}
     * Gifts an article to a recipient email, sends notification email.
     */
    public function gift(Request $request, string $pageSlug): mixed
    {
        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $page = Page::where('slug', $pageSlug)
            ->where('site_id', $siteId)
            ->first();

        if (!$page) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Article not found',
            ], 404);
        }

        $recipientEmail = $request->input('recipient_email');
        $personalMessage = $request->input('personal_message');

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Invalid email address',
            ], 400);
        }

        $result = $this->giftingService->giftArticle(
            $member,
            $page,
            $recipientEmail,
            $siteId,
            $personalMessage
        );

        if (!$result['success']) {
            return $this->resourceResponse([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        $shareLink = $this->giftingService->generateShareLink($result['gift']);

        try {
            $mail = new GiftedArticleMail(
                $result['gift'],
                $shareLink,
                $recipientEmail,
                $personalMessage
            );
            MailManager::getInstance()->send($mail);
        } catch (\Exception $e) {
            Logger::error('Failed to send gift article email', [
                'gift_id' => $result['gift']->id,
                'recipient_email' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse([
            'success' => true,
            'message' => $result['message'],
            'share_link' => $shareLink,
            'gift_id' => $result['gift']->id,
        ]);
    }

    /**
     * POST /api/{site}/gift/{token}/claim
     * Claims a gift by token for the authenticated member.
     */
    public function claim(Request $request, string $token): mixed
    {
        $member = MemberAuth::getMember();
        $result = $this->giftingService->claimGift($token, $member);

        if (!$result['success']) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        $gift = $result['gift'];

        return $this->resourceResponse([
            'success' => true,
            'message' => $result['message'],
            'already_claimed' => $result['already_claimed'] ?? false,
            'gift' => [
                'id' => $gift->id,
                'status' => $gift->status,
                'personal_message' => $gift->personal_message,
                'gifted_at' => $gift->gifted_at,
                'claimed_at' => $gift->claimed_at,
                'page' => $gift->page ? [
                    'title' => $gift->page->title,
                    'slug' => $gift->page->slug,
                ] : null,
                'gifted_by' => $gift->giftedBy ? [
                    'name' => $gift->giftedBy->name ?? $gift->giftedBy->email,
                    'email' => $gift->giftedBy->email,
                ] : null,
            ],
        ]);
    }
}