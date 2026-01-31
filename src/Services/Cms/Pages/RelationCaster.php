<?php

namespace App\Services\Cms\Pages;

use DateTime;

class RelationCaster
{
    private array $castingRules = [
        'metadata' => [
            'publish_date' => 'datetime',
            'expiry_date' => 'datetime',
            'featured' => 'boolean',
            'allow_comments' => 'boolean',
            'is_reusable_block' => 'boolean'
        ],
        'seo' => [
            'no_index' => 'boolean',
            'no_follow' => 'boolean'
        ],
        'settings' => [
            'menu_order' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'price' => 'float',
            'sale_price' => 'float',
            'recurring' => 'boolean'
        ],
        'social' => [
            'enable_sharing' => 'boolean',
            'track_shares' => 'boolean',
            'track_clicks' => 'boolean',
            'gtm_events' => 'boolean',
            'show_follower_count' => 'boolean',
            'show_share_count' => 'boolean',
            'show_recent_activity' => 'boolean',
            'testimonial_integration' => 'boolean',
            'auto_embed_links' => 'boolean',
            'lazy_load_embeds' => 'boolean',
            'platforms' => 'json',
            'pixel_ids' => 'json'
        ]
    ];

    /**
     * Add casting rules for a relation
     */
    public function addCastingRules(string $relation, array $rules): void
    {
        $this->castingRules[$relation] = array_merge(
            $this->castingRules[$relation] ?? [],
            $rules
        );
    }

    /**
     * Cast data for duplication based on relation type
     */
    public function castForDuplication(string $relationType, array $data): array
    {
        if (!isset($this->castingRules[$relationType])) {
            return $data;
        }

        $rules = $this->castingRules[$relationType];
        $casted = [];

        foreach ($data as $key => $value) {
            // Skip meta fields
            if (in_array($key, ['id', 'page_id', 'created_at', 'updated_at'])) {
                continue;
            }

            if (isset($rules[$key])) {
                $casted[$key] = $this->castValue($value, $rules[$key]);
            } else {
                $casted[$key] = $value;
            }
        }

        return $casted;
    }

    /**
     * Cast a single value based on type
     */
    private function castValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        return match($type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'datetime' => $this->castToDateTime($value),
            'json' => $this->castToJson($value),
            default => $value
        };
    }

    /**
     * Cast value to DateTime string
     */
    private function castToDateTime($value): ?string
    {
        if ($value instanceof DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            try {
                $date = new DateTime($value);
                return $date->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Cast value to JSON
     */
    private function castToJson($value): ?string
    {
        if (is_string($value)) {
            // Already JSON
            return $value;
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return null;
    }

    /**
     * Get casting rules for a relation
     */
    public function getRulesForRelation(string $relation): array
    {
        return $this->castingRules[$relation] ?? [];
    }
}