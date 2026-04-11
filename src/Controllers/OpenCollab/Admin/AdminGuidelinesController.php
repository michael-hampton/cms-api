<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Repositories\OpenCollab\GuidelinesContentRepository;

/**
 * Admin CRUD for contributor brand guidelines.
 *
 * Mirrors AdminContractController exactly.
 * When a new version is created, the site's guidelines_version column is
 * updated to the new version number so ContributorOnboardingService picks it up.
 *
 * Routes:
 *   GET  /api/{site}/open-collab/admin/guidelines           — list all versions
 *   GET  /api/{site}/open-collab/admin/guidelines/latest    — latest version
 *   POST /api/{site}/open-collab/admin/guidelines           — create new version
 *   GET  /api/{site}/open-collab/admin/guidelines/{id}      — show one
 */
class AdminGuidelinesController extends Controller
{
    public function __construct(
        private readonly GuidelinesContentRepository $guidelinesContentRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/open-collab/admin/guidelines
     */
    public function index(): JsonResponse
    {
        $guidelines = $this->guidelinesContentRepository->allForSite(SiteContext::getId());

        return $this->jsonResponse(
            $guidelines->map(fn($g) => $this->formatGuideline($g))->toArray()
        );
    }

    private function formatGuideline(\App\Models\Guideline $guideline): array
    {
        return [
            'id' => $guideline->id,
            'site_id' => $guideline->site_id,
            'version' => $guideline->version,
            'content' => $guideline->content,
            'created_at' => $guideline->created_at,
        ];
    }

    /**
     * GET /api/{site}/open-collab/admin/guidelines/latest
     */
    public function latest(): JsonResponse
    {
        $guideline = $this->guidelinesContentRepository->latestForSite(SiteContext::getId());

        if (!$guideline) {
            return $this->errorResponse('No guidelines found for this site.', 404);
        }

        return $this->jsonResponse(['guideline' => $this->formatGuideline($guideline)]);
    }

    /**
     * GET /api/{site}/open-collab/admin/guidelines/{id}
     */
    public function show(int $id): JsonResponse
    {
        $guideline = $this->guidelinesContentRepository->find($id);

        if (!$guideline || (int)$guideline->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Guidelines not found.', 404);
        }

        return $this->jsonResponse(['guideline' => $this->formatGuideline($guideline)]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/{site}/open-collab/admin/guidelines
     * Creates a new version. Version is auto-incremented.
     * Also updates sites.guidelines_version so the onboarding service detects the change.
     * Body: { content: string }
     */
    public function store(Request $request): JsonResponse
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $content = trim($body['content'] ?? $request->input('content', ''));

        if (empty($content)) {
            return $this->errorResponse('Guidelines content is required.', 422);
        }

        if (mb_strlen($content) < 50) {
            return $this->errorResponse('Guidelines content must be at least 50 characters.', 422);
        }

        $siteId = SiteContext::getId();
        $guideline = $this->guidelinesContentRepository->createVersion($siteId, $content);

        // Keep Site.guidelines_version in sync so ContributorOnboardingService
        // detects that contributors need to re-acknowledge.
        $site = Site::find($siteId);
        if ($site) {
            $site->update(['guidelines_version' => $guideline->version]);
        }

        return $this->jsonResponse([
            'guideline' => $this->formatGuideline($guideline),
            'message' => "Guidelines version {$guideline->version} created.",
        ], 201);
    }
}