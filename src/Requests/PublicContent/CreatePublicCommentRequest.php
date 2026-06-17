<?php

namespace App\Requests\PublicContent;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\FormRequest;

final class CreatePublicCommentRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'content' => 'required|max:2000',
            'parent_id' => 'integer',
        ];

        if (!MemberAuth::check()) {
            $rules['name'] = 'required|max:100';
            $rules['email'] = 'required|email|max:255';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your name.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'content.required' => 'Please enter your comment.',
            'content.max' => 'Your comment cannot exceed 2000 characters.',
        ];
    }
}
