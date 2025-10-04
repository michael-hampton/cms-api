<?php

namespace App\Framework\Database\Relations;

interface PivotOperationsInterface
{
    public function attach($ids, array $pivotData = []): int;
    public function detach($ids = null): int;
    public function sync(array $ids, array $pivotData = []): array;
    public function toggle($ids, array $pivotData = []): array;
}