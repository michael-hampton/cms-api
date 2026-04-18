<?php

namespace App\Requests\Badges;

use App\Framework\Http\FormRequest;

class StoreBadgeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['string', 'max:1000'],
            'icon' => ['string', 'max:255'],
            'criteria' => ['required', 'array', 'min:1'],
            'criteria.*.type' => ['required', 'string'],
            'criteria.*.operator' => ['required', 'string'],
            'criteria.*.value' => ['required', 'numeric'],
            'points' => ['numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'A badge name is required.',
            'criteria.required' => 'At least one criterion is required.',
            'criteria.min' => 'At least one criterion is required.',
            'criteria.*.type.required' => 'Each criterion must have a type.',
            'criteria.*.operator.required' => 'Each criterion must have an operator.',
            'criteria.*.value.required' => 'Each criterion must have a value.',
            'criteria.*.value.numeric' => 'Criterion values must be numeric.',
        ];
    }
}