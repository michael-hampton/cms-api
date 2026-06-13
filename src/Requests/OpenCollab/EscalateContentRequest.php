<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;
use App\Enums\OpenCollab\EscalationCategory;
use App\Enums\OpenCollab\RiskSeverity;

class EscalateContentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'in:' . implode(',', array_map(fn($c) => $c->value, EscalationCategory::cases()))],
            'severity' => ['required', 'string', 'in:' . implode(',', array_map(fn($c) => $c->value, RiskSeverity::cases()))],
            'risk_marker_id' => ['nullable', 'integer'],
            'cms_image_id' => ['nullable', 'integer'],
        ];
    }
}