<?php

namespace App\Services\PublicContent\Locale;

use App\DTO\PublicContent\Locale\LocaleRulesArtefact;
use App\DTO\PublicContent\Locale\PublicContentEdgeRedirectOutcome;
use App\Enums\PublicContent\EdgeRedirectReason;

/**
 * Portable dual-edge redirect decisions from locale artefact rules.
 *
 * Does not perform HTTP redirects — callers map the outcome to a response.
 * PublicContentLocaleResolver remains territory→locale mapping only.
 */
final class PublicContentEdgeRedirectResolver
{
    public function __construct(
        private readonly LocaleRulesArtefact $rules,
    ) {
    }

    /**
     * @param string $path Request path relative to the site root (leading slash optional)
     * @param string|null $knownRegion Optional region/prefix already chosen for the reader
     */
    public function resolve(string $path, ?string $knownRegion = null): PublicContentEdgeRedirectOutcome
    {
        $segments = $this->segments($path);
        $rules = $this->rules->edgeRedirects;

        $disabled = $this->disabledLocaleRedirect($segments);
        if ($disabled !== null) {
            return $disabled;
        }

        if ($rules->collapseDoubledRegion) {
            $collapsed = $this->doubledRegionRedirect($segments);
            if ($collapsed !== null) {
                return $collapsed;
            }
        }

        $home = $this->homeRedirect($segments, $knownRegion);
        if ($home !== null) {
            return $home;
        }

        return PublicContentEdgeRedirectOutcome::none();
    }

    /**
     * @param list<string> $segments
     */
    private function disabledLocaleRedirect(array $segments): ?PublicContentEdgeRedirectOutcome
    {
        if ($segments === []) {
            return null;
        }

        $prefix = $segments[0];
        $rule = $this->rules->findByUrlPrefix($prefix, enabledOnly: false);

        if ($rule === null || $rule->enabled) {
            return null;
        }

        return PublicContentEdgeRedirectOutcome::redirect(
            EdgeRedirectReason::DisabledLocale,
            $this->normalisePath($this->rules->edgeRedirects->disabledLocaleFallbackPath),
        );
    }

    /**
     * @param list<string> $segments
     */
    private function doubledRegionRedirect(array $segments): ?PublicContentEdgeRedirectOutcome
    {
        if (count($segments) < 2) {
            return null;
        }

        $first = $segments[0];
        $second = $segments[1];

        if (strtolower($first) !== strtolower($second)) {
            return null;
        }

        $rule = $this->rules->findByUrlPrefix($first, enabledOnly: true);
        if ($rule === null) {
            return null;
        }

        $remainder = array_slice($segments, 2);
        $target = '/' . $first;
        if ($remainder !== []) {
            $target .= '/' . implode('/', $remainder);
        }

        return PublicContentEdgeRedirectOutcome::redirect(
            EdgeRedirectReason::DoubledRegion,
            $this->normalisePath($target),
        );
    }

    /**
     * @param list<string> $segments
     */
    private function homeRedirect(array $segments, ?string $knownRegion): ?PublicContentEdgeRedirectOutcome
    {
        $rules = $this->rules->edgeRedirects;
        $isGlobalHome = $segments === [];

        if ($isGlobalHome && $rules->preferRegionalHome && $knownRegion !== null && $knownRegion !== '') {
            $rule = $this->rules->findByRegion($knownRegion)
                ?? $this->rules->findByUrlPrefix($knownRegion, enabledOnly: true);

            if ($rule !== null && $rule->enabled) {
                return PublicContentEdgeRedirectOutcome::redirect(
                    EdgeRedirectReason::RegionalHome,
                    $this->normalisePath($rules->regionalHomePath($rule->urlPrefix)),
                );
            }
        }

        if (
            !$rules->preferRegionalHome
            && count($segments) === 1
            && $this->rules->findByUrlPrefix($segments[0], enabledOnly: true) !== null
        ) {
            return PublicContentEdgeRedirectOutcome::redirect(
                EdgeRedirectReason::GlobalHome,
                $this->normalisePath($rules->globalHomePath),
            );
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function segments(string $path): array
    {
        $trimmed = trim($path);
        if ($trimmed === '' || $trimmed === '/') {
            return [];
        }

        return array_values(array_filter(
            explode('/', trim($trimmed, '/')),
            static fn(string $segment): bool => $segment !== '',
        ));
    }

    private function normalisePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/' . trim($path, '/');
    }
}
