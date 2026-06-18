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
            $resolved = htmlspecialchars($this->resolver->resolve(htmlspecialchars_decode($source, ENT_QUOTES), $site), ENT_QUOTES, 'UTF-8');

            return substr($tag, 0, $valueStart) . $resolved . substr($tag, $valueEnd);
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
        foreach (['src', 'url', 'thumbnail_url', 'preview_url'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key])) {
                $payload[$key] = $this->resolver->resolve($payload[$key], $site);
            }
        }

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->transformPayload($value, $site);
            }
        }

        return $payload;
    }
}
