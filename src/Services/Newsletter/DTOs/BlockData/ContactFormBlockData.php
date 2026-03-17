<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class ContactFormBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $title,
        public readonly ?string $subtitle,
        public readonly array $contactInfo,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            title: $data['title'] ?? '',
            subtitle: $data['subtitle'] ?? null,
            contactInfo: $data['contact_info'] ?? [],
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}