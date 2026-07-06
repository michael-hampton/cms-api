<?php

namespace App\Framework\Support\Config\Publishing;

use App\Framework\Support\Config\ConfigModel;

/**
 * Diffs three snapshots of a configuration document at the key level,
 * and rebuilds a final document that publishes only what the current
 * user is entitled to publish (Ticket 5):
 *
 *   - base:   the document as it was when the current user loaded it
 *   - mine:   the current user's edited working copy
 *   - latest: whatever is currently stored (may include someone else's
 *             published changes made after "base" was loaded)
 *
 * Keys changed only by the current user are published automatically.
 * Keys changed by both — including delete-vs-edit combinations — are
 * reported as conflicts and are never auto-merged; the caller must
 * supply an explicit ConflictResolutionChoice per conflicting key it
 * wants to publish. Anything left unresolved simply stays as latest's
 * value in the published document; the user's local edit for that key
 * is not lost, just not published yet.
 *
 * Deliberately does not reuse the existing inheritance-merge helpers:
 * those ignore empty values and deletions, which is exactly the
 * distinction this resolver has to get right.
 */
final class ConfigConflictResolver
{
    /**
     * @return list<KeyDiff>
     */
    public function diff(ConfigModel $base, ConfigModel $mine, ConfigModel $latest): array
    {
        $orderedKeys = [];
        $seen = [];

        foreach ([$base, $mine, $latest] as $model) {
            foreach ($model->all() as $entry) {
                if (!isset($seen[$entry->key])) {
                    $seen[$entry->key] = true;
                    $orderedKeys[] = $entry->key;
                }
            }
        }

        $diffs = [];

        foreach ($orderedKeys as $key) {
            $diffs[] = $this->diffKey($key, $base, $mine, $latest);
        }

        return $diffs;
    }

    /**
     * @return list<KeyDiff> only the entries with KeyDiffStatus::Conflict
     */
    public function conflicts(ConfigModel $base, ConfigModel $mine, ConfigModel $latest): array
    {
        return array_values(array_filter(
            $this->diff($base, $mine, $latest),
            static fn (KeyDiff $d): bool => $d->status === KeyDiffStatus::Conflict,
        ));
    }

    public function hasConflicts(ConfigModel $base, ConfigModel $mine, ConfigModel $latest): bool
    {
        return $this->conflicts($base, $mine, $latest) !== [];
    }

    /**
     * Rebuilds the document to publish: starts from `latest` (so
     * anyone else's changes are preserved), layers in every key the
     * current user is allowed to publish automatically, and applies
     * $resolutions for conflicting keys the user explicitly resolved.
     *
     * @param array<string, ConflictResolutionChoice> $resolutions keyed by key name
     */
    public function buildPublishableModel(
        ConfigModel $base,
        ConfigModel $mine,
        ConfigModel $latest,
        array $resolutions = [],
    ): ConfigModel {
        $result = $latest;

        foreach ($this->diff($base, $mine, $latest) as $keyDiff) {
            $result = match ($keyDiff->status) {
                KeyDiffStatus::Unchanged, KeyDiffStatus::TheirsOnly => $result,
                KeyDiffStatus::MineOnly, KeyDiffStatus::BothSame => $this->applyExistence(
                    $result,
                    $keyDiff->key,
                    $keyDiff->mineExists,
                    $keyDiff->mineValue,
                ),
                KeyDiffStatus::Conflict => $this->applyConflictResolution($result, $keyDiff, $resolutions[$keyDiff->key] ?? null),
            };
        }

        return $result;
    }

    private function applyConflictResolution(ConfigModel $result, KeyDiff $keyDiff, ?ConflictResolutionChoice $choice): ConfigModel
    {
        if ($choice === null) {
            // No explicit decision: leave latest's value as-is. The
            // user's local edit is not published, but is not discarded
            // either — that's the caller's working copy, untouched here.
            return $result;
        }

        return match ($choice->type) {
            ConflictChoiceType::KeepTheirs => $result,
            ConflictChoiceType::KeepMine => $this->applyExistence($result, $keyDiff->key, $keyDiff->mineExists, $keyDiff->mineValue),
            ConflictChoiceType::Edited => $this->applyExistence($result, $keyDiff->key, $choice->exists, $choice->value),
        };
    }

    private function applyExistence(ConfigModel $model, string $key, bool $shouldExist, mixed $value): ConfigModel
    {
        $existing = $model->getByKey($key);

        if (!$shouldExist) {
            return $existing !== null ? $model->removeByKey($key) : $model;
        }

        if ($existing !== null) {
            return $model->setValue($existing->id, $value);
        }

        return $model->add($key, $value);
    }

    private function diffKey(string $key, ConfigModel $base, ConfigModel $mine, ConfigModel $latest): KeyDiff
    {
        $baseEntry = $base->getByKey($key);
        $mineEntry = $mine->getByKey($key);
        $latestEntry = $latest->getByKey($key);

        $baseExists = $baseEntry !== null;
        $mineExists = $mineEntry !== null;
        $latestExists = $latestEntry !== null;

        $baseValue = $baseEntry?->value;
        $mineValue = $mineEntry?->value;
        $latestValue = $latestEntry?->value;

        $mineChanged = !$this->sameState($baseExists, $baseValue, $mineExists, $mineValue);
        $theirsChanged = !$this->sameState($baseExists, $baseValue, $latestExists, $latestValue);

        $status = match (true) {
            !$mineChanged && !$theirsChanged => KeyDiffStatus::Unchanged,
            $mineChanged && !$theirsChanged => KeyDiffStatus::MineOnly,
            !$mineChanged && $theirsChanged => KeyDiffStatus::TheirsOnly,
            $this->sameState($mineExists, $mineValue, $latestExists, $latestValue) => KeyDiffStatus::BothSame,
            default => KeyDiffStatus::Conflict,
        };

        return new KeyDiff(
            key: $key,
            status: $status,
            baseExists: $baseExists,
            baseValue: $baseValue,
            mineExists: $mineExists,
            mineValue: $mineValue,
            latestExists: $latestExists,
            latestValue: $latestValue,
        );
    }

    private function sameState(bool $existsA, mixed $valueA, bool $existsB, mixed $valueB): bool
    {
        if ($existsA !== $existsB) {
            return false;
        }

        if (!$existsA) {
            return true; // both absent
        }

        return $this->canonicalJson($valueA) === $this->canonicalJson($valueB);
    }

    private function canonicalJson(mixed $value): string
    {
        return json_encode($this->canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (is_array($value)) {
            if (!array_is_list($value)) {
                ksort($value);
            }

            foreach ($value as $k => $v) {
                $value[$k] = $this->canonicalize($v);
            }
        }

        return $value;
    }
}