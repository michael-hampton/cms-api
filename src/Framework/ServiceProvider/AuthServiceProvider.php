<?php

namespace App\Framework\ServiceProvider;

use App\Framework\Authorization\EloquentTokenRepository;
use App\Framework\Authorization\Gate;
use App\Framework\Authorization\SecureTokenGenerator;
use App\Framework\Authorization\TokenGeneratorInterface;
use App\Framework\Authorization\TokenRepositoryInterface;
use App\Models\Category;
use App\Models\Tag;
use App\Policies\CategoryPolicy;
use App\Policies\TagPolicy;
use App\Repositories\UserRepository;
use App\Repositories\UserRepositoryInterface;

class AuthServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);
//        Gate::define('create-tags', function () {
//            die('mike');
//        });

        $this->container->bind(
            TokenRepositoryInterface::class,
            EloquentTokenRepository::class
        );

        $this->container->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );

        $this->container->bind(
            TokenGeneratorInterface::class,
            SecureTokenGenerator::class
        );
    }
}