<?php

namespace App\Repositories;

use App\Models\Model;
use App\Models\PageSeo;

class PageSeoRepository extends Repository
{
    protected function getModelClass(): string
    {
        return PageSeo::class;
    }

    public function findByPageId(int $pageId): ?PageSeo
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
}