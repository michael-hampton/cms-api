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

    public function handle(int $sourceBrandId, int $targetBrandId): bool
    {
        if ($sourceBrandId === $targetBrandId) {
            throw new \Exception("Cannot merge a brand with itself");
        }

        return $this->database->transaction(function() use ($sourceBrandId, $targetBrandId) {
            $sourceBrand = $this->brandRepository->find($sourceBrandId);
            $targetBrand = $this->brandRepository->find($targetBrandId);

            if (!$sourceBrand || !$targetBrand) {
                throw new \Exception("One or both brands not found");
            }

            $products = $this->brandRepository->getProductsByBrandId($sourceBrandId);

            foreach ($products as $product) {
                $product->brand_id = $targetBrandId;
                $product->save();
            }

            // Add merge history
            $targetBrand->addCloneRecord('merged_from', $sourceBrand->id, null);
            $sourceBrand->addCloneRecord('merged_to', $targetBrand->id, null);

            if ($sourceBrand->logo) {
                $this->imageUploadService->delete($sourceBrand->logo);
            }

            $this->brandRepository->delete($sourceBrandId);

            return true;
        });
    }
}