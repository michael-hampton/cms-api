<?php

namespace App\Requests\OpenCollab\Admin;

use App\Framework\Http\FormRequest;

/**
 * Validates the user search query for site assignment.
 *
 * Rules:
 *   q  — required, string, 2–100 characters.
 *       Minimum of 2 prevents excessively broad queries.
 */
class SearchSiteUsersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }
}