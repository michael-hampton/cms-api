<?php

namespace App\Framework\Database\Relations;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Model;

class HasOneHandler extends RelationshipHandler
{
    public function loadForResults(array $results, array $relationData, string $relation): array
    {
        $localIds = $this->extractLocalIds($results, $relationData['local_key']);

        if (empty($localIds)) {
            return $this->setEmptyRelations($results, $relation, null);
        }

        $relatedRecords = $this->loadRelatedRecords($relationData, $localIds);
        $relatedLookup = $this->createUniqueLookupMap($relatedRecords, $relationData['foreign_key']);

        return $this->mapRelationsToResults($results, $relatedLookup, $relationData, $relation);
    }

    public function loadForSingleModel(Model $model, ?array $relationData = null): ?Model
    {
        $relationData = $relationData ?: $this->relationData;
        $localKeyValue = $model->getAttribute($relationData['local_key']);

        if (!$localKeyValue) {
            return null;
        }

        $relatedModel = $relationData['related'];
        $relatedInstance = new $relatedModel();
        $relatedQuery = new QueryBuilder($relatedInstance->getTable(), $this->eagerLoader, $this->database);

        $results = $relatedQuery
            ->where($relationData['foreign_key'], $localKeyValue)
            ->get();

        return $results->first() ?? null;
    }

    private function extractLocalIds(array $results, string $localKey): array
    {
        return array_filter(array_map(function($result) use ($localKey) {
            return $this->extractValue($result, $localKey);
        }, $results));
    }

    private function loadRelatedRecords(array $relationData, array $localIds): Collection
    {
        $relatedModel = $relationData['related'];
        $relatedInstance = new $relatedModel();
        $relatedQuery = new QueryBuilder($relatedInstance->getTable(), $this->eagerLoader, $this->database);

        return $relatedQuery->whereIn($relationData['foreign_key'], $localIds)->get();
    }

    private function createUniqueLookupMap(Collection $records, string $key): array
    {
        $lookup = [];
        foreach ($records as $record) {
            $keyValue = $this->extractValue($record, $key);
            // Only store first match for hasOne relationship
            if (!isset($lookup[$keyValue])) {
                $lookup[$keyValue] = $record;
            }
        }
        return $lookup;
    }

    private function setEmptyRelations(array $results, string $relation, $emptyValue): array
    {
        foreach ($results as &$result) {
            $this->setValue($result, $relation, $emptyValue);
        }
        return $results;
    }

    private function mapRelationsToResults(
        array $results,
        array $relatedLookup,
        array $relationData,
        string $relation
    ): array {
        foreach ($results as &$result) {
            $localKeyValue = $this->extractValue($result, $relationData['local_key']);
            $this->setValue($result, $relation, $relatedLookup[$localKeyValue] ?? null);
        }
        return $results;
    }
}