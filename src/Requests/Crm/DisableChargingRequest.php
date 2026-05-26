<?php

namespace App\Requests\Crm;

use App\Framework\Http\FormRequest;

class DisableChargingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}