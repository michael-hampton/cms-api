<?php

declare(strict_types=1);

namespace App\Data\PublicContent;

final readonly class PublicDirectoryRelationData
{
    public function __construct(
        public string $name,
        public string $slug,
    ) {
    }

    public static function fromEntity(object $entity): self
    {
        return new self(
            name: (string) $entity->name,
            slug: (string) $entity->slug,
        );
    }
}
