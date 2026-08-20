<?php

namespace App\DTO\PublicContent\Widgets;

final readonly class PublicContentPagePickerItem
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $pageType,
        public string $status,
        public ?string $customRoute = null,
    ) {
    }

    /**
     * @return array{id: int, title: string, slug: string, page_type: string, status: string, custom_route: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'page_type' => $this->pageType,
            'status' => $this->status,
            'custom_route' => $this->customRoute,
        ];
    }
}
