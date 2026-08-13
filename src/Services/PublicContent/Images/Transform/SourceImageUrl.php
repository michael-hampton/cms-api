<?php

namespace App\Services\PublicContent\Images\Transform;

use InvalidArgumentException;

/**
 * Fail-closed parse of a source image URL into the pieces builders need.
 * Invalid input is rejected rather than silently accepted.
 */
final readonly class SourceImageUrl
{
    public function __construct(
        public string $origin,
        public string $folder,
        public string $filename,
        public string $extension,
        public string $original,
    ) {
    }

    public static function parse(string $url): self
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Source image URL must not be empty.');
        }

        $parts = parse_url($trimmed);

        if ($parts === false) {
            throw new InvalidArgumentException('Source image URL is malformed.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if ($scheme === '' || $host === '' || !in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Source image URL must be an absolute http(s) URL.');
        }

        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || $path === '/') {
            throw new InvalidArgumentException('Source image URL must include a path with a filename.');
        }

        $info = pathinfo($path);
        $filename = (string) ($info['filename'] ?? '');
        $extension = strtolower((string) ($info['extension'] ?? ''));
        $dirname = (string) ($info['dirname'] ?? '/');

        if ($filename === '' || $extension === '') {
            throw new InvalidArgumentException('Source image URL must include a filename and extension.');
        }

        if (SupportedImageFormat::tryFrom($extension) === null) {
            throw new InvalidArgumentException('Source image URL extension is not supported.');
        }

        $folder = $dirname === '/' ? '/' : rtrim($dirname, '/') . '/';
        $origin = $scheme . '://' . $host;
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return new self(
            origin: $origin,
            folder: $folder,
            filename: $filename,
            extension: $extension,
            original: $trimmed,
        );
    }
}
