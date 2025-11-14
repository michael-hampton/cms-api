<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Repositories\BrandRepository;
use App\Services\ImageUploadService;

class BulkDeleteBrand
{
    private Database $database;

    public function __construct(
        private readonly BrandRepository    $brandRepository,
        private readonly ImageUploadService $imageUploadService,
        ?Database                           $database = null
    ) {
        $this->database = $database ?? Database::getInstance();
    }

    public function handle(array $brandIds): array
    {
        return $this->database->transaction(function() use ($brandIds) {
            $deleted = [];
            $failed = [];

            foreach ($brandIds as $brandId) {
                try {
                    $brand = $this->brandRepository->find($brandId);

                    if (!$brand) {
                        $failed[] = ['id' => $brandId, 'reason' => 'Brand not found'];
                        continue;
                    }

                    $productsCount = $this->brandRepository->getProductsByBrandId($brandId)->count();

                    if ($productsCount > 0) {
                        $failed[] = [
                            'id' => $brandId,
                            'reason' => "Brand has {$productsCount} associated products"
                        ];
                        continue;
                    }

                    if ($brand->logo) {
                        $this->imageUploadService->delete($brand->logo);
                    }

                    if ($brand->delete()) {
                        $deleted[] = $brandId;
                    } else {
                        $failed[] = ['id' => $brandId, 'reason' => 'Delete failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $brandId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'deleted' => $deleted,
                'failed' => $failed,
                'total' => count($brandIds)
            ];
        });
    }
}