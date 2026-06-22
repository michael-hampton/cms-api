<?php

namespace App\Framework\Middleware;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Services\Subscriptions\MemberSubscriptionAccountContextResolver;
use App\Services\Subscriptions\SubscriptionAccountAccessResolver;
use RuntimeException;

final readonly class RequireSubscriptionAccountAccess
{
    public function __construct(
        private SubscriptionAccountAccessResolver $resolver,
        private MemberSubscriptionAccountContextResolver $siteResolver,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $member = MemberAuth::getMember();
        $siteId = null;
        $siteSlug = $request->route('site');

        if (is_string($siteSlug) && $siteSlug !== '') {
            try {
                $siteId = (int) $this->siteResolver->resolve($siteSlug)->id;
            } catch (RuntimeException) {
                return Response::json(['success' => false, 'message' => 'Site not found.'], 404);
            }
        }

        $subscription = $member
            ? $this->resolver->resolve((int) $request->route('id', 0), (int) $member->id, $siteId)
            : null;

        if (!$subscription) {
            return Response::json(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        return $next($request);
    }
}
