<?php

namespace App\Services\PublicContent\Routing;

/**
 * Flexi-style route override pipeline: select one audience branch, then shallow-merge.
 *
 * Reads effective page / site routing settings shaped as:
 * - base route keys at the top level (or under "base")
 * - override_branches: list of {language, territory, subscriber_status?, values}
 */
final class PublicContentRouteOverrideResolver
{
    public function __construct(
        private readonly RouteOverrideBranchSelector $selector,
        private readonly RouteOverrideMerger $merger,
    ) {
    }

    /**
     * @param array<string, mixed> $routingSettings
     * @return array<string, mixed>
     */
    public function resolve(
        array $routingSettings,
        ?string $language,
        ?string $territory,
        ?string $subscriberStatus,
    ): array {
        if ($routingSettings === []) {
            return [];
        }

        $base = $this->baseRoute($routingSettings);
        $branches = $this->parseBranches($routingSettings['override_branches'] ?? []);

        if ($branches === []) {
            return $base;
        }

        $selected = $this->selector->select($branches, $language, $territory, $subscriberStatus);

        if ($selected === null) {
            return $base;
        }

        return $this->merger->merge($base, $selected->values);
    }

    /**
     * @param array<string, mixed> $routingSettings
     * @return array<string, mixed>
     */
    private function baseRoute(array $routingSettings): array
    {
        if (isset($routingSettings['base']) && is_array($routingSettings['base'])) {
            return $routingSettings['base'];
        }

        $base = $routingSettings;
        unset($base['override_branches'], $base['base']);

        return $base;
    }

    /**
     * @param mixed $raw
     * @return list<RouteOverrideBranch>
     */
    private function parseBranches(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $branches = [];

        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $language = trim((string) ($entry['language'] ?? ''));
            $territory = trim((string) ($entry['territory'] ?? ''));

            if ($language === '' || $territory === '') {
                continue;
            }

            $values = $entry['values'] ?? null;
            if (!is_array($values)) {
                $values = $entry;
                unset(
                    $values['language'],
                    $values['territory'],
                    $values['subscriber_status'],
                    $values['subscriberStatus'],
                    $values['values'],
                );
            }

            $status = $entry['subscriber_status'] ?? $entry['subscriberStatus'] ?? null;

            $branches[] = new RouteOverrideBranch(
                new RouteOverrideAudience(
                    $language,
                    $territory,
                    is_string($status) || $status === null ? $status : (string) $status,
                ),
                $values,
            );
        }

        return $branches;
    }
}
