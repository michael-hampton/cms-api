<?php

namespace App\Requests\Subscription\BusinessDecisions;

use App\Framework\Http\FormRequest;

class StoreRefundReasonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'label' => ['required', 'string', 'max:150'],
            'requires_note' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
