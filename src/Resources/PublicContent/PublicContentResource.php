<?php

namespace App\Resources\PublicContent;

use App\DTO\PublicContent\PublicContentDocument;

final readonly class PublicContentResource
{
    public function __construct(private PublicContentDocument $document)
    {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->document->id,
            'site_id' => $this->document->siteId,
            'slug' => $this->document->slug,
            'type' => $this->document->type,
            'title' => $this->document->title,
            'summary' => $this->document->summary,
            'seo' => $this->document->seo,
            'taxonomy' => $this->document->taxonomy,
            'authors' => $this->document->authors,
            'links' => $this->document->links,
            'content' => [
                'schema_version' => $this->document->schemaVersion,
                'regions' => array_map(
                    static fn($region) => $region->toArray(),
                    $this->document->regions,
                ),
                'components' => array_map(
                    static fn(array $components): array => array_map(
                        static fn($component): array => $component->toArray(),
                        $components,
                    ),
                    $this->document->components,
                ),
            ],
        ];
    }
}
