<?php

namespace App\Enums\PublicContent;

enum EdgeRedirectReason: string
{
    case DisabledLocale = 'disabled_locale';
    case DoubledRegion = 'doubled_region';
    case RegionalHome = 'regional_home';
    case GlobalHome = 'global_home';
    case None = 'none';
}
