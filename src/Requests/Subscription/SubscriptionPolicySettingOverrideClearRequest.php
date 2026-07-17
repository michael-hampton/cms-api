<?php

namespace App\Requests\Subscription;

use App\Framework\Http\FormRequest;

class SubscriptionPolicySettingOverrideClearRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'policy_class' => ['required', 'string'],
            'setting_key'  => ['required', 'string'],
            'reason' => ['required', 'string', 'min:1', 'max:1000'],
        ];
    }
}