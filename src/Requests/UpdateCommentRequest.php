<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string', 'min:10'],
            'category' => ['in:news,blog,review'],
            'published' => ['required']
        ];
    }

    public function authorize(): bool
    {
        $comment = $this->getComment(); // Would get from route parameter
        return $comment && $this->user()->can('update', $comment);
    }

    public function messages(): array
    {
        return [
            'content.min' => 'Comment must be at least 10 characters long.',
            'category.in' => 'Please select a valid category.',
        ];
    }

    private function getComment()
    {
        // In real implementation, would get from route parameter
        return (object)['id' => 1, 'user_id' => $this->user()?->id];
    }
}