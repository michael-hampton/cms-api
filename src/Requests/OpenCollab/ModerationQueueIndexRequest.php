<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class ModerationQueueIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string'],
            'risk_type' => ['nullable', 'string'],
            'severity' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer'],
            'unassigned' => ['nullable', 'boolean'],
            'submitted_before' => ['nullable', 'date'],
            'scheduled_before' => ['nullable', 'date'],
            'content_type' => ['nullable', 'string'],
        ];
    }
}