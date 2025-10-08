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
        ProductRepository $repository,
        ImageUploadService $imageUploadService,
        private readonly ProductViewRepository $productViewRepository
    ) {
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

        return $this->repository->create($data);
    }

    public function updateProduct(int $id, array $data, ?UploadedFile $imageFile = null): ?Model
    {
        $product = $this->repository->find($id);

        if (!$product) {
            return null;
        }

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

        return $this->repository->update($id, $data);
    }

    public function deleteProduct(int $id): bool
    {
        $product = $this->repository->find($id);

        if (!$product) {
            return false;
        }

        // Delete image using ImageUploadService
        if ($product->image) {
            try {
                $this->imageUploadService->delete($product->image);
            } catch (Exception $e) {
                // Log but don't fail deletion
                error_log('Failed to delete product image: ' . $e->getMessage());
            }
        }

        return $this->repository->delete($id);
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

        $this->productViewRepository->trackView($product->id, $userId, $sessionId, $ipAddress);
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
                'url' =>'/products/' . $product->slug
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

    public function duplicateProduct(int $productId, ?string $newName = null): Model
    {
        $originalProduct = $this->repository->find($productId);

        if (!$originalProduct) {
            throw new \Exception("Product not found");
        }

        $data = [
            'name' => $newName ?? ($originalProduct->name . ' (Copy)'),
            'description' => $originalProduct->description,
            'price' => $originalProduct->price,
            'sale_price' => $originalProduct->sale_price,
            'brand_id' => $originalProduct->brand_id,
            'category_id' => $originalProduct->category_id,
            'status' => 'draft',
        ];

        // Generate unique slug
        $baseName = $data['name'];
        $slug = Str::slug($baseName);
        $counter = 1;

        while ($this->repository->findBySlug($slug)) {
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

        return $this->repository->create($data);
    }
}