<?php

namespace App\Framework\Database\Relations;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;

class EagerLoader
{
    private Database $database;

    public function __construct(
        private RelationshipAnalyzer $analyzer,
        private RelationHandlerFactory $factory,
        ?Database $database = null

    ) {
        $this->database = $database ?? Database::getInstance();
    }

    /**
     * Main entry point for eager loading relations
     */
    public function loadRelationsForResults(array $results, array $relations, string $modelClass): array
    {
        if (empty($results) || empty($relations)) {
            return $results;
        }

        // Group relations by depth and load level by level
        $relationGroups = $this->groupRelationsByDepth($relations);

        foreach ($relationGroups as $depth => $relationPaths) {
            foreach ($relationPaths as $relationPath) {
                $results = $this->loadRelationPath($results, $relationPath, $modelClass);
            }
        }

        return $results;
    }

    private function groupRelationsByDepth(array $relations): array
    {
        $groups = [];
        foreach ($relations as $relation) {
            $depth = substr_count($relation, '.');
            $groups[$depth][] = $relation;
        }
        ksort($groups);
        return $groups;
    }

    private function loadRelationPath(array $results, string $relationPath, string $modelClass): array
    {
        $pathParts = explode('.', $relationPath);

        if (count($pathParts) === 1) {
            // Direct relation
            return $this->loadDirectRelation($results, $pathParts[0], $modelClass);
        }

        // Nested relation
        return $this->loadNestedRelation($results, $pathParts, $modelClass);
    }

    private function loadDirectRelation(array $results, string $relationName, string $modelClass): array
    {
        $tempModel = new $modelClass();

        if (!method_exists($tempModel, $relationName)) {
            return $results;
        }

        $relationData = $this->analyzer->analyzeRelationshipMethod($tempModel, $relationName);

        $handler = (new RelationHandlerFactory($this->database))->create($relationData['type'], $relationData);

        if (!$handler) {
            return $results;
        }

        return $handler->loadForResults($results, $relationData, $relationName);
    }

    private function loadNestedRelation(array $results, array $pathParts, string $modelClass): array
    {
        $targetRelation = array_pop($pathParts);

        // Navigate to parent level
        $parentResults = $this->navigateToParentLevel($results, $pathParts, $modelClass);

        if (empty($parentResults['results'])) {
            return $results;
        }

        // Load target relation on parent results
        $loadedParents = $this->loadDirectRelation(
            $parentResults['results'],
            $targetRelation,
            $parentResults['modelClass']
        );

        // Map back to original structure
        return $this->mapNestedResultsBack($results, $loadedParents, $pathParts, $targetRelation);
    }

    private function navigateToParentLevel(array $results, array $pathParts, string $modelClass): array
    {
        $currentResults = $results;
        $currentModelClass = $modelClass;

        foreach ($pathParts as $pathSegment) {
            $tempModel = new $currentModelClass();

            if (!method_exists($tempModel, $pathSegment)) {
                return ['results' => [], 'modelClass' => $currentModelClass];
            }

            $relationData = $this->analyzer->analyzeRelationshipMethod($tempModel, $pathSegment);
            $currentModelClass = $relationData['related'];

            $nextLevelResults = [];
            foreach ($currentResults as $result) {
                $relationValue = $this->extractValue($result, $pathSegment);

                if ($relationValue !== null) {
                    if ($relationValue instanceof Collection) {
                        $nextLevelResults = array_merge($nextLevelResults, $relationValue->all());
                    } elseif (is_array($relationValue) && $this->isIndexedArray($relationValue)) {
                        $nextLevelResults = array_merge($nextLevelResults, $relationValue);
                    } else {
                        $nextLevelResults[] = $relationValue;
                    }
                }
            }

            $currentResults = $nextLevelResults;
        }

        return ['results' => $currentResults, 'modelClass' => $currentModelClass];
    }

    private function mapNestedResultsBack(array $originalResults, array $loadedResults, array $pathParts, string $targetRelation): array
    {
        $loadedLookup = [];
        foreach ($loadedResults as $loaded) {
            $id = $this->extractValue($loaded, 'id');
            if ($id !== null) {
                $loadedLookup[$id] = $this->extractValue($loaded, $targetRelation);
            }
        }

        foreach ($originalResults as &$result) {
            $this->applyNestedRelation($result, $pathParts, $targetRelation, $loadedLookup, 0);
        }

        return $originalResults;
    }

    private function applyNestedRelation(&$result, array $pathParts, string $targetRelation, array $loadedLookup, int $depth): void
    {
        if ($depth === count($pathParts)) {
            $id = $this->extractValue($result, 'id');
            if ($id !== null && isset($loadedLookup[$id])) {
                $this->setValue($result, $targetRelation, $loadedLookup[$id]);
            }
            return;
        }

        $currentRelation = $pathParts[$depth];
        $relationValue = $this->extractValue($result, $currentRelation);

        if ($relationValue !== null) {
            if ($relationValue instanceof Collection) {
                // Handle Collection objects
                $items = $relationValue->all();
                foreach ($items as &$item) {
                    $this->applyNestedRelation($item, $pathParts, $targetRelation, $loadedLookup, $depth + 1);
                }
                $this->setValue($result, $currentRelation, new Collection($items));
            } elseif (is_array($relationValue) && $this->isIndexedArray($relationValue)) {
                foreach ($relationValue as &$item) {
                    $this->applyNestedRelation($item, $pathParts, $targetRelation, $loadedLookup, $depth + 1);
                }
                $this->setValue($result, $currentRelation, $relationValue);
            } else {
                $this->applyNestedRelation($relationValue, $pathParts, $targetRelation, $loadedLookup, $depth + 1);
                $this->setValue($result, $currentRelation, $relationValue);
            }
        }
    }

    private function extractValue($result, string $key)
    {
        if (is_array($result)) {
            return $result[$key] ?? null;
        } elseif (is_object($result)) {
            if (method_exists($result, 'getAttribute')) {
                return $result->getAttribute($key);
            } elseif (property_exists($result, $key)) {
                return $result->$key;
            }
        }
        return null;
    }

    protected function setValue(&$result, string $key, $value): void
    {
        if (is_array($result)) {
            $result[$key] = $value;
        } elseif ($result instanceof Collection) {
            // Handle Collection - set relation on each item in the collection
            foreach ($result as $item) {
                if (is_object($item) && method_exists($item, 'setRelation')) {
                    $item->setRelation($key, $value);
                }
            }
        } elseif (is_object($result)) {
            if (method_exists($result, 'setRelation')) {
                $result->setRelation($key, $value);
            } elseif (method_exists($result, 'setAttribute')) {
                $result->setAttribute($key, $value);
            } else {
                $result->$key = $value;
            }
        }
    }

    private function isIndexedArray(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }
}