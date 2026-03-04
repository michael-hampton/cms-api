<?php

namespace App\Requests\Briefs;

use App\Framework\Http\FormRequest;

class AddBriefWorkflowChangeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:draft,in_review,ready,converted,archived'],
            'changed_by' => ['integer'],
            'notes' => ['nullable', 'string'],
        ];
    }
}