<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class PersonBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $name,
        public readonly ?string $role,
        public readonly ?string $bio,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?array $image,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            name: $data['name'] ?? '',
            role: $data['role'] ?? null,
            bio: $data['bio'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            image: $data['image'] ?? null,
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}