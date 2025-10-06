<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;
use App\Repositories\CategoryRepository;

class UpdateImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'alt_text' => 'max:255',
            'caption' => 'max:500',
            'description' => 'max:1000',
            'categories' => 'array',
            'categories.*' => 'integer|exists:image_categories,id'
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['site_id'])) {
            $this->data['site_id'] = config('app.default_site_id');
        }
    }
}