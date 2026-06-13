<?php

namespace App\Services\OpenCollab\Policies;

use App\Models\Image;
use App\Models\Site;

interface ContributorImagePolicyInterface
{
    public function canBrowse(int $userId, Site $site): bool;

    public function canUpload(int $userId, Site $site): bool;

    /**
     * Can the contributor use this specific image in their article?
     * Covers both own images and shared/reusable images.
     */
    public function canUse(int $userId, Site $site, Image $image): bool;

    /**
     * Can the contributor edit the canonical CMS metadata for this image?
     * Deferred per Ticket 17 — included for interface completeness.
     */
    public function canEditCanonicalMetadata(int $userId, Site $site, Image $image): bool;
}