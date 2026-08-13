<?php

namespace App\Tests\Unit\Services\PublicContent\Routing;

use App\Services\PublicContent\Routing\PublicContentRouteOverrideResolver;
use App\Services\PublicContent\Routing\RouteOverrideBranchSelector;
use App\Services\PublicContent\Routing\RouteOverrideMerger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicContentRouteOverrideResolverTest extends TestCase
{
    public function test_selects_and_merges_matching_branch_onto_base(): void
    {
        $resolver = new PublicContentRouteOverrideResolver(
            new RouteOverrideBranchSelector(),
            new RouteOverrideMerger(),
        );

        $resolved = $resolver->resolve(
            [
                'title' => 'base',
                'other_routing_params' => [
                    ['name' => 'alpha', 'value' => '1'],
                ],
                'override_branches' => [
                    [
                        'language' => 'en',
                        'territory' => 'GB',
                        'subscriber_status' => 'subscriber',
                        'values' => [
                            'title' => 'sub-gb',
                            'other_routing_params' => [
                                ['name' => 'alpha', 'value' => 'replaced'],
                            ],
                        ],
                    ],
                ],
            ],
            'en',
            'GB',
            'subscriber',
        );

        self::assertSame('sub-gb', $resolved['title']);
        self::assertSame([
            ['name' => 'alpha', 'value' => 'replaced'],
        ], $resolved['other_routing_params']);
    }

    public function test_returns_base_when_request_cannot_match(): void
    {
        $resolver = new PublicContentRouteOverrideResolver(
            new RouteOverrideBranchSelector(),
            new RouteOverrideMerger(),
        );

        $resolved = $resolver->resolve(
            [
                'title' => 'base',
                'override_branches' => [
                    [
                        'language' => 'en',
                        'territory' => 'GB',
                        'values' => ['title' => 'gb'],
                    ],
                ],
            ],
            null,
            'GB',
            null,
        );

        self::assertSame(['title' => 'base'], $resolved);
    }

    public function test_duplicate_audiences_are_an_explicit_error(): void
    {
        $resolver = new PublicContentRouteOverrideResolver(
            new RouteOverrideBranchSelector(),
            new RouteOverrideMerger(),
        );

        $this->expectException(RuntimeException::class);

        $resolver->resolve(
            [
                'override_branches' => [
                    ['language' => 'en', 'territory' => 'GB', 'values' => ['title' => 'a']],
                    ['language' => 'en', 'territory' => 'GB', 'values' => ['title' => 'b']],
                ],
            ],
            'en',
            'GB',
            null,
        );
    }
}
