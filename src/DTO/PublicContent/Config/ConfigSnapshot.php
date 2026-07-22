<?php

namespace App\DTO\PublicContent\Config;

/**
 * Render-identity config snapshot. Either a values hash or the values map
 * may be present; both are part of the shared contract.
 *
 * @param array<string, mixed>|null $values
 */
final readonly class ConfigSnapshot
{
    /**
     * @param array<string, mixed>|null $values
     */
    public function __construct(
        public int $schemaVersion,
        public int $siteId,
        public ?string $valuesHash = null,
        public ?array $values = null,
    ) {
    }

    /**
     * @return array{
     *     schema_version: int,
     *     site_id: int,
     *     values_hash: ?string,
     *     values: ?array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'site_id' => $this->siteId,
            'values_hash' => $this->valuesHash,
            'values' => $this->values,
        ];
    }

    /**
     * Stable hash of the values map for render-identity comparison.
     *
     * @param array<string, mixed> $values
     */
    public static function hashValues(array $values): string
    {
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fromValues(int $schemaVersion, int $siteId, array $values): self
    {
        return new self(
            schemaVersion: $schemaVersion,
            siteId: $siteId,
            valuesHash: self::hashValues($values),
            values: $values,
        );
    }
}
