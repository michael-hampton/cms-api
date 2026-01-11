<?php

namespace App\Actions;

use App\Actions\Traits\UpdateTerritoryActiveStatus;
use App\Framework\Database\Database;
use App\Repositories\Cms\TerritoryRepository;

class BulkActivateTerritories
{
    use UpdateTerritoryActiveStatus;

    public function __construct(private readonly Database $database, private readonly TerritoryRepository $repository)
    {

    }

    public function handle(array $territoryIds): array
    {
        return $this->bulkUpdateActiveStatus($territoryIds, true);
    }
}