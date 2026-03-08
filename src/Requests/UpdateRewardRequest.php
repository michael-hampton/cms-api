<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class UpdateRewardRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'name' => 'string|max:255',
            'slug' => 'string|max:255',
            'description' => 'string',
            'reward_type' => 'in:voucher,discount,points',
            'criteria' => 'array',
            'reward_config' => 'array',
            'max_claims_per_member' => 'integer|min_number:1',
            'is_active' => 'boolean',
            'sort_order' => 'integer'
        ];
    }
}