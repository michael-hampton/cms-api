<?php

namespace App\Requests\Subscription\BusinessDecisions;

use App\Framework\Http\FormRequest;

class UpdateCancellationReasonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['string', 'max:50'],
            'label' => ['string', 'max:150'],
            'requires_note' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
