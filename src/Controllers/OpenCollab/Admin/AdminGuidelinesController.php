<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Events\OpenCollab\GuidelinesVersionBumpedEvent;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Support\SiteContext;
use App\Jobs\OpenCollab\GuidelineUpdatedFanoutJob;
use App\Jobs\Subscriptions\BuildPrintBatchesJob;
use App\Models\Site;
use App\Repositories\Cms\UserRepository;
use App\Repositories\OpenCollab\GuidelinesContentRepository;
use App\Repositories\OpenCollab\UserSiteRepository;
use App\Services\OpenCollab\Notifications\GuidelineUpdatedNotification;
use App\Services\OpenCollab\Notifications\ViolationRecordedNotification;

/**
 * Admin CRUD for contributor brand guidelines.
 *
 * Routes:
 *   GET    /api/{site}/open-collab/admin/guidelines           — list all versions
 *   GET    /api/{site}/open-collab/admin/guidelines/latest    — latest version
 *   POST   /api/{site}/open-collab/admin/guidelines           — create new version
 *   GET    /api/{site}/open-collab/admin/guidelines/{id}      — show one
 *   PUT    /api/{site}/open-collab/admin/guidelines/{id}      — update content (unsigned only)
 *   DELETE /api/{site}/open-collab/admin/guidelines/{id}      — delete latest version only
 */
class AdminGuidelinesController extends Controller
{
    public function __construct(
        private readonly GuidelinesContentRepository $guidelinesContentRepository,
        private readonly NotificationDispatcher $notificationDispatcher,
        private readonly UserSiteRepository     $userSiteRepository,
        private readonly UserRepository         $userRepository

    )
    {
        parent::__construct();
    }

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

    public function latest(): JsonResponse
    {
        $guideline = $this->guidelinesContentRepository->latestForSite(SiteContext::getId());
        if (!$guideline) {
            return $this->errorResponse('No guidelines found for this site.', 404);
        }
        return $this->jsonResponse(['guideline' => $this->formatGuideline($guideline)]);
    }

    public function show(int $id): JsonResponse
    {
        $guideline = $this->guidelinesContentRepository->find($id);
        if (!$guideline || (int)$guideline->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Guidelines not found.', 404);
        }
        return $this->jsonResponse(['guideline' => $this->formatGuideline($guideline)]);
    }

    public function store(Request $request): JsonResponse
    {
        $content = $request->input('content', '');

        if (empty($content)) {
            return $this->errorResponse('Guidelines content is required.', 422);
        }
        if (mb_strlen($content) < 50) {
            return $this->errorResponse('Guidelines content must be at least 50 characters.', 422);
        }

        $siteId = SiteContext::getId();
        $guideline = $this->guidelinesContentRepository->createVersion($siteId, $content);

        $site = Site::find($siteId);
        if ($site) {
            $site->update(['guidelines_version' => $guideline->version]);
        }

        event(new GuidelinesVersionBumpedEvent($guideline, $siteId, $guideline->version));

        return $this->jsonResponse([
            'guideline' => $this->formatGuideline($guideline),
            'message' => "Guidelines version {$guideline->version} created.",
        ], 201);
    }

    /**
     * PUT /api/{site}/open-collab/admin/guidelines/{id}
     * Updates content. Only allowed if no contributor has acknowledged this version.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $guideline = $this->guidelinesContentRepository->find($id);
        if (!$guideline || (int)$guideline->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Guidelines not found.', 404);
        }

        if ($this->guidelinesContentRepository->hasAnyAcknowledged($id)) {
            return $this->errorResponse(
                'This guidelines version has been acknowledged and cannot be edited. Create a new version instead.',
                409
            );
        }

        $content = $request->input('content', '');

        if (empty($content)) {
            return $this->errorResponse('Guidelines content is required.', 422);
        }
        if (mb_strlen($content) < 50) {
            return $this->errorResponse('Guidelines content must be at least 50 characters.', 422);
        }

        $guideline->update(['content' => $content]);

        dispatch(GuidelineUpdatedFanoutJob::for($guideline->id, SiteContext::getId()));

        return $this->jsonResponse([
            'guideline' => $this->formatGuideline($guideline->fresh()),
            'message' => "Guidelines version {$guideline->version} updated.",
        ]);
    }

    /**
     * DELETE /api/{site}/open-collab/admin/guidelines/{id}
     * Only the latest version can be deleted, and only if unacknowledged.
     */
    public function destroy(int $id): JsonResponse
    {
        $guideline = $this->guidelinesContentRepository->find($id);
        if (!$guideline || (int)$guideline->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Guidelines not found.', 404);
        }

        $latest = $this->guidelinesContentRepository->latestForSite(SiteContext::getId());
        if (!$latest || $latest->id !== $guideline->id) {
            return $this->errorResponse('Only the latest guidelines version can be deleted.', 409);
        }

        if ($this->guidelinesContentRepository->hasAnyAcknowledged($id)) {
            return $this->errorResponse(
                'This guidelines version has been acknowledged and cannot be deleted.',
                409
            );
        }

        $version = $guideline->version;
        $guideline->delete();

        // Roll back site guidelines_version pointer
        $site = Site::find(SiteContext::getId());
        $newLatest = $this->guidelinesContentRepository->latestForSite(SiteContext::getId());
        if ($site) {
            $site->update(['guidelines_version' => $newLatest ? $newLatest->version : 0]);
        }

        return $this->successResponse("Guidelines version {$version} deleted.");
    }
}