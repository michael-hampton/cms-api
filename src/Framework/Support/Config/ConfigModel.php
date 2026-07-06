<?php

namespace App\Framework\Support\Config;

use InvalidArgumentException;
use OutOfRangeException;

/**
 * Shared in-memory representation of configuration data: an ordered list
 * of ConfigEntry objects, each with a stable identity independent of its
 * key name.
 *
 * This model knows nothing about HTTP, storage, or Blade/JS rendering. It
 * exists purely so that every editor (visual form, raw JSON view, search,
 * conflict-resolution UI) and every backend concern (fingerprinting,
 * selective publish) builds on one well-tested representation instead of
 * re-implementing key/value handling per screen.
 *
 * Instances are immutable: every method that looks like a mutation
 * returns a new ConfigModel, leaving the original untouched. This makes
 * diffing between "loaded" and "current" trivial for later tickets
 * (fingerprinting, conflict resolution).
 */
final class ConfigModel
{
    /** @var list<ConfigEntry> */
    private readonly array $entries;

    /**
     * @param list<ConfigEntry> $entries
     */
    public function __construct(array $entries = [])
    {
        foreach ($entries as $entry) {
            if (!$entry instanceof ConfigEntry) {
                throw new InvalidArgumentException('ConfigModel entries must be ConfigEntry instances');
            }
        }

        $this->entries = array_values($entries);
    }

    // -----------------------------------------------------------------
    // Construction helpers
    // -----------------------------------------------------------------

    /**
     * Builds a ConfigModel from a plain associative array. Key order
     * follows the array's own iteration order. Since PHP arrays cannot
     * contain duplicate keys, the resulting model never has duplicates.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $entries = [];

        foreach ($data as $key => $value) {
            $entries[] = new ConfigEntry((string) $key, $value);
        }

        return new self($entries);
    }

    /**
     * Builds a ConfigModel from an ordered list of [key, value] pairs,
     * the shape used by the existing key/value editors. Unlike
     * fromArray(), this preserves duplicate keys exactly as given, since
     * surfacing (not silently collapsing) duplicates is the point.
     *
     * @param list<array{0: string, 1: mixed}> $pairs
     */
    public static function fromPairs(array $pairs): self
    {
        $entries = [];

        foreach ($pairs as $pair) {
            if (!is_array($pair) || !array_key_exists(0, $pair) || !array_key_exists(1, $pair)) {
                throw new InvalidArgumentException('Each pair must be a [key, value] tuple');
            }

            $entries[] = new ConfigEntry((string) $pair[0], $pair[1]);
        }

        return new self($entries);
    }

    // -----------------------------------------------------------------
    // Conversion back out
    // -----------------------------------------------------------------

    /**
     * Converts the model to a plain associative array. If duplicate keys
     * are present, the *last* entry for a given key wins, matching normal
     * PHP array-assignment semantics. Callers that care about duplicates
     * should check findDuplicateKeys() first.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->entries as $entry) {
            $result[$entry->key] = $entry->value;
        }

        return $result;
    }

    /**
     * Converts the model to an ordered list of [key, value] pairs,
     * preserving order and any duplicate keys.
     *
     * @return list<array{0: string, 1: mixed}>
     */
    public function toPairs(): array
    {
        return array_map(
            static fn (ConfigEntry $entry): array => [$entry->key, $entry->value],
            $this->entries,
        );
    }

    /**
     * @return list<ConfigEntry>
     */
    public function all(): array
    {
        return $this->entries;
    }

    public function size(): int
    {
        return count($this->entries);
    }

    // -----------------------------------------------------------------
    // Lookup
    // -----------------------------------------------------------------

