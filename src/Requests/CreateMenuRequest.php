<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;

class CreateMenuRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'string|max:255|unique:menus,slug',
            'description' => 'string|max:1000',
            'layout_config' => 'array',
            'layout_config.type' => 'string|in:horizontal,vertical,tiles',
            'layout_config.show_icons' => 'boolean',
            'layout_config.max_depth' => 'integer|min:1|max:10',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Menu name is required.',
            'name.max' => 'Menu name cannot exceed 255 characters.',
            'slug.unique' => 'This slug is already taken.',
            'layout_config.type.in' => 'Layout type must be horizontal, vertical, or tiles.',
            'layout_config.max_depth.max' => 'Maximum depth cannot exceed 10 levels.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name') && !$this->has('slug')) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }

        if (!$this->has('is_active')) {
            $this->data['is_active'] = true;
        }
    }
}