<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;
use App\Enums\OpenCollab\RiskSeverity;

class ResolveRiskMarkerRequest extends FormRequest
{
    public function rules(): array
    {
        // "Notes are required for resolving high/critical markers."
        // Marker severity isn't known at validation time without a DB hit,
        // so the service enforces this; here notes are simply optional-but-sanitised.
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}