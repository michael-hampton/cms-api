<?php

namespace App\Repositories\OpenCollab;

use App\Framework\Support\Collection;
use App\Models\GuidelineTemplate;
use App\Repositories\Repository;

class GuidelineTemplateRepository extends Repository
{
    public function allActive(): Collection
    {
        return GuidelineTemplate::where('is_active', true)->orderBy('name')->get();
    }

    public function findBySlug(string $slug): ?GuidelineTemplate
    {
        return GuidelineTemplate::where('slug', $slug)->first();
    }

    protected function getModelClass(): string
    {
        return GuidelineTemplate::class;
    }
}