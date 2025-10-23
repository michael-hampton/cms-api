<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'integer|exists:users,id',
            'status' => 'string|in:pending,processing,completed,cancelled,refunded',
            'shipping' => 'numeric|min:0',
            'discount' => 'numeric|min:0',
            'currency' => 'string|max:3',
            'customer_notes' => 'string|max:5000',
            'admin_notes' => 'string|max:5000',
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'payment_method' => 'string|max:255',
            'payment_status' => 'string|in:unpaid,paid,refunded'
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
                // Validate addresses if provided
                $shippingAddress = $request->input('shipping_address');
                if ($shippingAddress && !$this->validateAddress($shippingAddress)) {
                    throw new ValidationException('Invalid shipping address format');
                }

                $billingAddress = $request->input('billing_address');
                if ($billingAddress && !$this->validateAddress($billingAddress)) {
                    throw new ValidationException('Invalid billing address format');
                }
            }
        ];
    }

    private function validateAddress(array $address): bool
    {
        $requiredFields = ['street', 'city', 'country'];
        foreach ($requiredFields as $field) {
            if (empty($address[$field])) {
                return false;
            }
        }
        return true;
    }
}