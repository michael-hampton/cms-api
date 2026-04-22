<?php

namespace App\Repositories\Cms;

use App\Models\Model;
use App\Models\User;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class UserRepository extends Repository implements UserRepositoryInterface
{

    public function findByEmail(string $email, ?int $siteId = null): ?User
    {
        $user = User::where('email', $email)
            //->where('site_id', $siteId)
            ->first();

        if (empty($user)) return null;

        return new User($user);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $configuration = SearchConfigurationFactory::create('user');
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

    public function create(array $data): Model
    {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        return parent::create($data);
    }

    protected function getModelClass(): string
    {
        return User::class;
    }
}