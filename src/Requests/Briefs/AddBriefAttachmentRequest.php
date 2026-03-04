<?php

namespace App\Requests\Briefs;

use App\Framework\Http\FormRequest;

class AddBriefAttachmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'string'],  // TODO: confirm allowed types e.g. document|image|link
            'file_url' => ['required_without:file_name', 'url'],
            'file_name' => ['string', 'max:255'],
            'filesize' => ['integer'],
            'sort_order' => ['integer'],
            'metadata' => ['array'],
            // TODO: confirm full attachment shape from BriefService::addAttachment
        ];
    }
}