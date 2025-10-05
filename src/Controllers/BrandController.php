<?php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Resources\BrandResource;
use App\Search\SearchCriteriaParser;
use App\Services\BrandService;
use Exception;

class BrandController extends Controller
{
    public function __construct(private BrandService $brandService)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request);
            $result = $this->brandService->search($criteria);

            $collection = new PaginatedResourceCollection($result, BrandResource::class);
            return $this->resourceResponse($collection->toArray(), 200);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $logoFile = $request->file('logo');

            $brand = $this->brandService->createBrand($data, $logoFile);

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

    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $data = $request->all();
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

    public function destroy(int $id, Request $request): JsonResponse
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

    public function checkDelete(int $id): JsonResponse
    {
        try {
            $result = $this->brandService->checkDeletable($id);
            return $this->jsonResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function alternatives(int $id): JsonResponse
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

    public function merge(Request $request): JsonResponse
    {
        try {
            $sourceBrandId = $request->get('source_brand_id');
            $targetBrandId = $request->get('target_brand_id');

            $result = $this->brandService->mergeBrands($sourceBrandId, $targetBrandId);

            return $this->successResponse('Brands merged successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function active(): JsonResponse
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

    public function duplicate(int $id, Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $newName = $data['name'] ?? null;

            $duplicatedBrand = $this->brandService->duplicateBrand($id, $newName);

            return $this->jsonResponse($duplicatedBrand->toArray(), 201);

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
}