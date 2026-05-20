<?php

namespace App\Framework\Middleware;

use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Framework\View\ViewRenderer;
use App\Models\Page;
use App\Services\Subscriptions\SubscriptionModalService;

class CheckPageMemberAccess
{
    public function __construct(private readonly ViewRenderer $viewRenderer, private readonly SubscriptionModalService $subscriptionModalService)
    {

    }

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        
        $page = $request->getAttribute('page');

        if (!$page instanceof Page) {
            return $response;
        }

        $needsLogin = (new \App\Services\Url\CheckPageMemberAccess())->handle($request, $page);

        if ($needsLogin) {
            $message = $page->non_member_message ?? 'This content requires a member login.';
            $message .= '<button class="btn btn-primary btn-subscribe-required" onclick="showSubscriptionModal()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>Subscribe to Access</button>';

            $subscriptionModalData = $this->subscriptionModalService->getModalData(null, SiteContext::getId());

            $html = $this->viewRenderer->render('auth/requires-access', ['page' => $page, 'subscriptionModalData' => $subscriptionModalData]);

            return Response::html($html);
        }

        return $response;
    }
}