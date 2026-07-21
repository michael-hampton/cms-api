<?php

namespace App\Services\PublicContent\Images\Transform;

/**
 * Builds "v2" transform URLs: a `/v2/t:..,l:..,cw:..,ch:..,q:..,w:../` segment
 * is inserted ahead of the folder, e.g.
 * `https://host/folder/photo.jpg` -> `https://host/v2/q:80,w:800/folder/photo.jpg`.
 *
 * Unlike the simple builder, quality is always written (even with no width
 * requested) because a v2 URL is only ever produced when richer control
 * (a crop, or an already-v2 source) is in play.
 *
 * A format change appends an extra extension (`photo.jpg.webp`) rather than
 * replacing the original one, so the true source extension stays readable.
 */
final class RichImageUrlBuilder
{
    private const int DEFAULT_QUALITY = 80;

    /**
     * @param string $baseUrl A URL that should already be stripped of any existing v2 segment
     *                         (see {@see ImageUrlParameterReader}); stacked segments are cleaned
     *                         defensively via {@see self::normaliseDirname()} regardless.
     */
    public function build(string $baseUrl, ImageTransformOptions $options): string
    {
        $parts = parse_url($baseUrl) ?: [];
        $origin = $this->origin($parts);
        $path = $this->normaliseDirname($parts['path'] ?? $baseUrl);

        $info = pathinfo($path);
        $dir = ltrim($info['dirname'] ?? '/', '/');
        $filename = $info['filename'] ?? '';
        $extension = $info['extension'] ?? '';
        $folder = ($dir === '' || $dir === '.') ? '' : $dir . '/';

        $formatSuffix = ($options->format !== null && $options->format !== $extension)
            ? '.' . $options->format
            : '';

        return sprintf(
            '%s/v2/%s/%s%s.%s%s',
            $origin,
            $this->paramsSegment($options),
            $folder,
            $filename,
            $extension,
            $formatSuffix,
        );
    }

    private function paramsSegment(ImageTransformOptions $options): string
    {
        $params = [];

        if ($options->crop !== null) {
            $params[] = 't:' . $options->crop['t'];
            $params[] = 'l:' . $options->crop['l'];
            $params[] = 'cw:' . $options->crop['cw'];
            $params[] = 'ch:' . $options->crop['ch'];
        }

        // Rich URLs always carry a quality, even with no width requested.
        $params[] = 'q:' . ($options->quality ?? self::DEFAULT_QUALITY);

        if ($options->width !== null) {
            $params[] = 'w:' . $options->width;
        }

        return implode(',', $params);
    }

    /**
     * Strips any existing `/v2/{params}/` segment out of a path so re-transforming
     * an already-rich URL rebuilds cleanly instead of stacking segments.
     */
    private function normaliseDirname(string $path): string
    {
        return preg_replace('~/v2/[^/]+(?=/)~', '', $path) ?? $path;
    }

    /** @param array<string, mixed> $parts */
    private function origin(array $parts): string
    {
        if (!isset($parts['host'])) {
            return '';
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '//';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $scheme . $parts['host'] . $port;
    }
}
