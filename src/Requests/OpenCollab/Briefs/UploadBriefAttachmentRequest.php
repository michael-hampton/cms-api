<?php

namespace App\Requests\OpenCollab\Briefs;

use App\Framework\Http\FormRequest;

class UploadBriefAttachmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'An attachment file is required.',
            'file.file' => 'The attachment must be a valid uploaded file.',
        ];
    }
}
