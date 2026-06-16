<?php

namespace App\Repositories\PublicContent\Contracts;

use App\Framework\Support\Collection;

interface PageWidgetRepositoryInterface
{
    public function getForPage(int $pageId): Collection;
}
