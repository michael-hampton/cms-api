<?php

namespace App\Services\PublicContent\Images;

final class PublicContentImageUrlResolver
{
    /** @var list<string> */
    private array $localPrefixes = [
        '/storage/uploads/',
        '/uploads/',
    ];

    public function __construct(private readonly PublicContentImageUrlSigner $signer)
    {
    }

    public function resolve(string $url, string|int $site): string
    {
        $url = trim($url);

        if ($url === '' || !$this->isLocalManagedImage($url)) {
            return $url;
        }

        return sprintf(
            '/api/v1/%s/content-images/%s',
            (string) $site,
            $this->signer->sign($this->normalisePath($url)),
        );
    }

    public function isLocalManagedImage(string $url): bool
    {
        $path = $this->normalisePath($url);

        if ($path === '/' || str_contains($path, '..')) {
            return false;
        }

        foreach ($this->localPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function normalisePath(string $url): string
    {
        return '/' . ltrim(parse_url($url, PHP_URL_PATH) ?: '', '/');
    }
}
