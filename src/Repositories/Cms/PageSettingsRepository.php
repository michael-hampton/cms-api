<?php

namespace App\Repositories\Cms;

use App\Models\Model;
use App\Models\PageSettings;
use App\Repositories\Repository;

class PageSettingsRepository extends Repository
{
    public function __construct()
    {
        $this->withoutSiteFilter();
        parent::__construct();
    }
    protected function getModelClass(): string
    {
        return PageSettings::class;
    }

    public function findByPageId(int $pageId): ?PageSettings
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