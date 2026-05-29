<?php

namespace App\Resources\OpenCollab;

use App\Framework\Resource\JsonResource;

class ContributorProfileResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'bio' => $this->getAttribute('bio'),
            'avatar' => $this->getAttribute('avatar'),
            'expertise' => $this->getAttribute('expertise_array', []),
            'sample_links' => $this->sampleLinks(),
        ];
    }

    private function sampleLinks(): array
    {
        $links = $this->getAttribute('sample_links', []);

        return is_array($links) ? array_values($links) : [];
    }
}
