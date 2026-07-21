<?php

namespace App\Services\PublicContent\Images\Transform;

use App\Framework\Support\Logger;

final class FrameworkLoggerImageTransformLogger implements ImageTransformLogger
{
    public function __construct(private readonly Logger $logger)
    {
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }
}
