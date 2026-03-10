<?php

namespace App\Services\Product;

use App\Enums\Products\PriceChangeType;
use App\Events\Products\ProductCreatedEvent;
use App\Events\Products\ProductDeletedEvent;
use App\Events\Products\ProductPriceChangedEvent;
use App\Events\Products\ProductUpdatedEvent;
use App\Events\Products\ProductViewedEvent;
use App\Framework\Database\Database;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\Model;
use App\Models\Product;
use App\Repositories\Product\ProductRepository;
use App\Services\Shared\RequestContext;
use Exception;

class ProductService
{
    public function __construct(
        private readonly ProductRepository         $repository,
        private readonly ProductImageUploadService $imageUploadService,
        private readonly MerchantPricingResolver   $merchantPricingResolver,
        private readonly RequestContext            $requestContext,
        private readonly Database                  $database,
    )
    {
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
        return $this->database->transaction(function () use ($data, $imageFile) {
            // Handle image upload
            $data = $this->processImageUpload($data, $imageFile);

            // Extract related data
            $images = $data['images'] ?? [];
            $merchants = $data['merchants'] ?? [];
            $variants = $data['variants'] ?? [];
            $specifications = $data['specifications'] ?? [];
            $regionSetIds = $data['region_set_ids'] ?? [];

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

            $variantIdMapping = [];
            if (!empty($variants)) {
                $variantIdMapping = $this->createVariants($product->id, $variants);
            }

            if (!empty($merchants)) {
                $this->createMerchants($product, $merchants, $variantIdMapping);
            }

            if (!empty($specifications)) {
                $this->repository->syncSpecifications($product->id, $specifications);
            }

            if (!empty($regionSetIds)) {
                $this->repository->syncRegionSets($product->id, $regionSetIds);
            }

            // Emit event
            event(new ProductCreatedEvent($product, [
                'has_images' => !empty($images),
                'has_merchants' => !empty($merchants),
                'has_variants' => !empty($variants),
                'has_specifications' => !empty($specifications),
            ]));

            return $product;
        });
    }

    private function processImageUpload(array $data, ?UploadedFile $imageFile, ?string $oldPath = null): array
    {
        if ($imageFile && $imageFile->isValid()) {
            try {
                $data['image'] = $this->imageUploadService->upload($imageFile, $oldPath);
            } catch (Exception $e) {
                throw new Exception('Failed to upload product image: ' . $e->getMessage());
            }
        } elseif (isset($data['image']) && $this->imageUploadService->isBase64Image($data['image'])) {
            // Delete old image if replacing with base64
            if ($oldPath) {
                $this->deleteImageSafely($oldPath);
            }
            $data['image'] = $this->imageUploadService->saveBase64Image($data['image']);
        }

        return $data;
    }

    private function deleteImageSafely(string $path): void
    {
        try {
            $this->imageUploadService->delete($path);
        } catch (Exception $e) {
            Logger::error('Failed to delete image: ' . $e->getMessage(), [
                'path' => $path,
                'exception' => $e
            ]);
            // Don't throw - continue with deletion
        }
    }

    private function createVariants(int $productId, array $variants): array
    {
        $variantIds = $this->repository->syncVariants($productId, $variants);
        $variantIdMapping = [];

        // Create mapping: form index -> actual DB ID
        foreach ($variants as $index => $variant) {
            if (isset($variantIds[$index])) {
                // Form sends variant_id as 1, 2, 3... (1-indexed)
                // We need to map these to the actual database IDs
                $variantIdMapping[$index + 1] = $variantIds[$index];
            }
        }

        return $variantIdMapping;
    }

