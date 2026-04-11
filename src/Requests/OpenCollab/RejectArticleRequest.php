<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class RejectArticleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'in:policy_violation,quality,off_topic,plagiarism,misinformation,other'],
            'notes' => ['string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A rejection reason is required.',
            'reason.in' => 'Invalid rejection reason.',
        ];
    }
}