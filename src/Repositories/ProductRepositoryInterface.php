<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\Product;
use PhpParser\Node\Expr\AssignOp\Mod;

interface ProductRepositoryInterface
{
    public function all(): Collection;

    public function paginate(int $perPage = 15): array;

    public function find(int $id): ?Model;

    public function create(array $data): Model;

    public function delete(int $id): bool;

    public function findByCategory(string $category): Collection;

    public function findByBrand(string $brand): Collection;

    public function getOnSale(): Collection;
}