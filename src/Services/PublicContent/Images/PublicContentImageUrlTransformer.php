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

    /**
     * @param ImageTransformerInterface|null $imageTransformer Optional CDN-style transform library
     *        (see App\Services\PublicContent\Images\Transform). When given, it runs first for every
     *        image source; recognised remote hosts are rewritten to canonical transform URLs, while
     *        anything it does not recognise (including local managed paths) is fed unchanged into the
     *        existing resolver/signer below, preserving current behaviour.
     */
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

    private function replaceImageSources(string $html, string|int $site): string
    {
        $result = '';
        $offset = 0;

        while (($imgStart = stripos($html, '<img', $offset)) !== false) {
            $imgEnd = strpos($html, '>', $imgStart);

            if ($imgEnd === false) {
                break;
            }

            $result .= substr($html, $offset, $imgStart - $offset);
            $tag = substr($html, $imgStart, $imgEnd - $imgStart + 1);
            $result .= $this->replaceTagSource($tag, $site);
            $offset = $imgEnd + 1;
        }

        return $result . substr($html, $offset);
    }

    private function replaceTagSource(string $tag, string|int $site): string
    {
        foreach (['src="', "src='"] as $needle) {
            $start = stripos($tag, $needle);

            if ($start === false) {
                continue;
            }

            $valueStart = $start + strlen($needle);
            $quote = substr($needle, -1);
            $valueEnd = strpos($tag, $quote, $valueStart);

            if ($valueEnd === false) {
                return $tag;
            }

            $source = substr($tag, $valueStart, $valueEnd - $valueStart);
            $resolved = htmlspecialchars($this->resolveImageUrl(htmlspecialchars_decode($source, ENT_QUOTES), $site), ENT_QUOTES, 'UTF-8');
            $rewritten = substr($tag, 0, $valueStart) . $resolved . substr($tag, $valueEnd);

            return $this->ensureMissingImageFallback($rewritten);
        }

        return $this->ensureMissingImageFallback($tag);
    }

    /**
     * Post-render: when an image fails to load in the browser, swap to the
     * shared placeholder so readers never see a broken-image icon.
     */
    private function ensureMissingImageFallback(string $tag): string
    {
        if (stripos($tag, 'onerror=') !== false) {
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

    /**
     * Runs the CDN-style transform library first (when configured), falling
     * back to the untouched URL on any failure, then always finishes with
     * the existing resolver/signer so local managed paths keep working.
     */
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
