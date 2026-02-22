<?php

namespace App\Enums\Pages;

enum PageFilterType: string
{
    case Author = 'author';
    case Category = 'category';
    case Tag = 'tag';
    case Brand = 'brand'; // brand pages filter by tag relation, same as Tag
}