<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Repositories\Members\PaymentMethodRepository;

class CreatePaymentRequest extends FormRequest
{
    private PaymentMethodRepository $paymentMethodRepository;

    public function __construct()
    {
        parent::__construct();
        $this->paymentMethodRepository = new PaymentMethodRepository();
    }

    public function authorize(): bool
    {
        //return $this->user() && $this->user()->can('create', 'Payment');
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => 'required|string|max:50',
            'amount' => 'numeric|min:0',
            'currency' => 'string',
            'transaction_id' => 'string|max:255',
            'payment_intent_id' => 'string|max:255',
            'metadata' => 'array'
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {
                // Validate payment method exists and is active
                $paymentMethod = $this->paymentMethodRepository->findByCode($request->input('payment_method'));
                if (!$paymentMethod) {
                    throw new ValidationException('Invalid payment method');
                }
                if (!$paymentMethod->isActive()) {
                    throw new ValidationException('Payment method is not active');
                }
            }
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method.required' => 'Payment method is required',
            'amount.min' => 'Amount must be greater than or equal to 0',
            'currency.size' => 'Currency code must be exactly 3 characters'
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!isset($this->data['currency'])) {
            $this->data['currency'] = 'GBP';
        }
    }
}