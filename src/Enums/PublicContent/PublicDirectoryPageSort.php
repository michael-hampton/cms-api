<?php
declare(strict_types=1);

namespace App\Enums\PublicContent;

enum PublicDirectoryPageSort: string
{
    case Newest = 'newest';
    case Oldest = 'oldest';
    case TitleAsc = 'title_asc';
    case TitleDesc = 'title_desc';
    case MostViewed = 'most_viewed';
    case MostCommented = 'most_commented';
}