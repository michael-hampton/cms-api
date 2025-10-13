<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class UpdateSocialMediaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'facebook_url' => ['url', 'max:500'],
            'instagram_url' => ['url', 'max:500'],
            'twitter_url' => ['url', 'max:500'],
            'linkedin_url' => ['url', 'max:500']
        ];
    }

    public function messages(): array
    {
        return [
            'facebook_url.url' => 'Facebook URL must be a valid URL',
            'facebook_url.max' => 'Facebook URL must not exceed 500 characters',
            'instagram_url.url' => 'Instagram URL must be a valid URL',
            'instagram_url.max' => 'Instagram URL must not exceed 500 characters',
            'twitter_url.url' => 'Twitter URL must be a valid URL',
            'twitter_url.max' => 'Twitter URL must not exceed 500 characters',
            'linkedin_url.url' => 'LinkedIn URL must be a valid URL',
            'linkedin_url.max' => 'LinkedIn URL must not exceed 500 characters'
        ];
    }
}