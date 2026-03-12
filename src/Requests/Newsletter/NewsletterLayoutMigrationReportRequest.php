<?php

namespace App\Requests\Newsletter;

use App\Framework\Http\FormRequest;

class NewsletterLayoutMigrationReportRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'old_version_id' => 'required',
            'new_version_id' => 'required'
        ];
    }
}