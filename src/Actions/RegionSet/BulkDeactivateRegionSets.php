<?php

namespace App\Actions\RegionSet;

use App\Actions\Traits\UpdateRegionSetActiveStatus;
use App\Framework\Database\Database;
use App\Repositories\Cms\RegionSetRepository;

class BulkDeactivateRegionSets
{
    use UpdateRegionSetActiveStatus;

    public function __construct(private readonly Database $database, private readonly RegionSetRepository $repository)
    {

    }

    public function handle(array $regionSetIds): array
    {
        return $this->bulkUpdateActiveStatus($regionSetIds, false);
    }
}