<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class CreateNewsletterIssueRequest extends FormRequest
{
    public function rules(): array
    {
        // Mirrors NewsletterIssueDTO::fromArray
        return [
            'subject' => ['string', 'max:255'],
            'content_blocks' => ['array'],
            'snapshot_json' => ['array'],
            'scheduled_at' => ['date'],
        ];
    }
}