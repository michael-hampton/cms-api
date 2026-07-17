<?php

namespace App\Requests\Subscription;

use App\Framework\Http\FormRequest;

class SubscriptionPolicySettingOverrideRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'policy_class' => ['required', 'string'],
            'setting_key'  => ['required', 'string'],
            // Booleans and the nullable pause-limit int are both valid
            // shapes for `value` — type-checked against the specific
            // PolicySettingKey by SubscriptionPolicySettingOverrideService,
            // not here, since the rule depends on which setting_key was
            // submitted.
            'value'        => ['required'],
            'reason'       => ['required', 'string', 'min:1', 'max:1000'],
        ];
    }
}