<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;
use App\Models\Video;
use App\Policies\VideoPolicy;

class CreateVideoRequest extends FormRequest
{
    protected static function model(): string
    {
        return Video::class;
    }

    public function rules(): array
    {
        return [
            'title' => 'string|max:255',
            'description' => 'string'
        ];
    }

    public function messages(): array
    {
        return [
            'title.max' => 'Video title cannot exceed 255 characters',
        ];
    }

    protected function getPolicyClass(): ?string
    {
        return VideoPolicy::class;
    }

    protected function prepareForValidation(): void
    {
        // Validation for the file itself is handled in the controller/service
        // because FormRequest doesn't handle file validation well in your framework
    }
}