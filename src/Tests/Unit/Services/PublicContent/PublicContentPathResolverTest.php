<?php

namespace App\Tests\Unit\Services\PublicContent;

use App\Services\PublicContent\Slugs\PublicContentPathResolver;
use PHPUnit\Framework\TestCase;

final class PublicContentPathResolverTest extends TestCase
{
    public function testResolvesFlatSlug(): void
    {
        $resolver = new PublicContentPathResolver();
        $candidates = $resolver->resolveCandidates(999999, 'about-us');

        self::assertSame('about-us', $candidates[0]->slug);
        self::assertNull($candidates[0]->categorySlug);
    }

    public function testResolvesNestedCategoryPath(): void
    {
        $resolver = new PublicContentPathResolver();
        $candidates = $resolver->resolveCandidates(999999, 'news/local/my-article');
        $candidate = null;

        foreach ($candidates as $item) {
            if ($item->matchedPattern === '{category}/{subcategory}/{slug}') {
                $candidate = $item;
                break;
            }
        }

        self::assertNotNull($candidate);
        self::assertSame('my-article', $candidate->slug);
        self::assertSame('news', $candidate->categorySlug);
        self::assertSame('local', $candidate->subcategorySlug);
    }
}
