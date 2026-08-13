<?php

namespace App\Services\PublicContent\Images;

use App\Framework\Support\Collection;
use App\Models\Image;
use App\Models\Page;

/**
 * Eager-loads listing images for public-content widget pages and stamps
 * resolved_images so shared page-card rendering does not Image::find() per card.
 */
class PublicContentListingImageHydrator
{
    public function hydrate(Collection $pages): Collection
    {
        $targets = [];
        $imageIds = [];

        foreach ($pages as $page) {
            if (!$page instanceof Page) {
                continue;
            }

            if ($page->relationLoaded('listingImage')) {
                $this->stampResolvedImages($page);
                continue;
            }

            $imageId = (int) ($page->listing_image_id ?? 0);
            if ($imageId < 1) {
                $page->setRelation('listingImage', null);
                continue;
            }

            $targets[] = $page;
            $imageIds[$imageId] = true;
        }

        if ($targets === []) {
            return $pages;
        }

        $images = Image::query()
            ->whereIn('id', array_keys($imageIds))
            ->get()
            ->keyBy('id');

        foreach ($targets as $page) {
            $imageId = (int) $page->listing_image_id;
            $image = $images->get($imageId);
            $page->setRelation('listingImage', $image instanceof Image ? $image : null);
            $this->stampResolvedImages($page);
        }

        return $pages;
    }

    private function stampResolvedImages(Page $page): void
    {
        $image = $page->relationLoaded('listingImage') ? $page->listingImage : null;
        if (!$image instanceof Image || empty($image->url)) {
            return;
        }

        $payload = [
            'url' => (string) $image->url,
            'width' => $image->width ? (int) $image->width : null,
            'height' => $image->height ? (int) $image->height : null,
        ];

        $existing = is_array($page->resolved_images ?? null) ? $page->resolved_images : [];
        $existing['listing-card'] = $payload;
        if (empty($existing['hero-banner'])) {
            $existing['hero-banner'] = $payload;
        }

        $page->resolved_images = $existing;
    }
}
