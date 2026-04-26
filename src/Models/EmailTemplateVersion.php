<?php

namespace App\Models;

/**
 * Immutable snapshot of an EmailTemplate at a point in time.
 *
 * Created automatically by EmailTemplateService on every save.
 * Version numbers are per-template (not global).
 *
 * Schema (migration below):
 *   id                  bigint PK
 *   email_template_id   bigint FK → email_templates.id (cascade delete)
 *   version_number      int
 *   snapshot_json       json   — full template payload: name, slug, category,
 *                               blocks, use_default_theme, theme_id, is_active
 *   created_by          bigint nullable FK → users.id
 *   created_at          timestamp
 *
 * @property int $id
 * @property int $email_template_id
 * @property int $version_number
 * @property array $snapshot_json
 * @property int|null $created_by
 * @property string $created_at
 * @property string|null $created_by_name   (eager-loaded via join, read-only)
 */
class EmailTemplateVersion extends Model
{
    /** No updated_at — versions are immutable. */
    public $timestamps = false;
    protected $table = 'email_template_versions';
    protected $fillable = [
        'email_template_id',
        'version_number',
        'snapshot_json',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'snapshot_json' => 'array',
        'version_number' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function template(): ?EmailTemplate
    {
        return EmailTemplate::find($this->email_template_id);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * The blocks stored in this version's snapshot.
     */
    public function blocks(): array
    {
        return $this->snapshot_json['blocks'] ?? [];
    }

    /**
     * Return the snapshot fields that are safe to restore directly into an
     * EmailTemplate update payload.
     */
    public function toRestorePayload(): array
    {
        $snap = $this->snapshot_json;

        return array_filter([
            'name' => $snap['name'] ?? null,
            'slug' => $snap['slug'] ?? null,
            'description' => $snap['description'] ?? null,
            'category' => $snap['category'] ?? null,
            'blocks' => $snap['blocks'] ?? [],
            'use_default_theme' => $snap['use_default_theme'] ?? true,
            'theme_id' => $snap['theme_id'] ?? null,
            'is_active' => $snap['is_active'] ?? true,
        ], fn($v) => $v !== null);
    }
}