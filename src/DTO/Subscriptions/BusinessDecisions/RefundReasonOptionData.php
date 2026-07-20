<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions\BusinessDecisions;

final class RefundReasonOptionData
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $label,
        public readonly bool $requiresNote,
        public readonly ResolvedRefundOptions $options,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'requires_note' => $this->requiresNote,
            'options' => $this->options->toArray(),
        ];
    }
}
