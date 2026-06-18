<?php

namespace App\Services\PublicContent\Images;

final class PublicContentImageUrlTransformer
{
    public function __construct(private readonly PublicContentImageUrlResolver $resolver)
    {
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    public function transformBlocks(array $blocks, int $siteId): array
    {
        return array_map(fn (array $block): array => $this->transformBlock($block, $siteId), $blocks);
    }

    public function transformHtml(string $html, int $siteId): string
    {
        if ($html === '') {
            return $html;
        }

        return preg_replace_callback(
            '/(<img\b[^>]*\bsrc=["\'])([^"\']+)(["\'][^>]*>)/i',
            fn (array $matches): string => $matches[1] . htmlspecialchars($this->resolver->resolve(htmlspecialchars_decode($matches[2], ENT_QUOTES), $siteId), ENT_QUOTES, 'UTF-8') . $matches[3],
            $html,
        ) ?? $html;
    }

    private function transformBlock(array $block, int $siteId): array
    {
        if (!isset($block['data']) || !is_array($block['data'])) {
            return $block;
        }

        $block['data'] = $this->transformPayload($block['data'], $siteId);

        return $block;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function transformPayload(array $payload, int $siteId): array
    {
        foreach (['src', 'url', 'thumbnail_url', 'preview_url'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key])) {
                $payload[$key] = $this->resolver->resolve($payload[$key], $siteId);
            }
        }

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->transformPayload($value, $siteId);
            }
        }

        return $payload;
    }
}
