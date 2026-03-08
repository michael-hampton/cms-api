<?php

namespace App\Repositories\Newsletters;

use App\Enums\Newsletters\LayoutVersionState;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\NewsletterLayout;
use App\Models\NewsletterLayoutVersion;
use App\Repositories\Repository;

class NewsletterLayoutRepository extends Repository
{
    protected function getModelClass(): string
    {
        return NewsletterLayout::class;
    }

    public function findBySlug(string $slug): ?NewsletterLayout
    {
        return NewsletterLayout::where('slug', $slug)->first();
    }

    public function findBySlugForSite(string $slug, ?int $siteId = null): ?NewsletterLayout
    {
        return NewsletterLayout::where('slug', $slug)
            ->when(
                $siteId === null,
                fn($query) => $query->whereNull('site_id'),
                fn($query) => $query->where('site_id', $siteId)
            )
            ->first();
    }

    public function allSystemLayouts(): Collection
    {
        return NewsletterLayout::whereNull('site_id')
            ->where('is_system_layout', true)
            ->orderBy('name', 'asc')
            ->get();
    }

    public function allUserLayouts(int $siteId): Collection
    {
        return NewsletterLayout::where('site_id', $siteId)
            ->where('is_system_layout', false)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function allPublishedLayouts(int $siteId): Collection
    {
        $systemLayouts = $this->allSystemLayouts()->filter(
            fn(NewsletterLayout $l) => $l->latestPublishedVersion() !== null
        );

        $siteLayouts = $this->allUserLayouts($siteId)->filter(
            fn(NewsletterLayout $l) => $l->latestPublishedVersion() !== null
        );

        return $systemLayouts->merge($siteLayouts)->values();
    }

    public function createVersion(
        int     $layoutId,
        array   $layoutDefinition,
        int     $versionNumber,
        string  $state = 'draft',
        ?string $migrationScriptReference = null
    ): Model
    {
        return NewsletterLayoutVersion::create([
            'layout_id' => $layoutId,
            'version_number' => $versionNumber,
            'layout_definition_json' => $layoutDefinition,
            'migration_script_reference' => $migrationScriptReference,
            'state' => $state,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function findVersion(int $layoutId, int $versionNumber): ?NewsletterLayoutVersion
    {
        return NewsletterLayoutVersion::where('layout_id', $layoutId)
            ->where('version_number', $versionNumber)
            ->first();
    }

    public function findVersionById(int $versionId): ?Model
    {
        return NewsletterLayoutVersion::find($versionId);
    }

    public function updateVersionState(int $versionId, LayoutVersionState $state): bool
    {
        $version = NewsletterLayoutVersion::find($versionId);

        if (!$version) {
            return false;
        }

        $version->state = $state->value;
        return $version->save();
    }

    public function nextVersionNumber(int $layoutId): int
    {
        $latest = NewsletterLayoutVersion::where('layout_id', $layoutId)
            ->orderBy('version_number', 'desc')
            ->first();

        return $latest ? ($latest->version_number + 1) : 1;
    }

    public function versionHistory(int $layoutId): Collection
    {
        return NewsletterLayoutVersion::where('layout_id', $layoutId)
            ->orderBy('version_number', 'desc')
            ->get();
    }

    public function cloneLayout(int $sourceLayoutId, string $newName, string $newSlug, int $createdBy, int $siteId): NewsletterLayout
    {
        $source = NewsletterLayout::findOrFail($sourceLayoutId);
        $latestVersion = $source->latestPublishedVersion() ?? $source->latestVersion();

        $clone = NewsletterLayout::create([
            'site_id' => $siteId,   // ← NEW
            'name' => $newName,
            'slug' => $newSlug,
            'layout_definition_json' => $source->layout_definition_json,
            'is_system_layout' => false,
            'created_by' => $createdBy,
        ]);

        if ($latestVersion) {
            $this->createVersion(
                $clone->id,
                $latestVersion->layout_definition_json,
                1,
                LayoutVersionState::Draft->value
            );
        }

        return $clone;
    }
}