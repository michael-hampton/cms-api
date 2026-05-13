<?php

namespace App\Exceptions\OpenCollab;

use RuntimeException;

final class GuidelineNotEditableException extends RuntimeException
{
    public static function alreadyPublished(int $guidelineId): self
    {
        return new self("Guideline #{$guidelineId} is published and cannot be edited. Create a new version instead.");
    }

    public static function alreadyArchived(int $guidelineId): self
    {
        return new self("Guideline #{$guidelineId} is archived and cannot be edited.");
    }
}