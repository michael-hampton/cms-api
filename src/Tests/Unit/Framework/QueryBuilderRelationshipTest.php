<?php

namespace App\Tests\Unit\Framework;

use App\Framework\Database\QueryBuilder;
use App\Framework\Database\Relations\EagerLoader;
use App\Framework\Database\Relations\RelationHandlerFactory;
use App\Framework\Database\Relations\RelationshipAnalyzer;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class QueryBuilderRelationshipTest extends FunctionalTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->eagerLoader = new EagerLoader(new RelationshipAnalyzer(), new RelationHandlerFactory($this->database), $this->database);
    }

    /**
     * Test deeply nested whereHas with mixed AND/OR logic.
     * This ensures the fix for HY093 (parameter collisions) is working.
     */
    public function testDeeplyNestedMixedBooleanBindings(): void
    {
        $builder = new QueryBuilder('product_offer_bundles', $this->eagerLoader, $this->database);

        $builder->where('status', 'published')
            ->where(function ($q) {
                $q->whereHas('items', function ($itemQ) {
                    $itemQ->whereHas('product', function ($pQ) {
                        $pQ->where('name', 'LIKE', '%Gaming%');
                    })->orWhereHas('productOffer.product', function ($pQ) {
                        $pQ->where('name', 'LIKE', '%Gaming%');
                    });
                });
            });

        [$sql, $bindings] = $builder->toSql();

        // 1. Check SQL for parentheses around the OR EXISTS group
        $this->assertStringContainsString('AND (EXISTS', $sql, "User filters must be grouped to prevent syntax errors");
        $this->assertStringContainsString('OR EXISTS', $sql, "Internal OR logic must be preserved");

        // 2. Check for unique parameter naming
        $this->assertArrayHasKey('param_0', $bindings);
        // Ensure the sub-parameters from buildConditionsFromQuery exist and are unique
        $subParams = array_filter(array_keys($bindings), fn($k) => str_starts_with($k, 'sub_'));
        $this->assertCount(2, $subParams, "Should have 2 unique sub-parameters for the two LIKE checks");
    }

    /**
     * Test that the first condition in a subquery is always AND'd to the relationship join.
     */
    public function testSubqueryJoinPrefixing(): void
    {
        $builder = new QueryBuilder('product_offer_bundles', $this->eagerLoader, $this->database);

        // Using orWhereHas at the top level
        $builder->orWhereHas('items', function ($q) {
            $q->where('product_id', 1);
        });

        [$sql, $bindings] = $builder->toSql();

        // The subquery should look like: WHERE posts.user_id = users.id AND posts.title = ...
        // It should NOT look like: WHERE posts.user_id = users.id OR posts.title = ...
        $this->assertStringContainsString('WHERE `product_offer_bundle_items`.bundle_id = product_offer_bundles.id AND (product_offer_bundle_items.product_id', $sql);
    }

    /**
     * Test the LIMIT 1 placement.
     */
    public function testLimitPlacementInSubquery(): void
    {
        $builder = new QueryBuilder('categories', $this->eagerLoader, $this->database);

        $builder->whereHas('products', function ($q) {
            $q->where('price', '>', 100);
        });

        [$sql] = $builder->toSql();

        // LIMIT 1 must be after the WHERE condition
        $pattern = '/WHERE.*price.*LIMIT 1/i';
        $this->assertMatchesRegularExpression($pattern, $sql, "LIMIT 1 must appear at the end of the EXISTS subquery");
    }

    /**
     * Test whereHas with an array/In clause inside.
     */
    public function testWhereInInsideHas(): void
    {
        $builder = new QueryBuilder('orders', $this->eagerLoader, $this->database);

        $builder->whereHas('items', function ($q) {
            $q->whereIn('status', ['shipped', 'delivered']);
        });

        [$sql, $bindings] = $builder->toSql();

        $this->assertStringContainsString('IN (:in', $sql);
        $this->assertGreaterThanOrEqual(2, count($bindings));
    }

    /**
     * Test that dot notation resolves to nested EXISTS clauses.
     */
    public function testDotNotationRecursion(): void
    {
        $builder = new QueryBuilder('pages', $this->eagerLoader, $this->database);

        // This should produce two nested EXISTS clauses
        $builder->whereHas('pageAuthors.author', function ($q) {
            $q->where('name', 'OReilly');
        });

        [$sql] = $builder->toSql();

        $existsCount = substr_count($sql, 'EXISTS');
        $this->assertEquals(2, $existsCount, "Dot notation should resolve to two nested EXISTS clauses");
    }

    /**
     * Test orWhereHas and nested orWhereHas combinations.
     * This ensures that the "AND (" wrapping we added prevents logical bleeding.
     */
    public function testOrWhereHasNesting()
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->where('active', 1)
            ->orWhereHas('category', function ($q) {
                $q->where('name', 'Electronics');
            });

        [$sql, $bindings] = $builder->toSql();

        // Should produce: WHERE products.active = 1 OR EXISTS (...)
        $this->assertStringContainsString('or EXISTS', $sql);
        $this->assertStringContainsString('name', $sql);
    }

    /**
     * Test whereDoesntHave and orWhereDoesntHave.
     * This verifies the "NOT EXISTS" logic.
     */
    public function testNegativeRelationshipConstraints()
    {
        $builder = new QueryBuilder('orders', $this->eagerLoader, $this->database);

        $builder->whereDoesntHave('items')
            ->orWhereDoesntHave('user', function ($q) {
                $q->where('name', 'Mike');
            });

        [$sql] = $builder->toSql();

        // 1. Check for NOT EXISTS
        $this->assertStringContainsString('NOT EXISTS', $sql);
        // 2. Check for the OR link between the two negative constraints
        $this->assertStringContainsString(') or NOT EXISTS', $sql);
    }

    /**
     * Test withCount generation.
     * This ensures the SELECT (SELECT COUNT(*)...) subquery doesn't conflict with WHERE bindings.
     */
    public function testWithCountSubquery()
    {
        $builder = new QueryBuilder('merchants', $this->eagerLoader, $this->database);

        $builder->withCount('products')
            ->where('region', 'UK');

        [$sql, $bindings] = $builder->toSql();

        // Should produce: SELECT *, (SELECT COUNT(*) FROM products WHERE ...) AS products_count
        $this->assertStringContainsString('SELECT COUNT(*)', $sql);
        $this->assertStringContainsString('as products_count', $sql);
        $this->assertArrayHasKey('param_0', $bindings); // Region binding
    }

    /**
     * Test multiple LIKE and IN combinations.
     */
    public function testComplexFilters()
    {
        $builder = new QueryBuilder('items', $this->eagerLoader, $this->database);

        $builder->where('type', 'hardware')
            ->where(function ($q) {
                $q->where('sku', 'LIKE', 'PRO-%')
                    ->orWhereIn('vendor_id', [10, 20, 30]);
            });

        [$sql, $bindings] = $builder->toSql();

        $this->assertStringContainsString('sku LIKE', $sql);
        $this->assertStringContainsString('vendor_id IN ', $sql);
        $this->assertCount(5, $bindings); // type, sku, and 3 IDs
    }

    /**
     * Verify that whereIn correctly handles empty arrays by preventing IN ()
     */
    public function test_where_in_prevents_syntax_error_on_empty_array(): void
    {
        $builder = new QueryBuilder('pages', $this->eagerLoader, $this->database);

        // This should trigger the defensive 1=0 logic
        $builder->whereIn('status', []);

        [$sql, $bindings] = $builder->toSql();

        $this->assertStringContainsString('WHERE 1=0', $sql);
        $this->assertEmpty($bindings);
    }

    /**
     * Verify that whereIn uses pre-compiled fragments and named parameters
     */
    public function test_where_in_uses_named_parameters_and_fragments(): void
    {
        $builder = new QueryBuilder('pages', $this->eagerLoader, $this->database);
        $builder->whereIn('status', ['published', 'draft']);

        [$sql, $bindings] = $builder->toSql();

        // Check SQL structure
        $this->assertStringContainsString('status IN (:in_', $sql);

        // Check that bindings keys match the placeholders in SQL
        foreach ($bindings as $key => $value) {
            if (str_starts_with($key, 'in_')) {
                $this->assertStringContainsString(":{$key}", $sql);
            }
        }
        $this->assertCount(2, $bindings);
    }

    /**
     * Test relationship bindings (e.g., Tags/Authors) bubble up correctly.
     * This specifically tests the fix for the PageRepository calendar filters.
     */
    public function test_where_has_correctly_bubbles_up_nested_where_in_bindings(): void
    {
        $builder = new QueryBuilder('pages', $this->eagerLoader, $this->database);

        $builder->where('site_id', 1)
            ->whereHas('tags', function ($q) {
                $q->whereIn('id', [10, 20]);
            });

        [$sql, $bindings] = $builder->toSql();

        // 1. Verify Outer Binding
        $this->assertArrayHasKey('param_0', $bindings);
        $this->assertEquals(1, $bindings['param_0']);

        // 2. Verify Inner Bindings (the fix for test_search_calendar_pages_filters_by_tag_ids)
        $tagBindings = array_filter(array_keys($bindings), fn($k) => str_starts_with($k, 'in_'));

        $this->assertCount(2, $tagBindings, "Bindings from inside whereHas must be present in final array");
        foreach ($tagBindings as $key) {
            $this->assertStringContainsString(":{$key}", $sql, "SQL must contain the named placeholder for inner binding");
        }
    }

    /**
     * Test mixed Boolean logic (AND/OR) with whereHas to ensure grouping.
     */
    public function test_mixed_boolean_where_has_grouping(): void
    {
        $builder = new QueryBuilder('pages', $this->eagerLoader, $this->database);

        $builder->where('type', 'blog')
            ->where(function ($q) {
                $q->whereHas('authors', function ($authQ) {
                    $authQ->where('role', 'admin');
                })->orWhereHas('tags', function ($tagQ) {
                    $tagQ->where('slug', 'featured');
                });
            });

        [$sql, $bindings] = $builder->toSql();

        // Ensure the OR EXISTS is wrapped in parentheses relative to the type = blog
        $this->assertStringContainsString("AND (EXISTS", $sql);
        $this->assertStringContainsString("or EXISTS", $sql);
    }

    /**
     * Test whereDoesntHave logic (The NOT EXISTS check)
     */
    public function test_where_doesnt_have_generates_correct_sql(): void
    {
        $builder = new QueryBuilder('pages', $this->eagerLoader, $this->database);
        $builder->whereDoesntHave('pageAuthors');

        [$sql] = $builder->toSql();

        $this->assertStringContainsString('NOT EXISTS (SELECT 1 FROM `page_authors` WHERE', $sql);
    }
}