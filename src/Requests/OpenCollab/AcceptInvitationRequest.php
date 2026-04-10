<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class AcceptInvitationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}