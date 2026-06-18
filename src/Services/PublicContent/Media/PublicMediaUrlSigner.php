<?php

namespace App\Services\PublicContent\Media;

final class PublicMediaUrlSigner
{
    private const DEFAULT_TTL_SECONDS = 86400;

    public function __construct(
        private readonly PublicMediaPathResolver $paths = new PublicMediaPathResolver(),
    ) {
    }

    public function signedUrl(string $siteSlug, string $path, ?int $expiresAt = null): string
    {
        $normalised = $this->paths->normalise($path);

        if ($normalised === null || !$this->paths->isEligible($normalised)) {
            return $path;
        }

        $expiresAt ??= time() + $this->ttlSeconds();
        $token = $this->encode($normalised);
        $signature = $this->signature($siteSlug, $token, $expiresAt);

        return sprintf(
            '/api/v1/%s/media/%s?expires=%d&signature=%s',
            rawurlencode($siteSlug),
            rawurlencode($token),
            $expiresAt,
            $signature,
        );
    }

    public function verify(string $siteSlug, string $token, int|string|null $expiresAt, ?string $signature): ?string
    {
        $expiresAt = filter_var($expiresAt, FILTER_VALIDATE_INT);

        if (!$expiresAt || $expiresAt < time() || empty($signature)) {
            return null;
        }

        $expected = $this->signature($siteSlug, $token, $expiresAt);
        if (!hash_equals($expected, (string) $signature)) {
            return null;
        }

        $path = $this->decode($token);

        if ($path === null || !$this->paths->isEligible($path)) {
            return null;
        }

        return $path;
    }

    private function signature(string $siteSlug, string $token, int $expiresAt): string
    {
        return hash_hmac(
            'sha256',
            $siteSlug . '|' . $token . '|' . $expiresAt,
            $this->secret(),
        );
    }

    private function encode(string $path): string
    {
        return rtrim(strtr(base64_encode($path), '+/', '-_'), '=');
    }

    private function decode(string $token): ?string
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }

    private function ttlSeconds(): int
    {
        return max(60, (int) config('public-content.media.signed_ttl_seconds', self::DEFAULT_TTL_SECONDS));
    }

    private function secret(): string
    {
        $secret = getenv('PUBLIC_MEDIA_SIGNING_KEY')
            ?: getenv('APP_KEY')
            ?: config('app.key', null);

        return is_string($secret) && $secret !== ''
            ? $secret
            : 'local-public-media-signing-key';
    }
}
