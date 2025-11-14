<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Framework\Support\SiteContext;
use App\Framework\Support\Str;
use App\Models\Brand;
use App\Repositories\BrandRepository;
use App\Services\ImageUploadService;

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

    public function handle(int $brandId, ?string $newName = null, ?int $siteId = null): Brand
    {
        return $this->database->transaction(function() use ($brandId, $newName, $siteId) {
            $originalBrand = $this->brandRepository->find($brandId);

            if (!$originalBrand) {
                throw new \Exception("Brand not found");
            }

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
                } catch (\Exception $e) {
                    $data['logo'] = null;
                }
            }

            $newBrand = $this->brandRepository->create($data);

            // Add clone history with site information
            if ($targetSiteId !== $originalBrand->site_id) {
                $originalBrand->addCloneRecord('cloned_to', $newBrand->id, $targetSiteId);
                $newBrand->addCloneRecord('cloned_from', $originalBrand->id, $originalBrand->site_id);
            } else {
                $originalBrand->addCloneRecord('cloned_to', $newBrand->id, null);
                $newBrand->addCloneRecord('cloned_from', $originalBrand->id, null);
            }

            return $newBrand;
        });
    }
}