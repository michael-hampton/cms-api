<?php

namespace App\Framework\Database\Relations;

use App\Framework\Database\QueryBuilder;
use App\Models\Model;

class MorphToHandler extends RelationshipHandler
{
    public function loadForResults(array $results, array $relationData, string $relation): array
    {
        // Group results by their morph type
        $groupedByType = $this->groupResultsByMorphType($results, $relationData);

        if (empty($groupedByType)) {
            return $this->setEmptyRelations($results, $relation, null);
        }

        // Load related models for each type
        $relatedModels = $this->loadRelatedModelsByType($groupedByType, $relationData);

        // Map related models back to results
        return $this->mapRelatedToResults($results, $relatedModels, $relationData, $relation);
    }

    private function groupResultsByMorphType(array $results, array $relationData): array
    {
        $grouped = [];

        foreach ($results as $result) {
            $morphType = $this->extractValue($result, $relationData['morph_type']);
            $morphId = $this->extractValue($result, $relationData['morph_id']);

            if ($morphType && $morphId) {
                $grouped[$morphType][] = [
                    'id' => $morphId,
                    'result' => $result
                ];
            }
        }

        return $grouped;
    }

    private function setEmptyRelations(array $results, string $relation, $emptyValue): array
    {
        foreach ($results as &$result) {
            $this->setValue($result, $relation, $emptyValue);
        }
        return $results;
    }

    private function loadRelatedModelsByType(array $groupedByType, array $relationData): array
    {
        $allRelated = [];

        foreach ($groupedByType as $morphType => $items) {
            if (!class_exists($morphType)) {
                continue;
            }

            $ids = array_column($items, 'id');
            $relatedInstance = new $morphType();
            $relatedQuery = new QueryBuilder($relatedInstance->getTable(), $this->eagerLoader, $this->database);

            $related = $relatedQuery
                ->whereIn($relationData['owner_key'], $ids)
                ->get();

            // Index by ID for lookup
            foreach ($related as $model) {
                $key = $morphType . ':' . $this->extractValue($model, $relationData['owner_key']);
                $allRelated[$key] = $model;
            }
        }

        return $allRelated;
    }

    private function mapRelatedToResults(
        array  $results,
        array  $relatedModels,
        array  $relationData,
        string $relation
    ): array
    {
        foreach ($results as &$result) {
            $morphType = $this->extractValue($result, $relationData['morph_type']);
            $morphId = $this->extractValue($result, $relationData['morph_id']);

            if ($morphType && $morphId) {
                $key = $morphType . ':' . $morphId;
                $this->setValue($result, $relation, $relatedModels[$key] ?? null);
            } else {
                $this->setValue($result, $relation, null);
            }
        }

        return $results;
    }

    public function loadForSingleModel(Model $model, ?array $relationData = null): ?Model
    {
        $relationData = $relationData ?: $this->relationData;

        $morphType = $model->getAttribute($relationData['morph_type']);
        $morphId = $model->getAttribute($relationData['morph_id']);

        if (!$morphType || !$morphId) {
            return null;
        }

        if (!class_exists($morphType)) {
            return null;
        }

        $relatedInstance = new $morphType();
        $relatedQuery = new QueryBuilder($relatedInstance->getTable(), $this->eagerLoader, $this->database);

        $results = $relatedQuery
            ->where($relationData['owner_key'], $morphId)
            ->get();

        $result = $results->first() ?? null;

        return $result ? $this->ensureModelInstance($result, $morphType) : null;
    }
}