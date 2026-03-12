<?php

namespace App\Requests\Subscription;

use App\Framework\Http\FormRequest;

class UpdateIssueDeliveryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'issue_title' => ['required', 'string', 'max:255'],
            'issue_number' => ['required', 'nullable', 'string', 'max:100'],
            'issue_code' => ['nullable', 'string', 'max:100'],
            'subscription_plan_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'promotion_id' => ['nullable', 'integer'],
            'on_sale_date' => ['required', 'date'],
            'cut_off_date' => ['nullable', 'date'],
            'fulfilment_date' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,active,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
    }
}