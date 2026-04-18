<?php

namespace App\Requests\Badges;

use App\Framework\Http\FormRequest;

class UpdateBadgeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['string', 'max:255'],
            'description' => ['string', 'max:1000'],
            'icon' => ['string', 'max:255'],
            'criteria' => ['array', 'min:1'],
            'criteria.*.type' => ['required_with:criteria', 'string'],
            'criteria.*.operator' => ['required_with:criteria', 'string'],
            'criteria.*.value' => ['required_with:criteria', 'numeric'],
            'points' => ['numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'criteria.min' => 'At least one criterion is required.',
            'criteria.*.type.required_with' => 'Each criterion must have a type.',
            'criteria.*.operator.required_with' => 'Each criterion must have an operator.',
            'criteria.*.value.required_with' => 'Each criterion must have a value.',
            'criteria.*.value.numeric' => 'Criterion values must be numeric.',
        ];
    }
}