<?php

namespace App\Services\PublicContent\Widgets\Contracts;

use App\DTO\PublicContent\Widgets\WidgetTheme;

interface WidgetThemeResolverInterface
{
    public function forSite(int $siteId): WidgetTheme;
}
