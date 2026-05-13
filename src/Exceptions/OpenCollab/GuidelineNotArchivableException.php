<?php

namespace App\Exceptions\OpenCollab;

use RuntimeException;

final class GuidelineNotArchivableException extends RuntimeException
{
    public static function notPublished(int $guidelineId, string $currentStatus): self
    {
        return new self("Guideline #{$guidelineId} cannot be archived because its status is '{$currentStatus}'.");
    }
}