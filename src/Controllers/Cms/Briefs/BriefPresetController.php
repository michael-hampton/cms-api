<?php

namespace App\Controllers\Cms\Briefs;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\Briefs\BriefTemplateRepository;
use App\Requests\Briefs\StoreBriefPresetRequest;
use App\Requests\Briefs\UpdateBriefPresetRequest;
use App\Services\Cms\BriefService;
use Exception;

/**
 * Handles admin-managed Brief Preset CRUD and Brief creation from a preset.
 *
 * Presets are BriefTemplate records that may carry typed default_subtasks,
 * default_owner_ids, and a default_category_tag_id.  Write operations are
 * restricted to site admins; read operations are open to any authenticated user.
 */
class BriefPresetController extends Controller
{
    public function __construct(
        private readonly BriefService            $briefService,
        private readonly BriefTemplateRepository $briefTemplateRepository
    )
    {
        parent::__construct();
    }

    // ── Read (any authenticated user) ────────────────────────────────────────

    /**
     * GET /{site}/brief-preset
     *
     * Returns all presets (system + custom) for the current site.
     */
    public function index(Request $request, string $site): JsonResponse
    {
        try {
            $templates = $this->briefService->getTemplatesForSite(SiteContext::getId());

            return $this->resourceResponse(['items' => $templates]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /{site}/brief-preset/{id}
     *
     * Returns a single preset.  404 if not found.
     */
    public function show(int $id, string $site): JsonResponse
    {
        try {
            $preset = $this->briefTemplateRepository->find($id);

            if (!$preset) {
                return $this->errorResponse('Preset not found', 404);
            }

            return $this->resourceResponse(['data' => $preset->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ── Write (site admin only) ───────────────────────────────────────────────

    /**
     * POST /{site}/brief-preset
     *
     * Creates a new preset.  Returns 201.
     */
    public function store(StoreBriefPresetRequest $request, string $site): JsonResponse
    {
        $guard = $this->assertSiteAdmin($request);
        if ($guard !== null) {
            return $guard;
        }

        try {
            $preset = $this->briefService->createPreset(
                $request->validated(),
                SiteContext::getId()
            );

            return $this->resourceResponse(['data' => $preset->toArray()], 201);
        } catch (ValidationException $validationException) {
            return $this->errorResponse('Validation failed', 422, $validationException->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Returns a 403 JsonResponse when the current request is not from a site
     * admin, or null when the caller is allowed to proceed.
     *
     * Uses SiteContext::isSiteAdmin() which is already present in the codebase
     * (see BriefController::bulkAssign for SiteContext usage).
     */
    private function assertSiteAdmin(?Request $request): ?JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Forbidden', 403);
        }

        return null;
    }

    /**
     * PUT /{site}/brief-preset/{id}
     *
     * Updates an existing preset.  Returns 200.
     */
    public function update(int $id, UpdateBriefPresetRequest $request, string $site): JsonResponse
    {
        $guard = $this->assertSiteAdmin($request);
        if ($guard !== null) {
            return $guard;
        }

        try {
            $preset = $this->briefService->updatePreset($id, $request->all());

            return $this->resourceResponse(['data' => $preset->toArray()]);
        } catch (Exception $e) {
            // updatePreset throws when preset not found — surface as 404.
            if (str_contains($e->getMessage(), 'not found')) {
                return $this->errorResponse($e->getMessage(), 404);
            }

            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ── Brief creation from preset (any authenticated user) ──────────────────

    /**
     * DELETE /{site}/brief-preset/{id}
     *
     * Deletes a preset.  Returns 204.
     */
    public function destroy(int $id, string $site): JsonResponse
    {
        $guard = $this->assertSiteAdmin(null);
        if ($guard !== null) {
            return $guard;
        }

        try {
            $deleted = $this->briefTemplateRepository->delete($id);

            if (!$deleted) {
                return $this->errorResponse('Preset not found', 404);
            }

            return $this->noContentResponse();
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * POST /{site}/brief/from-preset/{id}
     *
     * Creates a Brief (and BriefTasks from default_subtasks) using the preset
     * as a template.  Returns 201 with the new Brief resource.
     */
    public function createFromPreset(int $id, Request $request, string $site): JsonResponse
    {
        try {
            $data = $request->all();
            $data['site_id'] = SiteContext::getId();

            $brief = $this->briefService->createFromTemplate($id, $data);

            return $this->resourceResponse(['data' => $brief->toArray()], 201);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'not found')) {
                return $this->errorResponse($e->getMessage(), 404);
            }

            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}