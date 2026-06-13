<?php

namespace App\DTO\OpenCollab;

use App\Enums\OpenCollab\OpenCollabImageRights;
use App\Framework\Http\UploadedFile;

final class ImageUploadData
{
    public function __construct(
        public readonly UploadedFile           $file,
        public readonly string                 $name,
        public readonly OpenCollabImageRights  $imageRights,
        public readonly string                 $altText,
        public readonly string                 $credit,
        public readonly string                 $sourceContext,
        public readonly ?string                $externalReference = null,
    ) {
    }
}