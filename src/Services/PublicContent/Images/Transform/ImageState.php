<?php

namespace App\Services\PublicContent\Images\Transform;

/**
 * Single mutable image-transform state carried through a transform pipeline.
 * Crop geometry uses the shared t/l/cw/ch shape also recovered by
 * {@see ImageUrlParameterReader}.
 */
final class ImageState
{
    /**
     * @param array{t: int, l: int, cw: int, ch: int}|null $crop
     */
    public function __construct(
        public ?int $width = null,
        public ?int $quality = null,
        public ?array $crop = null,
        public ?string $format = null,
    ) {
    }

    public function toOptions(?int $originalWidth = null): ImageTransformOptions
    {
        return new ImageTransformOptions(
            width: $this->width,
            quality: $this->quality,
            format: $this->format,
            crop: $this->crop,
            originalWidth: $originalWidth,
        );
    }

    public static function fromOptions(ImageTransformOptions $options): self
    {
        return new self(
            width: $options->width,
            quality: $options->quality,
            crop: $options->crop,
            format: $options->format,
        );
    }

    public static function fromParameters(ImageUrlParameters $parameters): self
    {
        return new self(
            width: $parameters->width,
            quality: $parameters->quality,
            crop: $parameters->crop,
            format: null,
        );
    }
}
