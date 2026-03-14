<?php

namespace App\Requests\Briefs;

use App\Framework\Http\FormRequest;

class ConvertBriefToArticleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'images' => ['array'],
            'blockType' => ['nullable', 'in:product,deal'],
            'products' => ['array'],
        ];
    }
}