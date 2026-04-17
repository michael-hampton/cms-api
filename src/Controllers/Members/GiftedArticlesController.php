<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;

/**
 * Renders the single-page gifted-articles shell view and handles
 * the claim redirect flow (login-then-claim).
 *
 * All data is served by GiftedArticlesApiController.
 */
class GiftedArticlesController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * GET /{site}/member/gifted-articles
     * Renders the SPA shell — JS fetches data from the API.
     */
    public function index(): mixed
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        return $this->view('member/gifted-articles/index', [
            'member' => MemberAuth::getMember(),
            'site' => SiteContext::get(),
        ]);
    }

    /**
     * GET /{site}/gift/{token}
     * Redirects unauthenticated visitors to login, then back here.
     * Authenticated members are forwarded to the SPA with the token
     * in the URL so the JS can trigger the claim flow automatically.
     */
    public function claim(Request $request, string $token): mixed
    {
        if (!MemberAuth::check()) {
            $_SESSION['intended_url'] = '/' . SiteContext::slug() . "/gift/{$token}";
            $_SESSION['gift_token'] = $token;
            return $this->redirect('/member/login');
        }

        // Render the SPA shell; JS will detect the token and call the claim API.
        return $this->view('member/gifted-articles/index', [
            'member' => MemberAuth::getMember(),
            'site' => SiteContext::get(),
            'gift_token' => $token,
        ]);
    }
}