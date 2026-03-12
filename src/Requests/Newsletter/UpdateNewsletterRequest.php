<?php

namespace App\Requests\Newsletter;

use App\Framework\Http\FormRequest;

class UpdateNewsletterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['string'],
            'interval' => ['required', 'in:daily,weekly,biweekly,monthly'],
            'active' => ['boolean'],
            'is_default' => ['boolean'],
            'content_type' => ['required', 'in:manual,auto_pages'],
            'max_pages' => ['integer', 'min:1'],
            'sort_by' => ['string'],
            'sort_order' => ['in:asc,desc'],
            'template' => ['string'],
            'layout_id' => ['integer'],
            'subject' => ['string', 'max:255'],
            'preview_text' => ['string', 'max:255'],
            'header_content' => ['string'],
            'footer_content' => ['string'],
            'custom_css' => ['string'],
        ];
    }
}