<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class StoreOnboardingProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'bio' => ['required', 'string', 'min:20', 'max:1000'],
            'avatar' => ['string', 'max:500'],  // URL or upload path
        ];
    }

    public function messages(): array
    {
        return [
            'bio.required' => 'A short bio is required before you can start contributing.',
            'bio.min' => 'Your bio must be at least 20 characters.',
        ];
    }
}