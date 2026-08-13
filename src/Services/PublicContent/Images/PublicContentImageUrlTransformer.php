<?php

namespace App\Services\PublicContent\Images;

use App\Services\PublicContent\Images\Transform\ImageTransformerInterface;
use App\Services\PublicContent\Images\Transform\ImageTransformOptions;
use Throwable;

final class PublicContentImageUrlTransformer
{
    /** @var list<string> */
    private array $imageKeys = [
        'src',
        'url',
        'image',
        'image_url',
        'imageUrl',
        'main_image',
        'mainImage',
        'thumbnail',
        'thumbnail_url',
        'thumbnailUrl',
        'preview_url',
        'previewUrl',
        'product_image',
        'productImage',
        'deal_image',
        'dealImage',
        'gallery_image',
        'galleryImage',
    ];

    public function __construct(
        private readonly PublicContentImageUrlResolver $resolver,
        private readonly ?ImageTransformerInterface $imageTransformer = null,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    public function transformBlocks(array $blocks, string|int $site): array
    {
        return array_map(fn (array $block): array => $this->transformBlock($block, $site), $blocks);
    }

    public function transformHtml(string $html, string|int $site): string
    {
        return $this->replaceImageSources($html, $site);
    }

    public function transformUrl(string $url, string|int $site): string
    {
        return $this->resolveImageUrl($url, $site);
    }

    private function replaceImageSources(string $html, string|int $site): string
    {
        // Matches full <img> tags while respecting single and double quotes inside attribute values
        $pattern = '/<img\b(?:["\'][^"\']*["\']|[^"\'>])*?>/is';

        return preg_replace_callback($pattern, function (array $matches) use ($site): string {
            return $this->replaceTagSource($matches[0], $site);
        }, $html);
    }

    private function replaceTagSource(string $tag, string|int $site): string
    {
        // Safely extracts and replaces only the src="..." or src='...' attribute
        $pattern = '/\bsrc\s*=\s*(["\'])(.*?)\1/is';

        $rewritten = preg_replace_callback($pattern, function (array $matches) use ($site): string {
            $quote = $matches[1];
            $source = $matches[2];
            $decoded = htmlspecialchars_decode($source, ENT_QUOTES);
            $resolved = htmlspecialchars($this->resolveImageUrl($decoded, $site), ENT_QUOTES, 'UTF-8');

            return 'src=' . $quote . $resolved . $quote;
        }, $tag, 1);

        return $this->ensureMissingImageFallback($rewritten ?? $tag);
    }

    private function ensureMissingImageFallback(string $tag): string
    {
        if (preg_match('/\bonerror\s*=/i', $tag)) {
            return $tag;
        }

        if (str_contains($tag, PublicContentMissingImageFallback::PUBLIC_URL)) {
            return $tag;
        }

        $handler = htmlspecialchars(
            (new PublicContentMissingImageFallback())->onerrorHandler(),
            ENT_QUOTES,
            'UTF-8',
        );
        $attribute = ' onerror="' . $handler . '"';

        if (str_ends_with($tag, '/>')) {
            return substr($tag, 0, -2) . $attribute . '/>';
        }

        if (str_ends_with($tag, '>')) {
            return substr($tag, 0, -1) . $attribute . '>';
        }

        return $tag;
    }

    private function transformBlock(array $block, string|int $site): array
    {
        if (!isset($block['data']) || !is_array($block['data'])) {
            return $block;
        }

        $block['data'] = $this->transformPayload($block['data'], $site);

        return $block;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function transformPayload(array $payload, string|int $site): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->transformPayload($value, $site);
                continue;
            }

            if (is_string($value) && $this->isImageKey((string) $key)) {
                $payload[$key] = $this->resolveImageUrl($value, $site);
            }
        }

        return $payload;
    }

    private function resolveImageUrl(string $url, string|int $site): string
    {
        $candidate = $url;

        if ($this->imageTransformer !== null) {
            try {
                $candidate = $this->imageTransformer->transform($url, new ImageTransformOptions());
            } catch (Throwable) {
                $candidate = $url;
            }
        }

        return $this->resolver->resolve($candidate, $site);
    }

    private function isImageKey(string $key): bool
    {
        return in_array($key, $this->imageKeys, true);
    }
}