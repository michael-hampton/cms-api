<?php

namespace App\Framework\ServiceProvider;

/**
 * Service Service Provider - Business logic services
 */
class ServiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->autoRegister('services', null, false);
    }
}