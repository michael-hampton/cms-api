<?php

namespace App\Validation\Custom;

use App\Framework\Validation\Rules\BaseValidationRule;

class SalePriceValidatorRule extends BaseValidationRule
{
    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '' || $value == 0) {
            return true; // Sale price is optional
        }

        $price = $data['price'] ?? null;
        if (!$price) {
            return true; // Can't validate without price
        }

        return is_numeric($value) && is_numeric($price) && $value < $price;
    }

    protected function getDefaultMessage(): string
    {
        return 'Sale price must be less than the regular price';
    }
}