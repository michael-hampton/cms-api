<?php

namespace App\Services\PublicContent\Images;

final readonly class PublicContentImageAsset
{
    public function __construct(
        public string $path,
        public string $content,
        public string $mimeType,
        public int $size,
        public int $lastModified,
        public string $etag,
    ) {
    }
}
