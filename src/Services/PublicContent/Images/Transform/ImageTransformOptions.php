<?php

namespace App\Services\PublicContent\Images\Transform;

/**
 * Requested image transform. Every field is optional: callers only set what
 * they actually want changed, and {@see RecognisedImageHostTransformer} fills
 * in the rest from whatever the source URL already encodes.
 */
final readonly class ImageTransformOptions
{
    /**
     * @param array{t: int, l: int, cw: int, ch: int}|null $crop Crop box: top, left, crop-width, crop-height.
     * @param int|null $originalWidth Known intrinsic width of the source image, used to prevent upscaling.
     */
    public function __construct(
        public ?int $width = null,
        public ?int $quality = null,
        public ?string $format = null,
        public ?array $crop = null,
        public ?int $originalWidth = null,
    ) {
    }

    public function hasCrop(): bool
    {
        return $this->crop !== null;
    }

    public function withWidth(?int $width): self
    {
        return new self($width, $this->quality, $this->format, $this->crop, $this->originalWidth);
    }
}
