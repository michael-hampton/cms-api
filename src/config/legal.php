<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contact & Company
    |--------------------------------------------------------------------------
    */
    'contact_email' => env('LEGAL_CONTACT_EMAIL', 'support@' . parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
    'registered_address' => env('LEGAL_REGISTERED_ADDRESS', ''), // e.g. "123 Example Street, London, EC1A 1BB"

    /*
    |--------------------------------------------------------------------------
    | Policy last-updated dates
    |--------------------------------------------------------------------------
    */
    'updated_default' => 'January 2025',
    'privacy_policy_updated' => 'January 2025',
    'cookie_policy_updated' => 'January 2025',
    'cancellation_rights_updated' => 'January 2025',
    'returns_policy_updated' => 'January 2025',
    'data_subject_rights_updated' => 'January 2025',
    'reviews_policy_updated' => 'January 2025',

];