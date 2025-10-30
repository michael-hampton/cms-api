<?php

namespace App\Framework\ServiceProvider;

use App\Framework\Authorization\EloquentTokenRepository;
use App\Framework\Authorization\Gate;
use App\Framework\Authorization\SecureTokenGenerator;
use App\Framework\Authorization\TokenGeneratorInterface;
use App\Framework\Authorization\TokenRepositoryInterface;
use App\Models\Author;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use App\Models\Voucher;
use App\Policies\AuthorPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\MenuPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PagePolicy;
use App\Policies\ProductPolicy;
use App\Policies\TagPolicy;
use App\Policies\UserPolicy;
use App\Policies\VoucherPolicy;
use App\Repositories\UserRepository;
use App\Repositories\UserRepositoryInterface;

class AuthServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);
        Gate::policy(Author::class, AuthorPolicy::class);;
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(Voucher::class, VoucherPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Menu::class, MenuPolicy::class);
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