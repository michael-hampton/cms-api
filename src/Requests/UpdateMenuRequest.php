<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class UpdateMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add your authorization logic here
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'string',
                'max:255'
            ],
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
        if (empty($this->data['site_id'])) {
            $this->data['site_id'] = config('app.default_site_id');
        }
    }
}