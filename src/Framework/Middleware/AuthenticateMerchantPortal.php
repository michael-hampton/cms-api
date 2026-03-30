<?php

namespace App\Framework\Middleware;

use App\Framework\Http\MiddlewareInterface;

class AuthenticateMerchantPortal extends AuthenticateWithSession implements MiddlewareInterface
{
    public function __construct()
    {
        parent::__construct('/merchant/login/merchant', 'merchant');
    }
}