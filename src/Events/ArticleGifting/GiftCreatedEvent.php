<?php

namespace App\Events\ArticleGifting;

use App\Models\GiftedArticle;

class GiftCreatedEvent
{
    public function __construct(
        public readonly GiftedArticle $gift
    )
    {
    }
}