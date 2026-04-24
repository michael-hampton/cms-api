<?php

namespace App\Services\MemberInsights\Newsletters\Suppression;

final class SuppressionSet
{
    /** @param int[] $newsletterIds */
    private function __construct(
        private readonly array $newsletterIds,
    )
    {
    }

    /** @param int[] $newsletterIds */
    public static function from(array $newsletterIds): self
    {
        return new self(array_values(array_unique($newsletterIds)));
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function contains(int $newsletterId): bool
    {
        return in_array($newsletterId, $this->newsletterIds, strict: true);
    }

    /** @return int[] */
    public function ids(): array
    {
        return $this->newsletterIds;
    }

    public function isEmpty(): bool
    {
        return empty($this->newsletterIds);
    }

    public function count(): int
    {
        return count($this->newsletterIds);
    }
}