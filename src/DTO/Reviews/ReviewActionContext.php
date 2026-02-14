<?php

namespace App\DTO\Reviews;

class ReviewActionContext
{
    public function __construct(
        public readonly ?int   $memberId,
        public readonly string $sessionId,
        public readonly int    $siteId,
        public readonly bool   $isAuthenticated
    )
    {
    }

    public static function fromAuth(
        ?int   $memberId,
        string $sessionId,
        int    $siteId
    ): self
    {
        return new self(
            memberId: $memberId,
            sessionId: $sessionId,
            siteId: $siteId,
            isAuthenticated: $memberId !== null
        );
    }
}