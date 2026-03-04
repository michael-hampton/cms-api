<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class StoreIssueDeliveryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'issue_title' => ['required', 'string', 'max:255'],
            'issue_number' => ['required', 'string', 'max:100'],
            'issue_code' => ['string', 'max:100'],
            'subscription_plan_id' => ['integer'],
            'product_id' => ['integer'],
            'promotion_id' => ['integer'],
            'on_sale_date' => ['required', 'date'],
            'cut_off_date' => ['date'],
            'fulfilment_date' => ['date'],
            'status' => ['in:draft,active,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
    }
}