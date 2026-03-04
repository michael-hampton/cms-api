<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class SaveNewsletterBrandingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'logo_url' => ['url'],
            'header_text' => ['string'],
            'footer_text' => ['string'],
            'custom_css' => ['string'],
            'theme_json' => ['array'],
            'theme_json.primary_color' => ['string', 'max:7'],
            'theme_json.secondary_color' => ['string', 'max:7'],
            'theme_json.text_color' => ['string', 'max:7'],
        ];
    }
}