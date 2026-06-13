<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;
use App\Framework\Http\Request;

class UploadImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file'               => ['required', 'file', 'image'],
            'name'               => ['required', 'string', 'max:255'],
            'image_rights'       => ['required', 'string'],
            'alt_text'           => ['required', 'string', 'max:500'],
            'credit'             => ['required_if:image_rights,contributor_owned,third_party_licensed,agency,editorial_use_only', 'string', 'max:255'],
            'rights_confirmation' => ['required', 'accepted'],
            'ai_generated'       => ['boolean'],
            'sponsored_content'  => ['boolean'],
            'affiliate_content'  => ['boolean'],
            'contains_music' => ['sometimes', 'boolean'],
            'unclear_rights' => ['sometimes', 'boolean'],
        ];
    }
}