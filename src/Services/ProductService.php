<?php

namespace App\Services;

use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\Model;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Repositories\ProductViewRepository;
use Exception;

class ProductService
{
    protected ProductRepository $repository;
    protected ImageUploadService $imageUploadService;

    public function __construct(
        ProductRepository                      $repository,
        ImageUploadService                     $imageUploadService,
        private readonly ProductViewRepository $productViewRepository
    )
    {
        $this->repository = $repository;
        $this->imageUploadService = $imageUploadService;

        // Configure for product images
        $this->imageUploadService
            ->setAllowedMimeTypes([
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/gif',
                'image/webp'
            ])
            ->setMaxFileSize(10 * 1024 * 1024); // 10MB
    }

    public function getAllProducts(): Collection
    {
        return $this->repository->all();
    }

    public function getPaginatedProducts(int $perPage = 15): array
    {
        return $this->repository->paginate($perPage);
    }

    public function getProduct(int $id): ?Model
    {
        return $this->repository->find($id);
    }

    public function createProduct(array $data, ?UploadedFile $imageFile = null): Model
    {
        // Handle image upload if file provided
        if ($imageFile && $imageFile->isValid()) {
            try {
                $data['image'] = $this->imageUploadService->uploadToPath(
                    $imageFile,
                    'products/' . date('Y-m')
                );
            } catch (Exception $e) {
                throw new Exception('Failed to upload product image: ' . $e->getMessage());
            }
        } elseif (isset($data['image']) && $this->isBase64Image($data['image'])) {
            $data['image'] = $this->saveBase64Image($data['image']);
        }

        // Extract related data
        $images = $data['images'] ?? [];
        $merchants = $data['merchants'] ?? [];
        $variants = $data['variants'] ?? [];
        $specifications = $data['specifications'] ?? [];

        // Remove from main data array
        unset($data['images'], $data['merchants'], $data['variants'], $data['specifications']);

        // Create product
        $product = $this->repository->create($data);

        // Record initial price history
        $this->repository->recordPriceHistory($product);

        // Create related records
        if (!empty($images)) {
            $this->repository->syncImages($product->id, $images);
        }
        if (!empty($merchants)) {
            $merchantIds = $this->repository->syncMerchants($product->id, $merchants);

            // Record price history for each merchant
            foreach ($merchantIds as $index => $merchantId) {
                $this->repository->recordMerchantPriceHistory(
                    $product->id,
                    $merchantId,
                    $merchants[$index]['price']
                );
            }
        }
        if (!empty($variants)) {
            $this->repository->syncVariants($product->id, $variants);
        }
        if (!empty($specifications)) {
            $this->repository->syncSpecifications($product->id, $specifications);
        }

        return $product;
    }

    public function updateProduct(int $id, array $data, ?UploadedFile $imageFile = null): ?Model
    {
        $product = $this->repository->find($id);

        if (!$product) {
            return null;
        }

        // Store old prices for comparison
        $oldPrice = $product->price;
        $oldSalePrice = $product->sale_price;

        $oldImagePath = $product->image ?? null;

        // Handle image upload if file provided
        if ($imageFile && $imageFile->isValid()) {
            try {
                $data['image'] = $this->imageUploadService->uploadToPath(
                    $imageFile,
                    'products/' . date('Y-m'),
                    $oldImagePath
                );
            } catch (Exception $e) {
                throw new Exception('Failed to upload product image: ' . $e->getMessage());
            }
        } elseif (isset($data['image']) && $this->isBase64Image($data['image'])) {
            // Delete old image if replacing with base64
            if ($oldImagePath) {
                $this->imageUploadService->delete($oldImagePath);
            }
            $data['image'] = $this->saveBase64Image($data['image']);
        }

        // Extract related data
        $images = $data['images'] ?? null;
        $merchants = $data['merchants'] ?? null;
        $variants = $data['variants'] ?? null;
        $specifications = $data['specifications'] ?? null;

        // Remove from main data array
        unset($data['images'], $data['merchants'], $data['variants'], $data['specifications']);

        // Update product
        $product = $this->repository->update($id, $data);

        // Record price history if prices changed
        $newPrice = $data['price'] ?? $oldPrice;
        $newSalePrice = $data['sale_price'] ?? $oldSalePrice;

        if ($newPrice != $oldPrice || $newSalePrice != $oldSalePrice) {
            $this->repository->recordPriceHistory($product);
        }

        // Sync related records if provided
        if ($merchants !== null) {
            $oldMerchants = $this->repository->getMerchants($product->id)->keyBy('id');
            $merchantIds = $this->repository->syncMerchants($product->id, $merchants);

            // Record price history for merchants with price changes
            foreach ($merchants as $index => $merchantData) {
                $merchantId = $merchantIds[$index];
                $oldMerchant = !empty($merchantData['id']) && $oldMerchants->has($merchantData['id']) ? $oldMerchants->get($merchantData['id']) : null;

                // Record if new merchant or price changed
                if (!$oldMerchant || $oldMerchant->price != $merchantData['price']) {
                    $this->repository->recordMerchantPriceHistory(
                        $product->id,
                        $merchantId,
                        $merchantData['price']
                    );
                }
            }
        }
        if ($variants !== null) {
            $this->repository->syncVariants($product->id, $variants);
        }
        if ($specifications !== null) {
            $this->repository->syncSpecifications($product->id, $specifications);
        }

        if (!empty($images)) {
            $this->repository->syncImages($product->id, $images);
        }

        return $product;
    }

