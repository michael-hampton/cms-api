<?php

namespace App\Enums\OpenCollab;

enum ImageAuditAction: string
{
    case Attached           = 'image_attached';
    case Replaced           = 'image_replaced';
    case Removed            = 'image_removed';
    case MetadataOverridden = 'image_metadata_overridden';
}