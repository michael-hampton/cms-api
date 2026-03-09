<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class WorkflowRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'job' => 'string|required',
            'mode' => 'string'
        ];
    }
}