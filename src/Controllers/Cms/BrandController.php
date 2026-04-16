<?php

namespace App\Controllers\Cms;

use App\Actions\Brand\BulkDeleteBrand;
use App\Actions\Brand\CloneBrand;
use App\Actions\Brand\MergeBrand;
use App\Controllers\Controller;
use App\Framework\Container;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Models\Site;
use App\Requests\BulkDeleteRequest;
use App\Requests\CreateBrandRequest;
use App\Requests\UpdateBrandRequest;
use App\Resources\BrandResource;
use App\Search\SearchCriteriaParser;
use App\Services\Cms\BrandService;
use Exception;

class BrandController extends Controller
{
    public function __construct(private BrandService $brandService)
    {
        parent::__construct();
    }

    public function index(Request $request, string $site): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $site);
            $result = $this->brandService->search($criteria);

            $collection = new PaginatedResourceCollection($result, BrandResource::class);
            return $this->resourceResponse($collection->toArray(), 200);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateBrandRequest $request, string $site): JsonResponse
    {
        try {
            $data = $request->validated();
            $logoFile = $request->file('logo');
            $siteId = Site::resolveSite($site);

            $brand = $this->brandService->createBrand($data,$siteId, $logoFile);

            return $this->jsonResponse([
                'brand' => BrandResource::make($brand)->toArray()
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            if (is_numeric($id)) {
                $brand = $this->brandService->getBrandById((int)$id);
            } else {
                $brand = $this->brandService->getBrandBySlug($id);
            }

            if (!$brand) {
                return $this->errorResponse('Brand not found', 404);
            }

            return $this->jsonResponse([
                'brand' => BrandResource::make($brand)->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, UpdateBrandRequest $request, string $site): JsonResponse
    {
        try {
            $data = $request->validated();
            $logoFile = $request->file('logo');

            $brand = $this->brandService->updateBrand($id, $data, $logoFile);

            return $this->jsonResponse([
                'brand' => BrandResource::make($brand)->toArray()
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
            $reassignToBrandId = $request->get('reassign_to');

            $result = $this->brandService->delete($id, $reassignToBrandId);

            if (!$result) {
                return $this->errorResponse('Brand not found', 404);
            }

            return $this->successResponse('Brand deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function checkDelete(int $id, string $site): JsonResponse
    {
        try {
            $result = $this->brandService->checkDeletable($id);
            return $this->jsonResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function alternatives(int $id, string $site): JsonResponse
    {
        try {
            $brands = $this->brandService->getAlternativeBrands($id);
            return $this->jsonResponse([
               'brands' => BrandResource::collection($brands)->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function merge(Request $request, string $site): JsonResponse
    {
        try {
            $sourceBrandId = $request->get('source_brand_id');
            $targetBrandId = $request->get('target_brand_id');

            $mergeBrand = Container::getInstance()->make(MergeBrand::class);

            $result = $mergeBrand->handle($sourceBrandId, $targetBrandId);

            return $this->successResponse('Brands merged successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function active(string $site): JsonResponse
    {
        try {
            $brands = $this->brandService->getActiveBrands();
            return $this->jsonResponse([
                'brands' => BrandResource::collection($brands)->toArray()
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

            $cloneBrand = Container::getInstance()->make(CloneBrand::class);

            $results = $cloneBrand->handle($id, $newName);

            return $this->jsonResponse($results, 201);

        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'not found') !== false) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 404);
            }

            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to duplicate brand: ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $bulkDeleteBrand = Container::getInstance()->make(BulkDeleteBrand::class);

            $result = $bulkDeleteBrand->handle($data['ids']);

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
}