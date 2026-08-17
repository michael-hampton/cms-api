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

    /**
     * Shared rule: a page with no language axis is treated as having no
     * locale at all, regardless of whether a region was resolved. Region
     * alone is not enough for the page to state a language.
     */
    public function isMissing(): bool
    {
        return $this->language === null || trim($this->language) === '';
    }

    /**
     * Fills in the single configured default language when the locale is
     * missing, and reports whether it had to. An existing language is left
     * untouched — this never overwrites a locale that is already present.
     */
    public function withDefaultLanguage(string $defaultLanguage): PublicContentLocaleDefaultResult
    {
        if (!$this->isMissing()) {
            return PublicContentLocaleDefaultResult::unchanged($this);
        }

        return PublicContentLocaleDefaultResult::applied(
            new self(language: $defaultLanguage, region: $this->region),
        );
    }
}
