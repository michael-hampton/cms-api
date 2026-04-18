<?php

namespace App\Events\Members;

class PageUnlikedByMember
{
    public function __construct(
        public readonly int $memberId,
        public readonly int $siteId,
        public readonly int $pageId,
    )
    {
    }
}