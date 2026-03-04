<?php

namespace App\Requests\Briefs;

use App\Framework\Http\FormRequest;

class UpdateBriefCommentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
        ];
    }
}