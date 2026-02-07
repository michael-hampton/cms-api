<?php

namespace App\Enums\Newsletters;

enum CommunicationChannel: string
{
    case Newsletter = 'newsletter';
    case Marketing = 'marketing_emails';
    case SpecialOffers = 'special_offers';
    case ThirdParty = 'third_party_communications';
    case ProductUpdates = 'product_updates';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::Newsletter => 'Newsletters',
            self::Marketing => 'Marketing Emails',
            self::SpecialOffers => 'Special Offers',
            self::ThirdParty => 'Third-Party Communications',
            self::ProductUpdates => 'Product Updates',
        };
    }
}