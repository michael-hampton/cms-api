<?php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\SiteContext;
use App\Repositories\PageRepository;
use App\Repositories\TerritoryRepository;
use App\Resources\PageResource;
use App\Resources\TerritoryResource;
use App\Search\SearchCriteriaParser;
use App\Services\TerritoryService;
use Exception;

class TerritoryController extends Controller
{
    private TerritoryService $service;
    private TerritoryRepository $repository;
    private PageRepository $pageRepository;

    public function __construct(
        TerritoryService $service,
        TerritoryRepository $repository,
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
            $territory = $this->service->create($requestData);

            return $this->jsonResponse(['territory' => $territory->toArrayWithRelations()], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $territory = $this->repository->findWithRelations($id);

            if (!$territory) {
                return $this->errorResponse('Territory not found', 404);
            }

            return $this->jsonResponse(['territory' => $territory->toArrayWithRelations()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $requestData = $request->all();
            $territory = $this->service->update($id, $requestData);

            return $this->jsonResponse(['territory' => $territory->toArrayWithRelations()]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $reassignToId = $request->get('reassign_to_territory_id');
            $result = $this->service->delete($id, $reassignToId);

            if (!$result) {
                return $this->errorResponse('Territory not found', 404);
            }

            return $this->successResponse('Territory deleted successfully');
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
            $alternatives = $this->service->getAlternativeTerritories($id);
            return $this->jsonResponse(['territories' => $alternatives->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function reorder(Request $request, string $siteName): JsonResponse
    {
        try {
            $orderedIds = $request->get('ordered_ids', []);
            $result = $this->service->reorder($orderedIds);

            return $this->successResponse('Territories reordered successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkUpdateRegionSet(Request $request, string $siteName): JsonResponse
    {
        try {
            $territoryIds = $request->get('territory_ids', []);
            $newRegionSetId = $request->get('region_set_id');

            if (empty($territoryIds) || !$newRegionSetId) {
                return $this->errorResponse('Missing required parameters', 400);
            }

            $result = $this->service->bulkUpdateRegionSet($territoryIds, $newRegionSetId);

            return $this->successResponse('Territories updated successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getByRegionSet(int $regionSetId, string $siteName): JsonResponse
    {
        try {
            $territories = $this->repository->getByRegionSet($regionSetId);
            return $this->jsonResponse(['territories' => $territories->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getActive(string $siteName): JsonResponse
    {
        try {
            $territories = $this->repository->getActive();
            return $this->jsonResponse(['territories' => $territories->toArray()]);
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

    public function getPages(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $territory = $this->repository->find($id);

            if (!$territory) {
                return $this->errorResponse('Territory not found', 404);
            }

            // Add territory filter to the request
            $request->merge(['territory_id' => $id]);

            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);

            $result = $this->pageRepository->search($criteria);

            $collection = new PaginatedResourceCollection($result, PageResource::class);
            return $this->resourceResponse($collection->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}