<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class ContactFormBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $title,
        public readonly ?string $subtitle,
        public readonly array   $contactInfo
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            subtitle: $data['subtitle'] ?? null,
            contactInfo: $data['contact_info'] ?? []
        );
    }
}