<?php

namespace App\Services;

use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
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
        return $this->repository->find($id, ['availableMerchants', 'availableMerchants.merchant']);
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

        $variantIdMapping = []; // Maps form indices to actual DB IDs
        if (!empty($variants)) {
            $variantIds = $this->repository->syncVariants($product->id, $variants);

            // Create mapping: form index -> actual DB ID
            foreach ($variants as $index => $variant) {
                if (isset($variantIds[$index])) {
                    // Form sends variant_id as 1, 2, 3... (1-indexed)
                    // We need to map these to the actual database IDs
                    $variantIdMapping[$index + 1] = $variantIds[$index];
                }
            }
        }

        if (!empty($merchants)) {
            // Map merchant variant_ids from form indices to actual DB IDs
            $mappedMerchants = array_map(function($merchantData) use ($variantIdMapping) {
                if (!empty($merchantData['variant_id']) && isset($variantIdMapping[$merchantData['variant_id']])) {
                    $merchantData['variant_id'] = $variantIdMapping[$merchantData['variant_id']];
                }
                return $merchantData;
            }, $merchants);

            $productMerchantIds = $this->repository->syncMerchants($product->id, $mappedMerchants);

            // Record price history for each merchant
            foreach ($productMerchantIds as $index => $productMerchantId) {
                $merchantData = $mappedMerchants[$index]; // Use mapped data
                $prices = $this->getEffectiveMerchantPrices($merchantData, $product->id);

                if ($prices['price'] !== null) {
                    $this->repository->recordMerchantPriceHistory(
                        $product->id,
                        $productMerchantId,
                        $prices['price'],
                        $merchantData['id'],
                        $prices['sale_price']
                    );
                }
            }
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
            $oldMerchants = $this->repository->getProductMerchantsWithDetails($product->id)->keyBy('id');
            $merchantIds = $this->repository->syncMerchants($product->id, $merchants);

            // Record price history for merchants with price changes
            foreach ($merchants as $index => $merchantData) {
                $merchantId = $merchantIds[$index];

                // Find old merchant by id or merchant_id + variant_id
                $oldMerchant = null;
                if (!empty($merchantData['id']) && $oldMerchants->has($merchantData['id'])) {
                    $oldMerchant = $oldMerchants->get($merchantData['id']);
                }

                $newPrices = $this->getEffectiveMerchantPrices($merchantData, $product->id);
                $oldPrice = $oldMerchant && isset($oldMerchant['price']) ? $oldMerchant['price'] : null;
                $oldSalePrice = $oldMerchant && isset($oldMerchant['sale_price']) ? $oldMerchant['sale_price'] : null;

                // Record if new merchant or price changed
                if (!$oldMerchant || $oldPrice != $newPrices['price'] || $oldSalePrice != $newPrices['sale_price']) {
                    if ($newPrices['price'] !== null) {
                        $this->repository->recordMerchantPriceHistory(
                            $product->id,
                            $merchantId,
                            $newPrices['price'],
                            $merchantData['id'],
                            $newPrices['sale_price']
                        );
                    }
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

    /**
     * Get the effective prices for a merchant, considering overrides and variant prices
     */
    protected function getEffectiveMerchantPrices(array $merchantData, int $productId): array
    {
        $price = null;
        $salePrice = null;

        // Get variant if specified
        $variant = null;
        if (!empty($merchantData['variant_id'])) {
            $variants = $this->repository->getVariants($productId);
            $variant = $variants->first(function($v) use ($merchantData) {
                return $v->id == $merchantData['variant_id'];
            });
        }

        // Determine effective regular price
        if (!empty($merchantData['override_price'])) {
            $price = $merchantData['price'] ?? null;
        } elseif ($variant) {
            $price = $variant->price ?? null;
        } else {
            $price = $merchantData['price'] ?? null;
        }

        // Determine effective sale price
        if (!empty($merchantData['override_sale_price'])) {
            $salePrice = $merchantData['sale_price'] ?? null;
        } elseif ($variant) {
            $salePrice = $variant->sale_price ?? null;
        } else {
            $salePrice = $merchantData['sale_price'] ?? null;
        }

        return [
            'price' => $price,
            'sale_price' => $salePrice
        ];
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
            Logger::error('Failed to delete image: ' . $e->getMessage());
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
}