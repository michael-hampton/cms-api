<?php

namespace App\Framework\Database\Relations;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Model;

class BelongsToHandler extends RelationshipHandler
{
    public function loadForResults(array $results, array $relationData, string $relation): array
    {
        $foreignIds = $this->extractForeignIds($results, $relationData['foreign_key']);

        if (empty($foreignIds)) {
            return $this->setEmptyRelations($results, $relation, null);
        }

        $relatedRecords = $this->loadRelatedRecords(
            $relationData,
            $relationData['owner_key'],
            $foreignIds
        );

        return $this->mapRelationsToResults($results, $relatedRecords, $relationData, $relation);
    }

    public function loadForSingleModel(Model $model, ?array $relationData = null): ?Model
    {
        $relationData = $relationData ?: $this->relationData;

        $foreignKeyValue = $model->getAttribute($this->relationData['foreign_key']);

        if (!$foreignKeyValue) {
            return null;
        }

        $relatedModel = $this->relationData['related'];
        $relatedInstance = new $relatedModel();
        $relatedQuery = new QueryBuilder($relatedInstance->getTable(), $this->eagerLoader, $this->database);

        $results = $relatedQuery
            ->where($relationData['owner_key'], $foreignKeyValue)
            ->get();

        $result = $results->first() ?? null;

        return $this->ensureModelInstance($result, $relatedModel);
    }

    private function extractForeignIds(array $results, string $foreignKey): array
    {
        return array_filter(array_map(function ($result) use ($foreignKey) {
            return $this->extractValue($result, $foreignKey);
        }, $results));
    }

    private function loadRelatedRecords(array $relationData, string $key, array $ids): Collection
    {
        $relatedModel = $relationData['related'];
        $relatedInstance = new $relatedModel();
        $relatedQuery = new QueryBuilder($relatedInstance->getTable(), $this->eagerLoader, $this->database);

        return $relatedQuery->whereIn($key, $ids)->get();
    }

    private function setEmptyRelations(array $results, string $relation, $emptyValue): array
    {
        foreach ($results as &$result) {
            $this->setValue($result, $relation, $emptyValue);
        }
        return $results;
    }

    private function mapRelationsToResults(
        array      $results,
        Collection $relatedRecords,
        array      $relationData,
        string     $relation
    ): array
    {
        $relatedLookup = $this->createLookupMap($relatedRecords, $relationData['owner_key']);

        foreach ($results as &$result) {
            $foreignKeyValue = $this->extractValue($result, $relationData['foreign_key']);
            $this->setValue($result, $relation, $relatedLookup[$foreignKeyValue] ?? null);
        }

        return $results;
    }

    private function createLookupMap(Collection $records, string $key): array
    {
        $lookup = [];
        foreach ($records as $record) {
            $keyValue = $this->extractValue($record, $key);
            $lookup[$keyValue] = $record;
        }
        return $lookup;
    }
}