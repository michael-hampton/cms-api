<?php

namespace App\Controllers\Newsletter;

use App\Controllers\Controller;
use App\Framework\Http\HtmlResponse;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Services\Newsletter\NewsletterSnapshotService;
use App\Services\Newsletter\NewsletterViewTokenService;

/**
 * Serves the view-in-browser newsletter page.
 * Route: GET /newsletter/view/{token}
 */
class NewsletterViewController extends Controller
{
    public function __construct(
        private readonly NewsletterViewTokenService $tokenService,
        private readonly NewsletterSnapshotService  $snapshotService,
    )
    {
        parent::__construct();
    }

    public function viewNewsletter(Request $request, string $token): JsonResponse
    {
        $snapshot = $this->tokenService->resolveSnapshot($token);

        if (!$snapshot) {
            return $this->errorResponse('This newsletter link has expired or is invalid.', 404);
        }

        $html = $this->snapshotService->renderFromSnapshot($snapshot->id);

        if (!$html) {
            return $this->errorResponse('Newsletter content could not be loaded.', 500);
        }

        return new HtmlResponse($html);
    }

    public function generateToken(Request $request, int $newsletterId): JsonResponse
    {
        try {
            $token = $this->tokenService->generateForNewsletter($newsletterId);
            $viewUrl = $this->tokenService->buildViewUrl($token);

            return $this->successResponse('View token generated', [
                'token' => $token,
                'view_url' => $viewUrl,
            ]);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate view token', 500);
        }
    }
}