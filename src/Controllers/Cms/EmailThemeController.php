<?php
// src/Controllers/EmailThemeController.php

namespace App\Controllers\Cms;

use App\Actions\EmailTheme\BulkDeleteEmailTheme;
use App\Actions\EmailTheme\CloneEmailTheme;
use App\Controllers\Controller;
use App\Framework\Container;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Models\Site;
use App\Repositories\Cms\EmailThemeRepository;
use App\Requests\BulkDeleteRequest;
use App\Requests\CreateEmailThemeRequest;
use App\Requests\UpdateEmailThemeRequest;
use App\Resources\EmailThemeResource;
use App\Search\SearchCriteriaParser;
use App\Services\Cms\EmailThemePreviewService;
use App\Services\Cms\EmailThemeService;
use Exception;

class EmailThemeController extends Controller
{
    public function __construct(
        private EmailThemeService             $emailThemeService,
        private EmailThemePreviewService $emailThemePreviewService,
        private readonly EmailThemeRepository $emailThemeRepository
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $site): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $site);
            $result = $this->emailThemeRepository->search($criteria);

            $collection = new PaginatedResourceCollection($result, EmailThemeResource::class);

            return $this->resourceResponse($collection->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateEmailThemeRequest $request, string $site): JsonResponse
    {
        try {
            $data = $request->validated();
            $siteId = Site::resolveSite($site);

            $logoFile = $request->hasFile('logo') ? $request->file('logo') : null;

            $theme = $this->emailThemeService->createTheme($data, $siteId, $logoFile);

            return $this->jsonResponse([
                'theme' => EmailThemeResource::make($theme)->toArray(),
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(int|string $id, string $site): JsonResponse
    {
        try {
            if (is_numeric($id)) {
                $theme = $this->emailThemeService->getThemeById((int)$id);
            } else {
                $siteId = Site::resolveSite($site);
                $theme = $this->emailThemeService->getThemeBySlug($id, $siteId);
            }

            if (!$theme) {
                return $this->errorResponse('Email theme not found', 404);
            }

            return $this->jsonResponse([
                'theme' => EmailThemeResource::make($theme)->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, UpdateEmailThemeRequest $request, string $site): JsonResponse
    {
        try {
            $data = $request->validated();
            $logoFile = $request->hasFile('logo') ? $request->file('logo') : null;

            $theme = $this->emailThemeService->updateTheme($id, $data, $logoFile);

            return $this->jsonResponse([
                'theme' => EmailThemeResource::make($theme)->toArray(),
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $id, Request $request, string $site): JsonResponse
    {
        try {
            $result = $this->emailThemeService->deleteTheme($id);

            if (!$result) {
                return $this->errorResponse('Email theme not found', 404);
            }

            return $this->successResponse('Email theme deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function getActive(string $site): JsonResponse
    {
        try {
            $siteId = Site::resolveSite($site);
            $themes = $this->emailThemeService->getActiveThemes($siteId);

            return $this->jsonResponse([
                'themes' => EmailThemeResource::collection($themes)->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function setDefault(int $id, Request $request, string $site): JsonResponse
    {
        try {
            $siteId = Site::resolveSite($site);
            $result = $this->emailThemeService->setDefaultTheme($id, $siteId);

            if (!$result) {
                return $this->errorResponse('Failed to set default theme', 500);
            }

            return $this->successResponse('Default theme updated successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function alternatives(int $id, string $site): JsonResponse
    {
        try {
            $siteId = Site::resolveSite($site);
            $themes = $this->emailThemeService->getAlternativeThemes($id, $siteId);

            return $this->jsonResponse([
                'themes' => EmailThemeResource::collection($themes)->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function duplicate(int $id, Request $request, string $site): JsonResponse
    {
        try {
            $data = $request->all();
            $newName = $data['name'] ?? null;

            $cloneEmailTheme = Container::getInstance()->make(CloneEmailTheme::class);
            $results = $cloneEmailTheme->handle($id, $newName);

            return $this->jsonResponse($results, 201);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'not found')) {
                return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 404);
            }

            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to duplicate email theme: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $bulkDeleteEmailTheme = Container::getInstance()->make(BulkDeleteEmailTheme::class);
            $result = $bulkDeleteEmailTheme->handle($data['ids']);

            return $this->resourceResponse([
                'message' => 'Bulk delete completed. Deleted: ' . count($result['deleted']) . ', Failed: ' . count($result['failed']),
                'result' => $result,
            ], 200);
        } catch (ValidationException $e) {
            return $this->resourceResponse(['error' => 'Validation failed', 'errors' => $e->getErrors()], 422);
        } catch (Exception $e) {
            return $this->resourceResponse(['error' => 'Bulk delete failed: ' . $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Preview endpoints
    // -------------------------------------------------------------------------

    /**
     * Render a live preview from an existing saved theme.
     *
     * GET /cms/{site}/email-themes/{id}/preview?sample=default|minimal|promotion
     */
    public function preview(int $id, Request $request): JsonResponse
    {
        try {
            $theme = $this->emailThemeService->getThemeById($id);

            if (!$theme) {
                return $this->errorResponse('Email theme not found', 404);
            }

            $sampleType = $request->query('sample', 'default');
            $html = $this->emailThemePreviewService->renderFromModel($theme, $sampleType);

            return $this->jsonResponse(['html' => $html]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Render a live preview from raw (unsaved) editor data.
     *
     * POST /cms/{site}/email-themes/preview
     *
     * Body: { theme: {...}, sample: 'default'|'minimal'|'promotion' }
     */
    public function previewFromData(Request $request, string $site): JsonResponse
    {
        try {
            $body = $request->all();
            $themeData = $body['theme'] ?? [];
            $sampleType = $body['sample'] ?? 'default';

            if (empty($themeData)) {
                return $this->errorResponse('Theme data is required', 422);
            }

            $html = $this->emailThemePreviewService->renderFromData($themeData, $sampleType);

            return $this->jsonResponse(['html' => $html]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}