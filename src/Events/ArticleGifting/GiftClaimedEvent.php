<?php

namespace App\Events\ArticleGifting;

use App\Models\GiftedArticle;
use App\Models\Member;

class GiftClaimedEvent
{
    public function __construct(
        public readonly GiftedArticle $gift,
        public readonly Member        $member
    )
    {
    }
}