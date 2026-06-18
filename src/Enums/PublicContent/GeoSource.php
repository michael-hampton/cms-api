<?php

namespace App\Enums\PublicContent;

enum GeoSource: string
{
    case CF_EDGE = 'cf-edge';
    case PROXY_INFERRED = 'proxy-inferred';
    case DEFAULT = 'default';
}
