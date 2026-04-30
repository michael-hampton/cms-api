<?php

namespace App\Framework\Database\Relations;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Model;
use RuntimeException;

abstract class RelationshipHandler implements RelationshipLoaderInterface, RelationContextInterface
{
    protected ?Model $model = null;
    protected array $relationData = [];
    protected bool $returnHandler = false;
    public EagerLoader $eagerLoader;

    public function __construct(
        public Database $database,
        array           $relationData = [],
        bool            $returnHandler = false
    ) {
        $this->relationData = $relationData;
        $this->returnHandler = $returnHandler;
        $this->eagerLoader = new EagerLoader(new RelationshipAnalyzer(), new RelationHandlerFactory($this->database));
    }

    // Set context for attach/detach operations
    public function setContext(Model $model, array $relationData): self
    {
        $this->model = $model;
        $this->relationData = $relationData;
        return $this;
    }

    public function hasContext(): bool
    {
        return $this->model !== null && !empty($this->relationData);
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function getRelationData(): array
    {
        return $this->relationData;
    }

    public function setRelationData(array $relationData): void
    {
        $this->relationData = $relationData;
    }

    public function shouldReturnHandler(): bool
    {
        return $this->returnHandler;
    }

    /**
     * Create a RelationBuilder for query chaining
     */
    public function newQuery(): RelationBuilder
    {
        if (!$this->hasContext()) {
            throw new RuntimeException("Handler context not set. Call setContext() first.");
        }

        return new RelationBuilder($this, $this->model, $this->relationData);
    }

    /**
     * Get the query builder for this relationship
     */
    public function getQuery(): RelationBuilder
    {
        return $this->newQuery();
    }


    // Default implementations - only BelongsToManyHandler will override these
    public function attach($ids): int
    {
        throw new \BadMethodCallException("Attach is only supported for belongsToMany relationships");
    }

    public function detach($ids = null): int
    {
        throw new \BadMethodCallException("Detach is only supported for belongsToMany relationships");
    }

    public function sync(array $ids): array
    {
        throw new \BadMethodCallException("Sync is only supported for belongsToMany relationships");
    }

    // Consistent utility methods for all handlers
    protected function extractValue($result, string $key)
    {
        if (is_array($result)) {
            return $result[$key] ?? null;
        }

        if ($result instanceof Collection) {
            // For collections, we need to extract from each item
            $values = [];
            foreach ($result as $item) {
                if (is_object($item) && method_exists($item, 'getAttribute')) {
                    $values[] = $item->getAttribute($key);
                } elseif (is_array($item)) {
                    $values[] = $item[$key] ?? null;
                }
            }
            return $values;
        }

        if (is_object($result)) {
            if (method_exists($result, 'getAttribute')) {
                return $result->getAttribute($key);
            }

            if (property_exists($result, $key)) {
                return $result->$key;
            }
        }
        return null;
    }

    protected function setValue(&$result, string $key, $value): void
    {
        if (is_array($result)) {
            $result[$key] = $value;
        }

        if ($result instanceof Collection) {
            // Handle Collection - set relation on each item in the collection
            foreach ($result as $item) {
                if (is_object($item) && method_exists($item, 'setRelation')) {
                    $item->setRelation($key, $value);
                }
            }
        }

        if (is_object($result)) {
            if (method_exists($result, 'setRelation')) {
                $result->setRelation($key, $value);
            } elseif (method_exists($result, 'setAttribute')) {
                // Don't use setAttribute for relations - use setRelation
                if (method_exists($result, 'setRelation')) {
                    $result->setRelation($key, $value);
                } else {
                    $result->setAttribute($key, $value);
                }
            } else {
                $result->$key = $value;
            }
        }
    }

    protected function extractId($result)
    {
        return $this->extractValue($result, 'id');
    }

    protected function createModelInstance(string $modelClass, array $data): Model
    {
        $model = new $modelClass($data);
        $model->setExists(true);
        $model->original = $data;
        return $model;
    }

    protected function ensureModelInstance($item, string $modelClass): Model
    {
        if ($item instanceof Model) {
            return $item;
        }

        if (is_array($item)) {
            return $this->createModelInstance($modelClass, $item);
        }

        throw new RuntimeException("Cannot convert item to model instance");
    }

    protected function validateContext(): void
    {
        if (!$this->hasContext()) {
            throw new RuntimeException(
                "Handler context not set. Call setContext() first."
            );
        }
    }

    protected function getParentId()
    {
        $this->validateContext();

        $parentId = $this->model->getAttribute('id');
        if (!$parentId) {
            throw new RuntimeException(
                "Parent model must have an ID for this operation."
            );
        }

        return $parentId;
    }

    public function withTimestamps(): self
    {
        $this->relationData['with_timestamps'] = true;
        return $this;
    }
}