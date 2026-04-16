<?php

namespace App\Controllers\Product;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Resources\ProductVariantResource;
use App\Search\SearchCriteriaParser;
use App\Services\Product\VariantService;
use Exception;

class VariantController extends Controller
{
    protected VariantService $variantService;

    public function __construct(VariantService $variantService)
    {
        $this->variantService = $variantService;
        parent::__construct();
    }

    public function index(Request $request, string $site): JsonResponse
    {
        try {

            // Use search infrastructure
            $criteria = SearchCriteriaParser::fromRequest($request, $site);

            $result = $this->variantService->searchVariants($criteria);

            $collection = new PaginatedResourceCollection($result, ProductVariantResource::class);

            return $this->resourceResponse($collection->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $variant = $this->variantService->getVariant($id);

            if (!$variant) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Variant not found'
                ], 404);
            }

            return $this->resourceResponse(['data' => $variant->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->only([
                'product_id',
                'sku',
                'name',
                'price',
                'sale_price',
                'price_modifier',
                'is_active',
                'images',
                'attributes',
            ]);

            $variant = $this->variantService->createVariant($data);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Variant created successfully',
                'data' => $variant->toArray()
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $data = $request->only([
                'sku',
                'name',
                'price',
                'sale_price',
                'price_modifier',
                'is_active',
                'images',
            ]);

            $updated = $this->variantService->updateVariant($id, $data);

            if (!$updated) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Variant not found'
                ], 404);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Variant updated successfully'
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->variantService->deleteVariant($id);

            if (!$deleted) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Variant not found'
                ], 404);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Variant deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateImages(int $id, Request $request): JsonResponse
    {
        try {
            $images = $request->input('images', []);

            $updated = $this->variantService->updateVariantImages($id, $images);

            if (!$updated) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Variant not found'
                ], 404);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Images updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $result = $this->variantService->toggleVariantStatus($id);

            if (!$result) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Variant not found'
                ], 404);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Status updated successfully',
                'is_active' => $result['is_active']
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}