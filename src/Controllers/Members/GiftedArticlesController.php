<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Services\Members\ArticleGiftingService;

class GiftedArticlesController extends Controller
{
    public function __construct(
        private ArticleGiftingService $giftingService
    )
    {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $gifts = $this->giftingService->getGiftedArticlesForMember($member, $siteId);
        $allowance = $this->giftingService->canMemberGift($member, $siteId);

        return $this->view('member/gifted-articles/index', [
            'member' => $member,
            'site' => SiteContext::get(),
            'receivedGifts' => $gifts['received'],
            'givenGifts' => $gifts['given'],
            'allowance' => $allowance
        ]);
    }

    public function showGiftForm(Request $request, string $pageSlug)
    {
        if (!MemberAuth::check()) {
            $_SESSION['intended_url'] = "/gift-article/{$pageSlug}";
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $page = Page::where('slug', $pageSlug)
            ->where('site_id', $siteId)
            ->where('status', 'published')
            ->first();

        if (!$page) {
            return $this->notFound('Article not found');
        }

        $allowance = $this->giftingService->canMemberGift($member, $siteId);

        return $this->view('member/gifted-articles/gift-form', [
            'member' => $member,
            'site' => SiteContext::get(),
            'page' => $page,
            'allowance' => $allowance
        ]);
    }

    public function giftArticle(Request $request, string $pageSlug)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Please login to gift articles'
            ], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $page = Page::where('slug', $pageSlug)
            ->where('site_id', $siteId)
            ->first();

        if (!$page) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Article not found'
            ], 404);
        }

        $recipientEmail = $request->input('recipient_email');
        $personalMessage = $request->input('personal_message');

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Invalid email address'
            ], 400);
        }

        $result = $this->giftingService->giftArticle(
            $member,
            $page,
            $recipientEmail,
            $siteId,
            $personalMessage
        );

        if ($result['success']) {
            $shareLink = $this->giftingService->generateShareLink($result['gift']);

            // TODO: Send email notification to recipient

            return $this->jsonResponse([
                'success' => true,
                'message' => $result['message'],
                'share_link' => $shareLink,
                'gift_id' => $result['gift']->id
            ]);
        }

        return $this->jsonResponse([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }

    public function claim(Request $request, string $token)
    {
        if (!MemberAuth::check()) {
            $_SESSION['intended_url'] = "/gift/{$token}";
            $_SESSION['gift_token'] = $token;
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $result = $this->giftingService->claimGift($token, $member);

        if ($result['success']) {
            $gift = $result['gift'];

            if (isset($result['already_claimed'])) {
                return $this->redirect('/' . SiteContext::slug() . '/' . $gift->page->slug)
                    ->with('message', $result['message']);
            }

            return $this->view('member/gifted-articles/claimed', [
                'member' => $member,
                'site' => SiteContext::get(),
                'gift' => $gift,
                'message' => $result['message']
            ]);
        }

        return $this->view('member/gifted-articles/claim-error', [
            'site' => SiteContext::get(),
            'message' => $result['message']
        ]);
    }

    public function getGiftModal(Request $request, string $pageSlug)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Please login to gift articles'
            ], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $page = Page::where('slug', $pageSlug)
            ->where('site_id', $siteId)
            ->where('status', 'published')
            ->first();

        if (!$page) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Article not found'
            ], 404);
        }

        $allowance = $this->giftingService->canMemberGift($member, $siteId);

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'page' => [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug
                ],
                'allowance' => $allowance
            ]
        ]);
    }
}