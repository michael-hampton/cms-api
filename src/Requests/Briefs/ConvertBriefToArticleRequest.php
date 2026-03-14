<?php

namespace App\Requests\Briefs;

use App\Framework\Http\Request;

class ConvertBriefToArticleRequest extends Request
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