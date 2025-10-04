<?php

namespace App\Framework\Database\Relations;

use App\Models\Model;

interface RelationshipLoaderInterface
{
    public function loadForResults(array $results, array $relationData, string $relation): array;
    public function loadForSingleModel(Model $model, array $relationData);
}