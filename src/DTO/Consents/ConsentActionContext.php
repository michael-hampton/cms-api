<?php

namespace App\DTO\Consents;

class ConsentActionContext
{
    public function __construct(
        public readonly string  $source,
        public readonly ?string $reason = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly ?int    $adminUserId = null,
        public readonly ?int    $siteId = null
    )
    {
    }

    public static function fromRequest(\App\Framework\Http\Request $request, string $source): self
    {
        return new self(
            source: $source,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            siteId: \App\Framework\Support\SiteContext::getId()
        );
    }

    public static function fromSystem(?string $reason = null): self
    {
        return new self(
            source: 'system',
            reason: $reason,
            siteId: \App\Framework\Support\SiteContext::getId()
        );
    }

    public static function fromAdmin(int $adminUserId, ?string $reason = null): self
    {
        return new self(
            source: 'admin',
            reason: $reason,
            adminUserId: $adminUserId,
            siteId: \App\Framework\Support\SiteContext::getId()
        );
    }
}