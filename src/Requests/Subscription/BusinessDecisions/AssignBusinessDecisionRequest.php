<?php

namespace App\Requests\Subscription\BusinessDecisions;

use App\Framework\Http\FormRequest;

class AssignBusinessDecisionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'assignable_type' => ['required', 'string', 'in:site,plan'],
            'assignable_id' => ['required', 'integer', 'min:1'],
            'business_decision_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
