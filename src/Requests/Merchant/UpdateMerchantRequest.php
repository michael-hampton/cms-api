<?php

namespace App\Requests\Merchant;

use App\Framework\Http\FormRequest;

class UpdateMerchantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact_id' => ['nullable', 'integer', 'exists:merchant_contacts,id'],
            'is_active' => ['nullable', 'boolean'],
            'urls' => ['nullable', 'array'],
            'urls.*.url' => ['required', 'url'],
            'urls.*.label' => ['nullable', 'string', 'max:255'],
            'urls.*.is_primary' => ['nullable', 'boolean'],
            'site_ids' => ['nullable', 'array'],
            'site_ids.*' => ['integer', 'exists:sites,id'],
        ];
    }
}