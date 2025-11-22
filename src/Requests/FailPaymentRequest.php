<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class FailPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        //return $this->user() && $this->user()->can('update', 'Payment');
        return true;
    }

    public function rules(): array
    {
        return [
            'error_message' => 'required|string',
            'error_data' => 'array'
        ];
    }

    public function messages(): array
    {
        return [
            'error_message.required' => 'Error message is required'
        ];
    }
}