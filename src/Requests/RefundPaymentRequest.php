<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class RefundPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        //return $this->user() && $this->user()->can('update', 'Payment');
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0',
            'reason' => 'required|string'
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Refund amount is required',
            'amount.min' => 'Refund amount must be greater than 0',
            'reason.required' => 'Refund reason is required'
        ];
    }
}