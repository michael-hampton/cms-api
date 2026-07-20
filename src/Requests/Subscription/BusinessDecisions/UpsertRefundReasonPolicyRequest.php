<?php

namespace App\Requests\Subscription\BusinessDecisions;

use App\Framework\Http\FormRequest;

class UpsertRefundReasonPolicyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'refund_reason_id' => ['required', 'integer', 'min:1'],
            'allow_full' => ['nullable', 'boolean'],
            'allow_pro_rated' => ['nullable', 'boolean'],
            'allow_manual' => ['nullable', 'boolean'],
            'allow_cancel_at_period_end' => ['nullable', 'boolean'],
            'allow_cancel_immediately_no_refund' => ['nullable', 'boolean'],
            'refund_max_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'manager_approval_threshold_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'default_notify_customer' => ['nullable', 'boolean'],
            'requires_internal_notes' => ['nullable', 'boolean'],
        ];
    }
}
