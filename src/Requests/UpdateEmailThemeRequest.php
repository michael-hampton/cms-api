<?php
// src/Requests/UpdateEmailThemeRequest.php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class UpdateEmailThemeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'colors' => ['nullable', 'array'],
            'colors.*' => ['string'],
            'fonts' => ['nullable', 'array'],
            'assets' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ];
    }
}