<?php

namespace App\Enums\OpenCollab;

/**
 * OpenCollab-specific image rights taxonomy.
 *
 * These values are stored on oc_image_submission_evidence and used in
 * contributor-facing flows. They are separate from App\Enums\ImageRights,
 * which is the CMS-internal rights system.
 */
enum OpenCollabImageRights: string
{
    case ContributorOwned    = 'contributor_owned';
    case StaffOwned          = 'staff_owned';
    case ThirdPartyLicensed  = 'third_party_licensed';
    case Agency              = 'agency';
    case EditorialUseOnly    = 'editorial_use_only';
    case Unknown             = 'unknown';

    public function requiresCredit(): bool
    {
        return match ($this) {
            self::StaffOwned, self::Unknown => false,
            default                         => true,
        };
    }

    public function isBlocking(): bool
    {
        return $this === self::Unknown;
    }

    public function label(): string
    {
        return match ($this) {
            self::ContributorOwned   => 'Contributor-owned',
            self::StaffOwned         => 'Staff-owned',
            self::ThirdPartyLicensed => 'Licensed third-party image',
            self::Agency             => 'Agency image',
            self::EditorialUseOnly   => 'Editorial use only',
            self::Unknown            => 'Rights not confirmed',
        };
    }
}