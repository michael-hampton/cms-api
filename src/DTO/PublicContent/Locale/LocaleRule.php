<?php

namespace App\DTO\PublicContent\Locale;

/**
 * One locale rule from the versioned locale-rules artefact.
 *
 * @param list<string> $aliases
 */
final readonly class LocaleRule
{
    public function __construct(
        public string $locale,
        public string $language,
        public string $region,
        public string $urlPrefix,
        public bool $enabled,
        public array $aliases = [],
    ) {
    }
}
