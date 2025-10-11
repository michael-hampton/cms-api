<?php

namespace App\Repositories;

use App\Models\User;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class UserRepository extends Repository implements UserRepositoryInterface
{

    public function findByEmail(string $email, int $siteId): ?User
    {
        $user = User::where('email', $email)
            //->where('site_id', $siteId)
            ->first();

        if (empty($user)) return null;

        return new User($user);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $configuration = SearchConfigurationFactory::createUserConfiguration();
        $engine = new SearchEngine($configuration);

        $query = User::query();

        return $engine->search($query, $criteria);
    }

    public function findById(int $id, int $siteId): ?User
    {
        $user = User::where('id', $id)
            // ->where('site_id', $siteId)
            ->first();

        return !empty($user) ? new User($user) : null;
    }

    protected function getModelClass(): string
    {
        return User::class;
    }
}