<?php

namespace App\Requests\Subscription\BusinessDecisions;

use App\Framework\Http\FormRequest;

class UpdateRefundReasonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:50'],
            'label' => ['sometimes', 'string', 'max:150'],
            'requires_note' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
