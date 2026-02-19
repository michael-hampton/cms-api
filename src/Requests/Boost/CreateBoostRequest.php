<?php

namespace App\Requests\Boost;

use App\Enums\Boost\BoostableType;
use App\Enums\Boost\BoostContext;
use App\Framework\Http\FormRequest;

class CreateBoostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'boostable_type' => ['required', 'string', 'in:' . implode(',', array_column(BoostableType::cases(), 'value'))],
            'target_id' => ['required', 'integer', 'min:1'],
            'merchant_id' => ['required', 'integer', 'min:1'],
            'context' => ['required', 'string', 'in:' . implode(',', array_column(BoostContext::cases(), 'value'))],
            'starts_at' => ['required', 'date', 'before:ends_at'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'multiplier' => ['required', 'numeric', 'min:1.01'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'campaign_override' => ['nullable', 'array'],
            'campaign_override.fixed_price' => ['sometimes', 'numeric', 'min:0'],
            'campaign_override.discount_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}