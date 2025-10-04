<?php

namespace App\Framework\Database\Relations;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Model;

class BelongsToManyHandler extends RelationshipHandler implements PivotOperationsInterface
{
    public function loadForResults(array $results, array $relationData, string $relation): array
    {
        $parentIds = $this->extractParentIds($results);

        if (empty($parentIds)) {
            return $this->setEmptyCollections($results, $relation);
        }

        [$relatedRecords, $groupedPivot] = $this->loadPivotAndRelated($parentIds, $relationData);

        return $this->attachRelationsToResults($results, $relatedRecords, $groupedPivot, $relationData, $relation);
    }

    public function loadForSingleModel(Model $model, ?array $relationData = null): Collection
    {
        $relationData = $relationData ?: $this->relationData;
        $parentId = $model->getAttribute('id');

        if (!$parentId) {
            return new Collection([]);
        }

        [$relatedRecords, $groupedPivot] = $this->loadPivotAndRelated([$parentId], $relationData);

        return $this->buildCollectionForModel($parentId, $relatedRecords, $groupedPivot, $relationData);
    }

    public function attach($ids, array $pivotData = []): int
    {
        $this->validateContext();
        $ids = is_array($ids) ? $ids : [$ids];
        $parentId = $this->getParentId();

        $existingIds = $this->getExistingRelationIds($parentId, $ids);
        $newIds = array_diff($ids, $existingIds);

        return $this->insertPivotRecords($parentId, $newIds, $pivotData);
    }

    public function detach($ids = null): int
    {
        $this->validateContext();
        $parentId = $this->getParentId();

        $query = new QueryBuilder($this->relationData['pivot_table'], $this->eagerLoader, $this->database);
        $query->where($this->relationData['foreign_key'], $parentId);

        if ($ids !== null) {
            $ids = is_array($ids) ? $ids : [$ids];
            $query->whereIn($this->relationData['related_key'], $ids);
        }

        return $query->delete();
    }

    public function sync(array $ids, array $pivotData = []): array
    {
        $this->validateContext();
        $parentId = $this->getParentId();

        $currentIds = $this->getCurrentRelationIds($parentId);
        $toAttach = array_diff($ids, $currentIds);
        $toDetach = array_diff($currentIds, $ids);

        $detached = !empty($toDetach) ? $this->detach($toDetach) : 0;
        $attached = !empty($toAttach) ? $this->attach($toAttach, $pivotData) : 0;

        return ['attached' => $attached, 'detached' => $detached];
    }

    public function toggle($ids, array $pivotData = []): array
    {
        $this->validateContext();
        $ids = is_array($ids) ? $ids : [$ids];
        $parentId = $this->getParentId();

        $existingIds = $this->getExistingRelationIds($parentId, $ids);
        $toAttach = array_diff($ids, $existingIds);
        $toDetach = array_intersect($ids, $existingIds);

        $attached = [];
        $detached = [];

        foreach ($toAttach as $id) {
            if ($this->attach($id, $pivotData) > 0) {
                $attached[] = $id;
            }
        }

        if (!empty($toDetach) && $this->detach($toDetach) > 0) {
            $detached = $toDetach;
        }

        return ['attached' => $attached, 'detached' => $detached];
    }

    // Private helper methods
    private function extractParentIds(array $results): array
    {
        return array_filter(array_map([$this, 'extractId'], $results));
    }

    private function setEmptyCollections(array $results, string $relation): array
    {
        foreach ($results as &$result) {
            $this->setValue($result, $relation, new Collection([]));
        }
        return $results;
    }

    private function loadPivotAndRelated(array $parentIds, array $relationData): array
    {
        $pivotRecords = $this->loadPivotRecords($parentIds, $relationData);

        if (empty($pivotRecords)) {
            return [[], []];
        }

        $relatedRecords = $this->loadRelatedRecords($pivotRecords, $relationData);
        $groupedPivot = $this->groupPivotRecords($pivotRecords, $relationData);

        return [$relatedRecords, $groupedPivot];
    }

