<?php

namespace App\DTO\PublicContent;

/**
 * Language and region as independent axes for localised public content.
 */
final readonly class PublicContentLocaleContext
{
    public function __construct(
        public ?string $language = null,
        public ?string $region = null,
    ) {
    }

    /**
     * BCP-47-ish tag used for hreflang / html lang when both axes are known.
     * Falls back to language alone, then region alone.
     */
    public function localeTag(): ?string
    {
        if ($this->language !== null && $this->language !== '' && $this->region !== null && $this->region !== '') {
            return $this->language . '-' . strtoupper($this->region);
        }

        if ($this->language !== null && $this->language !== '') {
            return $this->language;
        }

        if ($this->region !== null && $this->region !== '') {
            return $this->region;
        }

        return null;
    }

    /** @return array{language: ?string, region: ?string, locale: ?string} */
    public function toArray(): array
    {
        return [
            'language' => $this->language,
            'region' => $this->region,
            'locale' => $this->localeTag(),
        ];
    }
}
