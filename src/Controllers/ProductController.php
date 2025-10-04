<?php

namespace App\Controllers;

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

    public function index(Request $request): JsonResponse
    {
        try {
            // Use search infrastructure
            $criteria = SearchCriteriaParser::fromRequest($request);
            $result = $this->productRepository->search($criteria);

            return $this->searchResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateProductRequest $request): JsonResponse
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
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
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

    public function update(UpdateProductRequest $request, int $id): JsonResponse
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
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function destroy(int $id): JsonResponse
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
}