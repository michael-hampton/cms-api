<?php

namespace App\Events\Members;

class PageViewedByMember
{
    public function __construct(
        public readonly int $memberId,
        public readonly int $pageId,
        public readonly int $siteId,
    )
    {
    }
}