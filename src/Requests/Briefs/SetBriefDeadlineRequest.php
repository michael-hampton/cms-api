<?php

namespace App\Requests\Briefs;

use App\Framework\Http\FormRequest;

class SetBriefDeadlineRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'due_date' => ['required', 'date'],
            'reminder_days' => ['array'],
            'reminder_days.*' => ['integer', 'min:1'],
            'notify_collaborators' => ['boolean'],
            'user_id' => ['integer'],
        ];
    }
}