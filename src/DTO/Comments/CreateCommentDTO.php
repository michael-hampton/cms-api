<?php

namespace App\DTO\Comments;

class CreateCommentDTO
{
    public function __construct(
        public readonly string  $content,
        public readonly int     $pageId,
        public readonly ?int    $memberId,
        public readonly ?string $name,
        public readonly ?string $email,
        public readonly ?int    $parentId,
        public readonly int     $siteId,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            content: $data['content'],
            pageId: $data['page_id'],
            memberId: $data['member_id'] ?? null,
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            parentId: $data['parent_id'] ?? null,
            siteId: $data['site_id'],
            ipAddress: $data['ip_address'] ?? null,
            userAgent: $data['user_agent'] ?? null
        );
    }
}