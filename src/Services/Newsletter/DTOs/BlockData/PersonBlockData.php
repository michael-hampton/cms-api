<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class PersonBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $name,
        public readonly ?string $role,
        public readonly ?string $strapline,
        public readonly ?string $bio,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $twitter,
        public readonly ?string $instagram,
        public readonly ?string $facebook,
        public readonly ?string $linkedin,
        public readonly ?string $tiktok,
        public readonly ?string $youtube,
        public readonly ?array $image,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            name: $data['name'] ?? '',
            role: $data['role'] ?? null,
            strapline: $data['strapline'] ?? null,
            bio: $data['bio'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            twitter: $data['twitter'] ?? null,
            instagram: $data['instagram'] ?? null,
            facebook: $data['facebook'] ?? null,
            linkedin: $data['linkedin'] ?? null,
            tiktok: $data['tiktok'] ?? null,
            youtube: $data['youtube'] ?? null,
            image: $data['image'] ?? (!empty($data['avatarSrc']) ? ['src' => $data['avatarSrc'], 'alt' => $data['name'] ?? ''] : null),
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}
