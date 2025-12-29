<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;
use App\Models\Brand;

class CreateBrandRequest extends FormRequest
{
    protected static function model(): string
    {
        return Brand::class;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'string|max:255',
            'description' => 'string',
            'website' => 'url',
            'is_active' => 'boolean',
            'seo_title' => 'string|max:255',
            'seo_description' => 'string',
            'no_index' => 'boolean',
            'canonical_url' => 'url'
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['slug']) && !empty($this->data['name'])) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }

        if (!isset($this->data['is_active'])) {
            $this->data['is_active'] = true;
        }

        if (!isset($this->data['no_index'])) {
            $this->data['no_index'] = false;
        }
    }
}