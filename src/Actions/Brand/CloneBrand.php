<?php

namespace App\Actions\Brand;

use App\Framework\Database\Database;
use App\Framework\Support\SiteContext;
use App\Framework\Support\Str;
use App\Repositories\Cms\BrandRepository;
use App\Services\Cms\ImageUploadService;

class CloneBrand
{
    private Database $database;

    public function __construct(
        private readonly BrandRepository    $brandRepository,
        private readonly ImageUploadService $imageUploadService,
        ?Database                           $database = null
    ) {
        $this->database = $database ?? Database::getInstance();
    }

    public function handle(int $brandId, ?string $newName = null, ?int $siteId = null): array
    {
        return $this->database->transaction(function() use ($brandId, $newName, $siteId) {
            $originalBrand = $this->brandRepository->find($brandId);

            if (!$originalBrand) {
                throw new \Exception("Brand not found");
            }

            $results = ['success' => [], 'failed' => []];
            $targetSiteId = $siteId ?? SiteContext::getId();

            $data = [
                'name' => $newName ?? ($originalBrand->name . ' (Copy)'),
                'description' => $originalBrand->description,
                'website' => $originalBrand->website,
                'status' => 'inactive',
                'seo_title' => $originalBrand->seo_title,
                'seo_description' => $originalBrand->seo_description,
                'no_index' => $originalBrand->no_index ?? false,
                'site_id' => $siteId ?? SiteContext::getId(),
                'canonical_url' => null, // Don't copy canonical URL
            ];

            $data['slug'] = Str::slug($data['name'], [$this->brandRepository, 'findBySlug']);

            if ($originalBrand->logo) {
                try {
                    $data['logo'] = $this->imageUploadService->duplicate($originalBrand->logo);
                    $results['success'][] = 'logo';
                } catch (\Exception $e) {
                    $data['logo'] = null;
                    $results['failed'][] = ['field' => 'logo', 'error' => $e->getMessage()];
                }
            }

            $newBrand = $this->brandRepository->create($data);
            $results['success'][] = 'brand_created';

            // Add clone history
            if ($targetSiteId !== $originalBrand->site_id) {
                $originalBrand->addCloneRecord('cloned_to', $newBrand->id, $targetSiteId);
                $newBrand->addCloneRecord('cloned_from', $originalBrand->id, $originalBrand->site_id);
                $results['success'][] = 'cross_site_clone_history';
            } else {
                $originalBrand->addCloneRecord('cloned_to', $newBrand->id, null);
                $newBrand->addCloneRecord('cloned_from', $originalBrand->id, null);
                $results['success'][] = 'clone_history';
            }

            return [
                'brand' => $newBrand,
                'results' => $results,
                'original_brand_id' => $brandId,
                'cross_site' => $targetSiteId !== $originalBrand->site_id
            ];
        });
    }
}