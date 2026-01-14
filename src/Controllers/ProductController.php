<?php

namespace App\Controllers;

use App\Actions\Product\CloneProduct;
use App\Framework\Container;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\SiteContext;
use App\Models\ProductSpecificationGroup;
use App\Models\ProductVariant;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductSpecificationGroupRepository;
use App\Requests\CreateProductRequest;
use App\Requests\UpdateProductRequest;
use App\Resources\ProductResource;
use App\Search\SearchCriteriaParser;
use App\Services\Product\ProductService;
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

            $collection = new PaginatedResourceCollection($result, ProductResource::class);

            return $this->resourceResponse($collection->toArray());
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

            $duplicateProduct = Container::getInstance()->make(CloneProduct::class);

            $results = $duplicateProduct->handle(
                $id,
                $newName,
                $targetSiteId,
                $cloneRelations
            );

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

    public function merchants(Request $request, string $siteName): JsonResponse
    {
        try {
            $merchants = $this->productRepository->getAllMerchantLookups();

            return $this->resourceResponse([
                'success' => true,
                'items' => $merchants->map(fn($m) => [
                    'id' => $m->id,
                    'name' => $m->name
                ])->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function productMerchants(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $merchants = $this->productRepository->getProductMerchantsWithDetails($id);

            return $this->resourceResponse([
                'success' => true,
                'items' => $merchants->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function variants(int $id, string $siteName): JsonResponse
    {
        try {
            $variants = $this->productRepository->getVariants($id);

            return $this->resourceResponse([
                'success' => true,
                'items' => $variants->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateVariant(int $productId, int $variantId, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->only(['sku', 'name', 'price', 'sale_price', 'price_modifier', 'is_active']);

            $updated = $this->productRepository->updateVariant($variantId, $data);

            if (!$updated) {
                return $this->jsonResponse(['message' => 'Variant not found'], 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Variant updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function deleteVariant(int $productId, int $variantId, string $siteName): JsonResponse
    {
        try {
            $deleted = $this->productRepository->deleteVariant($variantId);

            if (!$deleted) {
                return $this->jsonResponse(['message' => 'Variant not found'], 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Variant deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateVariantImages(int $productId, int $variantId, Request $request, string $siteName): JsonResponse
    {
        try {
            $images = $request->input('images', []);

            // Validate variant exists and belongs to product
            $variant = ProductVariant::where('id', $variantId)
                ->where('product_id', $productId)
                ->first();

            if (!$variant) {
                return $this->jsonResponse(['message' => 'Variant not found'], 404);
            }

            // Sync images
            $this->productRepository->syncVariantImages($variantId, $productId, $images);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Images updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function pages(int $id, Request $request, string $siteName): JsonResponse
    {
        try {

            $pages = $this->productRepository->getProductPages($id);

            return $this->resourceResponse([
                'success' => true,
                'pages' => $pages->map(fn($page) => [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'type' => $this->extractBlockType($page->blocks),
                    'block_count' => $page->blocks->count(),
                    'status' => $page->status,
                    'created_at' => $page->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $page->updated_at->format('Y-m-d H:i:s')
                ])->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    private function extractBlockType($blocks): array
    {
        return $blocks
            ->whereIn('type', ['product', 'deal'])
            ->pluck('type')
            ->unique()
            ->toArray();
    }

    public function specificationGroups(string $siteName): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();
            $repository = app(ProductSpecificationGroupRepository::class);

            $groups = ProductSpecificationGroup::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(function ($group) {
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'slug' => $group->slug,
                    ];
                });

            return $this->resourceResponse([
                'success' => true,
                'groups' => $groups->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}