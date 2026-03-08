<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class StoreProductOfferBundleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['string', 'max:255'],
            'description' => ['string'],
            'bundle_price' => ['required', 'numeric', 'min:0'],
            'total_price' => ['numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['boolean'],
            'status' => ['in:draft,published,rejected'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.product_id' => ['integer'], //todo 'required_without:items.*.product_offer_id'
            'items.*.product_offer_id' => ['integer'], //todo 'required_without:items.*.product_id'
            'items.*.quantity' => ['integer', 'min:1'],
        ];
    }
}