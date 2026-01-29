<?php

namespace App\Repositories;

use App\Framework\Database\Database;
use App\Framework\Database\QueryBuilder;
use App\Framework\Database\Relations\EagerLoader;
use App\Framework\Database\Relations\RelationHandlerFactory;
use App\Framework\Database\Relations\RelationshipAnalyzer;
use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Models\Model;

abstract class Repository
{
    protected Model $model;
    protected $database;
    protected ?int $siteId = null;
    protected bool $filterBySite = true;

    public function __construct()
    {
        $modelClass = $this->getModelClass();  // call the method, don’t use $ in front
        $this->model = new $modelClass();
        $this->database = Database::getInstance();
        $this->siteId = SiteContext::getId();
    }

    abstract protected function getModelClass(): string;

    public function find(int $id, array $relations = []): ?Model
    {
        if (!empty($relations) && is_array($relations) && count($relations) > 0) {
            return $this->model::with($relations)->find($id);
        }

        return $this->model::find($id);
    }

    public function findWithTrashed(int $id): ?Model
    {
        return $this->model::withTrashed()->find($id);
    }

    /**
     * Apply site filter to query if site_id is set
     */
    protected function applySiteFilter($query)
    {
        if ($this->filterBySite && $this->siteId !== null) {
            $query->where('site_id', $this->siteId);
        }
        return $query;
    }

    /**
     * Disable site filtering (for cross-site queries)
     */
    public function withoutSiteFilter(): self
    {
        $this->filterBySite = false;
        return $this;
    }

    /**
     * Enable site filtering
     */
    public function withSiteFilter(): self
    {
        $this->filterBySite = true;
        return $this;
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

        if (empty($model)) {
            return null;
        }

        $model->fill($data);
        $model->save();

        return $model;
    }

    public function delete(int $id): bool
    {
        $model = $this->find($id);

        if (empty($model)) {
            return false;
        }

        return $model->delete();
    }

    public function where(string $column, $operator, $value = null): QueryBuilder
    {
        $query = $this->model::where($column, $operator, $value);
        return $this->applySiteFilter($query);
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

    /**
     * Find by slug for current site
     */
    public function findBySlug(string $slug): ?Model
    {
        $query = $this->model::where('slug', $slug);
        return $this->applySiteFilter($query)->first();
    }
}