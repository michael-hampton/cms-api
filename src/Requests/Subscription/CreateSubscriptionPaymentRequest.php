<?php

namespace App\Requests\Subscription;

use App\Framework\Http\FormRequest;

class CreateSubscriptionPaymentRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'payment_method' => 'required|string',
            'amount' => 'required|numeric',
            'currency' => 'required|string'
        ];
    }
}