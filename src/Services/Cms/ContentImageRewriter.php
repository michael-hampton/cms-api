<?php

namespace App\Services\Cms;

final readonly class ContentImageRewriter
{
    public function __construct(private UnsplashImageImporter $importer)
    {
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

            $image = $this->importer->import($value, $siteId, [
                'alt_text' => $payload['alt'] ?? $payload['title'] ?? $payload['name'] ?? null,
                'caption' => $payload['caption'] ?? null,
                'credit' => $payload['credit'] ?? 'Unsplash',
            ]);

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

        return $this->importer->import($url, $siteId, ['alt_text' => $altText])->url;
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
