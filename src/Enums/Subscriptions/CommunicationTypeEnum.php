<?php

namespace App\Enums\Subscriptions;

enum CommunicationTypeEnum: string
{
    case RENEWAL_REMINDER  = 'renewal_reminder';
    case ACKNOWLEDGEMENT   = 'acknowledgement';
    case ITD               = 'itd';
    case CUSTOMER_CARE     = 'customer_care';
    case CCC_EXPIRY_REMINDER = 'ccc_expiry_reminder';
}