    private function loadPivotRecords(array $parentIds, array $relationData): Collection
    {
        $pivotQuery = new QueryBuilder($relationData['pivot_table'], $this->eagerLoader, $this->database);
        return $pivotQuery->whereIn($relationData['foreign_key'], $parentIds)->get();
    }

    private function loadRelatedRecords(Collection $pivotRecords, array $relationData): array
    {
        $relatedIds = $this->extractUniqueRelatedIds($pivotRecords, $relationData);

        $relatedModel = $relationData['related'];
        $relatedInstance = new $relatedModel();
        $relatedQuery = new QueryBuilder($relatedInstance->getTable(), $this->eagerLoader, $this->database);

        $records = $relatedQuery->whereIn('id', $relatedIds)->get();

        return $this->indexRecordsById($records);
    }

    private function extractUniqueRelatedIds(Collection $pivotRecords, array $relationData): array
    {
        return array_unique(array_filter(array_map(function($pivot) use ($relationData) {
            return $this->extractValue($pivot, $relationData['related_key']);
        }, $pivotRecords->toArray())));
    }

    private function indexRecordsById(Collection $records): array
    {
        $indexed = [];
        foreach ($records as $record) {
            $indexed[$this->extractId($record)] = $record;
        }
        return $indexed;
    }

    private function groupPivotRecords(Collection $pivotRecords, array $relationData): array
    {
        $grouped = [];
        foreach ($pivotRecords as $pivot) {
            $parentId = $this->extractValue($pivot, $relationData['foreign_key']);
            $grouped[$parentId][] = $pivot;
        }
        return $grouped;
    }

    private function attachRelationsToResults(
        array $results,
        array $relatedRecords,
        array $groupedPivot,
        array $relationData,
        string $relation
    ): array {
        foreach ($results as &$result) {
            $parentId = $this->extractId($result);
            $collection = $this->buildCollectionForModel(
                $parentId,
                $relatedRecords,
                $groupedPivot,
                $relationData
            );
            $this->setValue($result, $relation, $collection);
        }
        return $results;
    }

    private function buildCollectionForModel(
        $parentId,
        array $relatedRecords,
        array $groupedPivot,
        array $relationData
    ): Collection {
        $relatedModels = [];

        if (isset($groupedPivot[$parentId])) {
            foreach ($groupedPivot[$parentId] as $pivot) {
                $relatedId = $this->extractValue($pivot, $relationData['related_key']);
                if (isset($relatedRecords[$relatedId])) {
                    $relatedModels[] = $relatedRecords[$relatedId];
                }
            }
        }

        return new Collection($relatedModels);
    }

    private function getExistingRelationIds(int $parentId, array $ids): array
    {
        $existingQuery = new QueryBuilder($this->relationData['pivot_table'], $this->eagerLoader, $this->database);
        $existing = $existingQuery
            ->where($this->relationData['foreign_key'], $parentId)
            ->whereIn($this->relationData['related_key'], $ids)
            ->get();

        return array_column($existing->toArray(), $this->relationData['related_key']);
    }

    private function getCurrentRelationIds(int $parentId): array
    {
        $currentQuery = new QueryBuilder($this->relationData['pivot_table'], $this->eagerLoader, $this->database);
        $current = $currentQuery
            ->where($this->relationData['foreign_key'], $parentId)
            ->get();

        return array_column($current->toArray(), $this->relationData['related_key']);
    }

    private function insertPivotRecords(int $parentId, array $newIds, array $pivotData): int
    {
        $attached = 0;
        foreach ($newIds as $relatedId) {
            $pivotRecord = array_merge($pivotData, [
                $this->relationData['foreign_key'] => $parentId,
                $this->relationData['related_key'] => $relatedId,
                'created_at' => $pivotData['created_at'] ?? date('Y-m-d H:i:s'),
                'updated_at' => $pivotData['updated_at'] ?? date('Y-m-d H:i:s'),
            ]);

            $insertQuery = new QueryBuilder($this->relationData['pivot_table'], $this->eagerLoader, $this->database);
            if ($insertQuery->insert($pivotRecord)) {
                $attached++;
            }
        }
        return $attached;
    }
}