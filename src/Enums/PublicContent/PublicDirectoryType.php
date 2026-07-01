<?php

namespace App\Enums\PublicContent;

enum PublicDirectoryType: string
{
    case Author = 'author';
    case Category = 'category';
    case Tag = 'tag';
    case BuyingGuide = 'buying-guide';
    case Review = 'review';

    /**
     * Entity-backed directory types (Author/Category/Tag) have a browsable
     * index of entities and a show() page per entity. Page-type-backed
     * directory types (BuyingGuide/Review) are a direct listing of Pages
     * filtered by page_type — there is no entity and no show() page.
     */
    public function hasEntity(): bool
    {
        return match ($this) {
            self::Author, self::Category, self::Tag => true,
            self::BuyingGuide, self::Review => false,
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Author => 'authors',
            self::Category => 'categories',
            self::Tag => 'tags',
            self::BuyingGuide => 'buying-guides',
            self::Review => 'reviews',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::BuyingGuide => 'Buying Guides',
            default => ucfirst($this->plural()),
        };
    }
}