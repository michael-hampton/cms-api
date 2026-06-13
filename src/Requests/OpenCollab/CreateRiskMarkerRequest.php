<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;
use App\Enums\OpenCollab\RiskType;
use App\Enums\OpenCollab\RiskSeverity;

class CreateRiskMarkerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'risk_type' => ['required', 'string', $this->enumRule(RiskType::class)],
            'severity' => ['required', 'string', $this->enumRule(RiskSeverity::class)],
            'details' => ['nullable', 'array'],
            'cms_image_id' => ['nullable', 'integer'],
        ];
    }

    // ASSUMED: a helper exists elsewhere for "Validation must use Enum::from()
    // or equivalent" — if not, this is the minimal version:
    private function enumRule(string $enumClass): string
    {
        return 'in:' . implode(',', array_map(fn($c) => $c->value, $enumClass::cases()));
    }
}