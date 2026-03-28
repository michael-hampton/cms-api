<?php

namespace App\DTO\Subscriptions;

use App\Models\Territory;

/**
 * Immutable value object capturing the decision context for a single
 * subscription fulfilment. Passed from FulfilmentDecisionService through
 * to the delivery channel so each layer has the information it needs
 * without re-querying.
 *
 * Having this as an explicit DTO (rather than a loose array) means:
 *   - The shape of decision context is enforced at construction time.
 *   - Changes to what constitutes a decision are visible at the type level.
 *   - Services do not need to agree on array key names.
 */
class FulfilmentDecisionContext
{
    public function __construct(
        public readonly ?Territory $territory,
        public array $addressSnapshot,
        public readonly array      $channelMetadata = [],
    )
    {
    }

    public function hasTerritory(): bool
    {
        return $this->territory !== null;
    }

    public function territoryId(): ?int
    {
        return $this->territory?->id;
    }
}