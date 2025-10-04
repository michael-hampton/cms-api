<?php

namespace App\Framework\ServiceProvider;

/**
 * Repository Service Provider - Auto-discovers all repositories
 */
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->autoRegister('repositories', null, true);
    }
}