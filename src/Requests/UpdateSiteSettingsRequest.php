<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class UpdateSiteSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'guidelines_version' => ['nullable', 'string', 'max:50'],
            'require_payment_setup' => ['required', 'boolean'],
            'require_kyc_verification' => ['nullable', 'boolean'],
            'require_contracts' => ['required', 'boolean'],
            'require_guidelines_ack' => ['required', 'boolean'],
            'require_age_verification' => ['nullable', 'boolean'],
            'minimum_contributor_age' => ['nullable', 'integer', 'min:13', 'max:120'],
        ];
    }
}
