<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Repositories\PaymentMethodRepository;

class CreatePaymentMethodRequest extends FormRequest
{
    private PaymentMethodRepository $paymentMethodRepository;

    public function __construct()
    {
        parent::__construct();
        $this->paymentMethodRepository = new PaymentMethodRepository();
    }

    public function authorize(): bool
    {
        //return $this->user() && $this->user()->can('create', 'PaymentMethod');
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50',
            'provider' => 'string|max:50',
            'is_active' => 'boolean',
            'requires_processing' => 'boolean',
            'instructions' => 'string',
            'sort_order' => 'integer',
            'configuration' => 'array'
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {
                // Check for unique code
                $existing = $this->paymentMethodRepository->findByCode($request->input('code'));
                $paymentMethodId = $request->route('id') ?? null;

                if ($existing && (!$paymentMethodId || $existing->id !== (int)$paymentMethodId)) {
                    throw new ValidationException('Payment method code already exists', ['code' => 'Code already exists']);
                }
            }
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Payment method name is required',
            'code.required' => 'Payment method code is required',
            'code.max' => 'Code must not exceed 50 characters',
            'name.max' => 'Name must not exceed 100 characters'
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!isset($this->data['is_active'])) {
            $this->data['is_active'] = true;
        }

        if (!isset($this->data['requires_processing'])) {
            $this->data['requires_processing'] = false;
        }

        if (!isset($this->data['sort_order'])) {
            $this->data['sort_order'] = 0;
        }
    }
}