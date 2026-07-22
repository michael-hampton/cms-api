<?php

namespace App\DTO\PublicContent\Config;

/**
 * Runtime config-bridge request (content-bridge style).
 *
 * @param list<string> $keys
 */
final readonly class ConfigBridgeRequest
{
    /**
     * @param list<string> $keys
     */
    public function __construct(
        public int $siteId,
        public array $keys = [],
        public ?ConfigSnapshot $snapshot = null,
    ) {
    }

    /**
     * @return array{
     *     site_id: int,
     *     keys: list<string>,
     *     snapshot: ?array{
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
            'keys' => array_values($this->keys),
            'snapshot' => $this->snapshot?->toArray(),
        ];
    }
}
