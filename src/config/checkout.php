<?php

/*
|--------------------------------------------------------------------------
| Currency Configuration
|--------------------------------------------------------------------------
|
| default_currency — the ISO 4217 code used when a site has no explicit
| currency configured. Must be lowercase; CurrencyResolver normalises it.
|
*/

return [
    'default_currency' => env('DEFAULT_CURRENCY', 'usd'),
];