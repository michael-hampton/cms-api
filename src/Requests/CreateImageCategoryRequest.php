<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class CreateImageCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'string|max:255|unique:image_categories,slug',
            'description' => 'string|max:1000'
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['site_id'])) {
            $this->data['site_id'] = config('app.default_site_id');
        }
    }
}