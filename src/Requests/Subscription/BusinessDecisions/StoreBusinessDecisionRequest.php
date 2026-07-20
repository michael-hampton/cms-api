<?php

namespace App\Requests\Subscription\BusinessDecisions;

use App\Framework\Http\FormRequest;

class StoreBusinessDecisionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['string', 'max:1000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
