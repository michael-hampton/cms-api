<?php

namespace App\Framework\ServiceProvider;

use App\Framework\Authorization\Gate;
use App\Models\Category;
use App\Models\Tag;
use App\Policies\CategoryPolicy;
use App\Policies\TagPolicy;

class AuthServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);
//        Gate::define('create-tags', function () {
//            die('mike');
//        });
    }
}