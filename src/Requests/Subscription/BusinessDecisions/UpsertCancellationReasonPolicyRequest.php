<?php

namespace App\Requests\Subscription\BusinessDecisions;

use App\Framework\Http\FormRequest;

/**
 * All fields nullable/optional — see the "nullable inheritance" note on
 * the cancellation_reason_policies migration. An admin may set only the
 * fields they want to override at this decision level and leave the
 * rest to inherit.
 */
class UpsertCancellationReasonPolicyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'cancellation_reason_id' => ['required', 'integer', 'min:1'],
            'show_save_actions' => ['nullable', 'boolean'],
            'allow_discount' => ['nullable', 'boolean'],
            'allow_offer_switch' => ['nullable', 'boolean'],
            'allow_cancel' => ['nullable', 'boolean'],
            'refund_max_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'marketing_consent' => ['nullable', 'boolean'],
        ];
    }
}
