<?php

namespace App\Actions;

use App\Framework\Support\Str;
use App\Repositories\Product\ProductRepository;
use App\Services\Cms\ImageUploadService;

class CloneProduct
{
    private ProductRepository $repository;
    private ImageUploadService $imageUploadService;

    public function __construct(
        ProductRepository                      $repository,
        ImageUploadService                     $imageUploadService
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
    
    public function handle(
        int $productId,
        ?string $newName = null,
        ?int $targetSiteId = null,
        array $cloneRelations = []
    ): array
    {
        $originalProduct = $this->repository->find($productId);

        if (!$originalProduct) {
            throw new \Exception("Product not found");
        }

        $results = [
            'success' => [],
            'failed' => [],
            'relations' => [
                'images' => ['cloned' => 0, 'failed' => 0],
                'merchants' => ['cloned' => 0, 'failed' => 0],
                'variants' => ['cloned' => 0, 'failed' => 0],
                'specifications' => ['cloned' => 0, 'failed' => 0]
            ]
        ];

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
                $results['success'][] = 'main_image';
            } catch (\Exception $e) {
                $data['image'] = null;
                $results['failed'][] = ['field' => 'main_image', 'error' => $e->getMessage()];
            }
        }

        $newProduct = $this->repository->create($data);
        $results['success'][] = 'product_created';

        // Add clone history
        if ($targetSiteId && $targetSiteId !== $originalProduct->site_id) {
            $originalProduct->addCloneRecord('cloned_to', $newProduct->id, $targetSiteId);
            $newProduct->addCloneRecord('cloned_from', $originalProduct->id, $originalProduct->site_id);
            $results['success'][] = 'cross_site_clone_history';
        } else {
            $originalProduct->addCloneRecord('cloned_to', $newProduct->id, null);
            $newProduct->addCloneRecord('cloned_from', $originalProduct->id, null);
            $results['success'][] = 'clone_history';
        }

        // Track relation duplication
        $relationResults = $this->duplicateProductRelations(
            $originalProduct->id,
            $newProduct->id,
            $cloneRelations
        );

        $results['relations'] = $relationResults;

        return [
            'product' => $newProduct,
            'results' => $results,
            'original_product_id' => $originalProduct->id,
            'cross_site' => $targetSiteId && $targetSiteId !== $originalProduct->site_id
        ];
    }

    protected function duplicateProductRelations(int $originalId, int $newId, array $cloneRelations): array
    {
        $results = [
            'images' => ['cloned' => 0, 'failed' => 0],
            'merchants' => ['cloned' => 0, 'failed' => 0],
            'variants' => ['cloned' => 0, 'failed' => 0],
            'specifications' => ['cloned' => 0, 'failed' => 0]
        ];

        // Duplicate images if selected
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
                    $results['images']['cloned']++;
                } else {
                    $results['images']['failed']++;
                }
            }

            if (!empty($imageData)) {
                $this->repository->syncImages($newId, $imageData);
            }
        }

        $variantMapping = []; // Map old variant IDs to new variant IDs

        // Duplicate variants if selected
        if ($cloneRelations['variants']) {
            $variants = $this->repository->getVariants($originalId);

            $variantData = $variants->map(function ($v) use (&$results) {
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

                $results['variants']['cloned']++;

                return [
                    'sku' => $v->sku . '-COPY',
                    'name' => $v->name,
                    'attributes' => $v->attributes,
                    'price' => $v->price,
                    'sale_price' => $v->sale_price,
                    'price_modifier' => $v->price_modifier,
                    'is_active' => false,
                    'images' => $imageData,
                ];
            })->toArray();

            if (!empty($variantData)) {
                $newVariantIds = $this->repository->syncVariants($newId, $variantData);

                // Create mapping of old to new variant IDs (by array index)
                foreach ($variants as $index => $oldVariant) {
                    if (isset($newVariantIds[$index])) {
                        $variantMapping[$oldVariant->id] = $newVariantIds[$index];
                    }
                }
            }
        }

        // Duplicate merchants if selected
        if ($cloneRelations['merchants']) {
            $merchants = $this->repository->getProductMerchantsWithDetails($originalId);
            $merchantData = $merchants->map(function ($m) use ($variantMapping, &$results) {

                $data = [
                    'name' => $m['name'],
                    'url' => $m['url'],
                    'price' => $m['price'],
                    'sale_price' => $m['sale_price'] ?? null,
                    'is_available' => $m['is_available'] ?? true,
                    'override_price' => $m['override_price'] ?? false,
                    'override_sale_price' => $m['override_sale_price'] ?? false,
                    'variant_sku' => $m['variant_sku'] ?? null,
                ];

                // Map old variant ID to new variant ID if variant exists
                if (isset($m['variant_id']) && isset($variantMapping[$m['variant_id']])) {
                    $data['variant_id'] = $variantMapping[$m['variant_id']];
                }

                $results['merchants']['cloned']++;

                return $data;
            })->toArray();

            if (!empty($merchantData)) {
                $this->repository->syncMerchants($newId, $merchantData);
            }
        }

        // Duplicate specifications if selected
        if ($cloneRelations['specifications']) {
            $specifications = $this->repository->getSpecifications($originalId);
            $specData = $specifications->map(function ($s) use (&$results) {
                $results['specifications']['cloned']++;

                return [
                    'category' => $s->category,
                    'key' => $s->key,
                    'value' => $s->value,
                    'sort_order' => $s->sort_order,
                ];
            })->toArray();

            if (!empty($specData)) {
                $this->repository->syncSpecifications($newId, $specData);
            }
        }

        return $results;
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