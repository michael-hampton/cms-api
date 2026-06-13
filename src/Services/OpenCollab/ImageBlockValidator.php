<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\ImageBlockValidationResult;
use App\Models\Site;
use App\Repositories\Cms\SiteRepository;
use App\Services\OpenCollab\Policies\ContributorImagePolicyInterface;
use App\Services\OpenCollab\Policies\ImageRightsCreditPolicy;

/**
 * Validates image blocks in an article payload.
 *
 * Checks:
 *  - cms_image_id is present (for non-legacy blocks)
 *  - Image exists, is active, and belongs to the correct site
 *  - Contributor may use the image
 *  - Alt text is present (unless is_decorative is set)
 *  - Credit is present when rights require it
 *  - Rights are not blocking
 *
 * Legacy blocks (no cms_image_id, has src) are allowed through — they
 * are handled by the legacy migration path (Ticket 15).
 */
class ImageBlockValidator implements ImageBlockValidatorInterface
{
    public function __construct(
        private readonly CmsImageClientInterface         $cmsImageClient,
        private readonly ContributorImagePolicyInterface $imagePolicy,
        private readonly ImageRightsCreditPolicy         $creditPolicy,
        private readonly SiteRepository                  $siteRepository,
    ) {
    }

    public function validateBlocks(array $blocks, int $siteId, int $contributorId): ImageBlockValidationResult
    {
        $imageBlocks = array_filter($blocks, static fn($b) => ($b['type'] ?? '') === 'image');

        if (empty($imageBlocks)) {
            return ImageBlockValidationResult::ok();
        }

        $site = $this->siteRepository->find($siteId);
        if (!$site) {
            return ImageBlockValidationResult::fail(['_general' => ['Site not found.']]);
        }

        $errors = [];

        foreach ($imageBlocks as $index => $block) {
            $blockErrors = $this->validateSingleBlock($block, $index, $site, $contributorId);
            if (!empty($blockErrors)) {
                $errors = array_merge($errors, $blockErrors);
            }
        }

        return empty($errors)
            ? ImageBlockValidationResult::ok()
            : ImageBlockValidationResult::fail($errors);
    }

    // -------------------------------------------------------------------------
    // Private
    // -------------------------------------------------------------------------

    private function validateSingleBlock(array $block, int|string $index, Site $site, int $contributorId): array
    {
        $errors  = [];
        $blockId = $block['id'] ?? "image_block_{$index}";

        $cmsImageId  = $block['cms_image_id'] ?? null;
        $legacySrc   = $block['src'] ?? '';
        $isDecorative = (bool) ($block['is_decorative'] ?? false);

        // Legacy block — no cms_image_id and has a src. Allow through, warn.
        if ($cmsImageId === null && $legacySrc !== '') {
            return [];
        }

        // Non-legacy block must have a cms_image_id
        if ($cmsImageId === null) {
            $errors["{$blockId}.cms_image_id"] = ['An image must be selected.'];
            return $errors;
        }

        // Resolve the image
        $image = $this->cmsImageClient->find((int) $site->id, (int) $cmsImageId);

        if ($image === null) {
            $errors["{$blockId}.cms_image_id"] = ['The selected image is not available or has been deleted.'];
            return $errors;
        }

        // Site scope check (belt-and-braces — client already enforces this)
        if ((int) $image->site_id !== (int) $site->id) {
            $errors["{$blockId}.cms_image_id"] = ['The selected image does not belong to this site.'];
            return $errors;
        }

        // Contributor permission
        if (!$this->imagePolicy->canUse($contributorId, $site, $image)) {
            $errors["{$blockId}.cms_image_id"] = ['You do not have permission to use this image.'];
            return $errors;
        }

        // Rights check
        $imageRights = $image->image_rights ?? '';
        if ($imageRights !== '' && $this->creditPolicy->isBlocking($imageRights)) {
            $errors["{$blockId}.image_rights"] = ['This image has unconfirmed rights and cannot be used.'];
        }

        // Alt text
        if ($isDecorative) {
            $alt = $block['alt'] ?? '';
            if ($alt !== '') {
                $errors["{$blockId}.alt"] = ['Decorative images must have empty alt text.'];
            }
        } else {
            $alt = trim($block['alt'] ?? '');
            if ($alt === '') {
                $errors["{$blockId}.alt"] = ['Alt text is required for this image.'];
            }
        }

        // Credit
        if ($imageRights !== '' && $this->creditPolicy->requiresCredit($imageRights)) {
            $credit = trim($block['credit'] ?? '');
            if ($credit === '') {
                $errors["{$blockId}.credit"] = ['Credit is required for this image rights type.'];
            }
        }

        return $errors;
    }
}