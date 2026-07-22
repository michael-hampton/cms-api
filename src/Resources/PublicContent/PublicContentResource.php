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
            'access' => $this->document->access,
            'design_tokens' => $this->document->designTokens,
            'widgets' => $this->document->widgets,
            'content' => [
                'schema_version' => $this->document->schemaVersion,
                'regions' => $this->regions(),
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

    private function regions(): array
    {
        $regions = [];

        foreach ($this->document->regions as $region) {
            $regions[$region->name] = $region->toArray();
        }

        return $regions;
    }
}
