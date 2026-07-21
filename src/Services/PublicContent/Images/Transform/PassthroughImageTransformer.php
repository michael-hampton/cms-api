<?php

namespace App\Services\PublicContent\Images\Transform;

/**
 * Used for any host {@see RecognisedImageHostTransformer} does not recognise
 * (or file type it does not support). Always returns the URL unchanged.
 */
final class PassthroughImageTransformer implements ImageTransformerInterface
{
    public function supports(string $url): bool
    {
        return false;
    }

    public function transform(string $url, ImageTransformOptions $options): string
    {
        return $url;
    }
}
