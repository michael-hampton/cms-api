<?php

namespace App\Requests\Members;

use App\Framework\Http\FormRequest;

class UpdateMemberCampaignRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['string', 'max:255'],
            'slug' => ['string', 'max:255'],
            'description' => ['string'],
            'is_active' => ['boolean'],
            'start_date' => ['date'],
            'end_date' => ['date'],
            'segment_id' => ['integer'],
            'channel' => ['string', 'in:email,notification,push'],
            'purpose' => ['string', 'in:marketing,product_updates,transactional'],
            'fallback_channels' => ['array'],
            'fallback_channels.*' => ['string', 'in:email,notification,push'],
            'template' => ['string', 'max:255'],
            'cooldown_hours' => ['integer', 'min:0'],
            'priority' => ['integer', 'min:0'],
        ];
    }
}
