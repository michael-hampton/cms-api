<?php

namespace App\Services\PublicContent\Images\Transform;

/**
 * Thin logging boundary so {@see ImageTransformer} can be unit tested without
 * static-mocking the framework `Logger`.
 */
interface ImageTransformLogger
{
    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void;
}
