<?php

namespace App\Exceptions\OpenCollab;

use RuntimeException;

final class GuidelineNotPublishableException extends RuntimeException
{
    public static function notDraft(int $guidelineId, string $currentStatus): self
    {
        return new self("Guideline #{$guidelineId} cannot be published because its status is '{$currentStatus}'.");
    }
}