<?php

namespace App\Framework\Database\Relations;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Model;

interface RelationBuilderInterface
{
    /**
     * Execute the query and return results
     */
    public function get(): Collection;

    /**
     * Get the first result
     */
    public function first(): ?Model;

    /**
     * Get the underlying QueryBuilder
     */
    public function getQuery(): QueryBuilder;

    /**
     * Get the relationship handler
     */
    public function getHandler(): RelationshipHandler;

    /**
     * Get the parent model
     */
    public function getParent(): Model;
}