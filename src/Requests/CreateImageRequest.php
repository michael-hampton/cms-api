<?php

namespace App\Requests;

use App\Enums\ImageRights;
use App\Framework\Http\FormRequest;
use App\Models\Image;
use App\Policies\ImagePolicy;

class CreateImageRequest extends FormRequest
{
    protected static function model(): string
    {
        return Image::class;
    }

    public function rules(): array
    {
        return [
            'name' => 'string|max:255',
            'credit' => 'string|max:255',
            'alt_text' => 'string',
            'caption' => 'string',
            'description' => 'string',
            'categories' => 'array',
            'tags' => 'array',
            'image_rights' => 'string|in:' . implode(',', ImageRights::values()),
        ];
    }

    protected function getPolicyClass(): ?string
    {
        return ImagePolicy::class;
    }
}