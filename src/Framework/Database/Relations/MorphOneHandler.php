<?php

namespace App\Framework\Database\Relations;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Model;

class MorphOneHandler extends RelationshipHandler
{
    public function loadForResults(array $results, array $relationData, string $relation): array
    {
        $localIds = $this->extractLocalIds($results, $relationData['local_key']);

        if (empty($localIds)) {
            return $this->setEmptyRelations($results, $relation, null);
        }

        $relatedRecords = $this->loadRelatedRecords($relationData, $localIds);
        $relatedLookup = $this->createUniqueLookupMap($relatedRecords, $relationData['morph_id']);

        return $this->mapRelationsToResults($results, $relatedLookup, $relationData, $relation);
    }

    private function extractLocalIds(array $results, string $localKey): array
    {
        return array_filter(array_map(function ($result) use ($localKey) {
            return $this->extractValue($result, $localKey);
        }, $results));
    }

    private function setEmptyRelations(array $results, string $relation, $emptyValue): array
    {
        foreach ($results as &$result) {
            $this->setValue($result, $relation, $emptyValue);
        }
        return $results;
    }

    private function loadRelatedRecords(array $relationData, array $localIds): Collection
    {
        $relatedModel = $relationData['related'];
        $relatedInstance = new $relatedModel();
        $relatedQuery = new QueryBuilder($relatedInstance->getTable(), $this->eagerLoader, $this->database);

        $parentClass = $this->model ? get_class($this->model) : null;

        return $relatedQuery
            ->where($relationData['morph_type'], $parentClass)
            ->whereIn($relationData['morph_id'], $localIds)
            ->get();
    }

    private function createUniqueLookupMap(Collection $records, string $key): array
    {
        $lookup = [];
        foreach ($records as $record) {
            $keyValue = $this->extractValue($record, $key);
            if (!isset($lookup[$keyValue])) {
                $lookup[$keyValue] = $record;
            }
        }
        return $lookup;
    }

    private function mapRelationsToResults(
        array  $results,
        array  $relatedLookup,
        array  $relationData,
        string $relation
    ): array
    {
        foreach ($results as &$result) {
            $localKeyValue = $this->extractValue($result, $relationData['local_key']);
            $this->setValue($result, $relation, $relatedLookup[$localKeyValue] ?? null);
        }
        return $results;
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
            ->where($relationData['morph_type'], get_class($model))
            ->where($relationData['morph_id'], $localKeyValue)
            ->get();

        $result = $results->first() ?? null;

        return $result ? $this->ensureModelInstance($result, $relatedModel) : null;
    }
}