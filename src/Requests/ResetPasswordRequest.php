<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'token' => 'required',
            'password' => 'required|min:8|confirmed'
        ];
    }
}