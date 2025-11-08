<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', 'Order');
    }

    public function rules(): array
    {
        return [
            'user_id' => 'integer|exists:members,id',
            'order_number' => 'string|max:255',
            'status' => 'string|in:pending,processing,completed,cancelled,refunded',
            'shipping' => 'numeric|min:0',
            'discount' => 'numeric|min:0',
            'currency' => 'string|max:3',
            'customer_notes' => 'string|max:5000',
            'admin_notes' => 'string|max:5000',
            'shipping_address_id' => 'nullable|integer',
            'billing_address_id' => 'nullable|integer',
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'payment_method' => 'string|max:255',
            'payment_status' => 'string|in:unpaid,paid,refunded',
            'items' => 'required|array|min:1',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.product_sku' => 'string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax' => 'numeric|min:0',
            'items.*.metadata' => 'array'
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {
                // Validate items array is not empty
                if (empty($request->input('items'))) {
                    throw new ValidationException('Order must have at least one item');
                }

                // Validate addresses if provided
                $shippingAddress = $request->input('shipping_address');
                $shippingAddressId = $request->input('shipping_address_id');
                $billingAddressId = $request->input('billing_address_id');

                if (empty($shippingAddressId) && !empty($shippingAddress) && !$this->validateAddress($shippingAddress)) {
                    throw new ValidationException('Invalid shipping address format');
                }

                $billingAddress = $request->input('billing_address');
                if (empty($billingAddressId) && !empty($billingAddress) && !$this->validateAddress($billingAddress)) {
                    throw new ValidationException('Invalid billing address format');
                }
            }
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!isset($this->data['status'])) {
            $this->data['status'] = 'pending';
        }

        if (!isset($this->data['payment_status'])) {
            $this->data['payment_status'] = 'unpaid';
        }

        if (!isset($this->data['currency'])) {
            $this->data['currency'] = 'USD';
        }

        if (!isset($this->data['shipping'])) {
            $this->data['shipping'] = 0;
        }

        if (!isset($this->data['discount'])) {
            $this->data['discount'] = 0;
        }
    }

    private function validateAddress(array $address): bool
    {
        $requiredFields = ['address_line_1', 'city', 'country'];
        foreach ($requiredFields as $field) {
            if (empty($address[$field])) {
                return false;
            }
        }
        return true;
    }
}