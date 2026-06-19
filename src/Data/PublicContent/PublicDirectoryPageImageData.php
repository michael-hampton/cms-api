<?php

declare(strict_types=1);

namespace App\Data\PublicContent;

final readonly class PublicDirectoryPageImageData
{
    public function __construct(
        public string $url,
        public ?int $width,
        public ?int $height,
        public string $alt,
    ) {
    }
}
