<?php

namespace App\Actions\PublicContent;

use App\DTO\PublicContent\PublicContentDocument;
use App\DTO\PublicContent\ResolvedGeo;
use App\DTO\PublicContent\ResolvedPublicContentPath;
use App\Models\Member;
use App\Services\PublicContent\CompositionDeadline;
use App\Services\PublicContent\Slugs\PublicContentPathResolver;

final class GetPublicContentByPathAction
{
    public function __construct(
        private readonly GetPublicContentAction $content,
        private readonly PublicContentPathResolver $paths
    ) {}

    public function execute(
        int $siteId,
        string $path,
        ?Member $member = null,
        ?string $territorySlug = null,
        ?ResolvedGeo $geo = null,
        ?CompositionDeadline $deadline = null,
    ): ?PublicContentDocument {
        foreach ($this->paths->resolveCandidates($siteId, $path) as $candidate) {
            $document = $this->content->execute(
                $siteId,
                $candidate->slug,
                $member,
                $territorySlug,
                $geo,
                $deadline,
            );

            if (!$document || !$this->documentMatchesPath($document, $candidate)) {
                continue;
            }

            return $this->withCanonicalPath($document, $candidate);
        }

        return null;
    }

    private function documentMatchesPath(PublicContentDocument $document, ResolvedPublicContentPath $path): bool
    {
        if ($path->pageType !== null && $document->type !== $path->pageType) {
            return false;
        }

        if ($path->categorySlug === null && $path->subcategorySlug === null) {
            return true;
        }

        $categorySlugs = array_map(
            static fn(array $category): string => (string) ($category['slug'] ?? ''),
            $document->taxonomy['categories'] ?? []
        );

        if ($path->categorySlug !== null && !in_array($path->categorySlug, $categorySlugs, true)) {
            return false;
        }

        if ($path->subcategorySlug !== null && !in_array($path->subcategorySlug, $categorySlugs, true)) {
            return false;
        }

        return true;
    }

    private function withCanonicalPath(PublicContentDocument $document, ResolvedPublicContentPath $path): PublicContentDocument
    {
        if ($path->matchedPattern === '{path}') {
            return $document;
        }

        $links = $document->links;
        $canonical = (string) ($links['canonical'] ?? '');
        $prefix = $this->canonicalPrefix($canonical, $document->slug);

        if ($prefix !== null) {
            $links['canonical'] = $prefix . $this->encodePath($path->path);
        }

        return new PublicContentDocument(
            id: $document->id,
            siteId: $document->siteId,
            slug: $document->slug,
            type: $document->type,
            title: $document->title,
            summary: $document->summary,
            seo: $document->seo,
            taxonomy: $document->taxonomy,
            regions: $document->regions,
            components: $document->components,
            authors: $document->authors,
            landingSections: $document->landingSections,
            links: $links,
            widgets: $document->widgets,
            access: $document->access,
            schemaVersion: $document->schemaVersion
        );
    }

    private function canonicalPrefix(string $canonical, string $slug): ?string
    {
        $encodedSlug = rawurlencode($slug);

        if (str_ends_with($canonical, '/' . $encodedSlug)) {
            return substr($canonical, 0, -strlen($encodedSlug));
        }

        return null;
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map(
            static fn(string $segment): string => rawurlencode($segment),
            array_values(array_filter(explode('/', trim($path, '/')), static fn(string $segment): bool => $segment !== ''))
        ));
    }
}
