<?php
declare(strict_types=1);

namespace App\Enums\PublicContent;

enum PublicDirectoryIndexSort: string
{
    case NameAsc = 'name_asc';
    case NameDesc = 'name_desc';
    case Newest = 'newest';
    case Oldest = 'oldest';
    case MostArticles = 'most_articles';
}