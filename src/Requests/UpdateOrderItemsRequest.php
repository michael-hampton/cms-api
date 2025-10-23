<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;

class UpdateOrderItemsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.product_sku' => 'string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax' => 'numeric|min:0',
            'items.*.metadata' => 'array'
        ];
    }

    public function authorize(): bool
    {
        return true;
        //return $this->user() && $this->user()->can('update', 'Order');
    }

    public function after(): array
    {
        return [
            function ($request) {
                if (empty($request->input('items'))) {
                    throw new ValidationException('Order must have at least one item');
                }
            }
        ];
    }
}