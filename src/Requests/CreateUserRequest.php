<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', 'User');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'age' => 'required|integer|min:18',
            'role' => 'in:user,admin'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please provide your full name.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'password.min' => 'Password must be at least 8 characters long.',
            'age.min' => 'You must be at least 18 years old.',
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {
                // Custom validation logic after main validation
                if ($request->input('email') === 'admin@example.com' && $request->input('role') !== 'admin') {
                    throw new ValidationException('Admin email must have admin role');
                }
            }
        ];
    }

    protected function prepareForValidation(): void
    {
        // Clean up data before validation
        $this->data['email'] = strtolower(trim($this->data['email'] ?? ''));
        $this->data['name'] = trim($this->data['name'] ?? '');
    }
}