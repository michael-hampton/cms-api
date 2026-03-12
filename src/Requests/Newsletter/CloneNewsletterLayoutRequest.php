<?php

namespace App\Requests\Newsletter;

use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;

class CloneNewsletterLayoutRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['string', 'max:255'],
            'cloned_by' => ['integer'],
            'site_id' => ['integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['slug']) && !empty($this->data['name'])) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }
    }
}