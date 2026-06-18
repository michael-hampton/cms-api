<?php

namespace App\Services\PublicContent\Images;

final class PublicContentImageUrlSigner
{
    private const string VERSION = 'v1';

    public function sign(string $path): string
    {
        $path = $this->normalisePath($path);
        $payload = self::VERSION . ':' . $path;
        $encodedPayload = $this->base64UrlEncode($payload);
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $encodedPayload, $this->secret(), true));

        return $encodedPayload . '.' . $signature;
    }

    public function verify(string $token): ?string
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $signature] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $encodedPayload, $this->secret(), true));

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = $this->base64UrlDecode($encodedPayload);

        if ($payload === null || !str_starts_with($payload, self::VERSION . ':')) {
            return null;
        }

        return substr($payload, strlen(self::VERSION) + 1);
    }

    private function normalisePath(string $path): string
    {
        return '/' . ltrim(parse_url(trim($path), PHP_URL_PATH) ?: '', '/');
    }

    private function secret(): string
    {
        $secret = (string) (config('public_content.images.signing_key', '') ?: getenv('APP_KEY') ?: getenv('PUBLIC_CONTENT_IMAGE_SIGNING_KEY') ?: 'local-public-content-images');

        return $secret !== '' ? $secret : 'local-public-content-images';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
