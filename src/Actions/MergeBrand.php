<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Repositories\BrandRepository;
use App\Services\ImageUploadService;

class MergeBrand
{
    private Database $database;

    public function __construct(
        private readonly BrandRepository    $brandRepository,
        private readonly ImageUploadService $imageUploadService,
        ?Database                           $database = null
    ) {
        $this->database = $database ?? Database::getInstance();
    }

    public function handle(int $sourceBrandId, int $targetBrandId): array
    {
        if ($sourceBrandId === $targetBrandId) {
            throw new \Exception("Cannot merge a brand with itself");
        }

        return $this->database->transaction(function() use ($sourceBrandId, $targetBrandId) {
            $results = [
                'success' => [],
                'failed' => [],
                'products_reassigned' => 0
            ];

            $sourceBrand = $this->brandRepository->find($sourceBrandId);
            $targetBrand = $this->brandRepository->find($targetBrandId);

            if (!$sourceBrand || !$targetBrand) {
                throw new \Exception("One or both brands not found");
            }

            // Reassign all products from source to target
            $products = $this->brandRepository->getProductsByBrandId($sourceBrandId);

            foreach ($products as $product) {
                try {
                    $product->brand_id = $targetBrandId;
                    $product->save();
                    $results['products_reassigned']++;
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'operation' => 'reassign_product',
                        'product_id' => $product->id,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $results['success'][] = 'products_reassigned';

            // Add merge history
            try {
                $targetBrand->addCloneRecord('merged_from', $sourceBrand->id, null);
                $sourceBrand->addCloneRecord('merged_to', $targetBrand->id, null);
                $results['success'][] = 'merge_history';
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'operation' => 'merge_history',
                    'error' => $e->getMessage()
                ];
            }

            // Delete source brand's logo
            if ($sourceBrand->logo) {
                try {
                    $this->imageUploadService->delete($sourceBrand->logo);
                    $results['success'][] = 'logo_deleted';
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'operation' => 'delete_logo',
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Delete source brand
            try {
                $this->brandRepository->delete($sourceBrandId);
                $results['success'][] = 'brand_deleted';
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'operation' => 'delete_brand',
                    'error' => $e->getMessage()
                ];
                throw $e; // Re-throw to rollback transaction
            }

            return [
                'success' => true,
                'results' => $results,
                'source_brand_id' => $sourceBrandId,
                'target_brand_id' => $targetBrandId
            ];
        });
    }
}