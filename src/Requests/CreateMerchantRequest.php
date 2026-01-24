<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class CreateMerchantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'string|max:255',
            'description' => 'string',
            'contact_id' => 'integer|exists:merchant_contacts,id',
            'is_active' => 'boolean',
            //'logo' => 'file|mimes:jpeg,jpg,png,gif,webp,svg|max:5120',
            'urls' => 'array',
            'urls.*.url' => 'required|url',
            'urls.*.label' => 'string|max:255',
            'urls.*.is_primary' => 'boolean',
            'site_ids' => 'array',
            //'site_ids.*' => 'integer|exists:sites,id',
        ];
    }
}