    public function getById(string $id): ?ConfigEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->id === $id) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Returns the first entry matching the given key, if any.
     */
    public function getByKey(string $key): ?ConfigEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->key === $key) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Returns all entries matching the given key, in order.
     *
     * @return list<ConfigEntry>
     */
    public function getAllByKey(string $key): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (ConfigEntry $entry): bool => $entry->key === $key,
        ));
    }

    // -----------------------------------------------------------------
    // Mutation (each returns a new ConfigModel)
    // -----------------------------------------------------------------

    public function add(string $key, mixed $value): self
    {
        return new self([...$this->entries, new ConfigEntry($key, $value)]);
    }

    /**
     * Removes the entry with the given id, if present. No-op otherwise.
     */
    public function removeById(string $id): self
    {
        return new self(array_values(array_filter(
            $this->entries,
            static fn (ConfigEntry $entry): bool => $entry->id !== $id,
        )));
    }

    /**
     * Removes every entry with the given key.
     */
    public function removeByKey(string $key): self
    {
        return new self(array_values(array_filter(
            $this->entries,
            static fn (ConfigEntry $entry): bool => $entry->key !== $key,
        )));
    }

    /**
     * Renames the entry with the given id in place (identity preserved).
     * This is what distinguishes a "rename" from delete-and-re-add: the
     * id does not change, only the key.
     *
     * @throws OutOfRangeException if no entry with that id exists
     */
    public function rename(string $id, string $newKey): self
    {
        $index = $this->indexOfId($id);

        if ($index === null) {
            throw new OutOfRangeException(sprintf('ConfigModel::rename: no entry with id "%s"', $id));
        }

        $entries = $this->entries;
        $entries[$index] = $entries[$index]->withKey($newKey);

        return new self($entries);
    }

    /**
     * Replaces the value of the entry with the given id (identity
     * preserved).
     *
     * @throws OutOfRangeException if no entry with that id exists
     */
    public function setValue(string $id, mixed $newValue): self
    {
        $index = $this->indexOfId($id);

        if ($index === null) {
            throw new OutOfRangeException(sprintf('ConfigModel::setValue: no entry with id "%s"', $id));
        }

        $entries = $this->entries;
        $entries[$index] = $entries[$index]->withValue($newValue);

        return new self($entries);
    }

    private function indexOfId(string $id): ?int
    {
        foreach ($this->entries as $index => $entry) {
            if ($entry->id === $id) {
                return $index;
            }
        }

        return null;
    }

    // -----------------------------------------------------------------
    // Duplicate detection
    // -----------------------------------------------------------------

    /**
     * Deterministically finds keys that occur more than once, in the
     * order those keys first appear. For each duplicated key, entry ids
     * are listed in the order they appear in the model.
     *
     * @return list<array{key: string, entryIds: list<string>}>
     */
    public function findDuplicateKeys(): array
    {
        $idsByKey = [];
        $order = [];

        foreach ($this->entries as $entry) {
            if (!array_key_exists($entry->key, $idsByKey)) {
                $idsByKey[$entry->key] = [];
                $order[] = $entry->key;
            }

            $idsByKey[$entry->key][] = $entry->id;
        }

        $duplicates = [];

        foreach ($order as $key) {
            if (count($idsByKey[$key]) > 1) {
                $duplicates[] = ['key' => $key, 'entryIds' => $idsByKey[$key]];
            }
        }

        return $duplicates;
    }

    public function hasDuplicateKeys(): bool
    {
        return $this->findDuplicateKeys() !== [];
    }

    /**
     * Removes duplicate keys, keeping exactly one entry per key.
     *
     * @param 'keep-first'|'keep-last' $strategy
     */
    public function removeDuplicates(string $strategy = 'keep-first'): self
    {
        if ($strategy !== 'keep-first' && $strategy !== 'keep-last') {
            throw new InvalidArgumentException('removeDuplicates strategy must be "keep-first" or "keep-last"');
        }

        if ($strategy === 'keep-first') {
            $seen = [];
            $kept = [];

            foreach ($this->entries as $entry) {
                if (!isset($seen[$entry->key])) {
                    $seen[$entry->key] = true;
                    $kept[] = $entry;
                }
            }

            return new self($kept);
        }

        // keep-last: find the last index for each key, then keep only
        // entries sitting at those indices, preserving original order.
        $lastIndexByKey = [];

        foreach ($this->entries as $index => $entry) {
            $lastIndexByKey[$entry->key] = $index;
        }

        $kept = [];

        foreach ($this->entries as $index => $entry) {
            if ($lastIndexByKey[$entry->key] === $index) {
                $kept[] = $entry;
            }
        }

        return new self($kept);
    }

    // -----------------------------------------------------------------
    // Filtering (read-only; never mutates stored data or order)
    // -----------------------------------------------------------------

    /**
     * Returns the subset of entries matching the predicate. This is
     * purely a read/display operation: it never changes the model's
     * entries, their order, or their contents.
     *
     * @param callable(ConfigEntry, int): bool $predicate
     * @return list<ConfigEntry>
     */
    public function filter(callable $predicate): array
    {
        $result = [];

        foreach ($this->entries as $index => $entry) {
            if ($predicate($entry, $index)) {
                $result[] = $entry;
            }
        }

        return $result;
    }

    /**
     * Convenience filter for the common "search box" case:
     * case-insensitive substring match against the key.
     *
     * @return list<ConfigEntry>
     */
    public function filterByKeyContains(string $term): array
    {
        if ($term === '') {
            return $this->entries;
        }

        $needle = mb_strtolower($term);

        return array_values(array_filter(
            $this->entries,
            static fn (ConfigEntry $entry): bool => str_contains(mb_strtolower($entry->key), $needle),
        ));
    }

    // -----------------------------------------------------------------
    // Misc
    // -----------------------------------------------------------------

    /**
     * @return list<array{id: string, key: string, value: mixed}>
     */
    public function toSerializableArray(): array
    {
        return array_map(
            static fn (ConfigEntry $entry): array => $entry->toArray(),
            $this->entries,
        );
    }
}