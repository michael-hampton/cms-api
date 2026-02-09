<?php

namespace App\Framework\Database\Relations;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Model;

class MorphManyHandler extends RelationshipHandler
{
    public function loadForResults(array $results, array $relationData, string $relation): array
    {
        $localIds = $this->extractLocalIds($results, $relationData['local_key']);

        if (empty($localIds)) {
            return $this->setEmptyCollections($results, $relation);
        }

        $relatedRecords = $this->loadRelatedRecords($relationData, $localIds);

        $groupedRelated = $this->groupRelatedRecords($relatedRecords, $relationData['morph_id']);

        return $this->mapCollectionsToResults($results, $groupedRelated, $relationData, $relation);
    }

    private function extractLocalIds(array $results, string $localKey): array
    {
        return array_filter(array_map(function ($result) use ($localKey) {
            return $this->extractValue($result, $localKey);
        }, $results));
    }

    private function setEmptyCollections(array $results, string $relation): array
    {
        foreach ($results as &$result) {
            $this->setValue($result, $relation, new Collection([]));
        }
        return $results;
    }

    private function loadRelatedRecords(array $relationData, array $localIds): Collection
    {
        $relatedModel = $relationData['related'];
        $relatedInstance = new $relatedModel();

        $relatedQuery = new QueryBuilder($relatedInstance->getTable(), $this->eagerLoader, $this->database);

        return $relatedQuery
            ->where($relationData['morph_type'], $relationData['parent_class'] ?? null)
            ->whereIn($relationData['morph_id'], $localIds)
            ->get();
    }

    private function groupRelatedRecords(Collection $records, string $morphId): array
    {
        $grouped = [];
        foreach ($records as $record) {
            $morphIdValue = $this->extractValue($record, $morphId);
            $grouped[$morphIdValue][] = $record;
        }
        return $grouped;
    }

    private function mapCollectionsToResults(
        array  $results,
        array  $groupedRelated,
        array  $relationData,
        string $relation
    ): array
    {
        foreach ($results as &$result) {
            $localKeyValue = $this->extractValue($result, $relationData['local_key']);
            $relatedModels = $groupedRelated[$localKeyValue] ?? [];
            $this->setValue($result, $relation, new Collection($relatedModels));
        }
        return $results;
    }

    public function loadForSingleModel(Model $model, ?array $relationData = null): Collection
    {
        $relationData = $relationData ?: $this->relationData;
        $localKeyValue = $model->getAttribute($relationData['local_key']);

        if (!$localKeyValue) {
            return new Collection([]);
        }

        $relatedModel = $relationData['related'];
        $relatedInstance = new $relatedModel();
        $relatedQuery = new QueryBuilder($relatedInstance->getTable(), $this->eagerLoader, $this->database);

        echo '<pre>';
        print_r($relationData);
        die('mike');

        $results = $relatedQuery
            ->where($relationData['morph_type'], get_class($model))
            ->where($relationData['morph_id'], $localKeyValue)
            ->get();

        return $this->convertResultsToModels($results, $relatedModel);
    }

    private function convertResultsToModels(Collection $results, string $modelClass): Collection
    {
        return $results->map(function ($result) use ($modelClass) {
            return $this->ensureModelInstance($result, $modelClass);
        });
    }
}