    public function deleteProduct(int $id): bool
    {
        $product = $this->repository->find($id);

        if (!$product) {
            return false;
        }

        // Delete image using ImageUploadService
        if ($product->image) {
            $this->deleteImage($product->image);
        }

        $productImages = $this->repository->getImages($id);

        foreach ($productImages as $image) {
            $this->deleteImage($image->url);
        }

        // Delete variant images
        $variants = $this->repository->getVariants($id);
        foreach ($variants as $variant) {
            $variantImages = $this->repository->getVariantImages($variant->id);

            $this->repository->deleteVariantImages($variant->id);

            foreach ($variantImages as $image) {
                $this->deleteImage($image->url);
            }
        }

        // Delete price history
        $this->repository->deletePriceHistory($id);

        return $this->repository->delete($id);
    }

    protected function deleteImage(string $path): void
    {
        try {
            $this->imageUploadService->delete($path);
        } catch (Exception $e) {
            // Log but don't fail
            error_log('Failed to delete image: ' . $e->getMessage());
        }
    }

    public function getProductsByCategory(string $category): Collection
    {
        return $this->repository->findByCategory($category);
    }

    public function getProductsByBrand(string $brand): Collection
    {
        return $this->repository->findByBrand($brand);
    }

    public function getOnSaleProducts(): Collection
    {
        return $this->repository->getOnSale();
    }

    public function getRelatedProducts(Product $product, int $limit = 8): Collection
    {
        return $this->repository->findRelated($product, $limit);
    }

    public function getRecentlyViewedProducts(int $limit = 6): Collection
    {
        $viewedIds = $_SESSION['recently_viewed'] ?? [];

        if (empty($viewedIds)) {
            return new Collection([]);
        }

        return $this->repository->getRecentlyViewed(array_slice($viewedIds, 0, $limit), $limit);
    }

    public function trackView(Product $product): void
    {
        if (!isset($_SESSION['recently_viewed'])) {
            $_SESSION['recently_viewed'] = [];
        }

        $viewedIds = $_SESSION['recently_viewed'];

        // Remove product if already in list
        $viewedIds = array_filter($viewedIds, fn($id) => $id !== $product->id);

        // Add to beginning
        array_unshift($viewedIds, $product->id);

        // Keep only last 20
        $_SESSION['recently_viewed'] = array_slice($viewedIds, 0, 20);

        // Track in database
        $this->trackProductView($product);
    }

    protected function trackProductView(Product $product): void
    {
        $sessionId = session_id();
        $userId = auth()->id();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        $this->productViewRepository->trackView($product, $userId, $sessionId, $ipAddress);
    }

    public function generateStructuredData(Product $product): array
    {
        return [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $product->description,
            'image' => $product->main_image_url ?? $product->image_url,
            'brand' => [
                '@type' => 'Brand',
                'name' => $product->brand->name ?? 'Unknown'
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => $product->sale_price ?? $product->price,
                'priceCurrency' => 'USD',
                'availability' => $product->in_stock ? 'InStock' : 'OutOfStock',
                'url' => '/products/' . $product->slug
            ]
        ];
    }

    protected function isBase64Image(string $string): bool
    {
        return preg_match('/^data:image\/(\w+);base64,/', $string);
    }

    protected function saveBase64Image(string $base64String): string
    {
        preg_match('/^data:image\/(\w+);base64,/', $base64String, $matches);
        $extension = $matches[1] ?? 'png';
        $imageData = substr($base64String, strpos($base64String, ',') + 1);
        $imageData = base64_decode($imageData);

        $filename = 'products/' . date('Y-m') . '/' . uniqid() . '.' . $extension;
        $fullPath = rtrim(config('upload.path', 'uploads'), '/') . '/' . $filename;

        $this->imageUploadService->ensureDirectoryExists(dirname($fullPath));
        file_put_contents($fullPath, $imageData);

        return $filename;
    }

