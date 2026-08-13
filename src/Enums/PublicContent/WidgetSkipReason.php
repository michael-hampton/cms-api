<?php

namespace App\Enums\PublicContent;

enum WidgetSkipReason: string
{
    case RestrictedContent = 'restricted_content';
    case SupportsFailed = 'supports_failed';
    case EmptyHtml = 'empty_html';
    case BuildFailed = 'build_failed';
}