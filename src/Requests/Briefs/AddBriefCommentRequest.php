<?php

namespace App\Requests\Briefs;

use App\Framework\Http\FormRequest;

class AddBriefCommentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
            'user_id' => ['required', 'integer'],
        ];
    }
}