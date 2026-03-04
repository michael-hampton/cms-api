<?php

namespace App\Requests\Briefs;

use App\Framework\Http\FormRequest;

class UpdateBriefTaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['string', 'max:255'],
            'description' => ['string'],
            'assigned_to' => ['integer'],
            'due_date' => ['date'],
            'status' => ['string'],
        ];
    }
}