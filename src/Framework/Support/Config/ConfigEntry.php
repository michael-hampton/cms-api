<?php

namespace App\Framework\Support\Config;

/**
 * Represents a single configuration entry: a key/value pair with a stable
 * internal identity that is independent of the key name.
 *
 * Renaming an entry (see ConfigModel::rename()) preserves `id` and only
 * changes `key`. Removing an entry and adding a new one with the same key
 * always produces a different `id`. This distinction is what lets callers
 * (and the concurrent-edit/conflict logic built on top of this model)
 * tell "renamed" apart from "deleted and replaced".
 *
 * Instances are immutable; every mutating-looking method returns a new
 * instance.
 */
final class ConfigEntry
{
    public readonly string $id;
    public readonly string $key;
    public readonly mixed $value;

    public function __construct(string $key, mixed $value, ?string $id = null)
    {
        $this->id = $id ?? self::generateId();
        $this->key = $key;
        $this->value = $value;
    }

    public static function generateId(): string
    {
        // random_bytes-based v4-ish UUID; no external dependency required.
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function withKey(string $newKey): self
    {
        return new self($newKey, $this->value, $this->id);
    }

    public function withValue(mixed $newValue): self
    {
        return new self($this->key, $newValue, $this->id);
    }

    /**
     * @return array{id: string, key: string, value: mixed}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'value' => $this->value,
        ];
    }
}