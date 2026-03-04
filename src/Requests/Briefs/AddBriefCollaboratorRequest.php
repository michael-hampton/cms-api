<?php

namespace App\Requests\Briefs;

use App\Framework\Http\FormRequest;

class AddBriefCollaboratorRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'role' => ['required', 'in:writer,editor,reviewer,fact-checker'],
        ];
    }
}