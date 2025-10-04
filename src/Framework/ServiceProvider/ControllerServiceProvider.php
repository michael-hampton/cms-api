<?php

namespace App\Framework\ServiceProvider;

/**
 * Controller Service Provider - Web controllers
 */
class ControllerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->autoRegister('controllers', null, true);
    }
}