<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePermissions;
use App\Exceptions\OpenCollab\GuidelineNotArchivableException;
use App\Exceptions\OpenCollab\GuidelineNotEditableException;
use App\Exceptions\OpenCollab\GuidelineNotPublishableException;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Jobs\OpenCollab\GuidelineUpdatedFanoutJob;
use App\Models\Guideline;
use App\Repositories\OpenCollab\GuidelinesContentRepository;
use App\Repositories\OpenCollab\GuidelineTemplateRepository;
use App\Services\OpenCollab\GuidelineService;
use App\Services\OpenCollab\GuidelineTemplateService;
use App\Services\OpenCollab\OpenCollabAuthorizationService;

/**
 * Admin CRUD and lifecycle management for brand guidelines.
 *
 * Routes:
 *   GET    /api/{site}/open-collab/admin/guidelines                  — list all
 *   GET    /api/{site}/open-collab/admin/guidelines/latest           — latest published
 *   POST   /api/{site}/open-collab/admin/guidelines                  — create draft
 *   GET    /api/{site}/open-collab/admin/guidelines/{id}             — show one
 *   PUT    /api/{site}/open-collab/admin/guidelines/{id}             — update draft content
 *   DELETE /api/{site}/open-collab/admin/guidelines/{id}             — delete latest draft only
 *   POST   /api/{site}/open-collab/admin/guidelines/{id}/publish     — publish draft
 *   POST   /api/{site}/open-collab/admin/guidelines/{id}/archive     — archive published
 *   POST   /api/{site}/open-collab/admin/guidelines/{id}/clone       — clone to new draft
 *   POST   /api/{site}/open-collab/admin/guidelines/from-template    — draft from template
 */
class AdminGuidelinesController extends Controller
{
    use AuthorizesSitePermissions;

    public function __construct(
        private readonly GuidelinesContentRepository    $guidelinesContentRepository,
        private readonly GuidelineTemplateRepository    $templateRepository,
        private readonly GuidelineService               $guidelineService,
        private readonly GuidelineTemplateService       $guidelineTemplateService,
        private readonly OpenCollabAuthorizationService $authorization,
    )
    {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['guideline.edit', 'guideline.publish', 'guideline.archive'])) {
            return $response;
        }

        $guidelines = $this->guidelinesContentRepository->allForSite(SiteContext::getId());

        return $this->jsonResponse(
            $guidelines->map(fn($g) => $this->formatGuideline($g))->toArray()
        );
    }

    private function formatGuideline(Guideline $guideline): array
    {
        return [
            'id' => $guideline->id,
            'site_id' => $guideline->site_id,
            'version' => $guideline->version,
            'content' => $guideline->content,
            'status' => $guideline->status,
            'status_label' => $guideline->status,
            'published_at' => $guideline->published_at,
            'published_by' => $guideline->published_by,
            'archived_at' => $guideline->archived_at,
            'archived_by' => $guideline->archived_by,
            'source_template_id' => $guideline->source_template_id,
            'cloned_from_version_id' => $guideline->cloned_from_version_id,
            'created_at' => $guideline->created_at,
        ];
    }

    public function latest(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['guideline.acknowledge', 'guideline.publish'])) {
            return $response;
        }

        $guideline = $this->guidelinesContentRepository->latestPublishedForSite(SiteContext::getId());
        if (!$guideline) {
            return $this->errorResponse('No published guidelines found for this site.', 404);
        }

        return $this->jsonResponse(['guideline' => $this->formatGuideline($guideline)]);
    }

    public function show(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['guideline.edit', 'guideline.publish', 'guideline.archive'])) {
            return $response;
        }

        $guideline = $this->guidelinesContentRepository->find($id);
        if (!$guideline || (int)$guideline->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Guidelines not found.', 404);
        }

        return $this->jsonResponse(['guideline' => $this->formatGuideline($guideline)]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['guideline.create'])) {
            return $response;
        }

        $content = $request->input('content', '');

        if (empty($content)) {
            return $this->errorResponse('Guidelines content is required.', 422);
        }
        if (mb_strlen($content) < 50) {
            return $this->errorResponse('Guidelines content must be at least 50 characters.', 422);
        }

        $guideline = $this->guidelineService->createDraft(
            siteId: SiteContext::getId(),
            content: $content,
            createdByUserId: Auth::id(),
        );

        return $this->jsonResponse([
            'guideline' => $this->formatGuideline($guideline),
            'message' => "Guidelines draft version {$guideline->version} created.",
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['guideline.edit'])) {
            return $response;
        }

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

        try {
            $updated = $this->guidelineService->updateDraftContent($guideline, $content);
            dispatch(GuidelineUpdatedFanoutJob::for($guideline->id, SiteContext::getId()));
        } catch (GuidelineNotEditableException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }

        return $this->jsonResponse([
            'guideline' => $this->formatGuideline($updated),
            'message' => "Guidelines version {$updated->version} updated.",
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['guideline.archive'])) {
            return $response;
        }

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

        try {
            $this->guidelineService->assertEditable($guideline);
        } catch (GuidelineNotEditableException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }

        $version = $guideline->version;
        $guideline->delete();

        return $this->successResponse("Guidelines version {$version} deleted.");
    }

    public function publish(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['guideline.publish'])) {
            return $response;
        }

        $guideline = $this->guidelinesContentRepository->find($id);
        if (!$guideline || (int)$guideline->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Guidelines not found.', 404);
        }

        try {
            $published = $this->guidelineService->publishVersion($guideline, Auth::id());
        } catch (GuidelineNotPublishableException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }

        return $this->jsonResponse([
            'guideline' => $this->formatGuideline($published),
            'message' => "Guidelines version {$published->version} published.",
        ]);
    }

    public function archive(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['guideline.archive'])) {
            return $response;
        }

        $guideline = $this->guidelinesContentRepository->find($id);
        if (!$guideline || (int)$guideline->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Guidelines not found.', 404);
        }

        try {
            $archived = $this->guidelineService->archiveVersion($guideline, Auth::id());
        } catch (GuidelineNotArchivableException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }

        return $this->jsonResponse([
            'guideline' => $this->formatGuideline($archived),
            'message' => "Guidelines version {$archived->version} archived.",
        ]);
    }

    public function clone(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['guideline.create'])) {
            return $response;
        }

        $guideline = $this->guidelinesContentRepository->find($id);
        if (!$guideline || (int)$guideline->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Guidelines not found.', 404);
        }

        $draft = $this->guidelineService->cloneToDraft($guideline, Auth::id());

        return $this->jsonResponse([
            'guideline' => $this->formatGuideline($draft),
            'message' => "Draft version {$draft->version} created from version {$guideline->version}.",
        ], 201);
    }

    // ── Formatting ────────────────────────────────────────────────────────────

    public function storeFromTemplate(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['guideline.create'])) {
            return $response;
        }

        $templateId = (int)$request->input('template_id', 0);
        $template = $this->templateRepository->find($templateId);

        if (!$template || !$template->is_active) {
            return $this->errorResponse('Template not found or inactive.', 404);
        }

        $guideline = $this->guidelineTemplateService->createDraftFromTemplate(
            $template,
            SiteContext::getId(),
            Auth::id(),
        );

        return $this->jsonResponse([
            'guideline' => $this->formatGuideline($guideline),
            'message' => "Draft version {$guideline->version} created from template.",
        ], 201);
    }
}
