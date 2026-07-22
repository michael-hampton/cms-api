<?php

namespace App\Enums\PublicContent;

enum LayoutResolutionSource: string
{
    case PageSettings = 'page_settings';
    case SiteDefault = 'site_default';
}
