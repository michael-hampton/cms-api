<?php

namespace App\DTO\PublicContent\Sources;

use App\Enums\PublicContent\SourceResultStatus;
use App\Framework\Support\Collection;

/**
 * Uniform result envelope for public content "sources" (recirculation, recommendations, etc).
 *
 * Empty and degraded are deliberately distinct: empty means the source genuinely has no
 * items to show, while degraded means the source could not be resolved (exception or a
 * malformed upstream result) and the page must still render using typed-empty data.
 */
final readonly class SourceResult
{
    private function __construct(
        public mixed $data,
        public SourceResultStatus $status,
        public ?string $reason = null,
    ) {
    }

    public static function ok(mixed $data): self
    {
        return new self($data, SourceResultStatus::Ok, null);
    }

    public static function empty(): self
    {
        return new self([], SourceResultStatus::Empty, null);
    }

    public static function degraded(string $reason = 'unavailable'): self
    {
        return new self([], SourceResultStatus::Degraded, $reason);
    }

    public function isOk(): bool
    {
        return $this->status === SourceResultStatus::Ok;
    }

    public function isEmpty(): bool
    {
        return $this->status === SourceResultStatus::Empty;
    }

    public function isDegraded(): bool
    {
        return $this->status === SourceResultStatus::Degraded;
    }

    /**
     * Typed-empty data is returned for both empty and degraded results so rendering
     * code never has to branch on status just to iterate items.
     *
     * @return list<mixed>
     */
    public function items(): array
    {
        if ($this->isDegraded() || $this->isEmpty()) {
            return [];
        }

        if (is_array($this->data)) {
            return array_values($this->data);
        }

        if ($this->data instanceof Collection) {
            return array_values($this->data->all());
        }

        if (is_iterable($this->data)) {
            return array_values(iterator_to_array($this->data));
        }

        return [];
    }
}
