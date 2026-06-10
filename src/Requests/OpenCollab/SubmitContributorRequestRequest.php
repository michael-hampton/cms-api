<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

/**
 * Validates the public contributor-request submission payload.
 *
 * Validation lives here rather than in the controller so it can be
 * unit-tested independently and reused if the endpoint gains additional
 * transports (e.g. a multipart form with dynamic fields).
 */
class SubmitContributorRequestRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'name'  => ['required', 'string', 'min:2'],
            'bio'   => ['required', 'string', 'min:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'A valid email address is required.',
            'email.email'    => 'A valid email address is required.',
            'name.min'       => 'Name must be at least 2 characters.',
            'bio.min'        => 'Bio must be at least 20 characters.',
        ];
    }
}