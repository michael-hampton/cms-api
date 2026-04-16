<?php

namespace App\Events\Members;

class CommentPostedByMember
{
    public function __construct(
        public readonly int  $memberId,
        public readonly int  $siteId,
        public readonly ?int $entityId = null
    )
    {
    }
}