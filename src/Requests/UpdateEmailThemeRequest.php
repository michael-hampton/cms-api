<?php
// src/Requests/UpdateEmailThemeRequest.php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class UpdateEmailThemeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'string|max:255',
            'slug' => 'string|max:255',
            'description' => 'string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'colors' => 'array',
            'colors.*' => 'string',
            'fonts' => 'array',
            'assets' => 'array',
            'settings' => 'array',
        ];
    }
}