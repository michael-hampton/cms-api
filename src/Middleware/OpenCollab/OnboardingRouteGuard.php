<?php

namespace App\Middleware\OpenCollab;

use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\RedirectResponse;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Middleware\MiddlewareInterface;
use App\Models\Site;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\ContributorOnboardingService;

/**
 * OnboardingRouteGuard
 *
 * Prevents contributors with incomplete onboarding from accessing
 * protected creator routes.
 *
 * For JSON/API requests: returns 403 with a machine-readable payload.
 * For browser requests: redirects to the onboarding dashboard.
 *
 * This is a UX guard only. Backend policy classes remain the authoritative
 * enforcement layer for security-critical decisions (payouts, publishing).
 *
 * Usage in routes:
 *   $router->middleware([OnboardingRouteGuard::class])->group(function () {
 *       // content creation routes, submission flows, payout requests
 *   });
 */
class OnboardingRouteGuard implements MiddlewareInterface
{
    public function __construct(
        private readonly ContributorOnboardingService $onboardingService,
    )
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $userId = Auth::id();

        if (!$userId) {
            return $this->deny($request, 'Authentication required.', 401);
        }

        $site = $this->resolveSite();

        if (!$site) {
            // Cannot determine site — let the request through and let
            // the controller deal with the missing site context.
            return $next($request);
        }

        if (!$this->onboardingService->isComplete($userId, $site)) {
            return $this->denyIncomplete($request, $userId, $site);
        }

        return $next($request);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function denyIncomplete(Request $request, int $userId, Site $site): Response
    {
        $pending = $this->onboardingService->pendingSteps($userId, $site);

        if ($this->isApiRequest($request)) {
            return new JsonResponse([
                'error' => 'onboarding_incomplete',
                'message' => 'You must complete onboarding before accessing this feature.',
                'pending_steps' => $pending,
                'redirect' => '/contributor/onboarding',
            ], 403);
        }

        return new RedirectResponse('/contributor/onboarding');
    }

    private function deny(Request $request, string $message, int $status): Response
    {
        if ($this->isApiRequest($request)) {
            return new JsonResponse(['error' => 'unauthorized', 'message' => $message], $status);
        }

        return new RedirectResponse('/login');
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->path(), '/api/')
            || $request->expectsJson();
    }

    private function resolveSite(): ?Site
    {
        $siteId = SiteContext::getId();
        return $siteId ? Site::find($siteId) : null;
    }
}