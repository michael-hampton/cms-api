<?php

namespace App\Framework\Tests\Factories;

trait HasSiteId
{
    protected ?int $siteId = null;

    /**
     * Set the site ID for the model
     */
    public function forSite(int $siteId): static
    {
        $this->siteId = $siteId;
        $this->attributes['site_id'] = $siteId;
        return $this;
    }

    /**
     * Override definition to include site_id
     */
    protected function withSiteId(array $attributes): array
    {
        if ($this->siteId !== null) {
            $attributes['site_id'] = $this->siteId;
        }

        return $attributes;
    }
}