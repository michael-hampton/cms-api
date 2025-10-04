<?php

namespace App\Repositories;

use App\Framework\Database\Database;
use App\Framework\Database\QueryBuilder;
use App\Framework\Database\Relations\EagerLoader;
use App\Framework\Database\Relations\RelationHandlerFactory;
use App\Framework\Database\Relations\RelationshipAnalyzer;
use App\Framework\Support\Collection;
use App\Models\Model;

abstract class Repository
{
    protected Model $model;
    protected $database;

    public function __construct()
    {
        $modelClass = $this->getModelClass();  // call the method, don’t use $ in front
        $this->model = new $modelClass();
        $this->database = Database::getInstance();
    }

    abstract protected function getModelClass(): string;

    public function find(int $id): ?Model
    {
        return $this->model::find($id);
    }

    public function all(): Collection
    {
        return $this->model::all();
    }

    public function create(array $data): Model
    {
        return $this->model::create($data);
    }

    public function update(int $id, array $data): ?Model
    {
        $model = $this->find($id);

        if(empty($model)) {
            return null;
        }

        if ($model) {
            $model->fill($data);
            $model->save();
        }
        return $model;
    }

    public function delete(int $id): bool
    {
        $model = $this->find($id);

        if(empty($model)) {
            return false;
        }

        return $model->delete();
    }

    public function where(string $column, $operator, $value = null): QueryBuilder
    {
        return $this->model::where($column, $operator, $value);
    }

    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $query = new QueryBuilder(
            (new $this->model())->table,
            new EagerLoader(
                new RelationshipAnalyzer(),
                new RelationHandlerFactory($this->database),
                $this->database
            ),
            $this->database
        );
        return $query->paginate($perPage, $page);
    }

    protected function applyScopes(QueryBuilder $query, array $scopes): QueryBuilder
    {
        foreach ($scopes as $scope => $parameters) {
            if (method_exists($this->getModelClass(), 'scope' . ucfirst($scope))) {
                $query = $query->$scope(...(array)$parameters);
            }
        }
        return $query;
    }
}