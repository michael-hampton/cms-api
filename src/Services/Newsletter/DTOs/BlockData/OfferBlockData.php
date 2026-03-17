<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

use InvalidArgumentException;

class OfferBlockData extends BaseBlockData
{
    public function __construct(
        public readonly int  $offerId,
        public readonly ?int $dealId = null,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['offer_id'])) {
            throw new InvalidArgumentException('Missing required field: offer_id');
        }

        $instance = new static(
            offerId: (int)$data['offer_id'],
            dealId: isset($data['deal_id']) ? (int)$data['deal_id'] : null,
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}