<?php

namespace App\Requests\Members;

use App\Framework\Http\FormRequest;

class UpdateSegmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'key' => ['string', 'max:255'],
            'name' => ['string', 'max:255'],
            'description' => ['string', 'max:1000'],
            'category' => ['string', 'max:255'],
            'is_active' => ['boolean'],
            'rules' => ['array'],
            'rules.*.field' => ['required_with:rules', 'string', 'max:255'],
            'rules.*.operator' => ['required_with:rules', 'string', 'in:>,<,=,!=,>=,<=,IN,CONTAINS'],
            'rules.*.value' => ['required_with:rules'],
            'rules.*.boolean' => ['string', 'in:AND,OR'],
            'rules.*.sort_order' => ['integer', 'min:0'],
        ];
    }
}
