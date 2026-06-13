<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;
use App\Framework\Http\Request;

class SearchImagesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page'          => ['integer', 'min:1'],
            'per_page'      => ['integer', 'min:1', 'max:100'],
            'search'        => ['string', 'max:255'],
            'image_rights'  => ['string'],
            'uploaded_from' => ['date'],
            'uploaded_to'   => ['date'],
            'sort'          => ['string', 'in:created_at,name'],
            'direction'     => ['string', 'in:asc,desc'],
        ];
    }
}