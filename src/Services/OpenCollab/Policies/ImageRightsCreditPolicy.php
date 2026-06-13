<?php

namespace App\Services\OpenCollab\Policies;

use App\Enums\ImageRights;

/**
 * Determines credit requirements from an image's rights classification.
 *
 * Uses the existing App\Enums\ImageRights values that the CMS stores on the
 * Image model. Also handles the OpenCollab-specific rights strings that may
 * be stored on uploaded images.
 */
class ImageRightsCreditPolicy
{
    /** Rights values that always require a credit line */
    private const REQUIRES_CREDIT = [
        ImageRights::ATTRIBUTION_REQUIRED->value,
        ImageRights::CREATIVE_COMMONS->value,
        'contributor_owned',
        'third_party_licensed',
        'agency',
        'editorial_use_only',
    ];

    /** Rights values that block submission entirely */
    private const BLOCKING = [
        'unknown',
    ];

    public function requiresCredit(string $imageRights): bool
    {
        return in_array($imageRights, self::REQUIRES_CREDIT, true);
    }

    public function isBlocking(string $imageRights): bool
    {
        return in_array($imageRights, self::BLOCKING, true);
    }
}