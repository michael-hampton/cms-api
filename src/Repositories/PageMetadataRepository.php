<?php

namespace App\Repositories;

use App\Models\Model;
use App\Models\PageMetadata;

class PageMetadataRepository extends Repository
{
    public function __construct()
    {
        $this->withoutSiteFilter();
        parent::__construct();
    }

    protected function getModelClass(): string
    {
        return PageMetadata::class;
    }

    public function findByPageId(int $pageId): ?PageMetadata
    {
        return $this->where('page_id', $pageId)->first();
    }

    public function createOrUpdate(int $pageId, array $data): Model
    {
        $existing = $this->findByPageId($pageId);

        if ($existing) {
            $existing->fill($data);
            $existing->save();
            return $existing;
        }

        $data['page_id'] = $pageId;
        return $this->create($data);
    }

    public function getFeaturedPages(): array
    {
        $results = $this->where('featured', 1)->get();

        $models = [];
        foreach ($results as $data) {
            $model = new PageMetadata($data);
            $model->exists = true;
            $model->original = $model->attributes;
            $models[] = $model;
        }

        return $models;
    }
}