<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class CreateRefundRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'order_id' => 'required|integer',
            'refund_type' => 'required|in:full,partial',
            'refund_amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'internal_notes' => 'nullable|string',
            'notify_customer' => 'boolean',
            'restock_items' => 'boolean',
            'items' => 'nullable|array',
            'items.*.id' => 'nullable|integer',
            'items.*.product_id' => 'nullable|integer',
            'items.*.product_name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.refund_quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.refund_amount' => 'required|numeric|min:0'
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Order ID is required',
            'order_id.integer' => 'Order ID must be an integer',
            'refund_type.required' => 'Refund type is required',
            'refund_type.in' => 'Refund type must be either full or partial',
            'refund_amount.required' => 'Refund amount is required',
            'refund_amount.numeric' => 'Refund amount must be a number',
            'refund_amount.min' => 'Refund amount must be greater than 0',
            'reason.required' => 'Refund reason is required',
            'items.*.refund_quantity.min' => 'Refund quantity must be at least 1'
        ];
    }
}