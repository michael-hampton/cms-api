<?php

namespace App\Services\Cms;

use App\Enums\ImageRights;
use App\Models\Image;
use Closure;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class UnsplashImageImporter
{
    private Closure $logger;

    public function __construct(?Closure $logger = null)
    {
        $this->logger = $logger ?? static function (string $message): void {
        };
    }

    public function import(string $url, int $siteId, array $metadata = []): Image
    {
        $externalId = $this->externalId($url);

        $this->log("Checking image {$externalId} for site {$siteId}");

        $existing = Image::where('site_id', $siteId)
            ->where('external_provider', 'unsplash')
            ->where('external_id', $externalId)
            ->first();

        if ($existing) {
            $this->log("Reusing image {$externalId} as image #{$existing->id}");
            return $existing;
        }

        $this->log("Downloading {$url}");
        $startedAt = microtime(true);

        $context = stream_context_create([
            'http' => [
                'timeout' => 20,
                'follow_location' => 1,
                'max_redirects' => 5,
                'user_agent' => 'CMS External Image Importer',
            ],
        ]);

        $contents = @file_get_contents($url, false, $context);
        if ($contents === false || $contents === '') {
            throw new RuntimeException("Unable to download Unsplash image after 20 seconds: {$url}");
        }

        $size = strlen($contents);
        $elapsed = number_format(microtime(true) - $startedAt, 2);
        $this->log("Downloaded {$externalId} ({$size} bytes in {$elapsed}s)");

        $uploadRoot = rtrim(config('upload.path', 'uploads'), '/');
        $directory = 'images/imported/' . date('Y-m-d');
        $fullDirectory = $uploadRoot . '/' . $directory;

        if (!is_dir($fullDirectory) && !mkdir($fullDirectory, 0755, true) && !is_dir($fullDirectory)) {
            throw new RuntimeException('Unable to create imported image directory.');
        }

        $filename = $externalId . '-' . bin2hex(random_bytes(6)) . '.jpg';
        $relativePath = $directory . '/' . $filename;
        $fullPath = $uploadRoot . '/' . $relativePath;

        $this->log("Writing {$externalId} to {$relativePath}");

        if (file_put_contents($fullPath, $contents) === false) {
            throw new RuntimeException('Unable to store imported image.');
        }

        try {
            $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($fullPath) ?: 'image/jpeg';
            $dimensions = @getimagesize($fullPath) ?: [];
            $publicUrl = rtrim(config('app.url', ''), '/') . '/storage/uploads/' . $relativePath;

            $image = Image::create([
                'filename' => $filename,
                'original_name' => $externalId . '.jpg',
                'name' => $metadata['name'] ?? $metadata['alt_text'] ?? $externalId,
                'file_path' => $relativePath,
                'url' => $publicUrl,
                'mime_type' => $mimeType,
                'file_size' => $size,
                'width' => $dimensions[0] ?? null,
                'height' => $dimensions[1] ?? null,
                'alt_text' => $metadata['alt_text'] ?? null,
                'caption' => $metadata['caption'] ?? null,
                'credit' => $metadata['credit'] ?? 'Unsplash',
                'image_rights' => ImageRights::THIRD_PARTY_LICENSED->value,
                'is_active' => true,
                'is_archived' => false,
                'site_id' => $siteId,
                'external_provider' => 'unsplash',
                'external_id' => $externalId,
                'source_url' => $url,
            ]);

            $this->log("Created image #{$image->id} for {$externalId}");

            return $image;
        } catch (Throwable $exception) {
            @unlink($fullPath);
            $this->log("Removed partial file {$relativePath} after failure");
            throw $exception;
        }
    }

    public function supports(string $url): bool
    {
        try {
            $this->externalId($url);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function externalId(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $externalId = basename((string) ($parts['path'] ?? ''));

        if ($scheme !== 'https' || $host !== 'images.unsplash.com' || !str_starts_with($externalId, 'photo-')) {
            throw new InvalidArgumentException('A full images.unsplash.com photo URL is required.');
        }

        return $externalId;
    }

    private function log(string $message): void
    {
        ($this->logger)('[image] ' . $message);
    }
}
