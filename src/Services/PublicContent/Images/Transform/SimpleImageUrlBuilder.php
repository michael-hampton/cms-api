<?php

namespace App\Services\PublicContent\Images\Transform;

/**
 * Builds "v1" transform URLs: width and quality are written onto the end of
 * the filename, e.g. `photo.jpg` -> `photo-w800-q80.jpg`.
 *
 * Quality is only ever written when a width is requested, matching the
 * legacy behaviour this replaces. A format change swaps the file extension.
 */
final class SimpleImageUrlBuilder
{
    /** Applied whenever a width is requested but no explicit quality is given. */
    private const int DEFAULT_QUALITY = 80;

    /**
     * @param string $baseUrl A URL already stripped of any existing v1 suffix (see {@see ImageUrlParameterReader}).
     */
    public function build(string $baseUrl, ImageTransformOptions $options): string
    {
        $info = pathinfo($baseUrl);
        $dir = $info['dirname'] ?? '.';
        $filename = $info['filename'] ?? '';
        $extension = $options->format ?? ($info['extension'] ?? '');

        if ($options->width !== null) {
            $quality = $options->quality ?? self::DEFAULT_QUALITY;
            $filename = sprintf('%s-w%d-q%d', $filename, $options->width, $quality);
        }

        $prefix = $dir === '.' ? '' : $dir . '/';

        return sprintf('%s%s.%s', $prefix, $filename, $extension);
    }
}
