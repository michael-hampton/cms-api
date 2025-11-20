<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class CreateCommentRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'page_id' => 'required|integer',
            'content' => 'required|max:2000',
            'parent_id' => 'integer',
            'member_id' => 'integer'
        ];

        // If member_id is not present, name and email are required
        if (!$this->has('member_id') || empty($this->input('member_id'))) {
            $rules['name'] = 'required|max:100';
            $rules['email'] = 'required|email|max:255';
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['site_id'])) {
            $this->data['site_id'] = config('app.default_site_id');
        }
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your name.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'content.required' => 'Please enter your comment.',
            'content.max' => 'Your comment cannot exceed 2000 characters.'
        ];
    }
}