    private function createMerchants(Product $product, array $merchants, array $variantIdMapping): void
    {
        // Map merchant variant_ids from form indices to actual DB IDs
        $mappedMerchants = $this->mapMerchantVariantIds($merchants, $variantIdMapping);

        $productMerchantIds = $this->repository->syncMerchants($product->id, $mappedMerchants);

        // Record price history for each merchant
        foreach ($productMerchantIds as $index => $productMerchantId) {
            $merchantData = $mappedMerchants[$index];
            $variants = $this->repository->getVariants($product->id);
            $prices = $this->merchantPricingResolver->resolve($merchantData, $variants);

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

    private function mapMerchantVariantIds(array $merchants, array $variantIdMapping): array
    {
        return array_map(function ($merchantData) use ($variantIdMapping) {
            if (!empty($merchantData['variant_id']) && isset($variantIdMapping[$merchantData['variant_id']])) {
                $merchantData['variant_id'] = $variantIdMapping[$merchantData['variant_id']];
            }
            return $merchantData;
        }, $merchants);
    }

    public function updateProduct(int $id, array $data, ?UploadedFile $imageFile = null): ?Model
    {
        $product = $this->repository->find($id);

        if (!$product) {
            return null;
        }

        return $this->database->transaction(function () use ($product, $data, $imageFile) {
            // Store old prices for comparison
            $oldPrice = $product->price;
            $oldSalePrice = $product->sale_price;
            $oldImagePath = $product->image;

            // Handle image upload
            $data = $this->processImageUpload($data, $imageFile, $oldImagePath);

            // Extract related data
            $images = $data['images'] ?? null;
            $merchants = $data['merchants'] ?? null;
            $regionSetIds = $data['region_set_ids'] ?? null;
            $variants = $data['variants'] ?? null;
            $specifications = $data['specifications'] ?? null;

            // Remove from main data array
            unset($data['images'], $data['merchants'], $data['variants'], $data['specifications']);

            // Track changed attributes
            $changedAttributes = [];
            foreach ($data as $key => $value) {
                if ($product->$key != $value) {
                    $changedAttributes[$key] = ['old' => $product->$key, 'new' => $value];
                }
            }

            // Update product
            $product = $this->repository->update($product->id, $data);

            // Record price history if prices changed
            $newPrice = $data['price'] ?? $oldPrice;
            $newSalePrice = $data['sale_price'] ?? $oldSalePrice;

            if ($newPrice != $oldPrice || $newSalePrice != $oldSalePrice) {
                $this->repository->recordPriceHistory($product);

                event(new ProductPriceChangedEvent(
                    $product,
                    PriceChangeType::PRODUCT_BASE_PRICE,
                    $oldPrice,
                    $newPrice,
                    $oldSalePrice,
                    $newSalePrice
                ));
            }

            // Sync related records if provided
            if ($merchants !== null) {
                $this->updateMerchants($product, $merchants);
            }

            if ($regionSetIds !== null) {
                $this->repository->syncRegionSets($product->id, $regionSetIds);
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

            // Emit event
            if (!empty($changedAttributes)) {
                event(new ProductUpdatedEvent($product, $changedAttributes));
            }

            return $product;
        });
    }

    private function updateMerchants(Product $product, array $merchants): void
    {
        $oldMerchants = $this->repository->getProductMerchantsWithDetails($product->id)->keyBy('id');
        $merchantIds = $this->repository->syncMerchants($product->id, $merchants);

        // Record price history for merchants with price changes
        foreach ($merchants as $index => $merchantData) {
            $merchantId = $merchantIds[$index];

            // Find old merchant
            $oldMerchant = null;
            if (!empty($merchantData['id']) && $oldMerchants->has($merchantData['id'])) {
                $oldMerchant = $oldMerchants->get($merchantData['id']);
            }

            $variants = $this->repository->getVariants($product->id);
            $newPrices = $this->merchantPricingResolver->resolve($merchantData, $variants);
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

                    event(new ProductPriceChangedEvent(
                        $product,
                        PriceChangeType::MERCHANT_PRICE,
                        $oldPrice,
                        $newPrices['price'],
                        $oldSalePrice,
                        $newPrices['sale_price'],
                        $merchantData['id']
                    ));
                }
            }
        }
    }

    public function deleteProduct(int $id): bool
    {
        $product = $this->repository->find($id);

        if (!$product) {
            return false;
        }

        return $this->database->transaction(function () use ($product) {
            $productName = $product->name;

            // Delete image using ImageUploadService
            if ($product->image) {
                $this->deleteImage($product->image);
            }

            $productImages = $this->repository->getImages($product->id);

            foreach ($productImages as $image) {
                $this->deleteImage($image->url);
            }

            // Delete variant images
            $variants = $this->repository->getVariants($product->id);
            foreach ($variants as $variant) {
                $variantImages = $this->repository->getVariantImages($variant->id);

                $this->repository->deleteVariantImages($variant->id);

                foreach ($variantImages as $image) {
                    $this->deleteImage($image->url);
                }
            }

            // Delete price history
            $this->repository->deletePriceHistory($product->id);

            // Delete product
            $deleted = $this->repository->delete($product->id);

            if ($deleted) {
                event(new ProductDeletedEvent($product->id, $productName));
            }

            return $deleted;
        });
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

    public function trackView(Product $product): void
    {
        // Emit event - listener will handle session and DB tracking
        event(new ProductViewedEvent(
            $product,
            $this->requestContext->getUserId(),
            $this->requestContext->getSessionId(),
            $this->requestContext->getIpAddress()
        ));
    }
}