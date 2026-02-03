<?php

namespace App\Exceptions;

use Exception;

class BundleValidationException extends Exception
{
    public static function invalidDateFormat(): self
    {
        return new self('Invalid date format');
    }

    public static function endDateBeforeStart(): self
    {
        return new self('End date must be after start date');
    }

    public static function emptyItems(): self
    {
        return new self('Bundle must contain at least one item');
    }

    public static function insufficientItems(): self
    {
        return new self('Bundle must contain at least two items');
    }

    public static function duplicateItemTypes(): self
    {
        return new self('Bundle item cannot have both product and product offer');
    }

    public static function missingItemType(): self
    {
        return new self('Bundle item must have either product or product offer');
    }

    public static function multiMerchantNotAllowed(): self
    {
        return new self('Multi-merchant bundles are not allowed. Please enable in configuration or select items from the same merchant.');
    }

    public static function rejectionReasonRequired(): self
    {
        return new self('Rejection reason is required');
    }
}