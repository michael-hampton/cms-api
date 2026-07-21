<?php

namespace App\Services\PublicContent\Images\Transform;

/**
 * Parameters recovered from a URL by {@see ImageUrlParameterReader}.
 *
 * `style` is null when the URL carries no recognisable transform at all
 * (a plain, never-transformed source image).
 */
final readonly class ImageUrlParameters
{
    /**
     * @param array{t: int, l: int, cw: int, ch: int}|null $crop
     * @param string $baseUrl The URL with any transform suffix/segment stripped, ready to rebuild from.
     */
    public function __construct(
        public ?ImageUrlStyle $style,
        public ?int $width,
        public ?int $quality,
        public ?array $crop,
        public string $baseUrl,
    ) {
    }

    public function hasCrop(): bool
    {
        return $this->crop !== null;
    }

    public function isRich(): bool
    {
        return $this->style === ImageUrlStyle::Rich;
    }
}
