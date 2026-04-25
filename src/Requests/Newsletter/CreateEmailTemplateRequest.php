<?php

namespace App\Requests\Newsletter;

use App\Framework\Http\FormRequest;

class CreateEmailTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', 'in:transactional,marketing,system'],
            'theme_id' => ['nullable', 'integer', 'exists:email_themes,id'],
            'blocks' => ['nullable', 'array'],
            'blocks.*.type' => ['required_with:blocks', 'string', 'in:text,image,button,divider,spacer,single_column,two_column,product_card,order_summary,ad_slot'],
            'blocks.*.data' => ['required_with:blocks', 'array'],
            'blocks.*.visible' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}