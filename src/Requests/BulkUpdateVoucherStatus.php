<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class BulkUpdateVoucherStatus extends FormRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
            'status' => 'required|string|in:active,inactive,expired'
        ];
    }

    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', 'Order');
    }
}