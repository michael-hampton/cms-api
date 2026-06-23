<?php

namespace App\DTO\Billing;

use JsonSerializable;

final readonly class PaymentMethodDto implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $type,
        public string $brand,
        public string $last4,
        public int $expMonth,
        public int $expYear,
        public ?string $funding = null,
        public ?string $billingName = null,
        public ?string $billingEmail = null,
        public ?int $created = null,
        public bool $isDefault = false,
        public bool $canRemove = true,
    ) {
    }

    public static function fromStripe(object $method, string $defaultPaymentMethodId = '', bool $canRemove = true): self
    {
        $card = $method->card ?? null;
        $billingDetails = $method->billing_details ?? null;

        return new self(
            id: (string) $method->id,
            type: (string) ($method->type ?? 'card'),
            brand: (string) ($card->brand ?? 'card'),
            last4: (string) ($card->last4 ?? ''),
            expMonth: (int) ($card->exp_month ?? 0),
            expYear: (int) ($card->exp_year ?? 0),
            funding: isset($card->funding) ? (string) $card->funding : null,
            billingName: isset($billingDetails->name) ? (string) $billingDetails->name : null,
            billingEmail: isset($billingDetails->email) ? (string) $billingDetails->email : null,
            created: isset($method->created) ? (int) $method->created : null,
            isDefault: (string) $method->id === $defaultPaymentMethodId,
            canRemove: $canRemove,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->brand,
            'last4' => $this->last4,
            'exp_month' => $this->expMonth,
            'exp_year' => $this->expYear,
            'is_default' => $this->isDefault,
            'can_remove' => $this->canRemove,
        ];
    }

    public function toSavedCardArray(): array
    {
        $payload = [
            'id' => $this->id,
            'type' => $this->type,
            'card' => [
                'brand' => $this->brand,
                'last4' => $this->last4,
                'exp_month' => $this->expMonth,
                'exp_year' => $this->expYear,
                'funding' => $this->funding,
            ],
            'billing_details' => [
                'name' => $this->billingName,
                'email' => $this->billingEmail,
            ],
        ];

        if ($this->created !== null) {
            $payload['created'] = date('Y-m-d H:i:s', $this->created);
        }

        if ($this->isDefault) {
            $payload['is_default'] = true;
        }

        return $payload;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
