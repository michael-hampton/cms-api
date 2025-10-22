<?php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Repositories\ProductRepository;
use App\Requests\CreateProductRequest;
use App\Requests\UpdateProductRequest;
use App\Search\SearchCriteriaParser;
use App\Services\ProductService;
use Exception;

class ProductController extends Controller
{
    protected ProductService $productService;
    private ProductRepository $productRepository;

    public function __construct(
        ProductService $productService,
        ProductRepository $productRepository
    ) {
        $this->productService = $productService;
        $this->productRepository = $productRepository;
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            // Use search infrastructure
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $result = $this->productRepository->search($criteria);

            return $this->searchResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateProductRequest $request, string $siteName): JsonResponse
    {
        try {
            // Get the image file if uploaded
            $imageFile = $request->hasFile('image') ? $request->file('image') : null;

            // Create product with image file
            $product = $this->productService->createProduct(
                $request->validated(),
                $imageFile
            );

            return $this->jsonResponse([
                'message' => 'Product created successfully',
                'product' => $product->toArray() // Make sure to call toArray()
            ], 201);
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
        $product = $this->productService->getProduct($id);

        if (!$product) {
            return $this->jsonResponse([
                'message' => 'Product not found'
            ], 404);
        }

        return $this->jsonResponse(['product' => $product]);
    }

    public function update(UpdateProductRequest $request, int $id, string $siteName): JsonResponse
    {
        try {
            $imageFile = $request->hasFile('image') ? $request->file('image') : null;
            $updated = $this->productService->updateProduct(
                $id,
                $request->validated(),
                $imageFile
            );

            if (!$updated) {
                return $this->jsonResponse([
                    'message' => 'Product not found'
                ], 404);
            }

            $product = $this->productService->getProduct($id);

            return $this->jsonResponse([
                'message' => 'Product updated successfully',
                'product' => $product
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        }
    }

    public function destroy(int $id, string $siteName): JsonResponse
    {
        $deleted = $this->productService->deleteProduct($id);

        if (!$deleted) {
            return $this->jsonResponse([
                'message' => 'Product not found'
            ], 404);
        }

        return $this->jsonResponse([
            'message' => 'Product deleted successfully'
        ], 200);
    }

    public function duplicate(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $newName = $data['name'] ?? null;
            $targetSiteId = $data['site_id'] ?? null;

            // Determine which relations to clone (default all true)
            $cloneRelations = [
                'images' => $data['clone_images'] ?? true,
                'merchants' => $data['clone_merchants'] ?? true,
                'variants' => $data['clone_variants'] ?? true,
                'specifications' => $data['clone_specifications'] ?? true,
            ];

            $duplicatedProduct = $this->productService->duplicateProduct(
                $id,
                $newName,
                $targetSiteId,
                $cloneRelations
            );

            return $this->jsonResponse($duplicatedProduct->toArray(), 201);

        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'not found') !== false) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 404);
            }

            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to duplicate product: ' . $e->getMessage()
            ], 500);
        }
    }

    public function priceHistory(int $id, Request $request): JsonResponse
    {
        $merchantId = $request->query('merchant_id');

        $history = $this->productRepository->getPriceHistory($id, $merchantId);

        if ($history->isEmpty()) {
            return $this->jsonResponse([
                'message' => 'No price history found',
                'data' => []
            ]);
        }

        return $this->jsonResponse($history->toArray());
    }
}