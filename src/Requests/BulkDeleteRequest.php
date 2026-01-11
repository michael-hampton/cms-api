<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class BulkDeleteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'required|integer'
        ];
    }

    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', 'Order');
    }
}