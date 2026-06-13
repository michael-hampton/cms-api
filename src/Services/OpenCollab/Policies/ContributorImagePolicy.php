<?php

namespace App\Services\OpenCollab\Policies;

use App\Models\Image;
use App\Models\Site;
use App\Services\OpenCollab\SitePermissionResolver;

/**
 * Governs which images a contributor may browse, upload, and use.
 *
 * All decisions are site-scoped. Ownership is determined by the CMS
 * created_by field mapped to the OpenCollab user ID.
 *
 * A shared/reusable image is one explicitly marked with image_rights
 * that permits reuse (royalty_free, public_domain, creative_commons).
 * contributor_owned images are only reusable by their uploader.
 */
class ContributorImagePolicy implements ContributorImagePolicyInterface
{
    /** Rights values that make an image reusable by any authorised contributor */
    private const SHARED_RIGHTS = [
        'royalty_free',
        'public_domain',
        'creative_commons',
    ];

    public function __construct(
        private readonly SitePermissionResolver $permissionResolver,
    ) {
    }

    public function canBrowse(int $userId, Site $site): bool
    {
        return $this->hasAnyPermission($userId, (int) $site->id, [
            'images.browse_own',
            'images.use_shared',
            'content.create',
            'content.edit_own',
            'pages.edit',
        ]);
    }

    public function canUpload(int $userId, Site $site): bool
    {
        return $this->hasAnyPermission($userId, (int) $site->id, [
            'images.upload',
            'content.create',
        ]);
    }

    public function canUse(int $userId, Site $site, Image $image): bool
    {
        $siteId = (int) $site->id;

        // Image must belong to the same site
        if ((int) $image->site_id !== $siteId) {
            return false;
        }

        // Image must be active
        if (!(bool) $image->is_active) {
            return false;
        }

        // Contributor owns this image
        if ($this->isOwner($userId, $image)) {
            return $this->hasAnyPermission($userId, $siteId, [
                'images.use_own',
                'content.create',
                'content.edit_own',
            ]);
        }

        // Shared/reusable image
        if ($this->isSharedImage($image)) {
            return $this->hasAnyPermission($userId, $siteId, [
                'images.use_shared',
                'pages.edit',
            ]);
        }

        // Staff with broad access
        return $this->hasAnyPermission($userId, $siteId, [
            'images.edit_any_metadata',
        ]);
    }

    public function canEditCanonicalMetadata(int $userId, Site $site, Image $image): bool
    {
        // Deferred per Ticket 17 — only staff with explicit permission may edit
        return $this->permissionResolver->allows($userId, (int) $site->id, 'images.edit_any_metadata');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function isOwner(int $userId, Image $image): bool
    {
        // created_by is populated by the TracksCreator concern on Image
        return isset($image->created_by) && (int) $image->created_by === $userId;
    }

    private function isSharedImage(Image $image): bool
    {
        return in_array($image->image_rights, self::SHARED_RIGHTS, true);
    }

    private function hasAnyPermission(int $userId, int $siteId, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->permissionResolver->allows($userId, $siteId, $permission)) {
                return true;
            }
        }

        return false;
    }
}