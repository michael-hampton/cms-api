<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class UpdateUserPreferencesRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'preferences' => 'required|array',
            'preferences.*.consent_type_id' => 'required|string',
            'preferences.*.channel' => 'required|string|in:email,in_app',
            'preferences.*.granted' => 'required|boolean',
        ];
    }
}