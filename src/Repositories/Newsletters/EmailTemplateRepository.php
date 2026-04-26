<?php

namespace App\Repositories\Newsletters;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\NewsletterLayout;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

/**
 * Scoped repository for email template layouts.
 *
 * All reads and writes are constrained to rows where
 * type = NewsletterLayout::TYPE_EMAIL_TEMPLATE.
 *
 * The underlying storage is newsletter_layouts — this repository is a query
 * scope, not a separate table. It delegates structural version operations to
 * NewsletterLayoutRepository so there is no duplication.
 */
class EmailTemplateRepository extends Repository
{
    public function __construct(
        private readonly NewsletterLayoutRepository $layoutRepository,
    )
    {
        parent::__construct();
    }

    protected function getModelClass(): string
    {
        return NewsletterLayout::class;
    }

    // ── Base query scope ──────────────────────────────────────────────────────

    public function query(): QueryBuilder
    {
        return NewsletterLayout::where('type', NewsletterLayout::TYPE_EMAIL_TEMPLATE);
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $config = SearchConfigurationFactory::create('newsletter_layout');
        $engine = new SearchEngine($config);

        return $engine->search(
            NewsletterLayout::where('type', NewsletterLayout::TYPE_EMAIL_TEMPLATE),
            $criteria,
        );
    }

    public function getAllBySite(int $siteId, ?string $category = null): Collection
    {
        $q = $this->query()
            ->where('site_id', $siteId)
            ->orderBy('name', 'asc');

        // Category is stored inside layout_definition_json — filter in PHP
        // for now; if performance becomes a concern a generated column can be
        // added to the table later.
        $results = $q->get();

        if ($category !== null) {
            $results = $results->filter(
                fn(NewsletterLayout $l) => $l->category === $category
            )->values();
        }

        return $results;
    }

    public function getActiveBySite(int $siteId, ?string $category = null): Collection
    {
        $results = $this->getAllBySite($siteId, $category);

        return $results->filter(
            fn(NewsletterLayout $l) => $l->is_active
        )->values();
    }

    public function find(int $id, array $relations = []): ?NewsletterLayout
    {
        return $this->query()->where('id', $id)->first();
    }

    public function findBySlug(string $slug, ?int $siteId = null): ?NewsletterLayout
    {
        return $this->query()
            ->where('slug', $slug)
            ->where('site_id', $siteId)
            ->first();
    }

    public function slugExistsForSite(string $slug, int $siteId, ?int $excludeId = null): bool
    {
        $q = $this->query()
            ->where('slug', $slug)
            ->where('site_id', $siteId);

        if ($excludeId !== null) {
            $q->where('id', '!=', $excludeId);
        }

        return $q->exists();
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Create a new email template layout row.
     * Caller passes a flat email-template payload; this method encodes it into
     * the layout_definition_json structure before persisting.
     */
    public function createEmailTemplate(array $data): Model
    {
        return NewsletterLayout::create([
            'site_id' => $data['site_id'],
            'type' => NewsletterLayout::TYPE_EMAIL_TEMPLATE,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'is_system_layout' => false,
            'created_by' => $data['created_by'] ?? null,
            'layout_definition_json' => $this->buildDefinition($data),
        ]);
    }

    /**
     * Update an existing email template layout row.
     * Only the fields present in $data are merged; omitted fields are preserved.
     */
    public function updateEmailTemplate(int $id, array $data): NewsletterLayout
    {
        $layout = $this->find($id);

        if (!$layout) {
            throw new \RuntimeException("Email template layout {$id} not found.");
        }

        $updates = [];

        if (isset($data['name'])) {
            $updates['name'] = $data['name'];
        }
        if (isset($data['slug'])) {
            $updates['slug'] = $data['slug'];
        }

        // Merge email-template-specific fields into layout_definition_json.
        $etFields = array_intersect_key($data, array_flip([
            'category', 'use_default_theme', 'theme_id',
            'description', 'is_active', 'blocks',
        ]));

        if (!empty($etFields)) {
            $updates['layout_definition_json'] = $layout->mergeEmailTemplateFields($etFields);
        }

        if (!empty($updates)) {
            foreach ($updates as $key => $value) {
                $layout->{$key} = $value;
            }
            $layout->save();
        }

        return $layout->fresh();
    }

    // ── Version delegation ────────────────────────────────────────────────────

    /**
     * Delegates to NewsletterLayoutRepository so version logic is not duplicated.
     */
    public function createVersion(
        int   $layoutId,
        int   $versionNumber,
        array $snapshot,
        ?int  $createdBy = null,
    ): \App\Models\Model
    {
        return $this->layoutRepository->createVersion(
            layoutId: $layoutId,
            layoutDefinition: $snapshot,
            versionNumber: $versionNumber,
            state: 'published',  // email template versions are always live
            migrationScriptReference: null,
        );
    }

    public function maxVersionNumber(int $layoutId): int
    {
        return $this->layoutRepository->nextVersionNumber($layoutId) - 1;
    }

    public function versionHistory(int $layoutId): Collection
    {
        return $this->layoutRepository->versionHistory($layoutId);
    }

    public function findVersion(int $versionId): ?Model
    {
        return $this->layoutRepository->findVersionById($versionId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a layout_definition_json payload from a flat email template data array.
     */
    private function buildDefinition(array $data): array
    {
        return [
            'schema_version' => 1,
            'regions' => [],
            'email_template' => [
                'category' => $data['category'] ?? 'transactional',
                'use_default_theme' => (bool)($data['use_default_theme'] ?? true),
                'theme_id' => $data['theme_id'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => (bool)($data['is_active'] ?? true),
                'blocks' => $data['blocks'] ?? [],
            ],
        ];
    }
}