<?php

namespace App\Enums\Gifts;

enum GiftQuantityRule: string
{
    /**
     * Default. Customer receives one gift per qualifying trigger.
     * If two cart items both trigger the same gift, quantity = 2.
     * Technically stored as one GiftLine with quantity merged.
     */
    case ONE_PER_QUALIFYING = 'one_per_qualifying';

    /**
     * Customer receives at most max_per_order of this gift regardless
     * of how many items trigger it. Opt-in stinginess.
     */
    case CAP = 'cap';

    /**
     * Quantities from all qualifying triggers are summed.
     * Functionally identical to one_per_qualifying for most cases,
     * but the semantic intent is different: useful when trigger
     * quantity itself varies (e.g. buy 3 items → 3 gifts).
     */
    case MERGE = 'merge';
}