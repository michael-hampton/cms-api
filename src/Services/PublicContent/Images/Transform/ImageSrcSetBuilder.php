<?php

namespace App\Services\PublicContent\Images\Transform;

/**
 * Builds `srcset` attribute values: one transformed URL per requested width,
 * tagged `{url} {width}w`, largest first, honouring the no-upscale rule.
 */
final class ImageSrcSetBuilder
{
    public function __construct(private readonly ImageTransformerInterface $transformer)
    {
    }

    /**
     * @param list<int> $widths
     */
    public function buildSrcSet(string $url, array $widths, ?ImageTransformOptions $options = null): string
    {
        $options ??= new ImageTransformOptions();
        $orderedWidths = $widths;
        rsort($orderedWidths);

        $entries = [];

        foreach ($orderedWidths as $width) {
            if ($width <= 0) {
                continue;
            }

            $cappedWidth = $options->originalWidth !== null ? min($width, $options->originalWidth) : $width;

            $entryOptions = new ImageTransformOptions(
                width: $cappedWidth,
                quality: $options->quality,
                format: $options->format,
                crop: $options->crop,
                originalWidth: $options->originalWidth,
            );

            $entries[] = sprintf('%s %dw', $this->transformer->transform($url, $entryOptions), $cappedWidth);
        }

        return implode(', ', $entries);
    }
}
