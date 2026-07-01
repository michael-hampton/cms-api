<?php
declare(strict_types=1);

namespace App\Enums\PublicContent;

enum PublicDirectoryFacet: string
{
    case Category = 'category';
    case Tag = 'tag';
    case Author = 'author';
    case Year = 'year';
    case Month = 'month';
    case ArticleType = 'article_type';

    public function label(): string
    {
        return match ($this) {
            self::Category => 'Category',
            self::Tag => 'Tag',
            self::Author => 'Author',
            self::Year => 'Year',
            self::Month => 'Month',
            self::ArticleType => 'Type',
        };
    }
}