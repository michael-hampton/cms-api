<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;
use App\Repositories\CategoryRepository;

class CreateCommentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page_id' => 'required|integer',
            'name' => 'required|max:100',
            'email' => 'required|email|max:255',
            'content' => 'required|max:2000',
            'parent_id' => 'integer'
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['site_id'])) {
            $this->data['site_id'] = config('app.default_site_id');
        }
    }
}