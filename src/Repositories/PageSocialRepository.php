<?php

namespace App\Repositories;

use App\Models\Model;
use App\Models\PageSocial;

class PageSocialRepository extends Repository
{
    protected function getModelClass(): string
    {
        return PageSocial::class;
    }

    public function findByPageId(int $pageId): ?PageSocial
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