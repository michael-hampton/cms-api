<?php

namespace App\Services\PublicContent\Recirculation;

use App\DTO\PublicContent\Sources\SourceResult;
use App\Models\Page;

interface RecirculationSourceInterface
{
    public function resolve(Page $page, int $siteId, int $limit = 4): SourceResult;
}
