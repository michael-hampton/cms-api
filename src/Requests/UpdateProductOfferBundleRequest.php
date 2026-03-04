<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class UpdateProductOfferBundleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['string', 'max:255'],
            'slug' => ['string', 'max:255'],
            'description' => ['string'],
            'bundle_price' => ['numeric', 'min:0'],
            'total_price' => ['numeric', 'min:0'],
            'start_date' => ['date'],
            'end_date' => ['date', 'after:start_date'],
            'is_active' => ['boolean'],
            'status' => ['in:draft,published,rejected'],
            'items' => ['array', 'min:2'],
            'items.*.product_id' => ['integer', 'required_without:items.*.product_offer_id'],
            'items.*.product_offer_id' => ['integer', 'required_without:items.*.product_id'],
            'items.*.quantity' => ['integer', 'min:1'],
        ];
    }
}