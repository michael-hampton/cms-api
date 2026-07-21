<?php

namespace App\Services\PublicContent\Images\Transform;

interface ImageTransformerInterface
{
    /**
     * Whether this transformer can handle the given URL.
     */
    public function supports(string $url): bool;

    /**
     * Transform an image URL. Callers that wrap this interface (see
     * {@see ImageTransformer}) must fail open: an unrecognised host or an
     * internal failure returns the original URL unchanged rather than throwing.
     */
    public function transform(string $url, ImageTransformOptions $options): string;
}
