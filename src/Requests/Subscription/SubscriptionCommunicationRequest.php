<?php

namespace App\Requests\Subscription;

use App\Framework\Http\FormRequest;

class SubscriptionCommunicationRequest extends FormRequest
{

    public function rules(): array
    {
       return [
           'name'         => 'required|string|max:255',
           'trigger_type' => 'required|in:relative,fixed',
           'offset_days'  => 'nullable|integer',
           'fixed_date'   => 'nullable|date',
           'relative_to'  => 'nullable|string',
           'send_time'    => 'nullable|regex:/^\d{2}:\d{2}$/',
           'is_active'    => 'boolean',
           'sort_order'   => 'integer',
       ];
    }
}
