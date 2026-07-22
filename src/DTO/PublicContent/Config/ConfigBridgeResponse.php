<?php

namespace App\DTO\PublicContent\Config;

/**
 * Runtime config-bridge response (content-bridge style).
 *
 * @param array<string, mixed> $values
 */
final readonly class ConfigBridgeResponse
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        public int $siteId,
        public array $values,
        public ConfigSnapshot $snapshot,
    ) {
    }

    /**
     * @return array{
     *     site_id: int,
     *     values: array<string, mixed>,
     *     snapshot: array{
     *         schema_version: int,
     *         site_id: int,
     *         values_hash: ?string,
     *         values: ?array<string, mixed>
     *     }
     * }
     */
    public function toArray(): array
    {
        return [
            'site_id' => $this->siteId,
            'values' => $this->values,
            'snapshot' => $this->snapshot->toArray(),
        ];
    }
}
