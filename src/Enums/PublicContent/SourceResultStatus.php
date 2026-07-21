<?php

namespace App\Enums\PublicContent;

enum SourceResultStatus: string
{
    case Ok = 'ok';
    case Empty = 'empty';
    case Degraded = 'degraded';
}
