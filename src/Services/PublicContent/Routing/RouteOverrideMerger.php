<?php

namespace App\Services\PublicContent\Routing;

/**
 * Flexi-faithful shallow one-level merge of an override branch onto a base route.
 *
 * Overrides win on simple values and new keys.
 * other_routing_params and fcsis_routing_params are merged entry-by-entry by name.
 * No other list or nested structure is deep-merged — replaced wholesale.
 */
final class RouteOverrideMerger
{
    private const array BY_NAME_PARAM_LISTS = [
        'other_routing_params',
        'fcsis_routing_params',
    ];

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    public function merge(array $base, array $override): array
    {
        $merged = $base;

        foreach ($override as $key => $value) {
            if (in_array($key, self::BY_NAME_PARAM_LISTS, true) && is_array($value)) {
                $merged[$key] = $this->mergeRoutingParamsByName(
                    is_array($merged[$key] ?? null) ? $merged[$key] : [],
                    $value,
                );
                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }

    /**
     * @param list<array<string, mixed>>|array<string, mixed> $base
     * @param list<array<string, mixed>>|array<string, mixed> $override
     * @return list<array<string, mixed>>|array<string, mixed>
     */
    private function mergeRoutingParamsByName(array $base, array $override): array
    {
        if ($this->isListOfNamedParams($base) || $this->isListOfNamedParams($override)) {
            $indexed = [];

            foreach ($base as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $name = (string) ($entry['name'] ?? '');
                if ($name !== '') {
                    $indexed[$name] = $entry;
                }
            }

            foreach ($override as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $name = (string) ($entry['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $indexed[$name] = isset($indexed[$name])
                    ? array_merge($indexed[$name], $entry)
                    : $entry;
            }

            return array_values($indexed);
        }

        // Associative name => value maps: override wins per key, no deep merge of values.
        return array_merge($base, $override);
    }

    private function isListOfNamedParams(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        if (array_is_list($value)) {
            $first = $value[0] ?? null;

            return is_array($first) && array_key_exists('name', $first);
        }

        return false;
    }
}
