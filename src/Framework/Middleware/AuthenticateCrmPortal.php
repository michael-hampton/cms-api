<?php

namespace App\Framework\Middleware;

use App\Framework\Http\MiddlewareInterface;

class AuthenticateCrmPortal extends AuthenticateWithSession implements MiddlewareInterface
{
    public function __construct()
    {
        parent::__construct('/crm/login/crm', 'crm');
    }
}