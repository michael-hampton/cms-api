<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class UpdateContactInfoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'contact_email' => ['email', 'max:255'],
            'contact_phone' => ['string', 'max:50'],
            'contact_address_line1' => ['string', 'max:255'],
            'contact_address_line2' => ['string', 'max:255'],
            'contact_city' => ['string', 'max:100'],
            'contact_postcode' => ['string', 'max:20'],
            'contact_country' => ['string', 'max:100']
        ];
    }

    public function messages(): array
    {
        return [
            'contact_email.email' => 'Please provide a valid email address',
            'contact_email.max' => 'Email must not exceed 255 characters',
            'contact_phone.max' => 'Phone must not exceed 50 characters',
            'contact_address_line1.max' => 'Address line 1 must not exceed 255 characters',
            'contact_address_line2.max' => 'Address line 2 must not exceed 255 characters',
            'contact_city.max' => 'City must not exceed 100 characters',
            'contact_postcode.max' => 'Postcode must not exceed 20 characters',
            'contact_country.max' => 'Country must not exceed 100 characters'
        ];
    }
}