<?php

namespace App\Services\PublicContent\Inheritance;

/**
 * Shared parent→child merge helper. Child values win on conflicts.
 * Null / empty-string child values do not erase a parent value.
 */
final class PublicContentSettingsMerger
{
    /**
     * @param array<string, mixed> $parent
     * @param array<string, mixed> $child
     * @return array<string, mixed>
     */
    public function merge(array $parent, array $child): array
    {
        $merged = $parent;

        foreach ($child as $key => $value) {
            if ($this->isUnset($value)) {
                continue;
            }

            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->merge($merged[$key], $value);
                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }

    private function isUnset(mixed $value): bool
    {
        return $value === null || $value === '';
    }
}
