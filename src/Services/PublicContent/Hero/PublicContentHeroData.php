<?php

namespace App\Services\PublicContent\Hero;

use App\Enums\PublicContent\PageHeroType;

final readonly class PublicContentHeroData
{
    public function __construct(
        public PageHeroType $type,
        public ?string $imageUrl,
        public ?string $videoUrl,
        public string $title,
    ) {
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'imageUrl' => $this->imageUrl,
            'videoUrl' => $this->videoUrl,
            'title' => $this->title,
        ];
    }

    public function preloadUrl(): ?string
    {
        return $this->type === PageHeroType::Image ? $this->imageUrl : null;
    }
}