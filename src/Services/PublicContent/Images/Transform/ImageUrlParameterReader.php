<?php

namespace App\Services\PublicContent\Images\Transform;

/**
 * Reads width/quality/crop back out of a URL that may already have been
 * produced by {@see SimpleImageUrlBuilder} or {@see RichImageUrlBuilder},
 * and hands back the "clean" base URL those builders can rebuild from.
 *
 * This is what lets a second transform request (e.g. a different width on
 * an already-transformed image) replace the old parameters instead of
 * stacking a second suffix/segment onto the URL.
 */
final class ImageUrlParameterReader
{
    private const string SIMPLE_SUFFIX_PATTERN = '~^(?<name>.*)-w(?<width>\d+)(?:-q(?<quality>\d+))?$~';

    public function read(string $url): ImageUrlParameters
    {
        $url = trim($url);

        return $this->readRich($url) ?? $this->readSimple($url);
    }

    private function readRich(string $url): ?ImageUrlParameters
    {
        $pattern = '~^(?<prefix>.*)/v2/(?<params>[^/]+)/(?<rest>.+)$~';

        if (!preg_match($pattern, $url, $matches)) {
            return null;
        }

        $params = $this->parseParamSegment($matches['params']);
        $rest = $this->stripFormatSuffix($matches['rest']);
        $baseUrl = rtrim($matches['prefix'], '/') . '/' . $rest;

        return new ImageUrlParameters(
            style: ImageUrlStyle::Rich,
            width: isset($params['w']) ? (int) $params['w'] : null,
            quality: isset($params['q']) ? (int) $params['q'] : null,
            crop: $this->readCrop($params),
            baseUrl: $baseUrl,
        );
    }

    private function readSimple(string $url): ImageUrlParameters
    {
        $info = pathinfo($url);
        $filename = $info['filename'] ?? '';
        $extension = $info['extension'] ?? '';
        $dir = $info['dirname'] ?? '.';

        if (!preg_match(self::SIMPLE_SUFFIX_PATTERN, $filename, $matches)) {
            return new ImageUrlParameters(null, null, null, null, $url);
        }

        $baseUrl = ($dir === '.' ? '' : $dir . '/') . $matches['name'] . ($extension !== '' ? '.' . $extension : '');
        $quality = ($matches['quality'] ?? '') !== '' ? (int) $matches['quality'] : null;

        return new ImageUrlParameters(
            style: ImageUrlStyle::Simple,
            width: (int) $matches['width'],
            quality: $quality,
            crop: null,
            baseUrl: $baseUrl,
        );
    }

    /** @return array<string, string> */
    private function parseParamSegment(string $segment): array
    {
        $params = [];

        foreach (explode(',', $segment) as $pair) {
            [$key, $value] = array_pad(explode(':', $pair, 2), 2, null);

            if ($key !== null && $value !== null && $key !== '') {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * @param array<string, string> $params
     * @return array{t: int, l: int, cw: int, ch: int}|null
     */
    private function readCrop(array $params): ?array
    {
        if (!isset($params['t'], $params['l'], $params['cw'], $params['ch'])) {
            return null;
        }

        return [
            't' => (int) $params['t'],
            'l' => (int) $params['l'],
            'cw' => (int) $params['cw'],
            'ch' => (int) $params['ch'],
        ];
    }

    /**
     * Drops a format-override extension (e.g. the trailing ".webp" in
     * "photo.jpg.webp"), keeping the true original extension.
     */
    private function stripFormatSuffix(string $rest): string
    {
        $extensions = SupportedImageFormat::pattern();
        $pattern = '~^(?<base>.+\.(?:' . $extensions . '))\.(?:' . $extensions . ')$~i';

        if (preg_match($pattern, $rest, $matches)) {
            return $matches['base'];
        }

        return $rest;
    }
}
