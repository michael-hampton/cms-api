<?php

namespace App\Services\PublicContent\Media;

final class PublicContentMediaUrlTransformer
{
    public function __construct(
        private readonly PublicMediaUrlSigner $signer = new PublicMediaUrlSigner(),
        private readonly PublicMediaPathResolver $paths = new PublicMediaPathResolver(),
    ) {
    }

    public function transformHtml(string $html, string $siteSlug): string
    {
        if ($html === '') {
            return $html;
        }

        return preg_replace_callback(
            '/\b(src|srcset)=("|\')([^"\']+)(\2)/i',
            function (array $match) use ($siteSlug): string {
                $attribute = strtolower($match[1]);
                $quote = $match[2];
                $value = html_entity_decode($match[3], ENT_QUOTES, 'UTF-8');

                $replacement = $attribute === 'srcset'
                    ? $this->transformSrcSet($value, $siteSlug)
                    : $this->transformUrl($value, $siteSlug);

                return $match[1] . '=' . $quote . htmlspecialchars($replacement, ENT_QUOTES, 'UTF-8') . $quote;
            },
            $html,
        ) ?? $html;
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function transformStructuredData(array $data, string $siteSlug): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->transformStructuredData($value, $siteSlug);
                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            if ($this->isMediaField((string) $key) || $this->paths->isEligible($value)) {
                $data[$key] = $this->transformUrl($value, $siteSlug);
            }
        }

        return $data;
    }

    private function transformUrl(string $url, string $siteSlug): string
    {
        return $this->paths->isEligible($url)
            ? $this->signer->signedUrl($siteSlug, $url)
            : $url;
    }

    private function transformSrcSet(string $srcset, string $siteSlug): string
    {
        $parts = array_map('trim', explode(',', $srcset));

        foreach ($parts as &$part) {
            if ($part === '') {
                continue;
            }

            $tokens = preg_split('/\s+/', $part);
            if (!$tokens || $tokens[0] === '') {
                continue;
            }

            $tokens[0] = $this->transformUrl($tokens[0], $siteSlug);
            $part = implode(' ', $tokens);
        }

        return implode(', ', $parts);
    }

    private function isMediaField(string $key): bool
    {
        return in_array($key, [
            'src',
            'url',
            'image',
            'image_url',
            'thumbnail',
            'thumbnail_url',
            'preview_url',
            'avatar',
            'avatar_url',
        ], true);
    }
}
