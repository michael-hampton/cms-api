<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string'
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Please enter your current password',
            'new_password.required' => 'Please enter a new password',
            'new_password.min' => 'Password must be at least 8 characters',
            'new_password.confirmed' => 'Password confirmation does not match'
        ];
    }

    public function authorize(): bool
    {
        return true; // Already checked in controller with MemberAuth::check()
    }
}