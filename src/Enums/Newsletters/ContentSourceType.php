<?php

namespace App\Enums\Newsletters;

enum ContentSourceType: string
{
    case AutoPages = 'auto_pages';
    case CustomBlocks = 'custom_blocks';
    case Manual = 'manual'; // legacy
}