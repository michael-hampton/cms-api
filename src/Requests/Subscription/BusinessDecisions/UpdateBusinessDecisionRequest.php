<?php

namespace App\Requests\Subscription\BusinessDecisions;

use App\Framework\Http\FormRequest;

class UpdateBusinessDecisionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['string', 'max:150'],
            'description' => ['string', 'max:1000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
