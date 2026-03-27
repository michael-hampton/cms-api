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
            'is_active' => ['required', 'boolean'],
            'assigned_agent_id' => ['nullable', 'integer'],
            'crm_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}