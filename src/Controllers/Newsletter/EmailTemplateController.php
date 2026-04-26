<?php

namespace App\Controllers\Newsletter;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\SiteContext;
use App\Repositories\Newsletters\EmailTemplateRepository;
use App\Requests\Newsletter\CreateEmailTemplateRequest;
use App\Requests\Newsletter\UpdateEmailTemplateRequest;
use App\Resources\EmailTemplateResource;
use App\Search\SearchCriteriaParser;
use App\Services\Newsletter\EmailTemplateService;
use Exception;

/**
 * HTTP controller for the email template system.
 *
 * Version history endpoints mirror those on NewsletterLayoutController so the
 * two systems are symmetrical — making the eventual merge straightforward.
 */
class EmailTemplateController extends Controller
{
    public function __construct(
        private readonly EmailTemplateService    $service,
        private readonly EmailTemplateRepository $repository,
    )
    {
        parent::__construct();
    }

    // ── Collection ────────────────────────────────────────────────────────────

    public function index(Request $request, string $site): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $site);
            $result = $this->repository->search($criteria);

            return $this->resourceResponse(
                (new PaginatedResourceCollection($result, EmailTemplateResource::class))->toArray()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function store(CreateEmailTemplateRequest $request): JsonResponse
    {
        try {
            $template = $this->service->create($request->validated(), SiteContext::getId());

            return $this->jsonResponse([
                'template' => EmailTemplateResource::make($template)->toArray(),
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $template = is_numeric($id)
                ? $this->service->getById((int)$id)
                : $this->service->getBySlug((string)$id, SiteContext::getId());

            if ($template === null) {
                return $this->errorResponse('Email template not found', 404);
            }

            return $this->jsonResponse([
                'template' => EmailTemplateResource::make($template)->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, UpdateEmailTemplateRequest $request): JsonResponse
    {
        try {
            $template = $this->service->update($id, $request->validated());

            return $this->jsonResponse([
                'template' => EmailTemplateResource::make($template)->toArray(),
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                str_contains($e->getMessage(), 'not found') ? 404 : 500,
            );
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->delete($id);
            return $this->successResponse('Email template deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                str_contains($e->getMessage(), 'not found') ? 404 : 500,
            );
        }
    }

    public function duplicate(int $id, Request $request): JsonResponse
    {
        try {
            $template = $this->service->duplicate($id, $request->input('name', 'Copy'));

            return $this->jsonResponse([
                'template' => EmailTemplateResource::make($template)->toArray(),
            ], 201);
        } catch (Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                str_contains($e->getMessage(), 'not found') ? 404 : 500,
            );
        }
    }

    // ── Preview ───────────────────────────────────────────────────────────────

    public function preview(int $id, Request $request): JsonResponse
    {
        try {
            $result = $this->service->previewSaved(
                $id,
                (string)$request->query('dataset', 'mock_order'),
            );
            return $this->resourceResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                str_contains($e->getMessage(), 'not found') ? 404 : 500,
            );
        }
    }

    public function previewFromData(Request $request): JsonResponse
    {
        try {
            $result = $this->service->previewLive(
                blocks: (array)$request->input('blocks', []),
                dataset: (string)$request->input('dataset', 'mock_order'),
                siteId: SiteContext::getId(),
                themeId: $request->input('theme_id') ? (int)$request->input('theme_id') : null,
            );
            return $this->resourceResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ── Version history ───────────────────────────────────────────────────────

    /**
     * List all versions for a template (newest first).
     * Mirrors NewsletterLayoutController::versions().
     */
    public function versions(int $id): JsonResponse
    {
        try {
            $template = $this->repository->find($id);

            if (!$template) {
                return $this->errorResponse('Email template not found', 404);
            }

            $versions = $this->service->getVersions($id);

            return $this->resourceResponse([
                'items' => EmailTemplateVersionResource::collection($versions)->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Restore a template to the state captured in a specific version.
     * Returns the restored template (not the version).
     * Mirrors NewsletterLayoutController::addVersion() + transitionState() combined.
     */
    public function restoreVersion(int $id, int $versionId): JsonResponse
    {
        try {
            $restored = $this->service->restoreVersion($id, $versionId);

            return $this->jsonResponse([
                'template' => EmailTemplateResource::make($restored)->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                str_contains($e->getMessage(), 'not found') ? 404 : 500,
            );
        }
    }
}