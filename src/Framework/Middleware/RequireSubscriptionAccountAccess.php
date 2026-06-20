<?php

namespace App\Framework\Middleware;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Services\Subscriptions\SubscriptionAccountAccessResolver;

final readonly class RequireSubscriptionAccountAccess
{
    public function __construct(private SubscriptionAccountAccessResolver $resolver)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $member = MemberAuth::getMember();
        $siteId = $request->route('site') !== null ? (int) SiteContext::getId() : null;
        $subscription = $member
            ? $this->resolver->resolve((int) $request->route('id', 0), (int) $member->id, $siteId)
            : null;

        if (!$subscription) {
            return Response::json(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        return $next($request);
    }
}
