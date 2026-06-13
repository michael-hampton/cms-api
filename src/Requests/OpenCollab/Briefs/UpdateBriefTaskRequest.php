<?php

namespace App\Requests\OpenCollab\Briefs;

use App\Framework\Http\FormRequest;

class UpdateBriefTaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:pending,in_progress,completed'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Task status is required.',
            'status.in' => 'Invalid task status.',
        ];
    }
}