    public function duplicateProduct(
        int $productId,
        ?string $newName = null,
        ?int $targetSiteId = null,
        array $cloneRelations = []
    ): Model    {
        $originalProduct = $this->repository->find($productId);

        if (!$originalProduct) {
            throw new \Exception("Product not found");
        }

        // Default all relations to true if not specified
        $cloneRelations = array_merge([
            'images' => true,
            'merchants' => true,
            'variants' => true,
            'specifications' => true,
        ], $cloneRelations);

        // Use target site or original product's site
        $siteId = $targetSiteId ?? $originalProduct->site_id;

        $data = [
            'name' => $newName ?? ($originalProduct->name . ' (Copy)'),
            'description' => $originalProduct->description,
            'price' => $originalProduct->price,
            'sale_price' => $originalProduct->sale_price,
            'brand_id' => $originalProduct->brand_id,
            'category_id' => $originalProduct->category_id,
            'status' => 'draft',
            'site_id' => $siteId,
            'meta_title' => $originalProduct->meta_title,
            'meta_description' => $originalProduct->meta_description,
            'meta_keywords' => $originalProduct->meta_keywords,
        ];

        // Generate unique slug
        $baseName = $data['name'];
        $slug = Str::slug($baseName);
        $counter = 1;

        while ($this->repository->findBySlugAndSite($slug, $siteId)) {
            $slug = Str::slug($baseName . '-' . $counter);
            $counter++;
        }

        $data['slug'] = $slug;

        // Duplicate image
        if ($originalProduct->image) {
            try {
                $data['image'] = $this->imageUploadService->duplicate($originalProduct->image);
            } catch (\Exception $e) {
                $data['image'] = null;
            }
        }

        // Create duplicated product
        $newProduct = $this->repository->create($data);

        // Duplicate related data
        $this->duplicateProductRelations($originalProduct->id, $newProduct->id, $cloneRelations);

        return $newProduct;
    }

    protected function duplicateProductRelations(int $originalId, int $newId, array $cloneRelations): void
    {
        // Duplicate images if selected
        if ($cloneRelations['images']) {
            $images = $this->repository->getImages($originalId);

            $imageData = [];
            foreach ($images as $image) {
                $newImageUrl = $this->duplicateImage($image->url);

                if ($newImageUrl) {
                    $imageData[] = [
                        'url' => $newImageUrl,
                        'alt' => $image->alt,
                        'is_primary' => $image->is_primary,
                        'sort_order' => $image->sort_order,
                    ];
                }
            }
            if (!empty($imageData)) {
                $this->repository->syncImages($newId, $imageData);
            }
        }

        // Duplicate merchants if selected
        if ($cloneRelations['merchants']) {
            $merchants = $this->repository->getMerchants($originalId);
            $merchantData = $merchants->map(fn($m) => [
                'name' => $m->name,
                'url' => $m->url,
                'price' => $m->price,
                'is_available' => $m->is_available,
            ])->toArray();
            if (!empty($merchantData)) {
                $this->repository->syncMerchants($newId, $merchantData);
            }
        }

        // Duplicate variants if selected
        if ($cloneRelations['variants']) {
            $variants = $this->repository->getVariants($originalId);
            $variantData = $variants->map(function($v) {
                $imageData = [];

                // Duplicate variant images
                if ($v->images) {
                    foreach ($v->images as $image) {
                        $newImageUrl = $this->duplicateImage($image->url);
                        if ($newImageUrl) {
                            $imageData[] = [
                                'url' => $newImageUrl,
                                'alt' => $image->alt,
                                'is_primary' => $image->is_primary,
                                'sort_order' => $image->sort_order,
                            ];
                        }
                    }
                }

                return [
                    'sku' => $v->sku . '-COPY',
                    'attributes' => $v->attributes,
                    'price_modifier' => $v->price_modifier,
                    'is_active' => false,
                    'images' => $imageData,
                ];
            })->toArray();

            if (!empty($variantData)) {
                $this->repository->syncVariants($newId, $variantData);
            }
        }

        // Duplicate specifications if selected
        if ($cloneRelations['specifications']) {
            $specifications = $this->repository->getSpecifications($originalId);
            $specData = $specifications->map(fn($s) => [
                'category' => $s->category,
                'key' => $s->key,
                'value' => $s->value,
                'sort_order' => $s->sort_order,
            ])->toArray();
            if (!empty($specData)) {
                $this->repository->syncSpecifications($newId, $specData);
            }
        }
    }

    protected function duplicateImage(string $originalPath): ?string
    {
        try {
            return $this->imageUploadService->duplicate($originalPath);
        } catch (\Exception $e) {
            return null;
        }
    }
}