<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class CloseContributorAccountRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason for account closure is required.',
        ];
    }
}