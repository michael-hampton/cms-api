<?php

namespace App\Controllers\Newsletter;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Requests\SaveNewsletterBrandingRequest;
use App\Services\Newsletter\NewsletterBrandingService;

class NewsletterBrandingController extends Controller
{
    public function __construct(
        private readonly NewsletterBrandingService $brandingService,
        private readonly Logger                    $logger,
        private readonly NewsletterRepository $newsletterRepository
    )
    {
        parent::__construct();
    }

    public function show(int $newsletterId): JsonResponse
    {
        try {

            $newsletter = $this->newsletterRepository->find($newsletterId);

            if (!$newsletter) {
                return $this->errorResponse('Newsletter not found', 404);
            }

            $branding = $this->brandingService->getBranding($newsletterId)?->toArray();

            $branding['design_config'] = $newsletter->design_config
                ? (is_string($newsletter->design_config)
                    ? json_decode($newsletter->design_config, true)
                    : $newsletter->design_config)
                : null;

            return $this->resourceResponse([
                'branding' => $branding
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function save(SaveNewsletterBrandingRequest $request, int $newsletterId): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();
            $allowed = ['logo_url', 'header_text', 'footer_text', 'theme_json', 'custom_css'];
            $data = array_filter(
                array_intersect_key($request->validated(), array_flip($allowed)),
                fn($v) => $v !== null
            );

            $newsletter = $this->newsletterRepository->find($newsletterId);

            if (!$newsletter || $newsletter->site_id !== $siteId) {
                return $this->errorResponse('Newsletter not found', 404);
            }

            $branding = $this->brandingService->saveBranding($newsletterId, $data);

            $brandingJson = $request->input('branding_json', []);

            $branding = $branding->toArray();

            if (isset($brandingJson['design_config'])) {
                $newsletter->update([
                    'design_config' => json_encode($brandingJson['design_config'])
                ]);
                $branding['design_config'] = $brandingJson['design_config'];
            }


            return $this->successResponse('Branding saved', ['branding' => $branding]);
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