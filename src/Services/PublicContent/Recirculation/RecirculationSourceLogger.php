<?php

namespace App\Services\PublicContent\Recirculation;

/**
 * Logs recirculation source degradation without static coupling in tests.
 */
interface RecirculationSourceLogger
{
    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void;
}
