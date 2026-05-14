<?php

namespace App\Requests\OpenCollab\Admin;

use App\Framework\Http\FormRequest;

/**
 * Validates the payload for assigning a user to a site.
 */
class AssignSiteUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
        ];
    }
}