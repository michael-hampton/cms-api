<?php

namespace App\Requests\Crm;

use App\Enums\ManualPaymentType;
use App\Framework\Http\FormRequest;

class CreateManualPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type'            => ['required', 'string'],
            'amount'          => ['required', 'numeric'],
            'currency'        => ['nullable', 'string', 'max:3'],
            'reference'       => ['nullable', 'string', 'max:255'],
            'notes'           => ['nullable', 'string', 'max:1000'],
            'received_at'     => ['required', 'string'],
            'subscription_id' => ['nullable', 'integer'],
            'order_id'        => ['nullable', 'integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Validate the enum value before the standard rules run so the error
        // message is clear rather than a generic "string" failure.
        $type = $this->input('type');
        if ($type !== null) {
            try {
                ManualPaymentType::from($type);
            } catch (\ValueError) {
                $valid = implode(', ', array_column(ManualPaymentType::cases(), 'value'));
                throw new \InvalidArgumentException("Invalid payment type '{$type}'. Valid values: {$valid}.");
            }
        }
    }
}