<?php

namespace App\Data\PublicContent;

use App\Enums\PublicContent\PublicDirectoryType;

final readonly class PublicDirectoryEntityData
{
    public function __construct(
        public int $id,
        public PublicDirectoryType $type,
        public string $name,
        public string $slug,
        public ?string $description,
        public ?string $image,
        public ?string $icon,
        public ?string $color,
        public array $meta,
    ) {
    }

    public static function fromEntity(PublicDirectoryType $type, object $entity): self
    {
        return new self(
            (int) $entity->id,
            $type,
            (string) $entity->name,
            (string) $entity->slug,
            $entity->description ?? $entity->bio ?? null,
            $entity->avatar ?? null,
            $entity->icon ?? null,
            $entity->color ?? null,
            self::buildMeta($type, $entity),
        );
    }

    private static function buildMeta(PublicDirectoryType $type, object $entity): array
    {
        return match ($type) {
            PublicDirectoryType::Author => [
                'bio' => $entity->bio ?? null,
                'expertise' => $entity->expertise ?? null,
                'location' => $entity->location ?? [],
                'education' => $entity->education ?? [],
                'awards' => $entity->awards ?? [],
                'website' => $entity->website ?? null,
                'twitter' => $entity->twitter ?? null,
                'linkedin' => $entity->linkedin ?? null,
                'facebook' => $entity->facebook ?? null,
                'joined_at' => $entity->created_at ?? null,
                'years_of_experience' => $entity->years_of_experience ?? null,
            ],
            PublicDirectoryType::Category => ['parent_id' => $entity->parent_id ?? null],
            PublicDirectoryType::Tag => [
                'usage_count' => (int) ($entity->usage_count ?? 0),
                'featured' => (bool) ($entity->is_featured ?? false),
            ],
        };
    }
}
