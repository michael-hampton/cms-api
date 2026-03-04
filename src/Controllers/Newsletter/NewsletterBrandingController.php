<?php

namespace App\Controllers\Newsletter;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Requests\SaveNewsletterBrandingRequest;
use App\Services\Newsletter\NewsletterBrandingService;

class NewsletterBrandingController extends Controller
{
    public function __construct(
        private readonly NewsletterBrandingService $brandingService,
        private readonly Logger                    $logger,
    )
    {
        parent::__construct();
    }

    public function show(int $newsletterId): JsonResponse
    {
        try {
            $branding = $this->brandingService->getBranding($newsletterId);

            return $this->resourceResponse([
                'branding' => $branding?->toArray()
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function save(SaveNewsletterBrandingRequest $request, int $newsletterId): JsonResponse
    {
        try {
            $allowed = ['logo_url', 'header_text', 'footer_text', 'theme_json', 'custom_css'];
            $data = array_filter(
                array_intersect_key($request->all(), array_flip($allowed)),
                fn($v) => $v !== null
            );

            $branding = $this->brandingService->saveBranding($newsletterId, $data);

            return $this->successResponse('Branding saved', ['branding' => $branding->toArray()]);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            $this->logger->error('Failed to save newsletter branding', [
                'newsletter_id' => $newsletterId,
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Failed to save branding', 500);
        }
    }

    public function versions(int $newsletterId): JsonResponse
    {
        try {
            $history = $this->brandingService->getBrandingVersionHistory($newsletterId);
            return $this->resourceResponse(['versions' => $history]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function restoreVersion(Request $request, int $newsletterId): JsonResponse
    {
        try {
            $versionNumber = (int)$request->input('version_number');
            $branding = $this->brandingService->restoreBrandingVersion($newsletterId, $versionNumber);

            return $this->successResponse('Branding version restored', ['branding' => $branding->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\Exception $e) {
            $this->logger->error('Failed to restore branding version', [
                'newsletter_id' => $newsletterId,
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Failed to restore branding version', 500);
        }
    }
}