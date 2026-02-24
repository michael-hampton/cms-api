<?php

namespace App\Controllers\Newsletter;

use App\Controllers\Controller;
use App\Enums\Newsletter\LayoutVersionState;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Services\Newsletter\NewsletterLayoutService;

class NewsletterLayoutController extends Controller
{
    public function __construct(
        private readonly NewsletterLayoutService $layoutService,
        private readonly Logger                  $logger,
    )
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $siteId = (int)$request->input('site_id') ?: SiteContext::getId();
            $layouts = $this->layoutService->getAllLayouts($siteId);

            return $this->resourceResponse(['layouts' => $layouts->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function systemLayouts(): JsonResponse
    {
        try {
            $layouts = $this->layoutService->getSystemLayouts();
            return $this->resourceResponse(['layouts' => $layouts->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {

            $layout = $this->layoutService->createLayout(
                name: $request->input('name'),
                slug: $request->input('slug'),
                layoutDefinition: $request->input('layout_definition', []),
                isSystemLayout: false,
                createdBy: $request->input('created_by'),
                siteId: (int)$request->input('site_id') ?: SiteContext::getId(),  // ← NEW
            );

            return $this->jsonResponse(['layout' => $layout->toArray()], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create newsletter layout', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to create layout', 500);
        }
    }

    public function clone(Request $request, int $id): JsonResponse
    {
        try {
            $cloned = $this->layoutService->cloneLayout(
                sourceLayoutId: $id,
                newName: $request->input('name'),
                newSlug: $request->input('slug'),
                clonedBy: $request->input('cloned_by'),
                siteId: (int)$request->input('site_id') ?? SiteContext::getId(),  // ← NEW
            );

            return $this->jsonResponse(['layout' => $cloned->toArray()], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            $this->logger->error('Failed to clone newsletter layout', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to clone layout', 500);
        }
    }

    public function delete(int $id): JsonResponse
    {
        try {
            $this->layoutService->deleteLayout($id);
            return $this->successResponse('Layout deleted successfully');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete newsletter layout', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to delete layout', 500);
        }
    }

    public function versions(int $id): JsonResponse
    {
        try {
            $versions = $this->layoutService->getLayoutVersionHistory($id);
            return $this->resourceResponse(['versions' => $versions->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addVersion(Request $request, int $id): JsonResponse
    {
        try {
            $version = $this->layoutService->addLayoutVersion(
                layoutId: $id,
                layoutDefinition: $request->input('layout_definition', []),
                migrationScriptReference: $request->input('migration_script_reference'),
            );

            return $this->jsonResponse(['version' => $version->toArray()], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            $this->logger->error('Failed to add layout version', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to add layout version', 500);
        }
    }

    public function transitionState(Request $request, int $versionId): JsonResponse
    {
        try {
            $state = LayoutVersionState::from($request->input('state'));
            $version = $this->layoutService->transitionVersionState($versionId, $state);

            return $this->successResponse('State updated', ['version' => $version->toArray()]);
        } catch (\ValueError $e) {
            return $this->errorResponse('Invalid state value', 422);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            $this->logger->error('Failed to transition layout version state', [
                'version_id' => $versionId,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to transition state', 500);
        }
    }

    public function migrationReport(Request $request): JsonResponse
    {
        try {
            $report = $this->layoutService->buildMigrationReport(
                $request->input('old_version_id'),
                $request->input('new_version_id')
            );

            return $this->resourceResponse(['report' => $report]);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}