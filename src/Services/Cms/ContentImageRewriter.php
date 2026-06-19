<?php

namespace App\Services\Cms;

use Throwable;

final class ContentImageRewriter
{
    private array $failures = [];
    private array $replacements = [];

    public function __construct(
        private UnsplashImageImporter $importer,
        private string $fallbackUrl = UnsplashImageImporter::DEFAULT_FALLBACK_URL,
    ) {
    }

    public function rewrite(array $payload, int $siteId): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->rewrite($value, $siteId);
                continue;
            }

            if (!is_string($value) || !$this->importer->supports($value)) {
                continue;
            }

            $metadata = [
                'alt_text' => $payload['alt'] ?? $payload['title'] ?? $payload['name'] ?? null,
                'caption' => $payload['caption'] ?? null,
                'credit' => $payload['credit'] ?? 'Unsplash',
            ];

            try {
                $image = $this->importer->import($value, $siteId, $metadata);
            } catch (Throwable $originalException) {
                $image = $this->fallbackImage($value, $siteId, $metadata, $originalException);

                if ($image === null) {
                    continue;
                }
            }

            $payload[$key] = $image->url;

            if ($this->isImageKey((string) $key)) {
                $payload['image_id'] = $image->id;
            }
        }

        return $payload;
    }

    public function rewriteUrl(string $url, int $siteId, ?string $altText = null): string
    {
        if (!$this->importer->supports($url)) {
            return $url;
        }

        $metadata = ['alt_text' => $altText];

        try {
            return $this->importer->import($url, $siteId, $metadata)->url;
        } catch (Throwable $originalException) {
            $fallback = $this->fallbackImage(
                $url,
                $siteId,
                $metadata,
                $originalException
            );

            return $fallback?->url ?? $url;
        }
    }

    public function failures(): array
    {
        return $this->failures;
    }

    public function replacements(): array
    {
        return $this->replacements;
    }

    private function fallbackImage(
        string $originalUrl,
        int $siteId,
        array $metadata,
        Throwable $originalException,
    ): ?\App\Models\Image {
        try {
            $fallback = $this->importer->import(
                $this->fallbackUrl,
                $siteId,
                $metadata
            );

            $this->replacements[] = [
                'site_id' => $siteId,
                'original_url' => $originalUrl,
                'fallback_url' => $this->fallbackUrl,
                'image_id' => $fallback->id,
                'reason' => $originalException->getMessage(),
            ];

            return $fallback;
        } catch (Throwable $fallbackException) {
            $this->failures[] = [
                'site_id' => $siteId,
                'url' => $originalUrl,
                'message' => $originalException->getMessage(),
                'fallback_url' => $this->fallbackUrl,
                'fallback_message' => $fallbackException->getMessage(),
            ];

            return null;
        }
    }

    private function isImageKey(string $key): bool
    {
        return in_array($key, [
            'src',
            'image',
            'image_url',
            'imageUrl',
            'background_image',
            'backgroundImage',
            'thumbnail',
            'thumbnail_url',
            'url',
        ], true);
    }
}
