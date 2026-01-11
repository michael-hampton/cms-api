<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;
use App\Repositories\Members\PaymentMethodRepository;

class UpdatePaymentMethodRequest extends FormRequest
{
    private PaymentMethodRepository $paymentMethodRepository;

    public function __construct()
    {
        parent::__construct();
        $this->paymentMethodRepository = new PaymentMethodRepository();
    }

    public function authorize(): bool
    {
        //return $this->user() && $this->user()->can('update', 'PaymentMethod');
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'string|max:100',
            'is_active' => 'boolean',
            'requires_processing' => 'boolean',
            'instructions' => 'string',
            'sort_order' => 'integer',
            'configuration' => 'array'
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Name must not exceed 100 characters',
            'is_active.boolean' => 'Is active must be true or false'
        ];
    }
}