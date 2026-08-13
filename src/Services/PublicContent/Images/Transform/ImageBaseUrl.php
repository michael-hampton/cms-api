<?php

namespace App\Services\PublicContent\Images\Transform;

use InvalidArgumentException;

/**
 * Build-time image base setting. Validated when the transform library loads.
 * A malformed base is refused with a clear error — separate fail-closed
 * guarantee from {@see SourceImageUrl} parsing.
 */
final readonly class ImageBaseUrl
{
    public string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Image base URL must not be empty.');
        }

        $parts = parse_url($trimmed);

        if ($parts === false) {
            throw new InvalidArgumentException('Image base URL is malformed.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException(
                'Image base URL must be an absolute http(s) origin (scheme + host).',
            );
        }

        if (isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Image base URL must not include query or fragment.');
        }

        $this->value = rtrim($trimmed, '/');
    }

    public static function tryFromConfig(?string $configured): ?self
    {
        if ($configured === null) {
            return null;
        }

        $trimmed = trim($configured);
        if ($trimmed === '') {
            return null;
        }

        return new self($trimmed);
    }
}
