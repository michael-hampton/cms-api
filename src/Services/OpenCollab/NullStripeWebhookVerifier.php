<?php

namespace App\Services\OpenCollab;

use App\Framework\Http\Request;

class NullStripeWebhookVerifier extends StripeWebhookVerifier
{
    public function verify(Request $request): object
    {
        return (object)['id' => uniqid()];
    }
}