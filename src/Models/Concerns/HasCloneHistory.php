<?php

namespace App\Models\Concerns;

trait HasCloneHistory
{
    /**
     * Add a clone record to history
     */
    public function addCloneRecord(string $action, int $relatedId, ?int $relatedSiteId = null): void
    {
        $history = $this->clone_history ?? [];

        $record = [
            'action' => $action,
            'related_id' => $relatedId,
            'related_site_id' => $relatedSiteId,
            'timestamp' => now(),
        ];

        $history[] = $record;

        $this->clone_history = $history;
        $this->save();
    }

    /**
     * Get all clones created from this entity
     */
    public function getClonedToRecords(): array
    {
        if (!$this->clone_history) {
            return [];
        }

        return array_filter($this->clone_history, function($record) {
            return in_array($record['action'], ['cloned_to', 'merged_to']);
        });
    }

    /**
     * Get the original entity this was cloned from
     */
    public function getClonedFromRecord(): ?array
    {
        if (!$this->clone_history) {
            return null;
        }

        $records = array_filter($this->clone_history, function($record) {
            return in_array($record['action'], ['cloned_from', 'merged_from']);
        });

        return !empty($records) ? reset($records) : null;
    }

    /**
     * Check if this entity was cloned from another
     */
    public function isClone(): bool
    {
        return $this->getClonedFromRecord() !== null;
    }

    /**
     * Check if this entity has been cloned
     */
    public function hasBeenCloned(): bool
    {
        return !empty($this->getClonedToRecords());
    }
}