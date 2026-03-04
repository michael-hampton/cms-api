<?php

namespace App\Requests\Briefs;

use App\Framework\Http\FormRequest;

class CreateBriefTaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['string'],
            'assigned_to' => ['integer'],
            'due_date' => ['date'],
            'created_by' => ['integer'],
        ];
    }
}