<?php

namespace App\Requests\Briefs;

use App\Framework\Http\FormRequest;

class AddBriefRelationshipRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'relationship_type' => ['required', 'string'],  // e.g. related, blocks, depends_on
            'related_brief_id' => ['required', 'integer'],
        ];
    }
}