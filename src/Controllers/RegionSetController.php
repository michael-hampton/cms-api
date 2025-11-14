<?php

namespace App\Controllers;

use App\Actions\BulkActivateRegionSets;
use App\Actions\BulkDeactivateRegionSets;
use App\Actions\BulkDeleteRegionSet;
use App\Actions\CloneProduct;
use App\Actions\CloneRegionSet;
use App\Framework\Container;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Repositories\PageRepository;
use App\Repositories\RegionSetRepository;
use App\Requests\BulkDeleteRequest;
use App\Resources\PageResource;
use App\Resources\RegionSetResource;
use App\Search\SearchCriteriaParser;
use App\Services\RegionSetService;
use Exception;

class RegionSetController extends Controller
{
    private RegionSetService $service;
    private RegionSetRepository $repository;
    private PageRepository $pageRepository;

    public function __construct(
        RegionSetService $service,
        RegionSetRepository $repository,
        PageRepository $pageRepository
    ) {
        $this->service = $service;
        $this->repository = $repository;
        $this->pageRepository = $pageRepository;
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $result = $this->repository->search($criteria);

            return $this->searchResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(Request $request, string $siteName): JsonResponse
    {
        try {
            $requestData = $request->all();
            $regionSet = $this->service->create($requestData);

            return $this->jsonResponse(['region_set' => $regionSet->toArrayWithRelations()], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $regionSet = $this->repository->findWithRelations($id);

            if (!$regionSet) {
                return $this->errorResponse('Region set not found', 404);
            }

            return $this->jsonResponse(['region_set' => $regionSet->toArrayWithRelations()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $requestData = $request->all();
            $regionSet = $this->service->update($id, $requestData);

            return $this->jsonResponse(['region_set' => $regionSet->toArrayWithRelations()]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $reassignToId = $request->get('reassign_to_region_set_id');
            $result = $this->service->delete($id, $reassignToId);

            if (!$result) {
                return $this->errorResponse('Region set not found', 404);
            }

            return $this->successResponse('Region set deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function checkDeletable(int $id): JsonResponse
    {
        try {
            $result = $this->service->checkDeletable($id);
            return $this->jsonResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getAlternatives(int $id): JsonResponse
    {
        try {
            $alternatives = $this->service->getAlternativeRegionSets($id);
            return $this->jsonResponse(['region_sets' => $alternatives->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function duplicate(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $cloneRegionSet = Container::getInstance()->make(CloneRegionSet::class);
            $newName = $request->get('name');
            $newRegionSet = $cloneRegionSet->handle($id, $newName);

            return $this->jsonResponse(['region_set' => $newRegionSet->toArrayWithRelations()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function reorder(Request $request, string $siteName): JsonResponse
    {
        try {
            $orderedIds = $request->get('ordered_ids', []);
            $result = $this->service->reorder($orderedIds);

            return $this->successResponse('Region sets reordered successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getActive(string $siteName): JsonResponse
    {
        try {
            $regionSets = $this->repository->getActive();
            return $this->jsonResponse(['region_sets' => $regionSets->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getPages(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $regionSet = $this->repository->find($id);

            if (!$regionSet) {
                return $this->errorResponse('Region set not found', 404);
            }

            // Add region_set filter to the request
            $request->merge(['region_set_id' => $id]);

            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);

            $result = $this->pageRepository->search($criteria);

            $collection = new PaginatedResourceCollection($result, PageResource::class);
            return $this->resourceResponse($collection->toArray());
        } catch (Exception $e) {
            echo $e->getMessage();
            die;
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function searchAvailablePages(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $query = $request->get('q', '');
            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);

            $pages = $this->service->searchAvailablePages($id, $query, $perPage, $page);

            return $this->jsonResponse(['pages' => $pages['data']->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function assignPages(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $pageIds = $request->get('page_ids', []);

            if (empty($pageIds)) {
                return $this->errorResponse('No pages provided', 400);
            }

            $this->service->assignPages($id, $pageIds, SiteContext::getId());

            return $this->successResponse("Successfully assigned " . count($pageIds) . " page(s)");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function unassignPages(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $pageIds = $request->get('page_ids', []);

            if (empty($pageIds)) {
                return $this->errorResponse('No pages provided', 400);
            }

            $this->service->unassignPages($id, $pageIds);

            return $this->successResponse("Successfully unassigned " . count($pageIds) . " page(s)");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $bulkDeleteRegionSet = Container::getInstance()->make(BulkDeleteRegionSet::class);

            $result = $bulkDeleteRegionSet->handle($data['ids']);

            return $this->resourceResponse([
                'message' => "Bulk delete completed. Deleted: " . count($result['deleted']) . ", Failed: " . count($result['failed']),
                'result' => $result
            ], 200);
        } catch (ValidationException $e) {
            return $this->resourceResponse(['error' => 'Validation failed', 'errors' => $e->getErrors()], 422);
        } catch (\Exception $e) {
            return $this->resourceResponse(['error' => 'Bulk delete failed: ' . $e->getMessage()], 500);
        }
    }

    public function bulkActivate(BulkDeleteRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $handler = Container::getInstance()->make(BulkActivateRegionSets::class);

            $result = $handler->handle($data['ids']);

            return $this->resourceResponse([
                'message' => "Bulk activate completed. Updated: " . count($result['updated']) . ", Failed: " . count($result['failed']),
                'result' => $result
            ], 200);
        } catch (ValidationException $e) {
            return $this->resourceResponse(['error' => 'Validation failed', 'errors' => $e->getErrors()], 422);
        } catch (\Exception $e) {
            return $this->resourceResponse(['error' => 'Bulk activate failed: ' . $e->getMessage()], 500);
        }
    }

    public function bulkDeactivate(BulkDeleteRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $handler = Container::getInstance()->make(BulkDeactivateRegionSets::class);

            $result = $handler->handle($data['ids']);

            return $this->resourceResponse([
                'message' => "Bulk deactivate completed. Updated: " . count($result['updated']) . ", Failed: " . count($result['failed']),
                'result' => $result
            ], 200);
        } catch (ValidationException $e) {
            return $this->resourceResponse(['error' => 'Validation failed', 'errors' => $e->getErrors()], 422);
        } catch (\Exception $e) {
            return $this->resourceResponse(['error' => 'Bulk deactivate failed: ' . $e->getMessage()], 500);
        }
    }
}