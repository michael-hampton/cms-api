<?php

namespace App\Services\PublicContent\Recirculation;

use App\Framework\Support\Logger;

final class FrameworkRecirculationSourceLogger implements RecirculationSourceLogger
{
    public function warning(string $message, array $context = []): void
    {
        Logger::warning($message, $context);
    }
}
