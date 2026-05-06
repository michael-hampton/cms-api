<?php

namespace App\Requests\Crm;

use App\Framework\Http\FormRequest;

class UpdateMemberRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'size:2'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['required', 'boolean'],
            'assigned_agent_id' => ['nullable', 'integer'],
            'crm_notes' => ['nullable', 'string', 'max:5000'],
            'show_activity' => ['nullable', 'boolean'],
            'show_badges' => ['nullable', 'boolean'],
            'communication_preferences' => ['nullable', 'array'],
            'communication_preferences.marketing_emails' => ['nullable', 'boolean'],
            'communication_preferences.special_offers' => ['nullable', 'boolean'],
            'communication_preferences.third_party_communications' => ['nullable', 'boolean'],
        ];
    }
}
