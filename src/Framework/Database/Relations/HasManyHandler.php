<?php

namespace App\Framework\Database\Relations;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Model;

class HasManyHandler extends RelationshipHandler
{
    public function loadForResults(array $results, array $relationData, string $relation): array
    {
        $localIds = $this->extractLocalIds($results, $relationData['local_key']);

        if (empty($localIds)) {
            return $this->setEmptyCollections($results, $relation);
        }

        $relatedRecords = $this->loadAndConvertToModels($relationData, $localIds);
        $groupedRelated = $this->groupRelatedRecords($relatedRecords, $relationData['foreign_key']);

        return $this->mapCollectionsToResults($results, $groupedRelated, $relationData, $relation);
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

        $results = $relatedQuery
            ->where($relationData['foreign_key'], $localKeyValue)
            ->get();

        return $this->convertResultsToModels($results, $relatedModel);
    }

    private function extractLocalIds(array $results, string $localKey): array
    {
        return array_filter(array_map(function ($result) use ($localKey) {
            return $this->extractValue($result, $localKey);
        }, $results));
    }

    private function loadAndConvertToModels(array $relationData, array $localIds): Collection
    {
        $relatedModel = $relationData['related'];
        $relatedInstance = new $relatedModel();
        $relatedQuery = new QueryBuilder($relatedInstance->getTable(), $this->eagerLoader, $this->database);

        $relatedRecords = $relatedQuery->whereIn($relationData['foreign_key'], $localIds)->get();

        return $this->convertResultsToModels($relatedRecords, $relatedModel);
    }

    private function convertResultsToModels(Collection $results, string $modelClass): Collection
    {
        return $results->map(function($result) use ($modelClass) {
            return $this->ensureModelInstance($result, $modelClass);
        });
    }

    private function groupRelatedRecords(Collection $records, string $foreignKey): array
    {
        $grouped = [];
        foreach ($records as $record) {
            $foreignKeyValue = $this->extractValue($record, $foreignKey);
            $grouped[$foreignKeyValue][] = $record;
        }
        return $grouped;
    }

    private function setEmptyCollections(array $results, string $relation): array
    {
        foreach ($results as &$result) {
            $this->setValue($result, $relation, new Collection([]));
        }
        return $results;
    }

    private function mapCollectionsToResults(
        array $results,
        array $groupedRelated,
        array $relationData,
        string $relation
    ): array {
        foreach ($results as &$result) {
            $localKeyValue = $this->extractValue($result, $relationData['local_key']);
            $relatedModels = $groupedRelated[$localKeyValue] ?? [];
            $this->setValue($result, $relation, new Collection($relatedModels));
        }
        return $results;
    }
}