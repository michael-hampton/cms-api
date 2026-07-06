<?php

namespace App\Framework\Support\Config;

/**
 * Validates a ConfigModel's *structure* (empty keys, duplicate keys).
 *
 * Deliberately separate from JSON parsing (see
 * App\Support\Config\Json\DuplicateKeyAwareJsonParser): a document can
 * be syntactically valid JSON while still failing configuration
 * validation (e.g. an empty-string key, or the same key twice). Ticket 2
 * requires these two failure modes to be distinguishable, and Ticket 3
 * requires the same rules to gate both inline key add/delete and the
 * final pre-submit save check.
 */
final class ConfigValidator
{
    /**
     * @return list<ConfigValidationError>
     */
    public function validate(ConfigModel $model): array
    {
        $errors = [];

        foreach ($model->all() as $entry) {
            if (trim($entry->key) === '') {
                $errors[] = new ConfigValidationError(
                    entryId: $entry->id,
                    key: $entry->key,
                    code: 'empty_key',
                    message: 'Keys cannot be empty or whitespace-only.',
                );
            }
        }

        foreach ($model->findDuplicateKeys() as $duplicate) {
            foreach ($duplicate['entryIds'] as $entryId) {
                $errors[] = new ConfigValidationError(
                    entryId: $entryId,
                    key: $duplicate['key'],
                    code: 'duplicate_key',
                    message: sprintf('Key "%s" is used more than once.', $duplicate['key']),
                );
            }
        }

        return $errors;
    }

    public function isValid(ConfigModel $model): bool
    {
        return $this->validate($model) === [];
    }
}