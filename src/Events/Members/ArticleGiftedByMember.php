<?php

namespace App\Events\Members;

class ArticleGiftedByMember
{
    public function __construct(
        public readonly int  $giftedByMemberId,
        public readonly ?int $recipientMemberId,
        public readonly int  $siteId,
        public readonly int  $articleGiftId,
    )
    {
    }
}