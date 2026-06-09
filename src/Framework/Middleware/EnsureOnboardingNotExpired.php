<?php

namespace App\Framework\Middleware;

use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\RedirectResponse;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Middleware\MiddlewareInterface;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Services\OpenCollab\ContributorOnboardingService;

class EnsureOnboardingNotExpired implements MiddlewareInterface
{
    public function __construct(
        private readonly ContributorOnboardingService $onboardingService,
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        $userId = Auth::id();

        if (!$userId) {
            return $this->deny($request, 'Authentication required.', 401);
        }

        $site = $this->resolveSite();

        if (!$site) {
            return $next($request);
        }

        if ($this->onboardingService->isExpired($userId, $site)) {
            return $this->denyExpired($request);
        }

        return $next($request);
    }

    private function denyExpired(Request $request): Response
    {
        if ($this->isApiRequest($request)) {
            return JsonResponse::json([
                'error' => 'onboarding_expired',
                'message' => 'Your onboarding has expired. Please restart onboarding to continue.',
                'redirect' => '/contributor/onboarding',
            ], 409);
        }

        return new RedirectResponse('/contributor/onboarding');
    }

    private function deny(Request $request, string $message, int $status): Response
    {
        if ($this->isApiRequest($request)) {
            return JsonResponse::json([
                'error' => 'unauthorized',
                'message' => $message,
            ], $status);
        }

        return new RedirectResponse('/login');
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPath(), '/api/')
            || $request->wantsJson();
    }

    private function resolveSite(): ?Site
    {
        $siteId = SiteContext::getId();

        return $siteId ? Site::find($siteId) : null;
    }
}