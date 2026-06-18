<?php

namespace App\Services\PublicContent\Media;

final class PublicMediaPathResolver
{
    private const ALLOWED_EXTENSIONS = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
    ];

    /**
     * Only CMS-managed local media paths are eligible for public proxying.
     * External URLs, data URIs and arbitrary filesystem paths are deliberately ignored.
     */
    public function isEligible(string $path): bool
    {
        $normalised = $this->normalise($path);

        if ($normalised === null) {
            return false;
        }

        $relative = ltrim($normalised, '/');

        if (!str_starts_with($relative, 'storage/') && !str_starts_with($relative, 'uploads/')) {
            return false;
        }

        return $this->mimeType($normalised) !== null;
    }

    public function normalise(string $path): ?string
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        if (preg_match('#^(?:https?:)?//#i', $path) || str_starts_with(strtolower($path), 'data:')) {
            return null;
        }

        $path = parse_url($path, PHP_URL_PATH);
        if (!is_string($path) || trim($path) === '') {
            return null;
        }

        $path = '/' . ltrim(rawurldecode($path), '/');
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                return null;
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            return null;
        }

        return '/' . implode('/', $segments);
    }

    public function absolutePath(string $path): ?string
    {
        $normalised = $this->normalise($path);

        if ($normalised === null || !$this->isEligible($normalised)) {
            return null;
        }

        foreach ($this->candidateRoots() as $root) {
            $candidate = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($normalised, '/');
            $realRoot = realpath($root);
            $realCandidate = realpath($candidate);

            if ($realRoot === false || $realCandidate === false) {
                continue;
            }

            if (str_starts_with($realCandidate, rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                return $realCandidate;
            }
        }

        return null;
    }

    public function mimeType(string $path): ?string
    {
        $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        return self::ALLOWED_EXTENSIONS[$extension] ?? null;
    }

    /** @return list<string> */
    private function candidateRoots(): array
    {
        $roots = [];

        $configuredRoot = config('public-content.media.document_root', null);
        if (is_string($configuredRoot) && trim($configuredRoot) !== '') {
            $roots[] = $configuredRoot;
        }

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $roots[] = $_SERVER['DOCUMENT_ROOT'];
        }

        $roots[] = getcwd();
        $roots[] = dirname(__DIR__, 4);

        return array_values(array_unique(array_filter($roots)));
    }
}
