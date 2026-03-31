<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing\Label;

/**
 * Carries the contextual data needed to render a label that cannot be
 * derived from PrintFulfillment alone.
 *
 * Immutable: constructed once per GenerateLabelJob execution and passed
 * into the format strategy. Never mutated after construction.
 */
final class LabelContext
{
    public function __construct(
        public readonly int     $issueDeliveryId,
        public readonly ?int $issueNumber,
        public readonly ?string $issueTitle,
        public readonly string  $returnAddressLine1,
        public readonly ?string $returnAddressLine2,
        public readonly string  $returnCity,
        public readonly string  $returnPostcode,
        public readonly string  $returnCountry,
        public readonly string  $returnName,
    )
    {
    }

    public static function fromConfig(int $issueDeliveryId, ?int $issueNumber, ?string $issueTitle): self
    {
        return new self(
            issueDeliveryId: $issueDeliveryId,
            issueNumber: $issueNumber,
            issueTitle: $issueTitle,
            returnAddressLine1: (string)config('print.return_address.line_1'),
            returnAddressLine2: config('print.return_address.line_2'),
            returnCity: (string)config('print.return_address.city'),
            returnPostcode: (string)config('print.return_address.postcode'),
            returnCountry: (string)config('print.return_address.country'),
            returnName: (string)config('print.return_address.name'),
        );
    }

    public function returnAddressLines(): array
    {
        return array_filter([
            $this->returnName,
            $this->returnAddressLine1,
            $this->returnAddressLine2,
            $this->returnCity,
            $this->returnPostcode,
            $this->returnCountry,
        ]);
    }
}