<?php

declare(strict_types=1);

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\StripeConnectAccountService;

class StripeConnectController extends Controller
{
    public function __construct(
        private readonly StripeConnectAccountService $stripeConnectAccountService,
    )
    {
        parent::__construct();
    }

    /**
     * POST /api/{site}/open-collab/stripe-connect/onboard
     */
    public function onboard(): JsonResponse
    {
        $userId = Auth::id();
        $siteSlug = SiteContext::slug();

        $returnUrl = url("/{$siteSlug}/open-collab/settings?stripe_connect=return");
        $refreshUrl = url("/{$siteSlug}/open-collab/settings?stripe_connect=refresh");

        $result = $this->stripeConnectAccountService->createOrRefreshOnboarding(
            userId: $userId,
            returnUrl: $returnUrl,
            refreshUrl: $refreshUrl,
        );

        if (!$result['success']) {
            return $this->errorResponse($result['message'] ?? 'Stripe onboarding failed.', 502);
        }

        return $this->jsonResponse([
            'onboarding_url' => $result['onboarding_url'],
        ]);
    }

    /**
     * GET /api/{site}/open-collab/stripe-connect/status
     */
    public function status(): JsonResponse
    {
        return $this->jsonResponse(
            $this->stripeConnectAccountService->getOnboardingStatus(Auth::id())
        );
    }
}

