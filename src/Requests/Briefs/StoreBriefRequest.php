<?php

namespace App\Requests\Briefs;

use App\Framework\Http\FormRequest;

class StoreBriefRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['string'],
            'owner_id' => ['integer'],
            'category_id' => ['integer'],
            'target_word_count' => ['integer', 'min:1'],
            'target_publish_date' => ['date'],
            'seo_keywords' => ['string'],
            'target_audience' => ['string'],
            'site_id' => ['required', 'integer'],
        ];
    }
}