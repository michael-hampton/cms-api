<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class OfferBlockData extends BaseBlockData
{
    public function __construct(
        public readonly int  $offerId,
        public readonly ?int $dealId = null
    )
    {
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['offer_id'])) {
            throw new \InvalidArgumentException('Missing required field: offer_id');
        }

        return new self(
            offerId: (int)$data['offer_id'],
            dealId: isset($data['deal_id']) ? (int)$data['deal_id'] : null
        );
    }
}