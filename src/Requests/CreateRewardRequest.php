<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class CreateRewardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'string',
            'reward_type' => 'required|in:voucher,discount,points',
            'criteria' => 'required|array',
            'reward_config' => 'required|array',
            'max_claims_per_member' => 'integer|min_number:1',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'terms_and_conditions' => 'nullable|string',
        ];
    }
}