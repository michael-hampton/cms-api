<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class BulkToggleActiveRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'plan_ids' => ['array', 'required']
        ];
    }
}