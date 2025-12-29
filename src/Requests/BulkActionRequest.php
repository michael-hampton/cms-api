<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class BulkActionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'integer'
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'No items selected',
            'ids.array' => 'Invalid data format',
            'ids.*.integer' => 'Invalid item ID'
        ];
    }
}