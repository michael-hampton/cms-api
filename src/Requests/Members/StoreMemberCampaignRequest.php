<?php

namespace App\Requests\Members;

use App\Framework\Http\FormRequest;

class StoreMemberCampaignRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'description' => ['string'],
            'is_active' => ['boolean'],
            'start_date' => ['date'],
            'end_date' => ['date'],
            'segment_id' => ['integer'],
            'channel' => ['required', 'string', 'in:email,notification,push'],
            'purpose' => ['string', 'in:marketing,product_updates,transactional'],
            'fallback_channels' => ['array'],
            'fallback_channels.*' => ['string', 'in:email,notification,push'],
            'template' => ['required', 'string', 'max:255'],
            'cooldown_hours' => ['integer', 'min:0'],
            'priority' => ['integer', 'min:0'],
        ];
    }
}
