<?php

namespace App\Enums\PublicContent;

enum PublicDirectoryType: string
{
    case Author = 'author';
    case Category = 'category';
    case Tag = 'tag';

    public function plural(): string
    {
        return match ($this) {
            self::Author => 'authors',
            self::Category => 'categories',
            self::Tag => 'tags',
        };
    }

    public function title(): string
    {
        return ucfirst($this->plural());
    }
}
