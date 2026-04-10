<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Exceptions\OpenCollab\UnauthorisedPageAccessException;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Resource\ResourceCollection;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\Pages\PageRepository;
use App\Requests\OpenCollab\StoreContributorPageRequest;
use App\Requests\OpenCollab\UpdateContributorPageRequest;
use App\Resources\OpenCollab\ContributorPageResource;
use App\Services\OpenCollab\ContributorPageService;

class ContributorPageController extends Controller
{
    public function __construct(
        private readonly ContributorPageService $contributorPageService,
        private readonly PageRepository         $pageRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/open-collab/pages
     * Returns all pages owned by the authenticated contributor.
     */
    public function index(): JsonResponse
    {
        $pages = $this->pageRepository->getContributorPages(
            Auth::id(),
            SiteContext::getId(),
        );

        $collection = new ResourceCollection($pages, ContributorPageResource::class);

        return $this->resourceResponse(
            $collection->toArray()
        );
    }

    /**
     * POST /api/{site}/open-collab/pages
     */
    public function store(StoreContributorPageRequest $request): JsonResponse
    {
        try {
            $page = $this->contributorPageService->createPage(
                requestData: $request->validated(),
                contributorId: Auth::id(),
                siteId: SiteContext::getId(),
            );

            return $this->jsonResponse(
                ['page' => (new ContributorPageResource($page))->toArray()],
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->getErrors());
        }
    }

    /**
     * PUT /api/{site}/open-collab/pages/{id}
     */
    public function update(UpdateContributorPageRequest $request, int $id): JsonResponse
    {
        try {
            $page = $this->contributorPageService->updatePage(
                pageId: $id,
                requestData: $request->all(),
                contributorId: Auth::id(),
                siteId: SiteContext::getId(),
            );

            return $this->successResponse('success',
                ['page' => (new ContributorPageResource($page))->toArray()]
            );
        } catch (UnauthorisedPageAccessException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * DELETE /api/{site}/open-collab/pages/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->contributorPageService->deletePage($id, Auth::id());

            return $this->resourceResponse([], 204);
        } catch (UnauthorisedPageAccessException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }
}