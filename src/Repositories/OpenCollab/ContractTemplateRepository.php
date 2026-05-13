<?php

namespace App\Repositories\OpenCollab;

use App\Framework\Support\Collection;
use App\Models\ContractTemplate;
use App\Repositories\Repository;

class ContractTemplateRepository extends Repository
{
    public function allActive(): Collection
    {
        return ContractTemplate::where('is_active', true)->orderBy('name')->get();
    }

    public function findBySlug(string $slug): ?ContractTemplate
    {
        return ContractTemplate::where('slug', $slug)->first();
    }

    protected function getModelClass(): string
    {
        return ContractTemplate::class;
    }
}