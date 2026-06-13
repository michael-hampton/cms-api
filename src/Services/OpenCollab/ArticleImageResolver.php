<?php

namespace App\Services\OpenCollab;

use App\Models\Image;
use App\Models\Site;
use App\Resources\OpenCollab\ResolvedImageBlock;

/**
 * Resolves CMS image data for all image blocks in an article payload.
 *
 * Images are fetched in a single batch (via findMany) rather than one request
 * per block. Missing or inaccessible images produce an 'unavailable' marker
 * so the editor can show a recoverable state without crashing.
 *
 * Article-specific alt and credit are NOT replaced — the resolved_image
 * property carries canonical CMS values separately for the reset-from-image
 * action.
 */
class ArticleImageResolver
{
    public function __construct(
        private readonly CmsImageClientInterface $cmsImageClient,
    ) {
    }

    /**
     * @param  array[] $blocks  Raw block array from the article
     * @return array[]          Same blocks with resolved_image injected on image blocks
     */
    public function resolveForEditor(array $blocks, Site $site): array
    {
        $imageBlocks = [];
        $imageIds    = [];

        foreach ($blocks as $index => $block) {
            if (($block['type'] ?? '') !== 'image') {
                continue;
            }
            $imageBlocks[$index] = $block;
            $cmsImageId          = $block['cms_image_id'] ?? null;
            if ($cmsImageId !== null) {
                $imageIds[] = (int) $cmsImageId;
            }
        }

        if (empty($imageBlocks)) {
            return $blocks;
        }

        // Single batch fetch — no N+1
        $resolvedImages = empty($imageIds)
            ? []
            : $this->cmsImageClient->findMany((int) $site->id, $imageIds);

        $result = [];
        foreach ($blocks as $index => $block) {
            if (!isset($imageBlocks[$index])) {
                $result[] = $block;
                continue;
            }

            $cmsImageId    = $block['cms_image_id'] ?? null;
            $resolvedImage = $cmsImageId !== null ? ($resolvedImages[(int) $cmsImageId] ?? null) : null;

            $result[] = (new ResolvedImageBlock($block, $resolvedImage))->toArray();
        }

        return $result;
    }
}