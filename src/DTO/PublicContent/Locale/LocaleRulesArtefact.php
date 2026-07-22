<?php

namespace App\DTO\PublicContent\Locale;

/**
 * Versioned locale rules artefact. Loaded fail-closed at start-up.
 *
 * @param list<LocaleRule> $locales
 */
final readonly class LocaleRulesArtefact
{
    /**
     * @param list<LocaleRule> $locales
     */
    public function __construct(
        public int $schemaVersion,
        public array $locales,
        public string $sourcePath,
        public ?string $artefactVersion = null,
        public LocaleEdgeRedirectRules $edgeRedirects = new LocaleEdgeRedirectRules(),
    ) {
    }

    public function findByLocale(string $locale): ?LocaleRule
    {
        $needle = strtolower(str_replace('_', '-', $locale));

        foreach ($this->locales as $rule) {
            if (strtolower($rule->locale) === $needle) {
                return $rule;
            }

            foreach ($rule->aliases as $alias) {
                if (strtolower(str_replace('_', '-', (string) $alias)) === $needle) {
                    return $rule;
                }
            }
        }

        return null;
    }

    public function findByRegion(string $region): ?LocaleRule
    {
        $needle = strtoupper($region);

        foreach ($this->locales as $rule) {
            if (!$rule->enabled) {
                continue;
            }

            if (strtoupper($rule->region) === $needle || strtolower($rule->urlPrefix) === strtolower($region)) {
                return $rule;
            }
        }

        return null;
    }

    public function findByUrlPrefix(string $prefix, bool $enabledOnly = false): ?LocaleRule
    {
        $needle = strtolower(trim($prefix, '/'));

        foreach ($this->locales as $rule) {
            if ($enabledOnly && !$rule->enabled) {
                continue;
            }

            if (strtolower($rule->urlPrefix) === $needle) {
                return $rule;
            }
        }

        return null;
    }
}
