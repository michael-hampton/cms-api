<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class CreateInvitationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }
}