<?php

namespace App\Services\PublicContent\Images;

use App\Framework\Support\Cache\Cache;
use RuntimeException;

final class PublicContentImageAssetResolver
{
    private const int TTL_SECONDS = 3600;
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    public function __construct(private readonly PublicContentImageUrlSigner $signer)
    {
    }

    public function resolve(string $token): ?PublicContentImageAsset
    {
        $path = $this->signer->verify($token);

        if ($path === null || !$this->isAllowedPublicPath($path)) {
            return null;
        }

        $filePath = $this->toFilesystemPath($path);

        if ($filePath === null || !is_file($filePath) || !is_readable($filePath)) {
            return null;
        }

        $version = filemtime($filePath) ?: time();
        $cacheKey = 'public-content:image-asset:' . sha1($path . '|' . $version);

        return Cache::remember($cacheKey, self::TTL_SECONDS, fn (): PublicContentImageAsset => $this->buildAsset($path, $filePath));
    }

    private function buildAsset(string $path, string $filePath): PublicContentImageAsset
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new RuntimeException('Unable to read public content image asset.');
        }

        $mimeType = mime_content_type($filePath) ?: $this->guessMimeType($filePath);

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new RuntimeException('Unsupported public content image asset type.');
        }

        $lastModified = filemtime($filePath) ?: time();
        $etag = '"' . sha1($path . '|' . $lastModified . '|' . strlen($content)) . '"';

        return new PublicContentImageAsset(
            $path,
            $content,
            $mimeType,
            strlen($content),
            $lastModified,
            $etag,
        );
    }

    private function isAllowedPublicPath(string $path): bool
    {
        return str_starts_with($path, '/storage/uploads/')
            || str_starts_with($path, '/uploads/');
    }

    private function toFilesystemPath(string $path): ?string
    {
        $relative = match (true) {
            str_starts_with($path, '/storage/uploads/') => substr($path, strlen('/storage/uploads/')),
            str_starts_with($path, '/uploads/') => substr($path, strlen('/uploads/')),
            default => null,
        };

        if ($relative === null || $relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $basePath = rtrim((string) config('upload.path', 'uploads'), '/');
        $candidate = $basePath . '/' . ltrim($relative, '/');
        $baseRealPath = realpath($basePath);
        $candidateRealPath = realpath($candidate);

        if ($baseRealPath === false || $candidateRealPath === false) {
            return $candidate;
        }

        if (!str_starts_with($candidateRealPath, rtrim($baseRealPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $candidateRealPath;
    }

    private function guessMimeType(string $filePath): string
    {
        return match (strtolower(pathinfo($filePath, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
