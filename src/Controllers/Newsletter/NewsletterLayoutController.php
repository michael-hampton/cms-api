<?php

namespace App\Controllers\Newsletter;

use App\Controllers\Controller;
use App\Enums\Newsletters\LayoutVersionState;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Repositories\Newsletters\NewsletterLayoutRepository;
use App\Requests\Newsletter\CloneNewsletterLayoutRequest;
use App\Requests\Newsletter\NewsletterLayoutMigrationReportRequest;
use App\Requests\Newsletter\StoreNewsletterLayoutRequest;
use App\Resources\NewsletterLayoutResource;
use App\Services\Newsletter\NewsletterLayoutService;

class NewsletterLayoutController extends Controller
{
    public function __construct(
        private readonly NewsletterLayoutService    $layoutService,
        private readonly Logger                     $logger,
        private readonly NewsletterLayoutRepository $newsletterLayoutRepository,
    )
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $siteId = (int)$request->input('site_id') ?: SiteContext::getId();
            $layouts = $this->layoutService->getAllLayouts($siteId);

            return $this->resourceResponse(NewsletterLayoutResource::collection($layouts)->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function systemLayouts(): JsonResponse
    {
        try {
            $layouts = $this->layoutService->getSystemLayouts();

            return $this->resourceResponse(NewsletterLayoutResource::collection($layouts)->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(StoreNewsletterLayoutRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $layout = $this->layoutService->createLayout(
                name: $data['name'],
                slug: $data['slug'],
                layoutDefinition: $data['layout_definition'] ?? [],
                isSystemLayout: false,
                createdBy: $data['created_by'] ?? null,
                siteId: SiteContext::getId(),
            );

            return $this->jsonResponse(NewsletterLayoutResource::make($layout)->toArray(), 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (\Exception $e) {
            $this->logger->error('Failed to create newsletter layout', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to create layout', 500);
        }
    }

    public function clone(CloneNewsletterLayoutRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->validated();
            $cloned = $this->layoutService->cloneLayout(
                sourceLayoutId: $id,
                newName: $data['name'],
                newSlug: $data['slug'],
                clonedBy: $data['cloned_by'] ?? Auth::id(),
                siteId: (int)$data['site_id'] ?? SiteContext::getId(),
            );

            return $this->jsonResponse(NewsletterLayoutResource::make($cloned)->toArray(), 201);
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
            $layout = $this->newsletterLayoutRepository->find($id);

            if (!$layout) {
                return $this->errorResponse('Layout not found', 404);
            }

            $versions = $this->layoutService->getLayoutVersionHistory($id);

            return $this->resourceResponse(['versions' => $versions->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addVersion(Request $request, int $id): JsonResponse
    {
        try {
            $layout = $this->newsletterLayoutRepository->find($id);

            if (!$layout) {
                return $this->errorResponse('Layout not found', 404);
            }

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
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Failed to transition state', 500);
        }
    }

    public function migrationReport(NewsletterLayoutMigrationReportRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $report = $this->layoutService->buildMigrationReport(
                $data['old_version_id'],
                $data['new_version_id']
            );

            return $this->resourceResponse(['report' => $report]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}