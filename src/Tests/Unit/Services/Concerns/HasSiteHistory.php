<?php

namespace App\Tests\Unit\Services\Concerns;

use App\Models\Model;

trait HasSiteHistory
{
    private function setCloneHistoryExpectations(
        Model$originalModel,
        Model $newModel,
        int $clonedFromId,
        int $clonedToId,
        string $type = 'cloned',
        ?int $clonedFromSiteId = null,
        ?int $clonedToSiteId = null
    )
    {
        $originalModel->shouldReceive('addCloneRecord')
            ->once()
            ->with($type . '_to', $clonedToId, $clonedToSiteId);

        $newModel->shouldReceive('addCloneRecord')
            ->once()
            ->with($type . '_from', $clonedFromId, $clonedFromSiteId);
    }
}