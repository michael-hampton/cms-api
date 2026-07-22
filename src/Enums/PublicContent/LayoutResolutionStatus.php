<?php

namespace App\Enums\PublicContent;

enum LayoutResolutionStatus: string
{
    case Resolved = 'resolved';
    case NoLayoutResolved = 'no_layout_resolved';
}
