<?php

namespace App\Services\PublicContent\Hero;

use App\Enums\PublicContent\PageHeroType;
use App\Models\Page;
use App\Repositories\Cms\ImageRepository;
use App\Services\PublicContent\Images\PublicContentImageUrlResolver;

class PublicContentHeroDataResolver
{
    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly PublicContentImageUrlResolver $imageUrlResolver
    ) {
    }

    public function resolve(Page $page): ?PublicContentHeroData
    {
        $heroType = PageHeroType::tryFrom((string)$page->hero_type);
        $videoUrl = trim((string)$page->hero_video_url);

        // 🌟 EXTRACT AND SANITIZE THE POSITION VALUE WITH A FALLBACK
        $heroTitlePosition = trim((string)($page->hero_title_position ?? 'standard'));
        if ($heroTitlePosition === '') {
            $heroTitlePosition = 'standard';
        }

        if ($heroType === PageHeroType::Video && $videoUrl !== '') {
            return new PublicContentHeroData(
                type: PageHeroType::Video,
                imageUrl: null,
                videoUrl: $videoUrl,
                title: (string)$page->title,
                heroTitlePosition: $heroTitlePosition,
            );
        }

        $imageId = $page->listing_image_id ?: $page->hero_image_id;

        if (!$imageId) {
            return new PublicContentHeroData(
                type: PageHeroType::Image,
                imageUrl: null,
                videoUrl: null,
                title: (string)$page->title,
                heroTitlePosition: $heroTitlePosition,
            );
        }

        $image = $this->imageRepository->find($imageId);

        $url = $this->imageUrlResolver->resolve($image->url, $page->site_id);

        return new PublicContentHeroData(
            type: PageHeroType::Image,
            imageUrl: $url,
            videoUrl: null,
            title: (string)$page->title,
            heroTitlePosition: $heroTitlePosition,
        );
    }
}