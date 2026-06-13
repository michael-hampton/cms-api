<?php

namespace App\DTO\OpenCollab;

use App\Enums\OpenCollab\OpenCollabImageRights;

final class ImageSearchQuery
{
    public function __construct(
        public readonly int                      $page         = 1,
        public readonly int                      $perPage      = 20,
        public readonly ?string                  $search       = null,
        public readonly ?int                     $uploadedBy   = null,
        public readonly ?OpenCollabImageRights   $imageRights  = null,
        public readonly ?string                  $uploadedFrom = null,
        public readonly ?string                  $uploadedTo   = null,
        public readonly string                   $sort         = 'created_at',
        public readonly string                   $direction    = 'desc',
    ) {
    }
}