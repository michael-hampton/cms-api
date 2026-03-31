<?php

namespace App\Requests\Offers;

use App\Framework\Http\FormRequest;

class StoreProductOfferBundleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['string', 'max:255'],
            'description' => ['string'],
            'bundle_price' => ['required', 'numeric', 'min_number:0'],
            'total_price' => ['numeric', 'min_number:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
            'status' => ['in:pending,published,rejected'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.product_id' => ['integer', 'required_without:items.*.product_offer_id'],
            'items.*.product_offer_id' => ['integer', 'required_without:items.*.product_id'],
            'items.*.quantity' => ['integer', 'min_number:1'],
            'region_set_ids' => ['nullable', 'array'],
            'region_set_ids.*' => ['integer', 'exists:region_sets,id'],
            'terms_and_conditions' => ['nullable', 'string'],
        ];
    }
}