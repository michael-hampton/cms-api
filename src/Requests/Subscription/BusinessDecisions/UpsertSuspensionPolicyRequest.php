<?php

namespace App\Requests\Subscription\BusinessDecisions;

use App\Framework\Http\FormRequest;

class UpsertSuspensionPolicyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'allow_suspend' => ['nullable', 'boolean'],
            'requires_note' => ['nullable', 'boolean'],
        ];
    }
}
