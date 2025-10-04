<?php

namespace App\Framework\Database\Relations;

use App\Models\Model;

interface RelationContextInterface
{
    public function setContext(Model $model, array $relationData): self;
    public function hasContext(): bool;
    public function getModel(): ?Model;
    public function getRelationData(): array;
}