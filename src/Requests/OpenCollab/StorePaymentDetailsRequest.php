<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

/**
 * Validates the payment details step of contributor onboarding.
 *
 * We receive either a Stripe token (tokenised card/bank details)
 * or a payment method type with a reference token.
 * Raw card numbers must NEVER be sent here — Stripe.js tokenises on the client.
 */
class StorePaymentDetailsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'payment_method_type' => ['required', 'string', 'in:stripe,bank_transfer'],
            'stripe_token' => ['required_if:payment_method_type,stripe', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method_type.required' => 'A payment method type is required.',
            'payment_method_type.in' => 'Supported payment methods are: stripe, bank_transfer.',
            'stripe_token.required_if' => 'A Stripe token is required when using card payments.',
        ];
    }
}