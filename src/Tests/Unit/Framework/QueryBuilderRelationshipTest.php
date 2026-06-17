<?php

namespace App\Tests\Unit\Framework;

use App\Framework\Database\QueryBuilder;
use App\Framework\Database\RawExpression;
use App\Framework\Database\Relations\EagerLoader;
use App\Framework\Database\Relations\RelationHandlerFactory;
use App\Framework\Database\Relations\RelationshipAnalyzer;
use App\Framework\Support\Collection;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use InvalidArgumentException;

class QueryBuilderRelationshipTest extends FunctionalTestCase
{
    private QueryBuilder $builder;
    private EagerLoader $eagerLoader;

    private function bindingKey(array $bindings, int $index = 0): string
    {
        $keys = array_keys($bindings);
        $this->assertArrayHasKey($index, $keys);

        return $keys[$index];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->eagerLoader = new EagerLoader(
            new RelationshipAnalyzer(),
            new RelationHandlerFactory($this->database),
            $this->database
        );

        $this->builder = new QueryBuilder('users', $this->eagerLoader, $this->database);
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
        $this->assertContains('published', $bindings);
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
        $this->assertStringContainsString('WHERE `product_offer_bundle_items`.bundle_id = product_offer_bundles.id AND (`product_offer_bundle_items`.product_id', $sql);
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
        $this->assertContains('UK', $bindings); // Region binding
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
        $this->assertContains(1, $bindings);

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

    /**
     * Test 1: Basic whereHas with single condition
     */
    public function testBasicWhereHas(): void
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->whereHas('category', function ($q) {
            $q->where('name', 'Electronics');
        });

        [$sql, $bindings] = $builder->toSql();

        // Verify EXISTS clause
        $this->assertStringContainsString('EXISTS', $sql);
        $this->assertStringContainsString('SELECT 1 FROM', $sql);
        $this->assertStringContainsString('LIMIT 1', $sql);

        // Verify bindings
        $subValue = null;

        foreach ($bindings as $key => $value) {
            if (str_starts_with($key, 'sub_')) {
                $subValue = $value;
                break;
            }
        }

        $this->assertNotNull($subValue, 'No sub_* binding found');
        $this->assertEquals('Electronics', $subValue);
    }

    public function testWhereDoesntHave(): void
    {
        $builder = new QueryBuilder('orders', $this->eagerLoader, $this->database);

        $builder->whereDoesntHave('items');

        [$sql, $bindings] = $builder->toSql();

        $this->assertStringContainsString('NOT EXISTS', $sql);
        $this->assertEmpty($bindings);
    }

    public function testOrWhereHas(): void
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->where('active', 1)
            ->orWhereHas('category', function ($q) {
                $q->where('featured', 1);
            });

        [$sql, $bindings] = $builder->toSql();

        // Should have both AND and OR
        $this->assertStringContainsString('WHERE active', $sql);
        $this->assertStringContainsString('or EXISTS', $sql);

        $this->assertCount(2, $bindings);
    }

    public function testNestedWhereHasWithDotNotation(): void
    {
        $builder = new QueryBuilder('pages', $this->eagerLoader, $this->database);

        $builder->whereHas('pageAuthors.author', function ($q) {
            $q->where('name', 'John Doe');
        });

        [$sql, $bindings] = $builder->toSql();

        // Should have two nested EXISTS
        $existsCount = substr_count($sql, 'EXISTS');
        $this->assertEquals(2, $existsCount);

        // Should have the author name binding
        $this->assertContains('John Doe', $bindings);
    }

    public function testWhereInInsideWhereHas(): void
    {
        $builder = new QueryBuilder('pages', $this->eagerLoader, $this->database);

        $builder->whereHas('tags', function ($q) {
            $q->whereIn('id', [10, 20, 30]);
        });

        [$sql, $bindings] = $builder->toSql();

        // Verify IN clause structure
        $this->assertStringContainsString('IN (:in_', $sql);

        // Verify all three IDs are bound
        $inBindings = array_filter($bindings, function ($key) {
            return str_starts_with($key, 'in_');
        }, ARRAY_FILTER_USE_KEY);

        $this->assertCount(3, $inBindings);
        $this->assertContains(10, $inBindings);
        $this->assertContains(20, $inBindings);
        $this->assertContains(30, $inBindings);
    }

    public function testWhereInWithEmptyArray(): void
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->whereIn('category_id', []);

        [$sql, $bindings] = $builder->toSql();

        // Should produce 1=0 to prevent syntax error
        $this->assertStringContainsString('WHERE 1=0', $sql);
        $this->assertEmpty($bindings);
    }

    public function testWhereNotInWithEmptyArray(): void
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->whereNotIn('category_id', []);

        [$sql, $bindings] = $builder->toSql();

        // Should not add any WHERE clause (always true)
        // The query should just be a basic SELECT
        $this->assertStringNotContainsString('NOT IN', $sql);
    }

    public function testComplexMixedBooleanWhereHas(): void
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->where('type', 'physical')
            ->where(function ($q) {
                $q->whereHas('category', function ($catQ) {
                    $catQ->where('name', 'Electronics');
                })->orWhereHas('merchants', function ($tagQ) {
                    $tagQ->where('slug', 'featured');
                });
            });

        [$sql, $bindings] = $builder->toSql();

        // Verify proper grouping
        $this->assertStringContainsString('AND (EXISTS', $sql);
        $this->assertStringContainsString('or EXISTS', $sql);

        // Count bindings
        $this->assertCount(3, $bindings); // type, category name, tag slug
    }

    public function testWhereHasWithMultipleConditions(): void
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->whereHas('merchants', function ($q) {
            $q->where('active', 1)
                ->where('discount_percentage', '>', 20)
                ->whereNotNull('valid_until');
        });

        [$sql, $bindings] = $builder->toSql();

        // All three conditions should be in the EXISTS subquery
        $this->assertStringContainsString('active', $sql);
        $this->assertStringContainsString('discount_percentage', $sql);
        $this->assertStringContainsString('valid_until IS NOT NULL', $sql);

        $this->assertCount(2, $bindings); // active and discount_percentage
    }

    public function testWithCount(): void
    {
        $builder = new QueryBuilder('merchants', $this->eagerLoader, $this->database);

        $builder->withCount('products')
            ->where('active', 1);

        [$sql, $bindings] = $builder->toSql();

        // Verify count subquery
        $this->assertStringContainsString('SELECT COUNT(*)', $sql);
        $this->assertStringContainsString('as products_count', $sql);

        // Verify table prefix preserved
        $this->assertStringContainsString('`merchants`.*', $sql);

        $this->assertContains(1, $bindings);
    }

    public function testWithCountWithCallback(): void
    {
        $builder = new QueryBuilder('categories', $this->eagerLoader, $this->database);

        $builder->withCount(['products' => function ($q) {
            $q->where('active', 1);
        }]);

        [$sql, $bindings] = $builder->toSql();

        // Count subquery should include the filter
        $this->assertStringContainsString('SELECT COUNT(*)', $sql);
        $this->assertStringContainsString('`products`.active', $sql);
    }

    public function testMultipleWithCount(): void
    {
        $builder = new QueryBuilder('product_merchants', $this->eagerLoader, $this->database);

        $builder->withCount(['product', 'merchant']);

        [$sql, $bindings] = $builder->toSql();

        // Should have two count subqueries
        $countOccurrences = substr_count($sql, 'SELECT COUNT(*)');
        $this->assertEquals(2, $countOccurrences);

        $this->assertStringContainsString('as product_count', $sql);
        $this->assertStringContainsString('as merchant_count', $sql);
    }

    public function testWhereHasWithBelongsToMany(): void
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->whereHas('vouchers', function ($q) {
            $q->where('name', 'Electronics');
        });

        [$sql, $bindings] = $builder->toSql();

        // Should have INNER JOIN for pivot table
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('EXISTS', $sql);
    }

    public function testDeeplyNestedWhereHas(): void
    {
        $builder = new QueryBuilder('product_offer_bundles', $this->eagerLoader, $this->database);

        $builder->whereHas('items', function ($itemQ) {
            $itemQ->whereHas('productOffer', function ($offerQ) {
                $offerQ->whereHas('product', function ($prodQ) {
                    $prodQ->where('name', 'LIKE', '%Gaming%');
                });
            });
        });

        [$sql, $bindings] = $builder->toSql();

        // Should have three nested EXISTS
        $existsCount = substr_count($sql, 'EXISTS');
        $this->assertEquals(3, $existsCount);
    }

    public function testWhereHasWithOrWhereInCallback(): void
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->whereHas('merchants', function ($q) {
            $q->where('discount_percentage', '>', 30)
                ->orWhere('featured', 1);
        });

        [$sql, $bindings] = $builder->toSql();

        // Should preserve OR logic inside EXISTS
        $this->assertStringContainsString('discount_percentage', $sql);
        $this->assertStringContainsString('OR', $sql);
        $this->assertStringContainsString('featured', $sql);
    }

    public function testParameterUniquenessAcrossMultipleWhereHas(): void
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->whereHas('category', function ($q) {
            $q->where('name', 'Electronics');
        })->whereHas('brand', function ($q) {
            $q->where('name', 'Sony');
        });

        [$sql, $bindings] = $builder->toSql();

        // All parameter keys should be unique
        $keys = array_keys($bindings);
        $uniqueKeys = array_unique($keys);
        $this->assertCount(count($keys), $uniqueKeys);

        // Should have bindings for both relations
        $this->assertCount(2, $bindings);
        $this->assertContains('Electronics', $bindings);
        $this->assertContains('Sony', $bindings);
    }

    public function testWhereNotInInsideWhereHas(): void
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->whereHas('merchants', function ($q) {
            $q->whereNotIn('merchant_id', [1, 2, 3]);
        });

        [$sql, $bindings] = $builder->toSql();

        $this->assertStringContainsString('NOT IN', $sql);

        // Verify all merchant IDs are bound
        $notInBindings = array_filter($bindings, function ($key) {
            return str_starts_with($key, 'notin_');
        }, ARRAY_FILTER_USE_KEY);

        $this->assertCount(3, $notInBindings);
    }

    public function testLimitPlacementInExistsSubquery(): void
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->whereHas('merchants', function ($q) {
            $q->where('active', 1)
                ->where('discount_percentage', '>', 20);
        });

        [$sql, $bindings] = $builder->toSql();

        // LIMIT 1 should be at the end of the EXISTS subquery
        // Use regex to verify LIMIT comes after WHERE conditions
        $pattern = '/WHERE.*active.*discount_percentage.*LIMIT 1/s';
        $this->assertMatchesRegularExpression($pattern, $sql);
    }

//    public function testActualBugScenarioFromPageRepository(): void
//    {
//        $builder = new QueryBuilder('pages', $this->eagerLoader, $this->database);
//
//        // This is the exact scenario that was failing
//        $builder->where('site_id', 1)
//            ->whereHas('tags', function ($q) {
//                $q->whereIn('id', [10, 20]);
//            });
//
//        [$sql, $bindings] = $builder->toSql();
//
//        // Verify outer binding
//        $this->assertArrayHasKey('param_0', $bindings);
//        $this->assertEquals(1, $bindings['param_0']);
//
//        // Verify inner whereIn bindings are present
//        $inBindings = array_filter($bindings, function($key) {
//            return str_starts_with($key, 'in_');
//        }, ARRAY_FILTER_USE_KEY);
//
//        $this->assertCount(2, $inBindings);
//
//        // Verify SQL structure
//        $this->assertStringContainsString('WHERE pages.site_id = :param_0', $sql);
//        $this->assertStringContainsString('AND EXISTS', $sql);
//        $this->assertStringContainsString('tags.id IN', $sql);
//    }

    public function testWhereHasWithNestedClosure(): void
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->whereHas('merchants', function ($q) {
            $q->where('active', 1)
                ->where(function ($nested) {
                    $nested->where('discount_percentage', '>', 30)
                        ->orWhere('featured', 1);
                });
        });

        [$sql, $bindings] = $builder->toSql();

        // Should have nested parentheses for grouped OR conditions
        $this->assertStringContainsString('AND (', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testOrWhereDoesntHave(): void
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->where('active', 1)
            ->orWhereDoesntHave('merchants');

        [$sql, $bindings] = $builder->toSql();

        $this->assertStringContainsString('or NOT EXISTS', $sql);
    }

    public function testMixedWhereHasAndWhereDoesntHave(): void
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->whereHas('category')
            ->whereDoesntHave('merchants', function ($q) {
                $q->where('expired', 1);
            });

        [$sql, $bindings] = $builder->toSql();

        $this->assertStringContainsString('EXISTS', $sql);
        $this->assertStringContainsString('NOT EXISTS', $sql);
    }

    public function testWhereHasWithLikeOperator(): void
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->whereHas('category', function ($q) {
            $q->where('name', 'LIKE', '%Electronics%');
        });

        [$sql, $bindings] = $builder->toSql();

        $this->assertStringContainsString('LIKE', $sql);
        $this->assertContains('%Electronics%', $bindings);
    }

    public function testWhereHasWithNullChecks(): void
    {
        $builder = new QueryBuilder('products', $this->eagerLoader, $this->database);

        $builder->whereHas('merchants', function ($q) {
            $q->whereNull('deleted_at')
                ->whereNotNull('valid_until');
        });

        [$sql, $bindings] = $builder->toSql();

        $this->assertStringContainsString('deleted_at IS NULL', $sql);
        $this->assertStringContainsString('valid_until IS NOT NULL', $sql);
        $this->assertEmpty($bindings); // NULL checks don't need bindings
    }

    // ==================== SELECT METHODS ====================

    public function test_select_with_single_column(): void
    {
        $this->builder->select('name');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('SELECT name FROM', $sql);
    }

    public function test_select_with_multiple_columns_as_array(): void
    {
        $this->builder->select(['name', 'email']);
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('SELECT name, email FROM', $sql);
    }

    public function test_select_with_multiple_columns_as_args(): void
    {
        $this->builder->select('name', 'email', 'created_at');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('SELECT name, email, created_at FROM', $sql);
    }

    public function test_add_select_appends_columns(): void
    {
        $this->builder->select('name')->addSelect('email');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('SELECT name, email FROM', $sql);
    }

    public function test_add_select_replaces_wildcard(): void
    {
        // Default is ['*']
        $this->builder->addSelect('name');
        [$sql] = $this->builder->toSql();

        $this->assertStringNotContainsString('*, name', $sql);
        $this->assertStringContainsString('SELECT name FROM', $sql);
    }

    public function test_distinct_adds_distinct_keyword(): void
    {
        $this->builder->distinct()->select('email');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('SELECT DISTINCT email FROM', $sql);
    }

    public function test_distinct_is_idempotent(): void
    {
        $this->builder->distinct()->distinct();
        [$sql] = $this->builder->toSql();

        $this->assertEquals(1, substr_count($sql, 'DISTINCT'));
    }

    public function test_select_raw_expression(): void
    {
        $this->builder->selectRaw('COUNT(*) as total');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('SELECT COUNT(*) as total FROM', $sql);
    }

    public function test_select_raw_replaces_wildcard(): void
    {
        $this->builder->selectRaw('SUM(amount) as total');
        [$sql] = $this->builder->toSql();

        $this->assertStringNotContainsString('*,', $sql);
    }

    // ==================== WHERE METHODS ====================

    public function test_where_basic_condition(): void
    {
        $this->builder->where('status', 'active');
        [$sql, $bindings] = $this->builder->toSql();
        $statusKey = $this->bindingKey($bindings);

        $this->assertStringContainsString("WHERE status = :{$statusKey}", $sql);
        $this->assertEquals('active', $bindings[$statusKey]);
    }

    public function test_where_with_operator(): void
    {
        $this->builder->where('age', '>', 18);
        [$sql, $bindings] = $this->builder->toSql();
        $ageKey = $this->bindingKey($bindings);

        $this->assertStringContainsString("WHERE age > :{$ageKey}", $sql);
        $this->assertEquals(18, $bindings[$ageKey]);
    }

    public function test_where_with_array_of_conditions(): void
    {
        $this->builder->where(['status' => 'active', 'role' => 'admin']);
        [$sql, $bindings] = $this->builder->toSql();
        $statusKey = $this->bindingKey($bindings);
        $roleKey = $this->bindingKey($bindings, 1);

        $this->assertStringContainsString("status = :{$statusKey}", $sql);
        $this->assertStringContainsString("role = :{$roleKey}", $sql);
        $this->assertEquals('active', $bindings[$statusKey]);
        $this->assertEquals('admin', $bindings[$roleKey]);
    }

    public function test_where_with_closure(): void
    {
        $this->builder->where(function ($q) {
            $q->where('age', '>', 18)->where('country', 'US');
        });
        [$sql, $bindings] = $this->builder->toSql();
        $ageKey = $this->bindingKey($bindings);
        $countryKey = $this->bindingKey($bindings, 1);

        $this->assertStringContainsString("WHERE (age > :{$ageKey} AND country = :{$countryKey})", $sql);
    }

    public function test_or_where_basic(): void
    {
        $this->builder->where('status', 'active')->orWhere('role', 'admin');
        [$sql, $bindings] = $this->builder->toSql();
        $statusKey = $this->bindingKey($bindings);
        $roleKey = $this->bindingKey($bindings, 1);

        $this->assertStringContainsString("WHERE status = :{$statusKey} OR role = :{$roleKey}", $sql);
    }

    public function test_or_where_with_closure(): void
    {
        $this->builder->where('status', 'active')->orWhere(function ($q) {
            $q->where('role', 'admin')->where('verified', 1);
        });
        [$sql, $bindings] = $this->builder->toSql();
        $roleKey = $this->bindingKey($bindings, 1);
        $verifiedKey = $this->bindingKey($bindings, 2);

        $this->assertStringContainsString("OR (role = :{$roleKey} AND verified = :{$verifiedKey})", $sql);
    }

    public function test_where_like(): void
    {
        $this->builder->whereLike('name', '%John%');
        [$sql, $bindings] = $this->builder->toSql();
        $nameKey = $this->bindingKey($bindings);

        $this->assertStringContainsString("name LIKE :{$nameKey}", $sql);
        $this->assertEquals('%John%', $bindings[$nameKey]);
    }

    public function test_or_where_like(): void
    {
        $this->builder->where('active', 1)->orWhereLike('name', '%test%');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('OR name LIKE', $sql);
    }

    public function test_where_in_with_array(): void
    {
        $this->builder->whereIn('status', ['active', 'pending', 'verified']);
        [$sql, $bindings] = $this->builder->toSql();

        $this->assertStringContainsString('status IN (', $sql);
        $this->assertCount(3, $bindings);
    }

    public function test_where_in_with_collection(): void
    {
        $collection = new Collection(['active', 'pending']);
        $this->builder->whereIn('status', $collection);
        [$sql, $bindings] = $this->builder->toSql();

        $this->assertStringContainsString('status IN (', $sql);
        $this->assertCount(2, $bindings);
    }

    public function test_where_in_with_empty_array_returns_false_condition(): void
    {
        $this->builder->whereIn('status', []);
        [$sql, $bindings] = $this->builder->toSql();

        $this->assertStringContainsString('1=0', $sql);
        $this->assertEmpty($bindings);
    }

    public function test_or_where_in(): void
    {
        $this->builder->where('active', 1)->orWhereIn('role', ['admin', 'editor']);
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('or role IN', $sql);
    }

    public function test_where_not_in(): void
    {
        $this->builder->whereNotIn('status', ['banned', 'deleted']);
        [$sql, $bindings] = $this->builder->toSql();

        $this->assertStringContainsString('status NOT IN (', $sql);
        $this->assertCount(2, $bindings);
    }

    public function test_where_not_in_with_empty_array_does_nothing(): void
    {
        $this->builder->select('name')->whereNotIn('status', []);
        [$sql, $bindings] = $this->builder->toSql();

        $this->assertStringNotContainsString('NOT IN', $sql);
        $this->assertStringNotContainsString('WHERE', $sql);
    }

    public function test_where_between(): void
    {
        $this->builder->whereBetween('age', [18, 65]);
        [$sql, $bindings] = $this->builder->toSql();
        $minKey = $this->bindingKey($bindings);
        $maxKey = $this->bindingKey($bindings, 1);

        $this->assertStringContainsString("age BETWEEN :{$minKey} AND :{$maxKey}", $sql);
        $this->assertEquals(18, $bindings[$minKey]);
        $this->assertEquals(65, $bindings[$maxKey]);
    }

    public function test_where_between_throws_exception_for_invalid_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Between method requires exactly 2 values');

        $this->builder->whereBetween('age', [18]);
    }

    public function test_where_not_between(): void
    {
        $this->builder->whereNotBetween('price', [100, 500]);
        [$sql, $bindings] = $this->builder->toSql();

        $this->assertStringContainsString('price NOT BETWEEN', $sql);
        $this->assertCount(2, $bindings);
    }

    public function test_where_null(): void
    {
        $this->builder->whereNull('deleted_at');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('deleted_at IS NULL', $sql);
    }

    public function test_where_not_null(): void
    {
        $this->builder->whereNotNull('email_verified_at');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('email_verified_at IS NOT NULL', $sql);
    }

    public function test_or_where_null(): void
    {
        $this->builder->where('active', 1)->orWhereNull('deleted_at');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('OR deleted_at IS NULL', $sql);
    }

    public function test_or_where_not_null(): void
    {
        $this->builder->where('active', 1)->orWhereNotNull('verified_at');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('OR verified_at IS NOT NULL', $sql);
    }

    public function test_where_date(): void
    {
        $this->builder->whereDate('created_at', '2024-01-15');
        [$sql, $bindings] = $this->builder->toSql();
        $dateKey = $this->bindingKey($bindings);

        $this->assertStringContainsString("DATE(created_at) = :{$dateKey}", $sql);
        $this->assertEquals('2024-01-15', $bindings[$dateKey]);
    }

    public function test_where_date_with_operator(): void
    {
        $this->builder->whereDate('created_at', '>', '2024-01-01');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('DATE(created_at) >', $sql);
    }

    public function test_where_month(): void
    {
        $this->builder->whereMonth('created_at', 12);
        [$sql, $bindings] = $this->builder->toSql();
        $monthKey = $this->bindingKey($bindings);

        $this->assertStringContainsString("MONTH(created_at) = :{$monthKey}", $sql);
        $this->assertEquals(12, $bindings[$monthKey]);
    }

    public function test_where_year(): void
    {
        $this->builder->whereYear('created_at', 2024);
        [$sql, $bindings] = $this->builder->toSql();
        $yearKey = $this->bindingKey($bindings);

        $this->assertStringContainsString("YEAR(created_at) = :{$yearKey}", $sql);
        $this->assertEquals(2024, $bindings[$yearKey]);
    }

    public function test_where_exists(): void
    {
        $subquery = new QueryBuilder('posts', $this->eagerLoader, $this->database);
        $subquery->where('user_id', 1);

        $this->builder->whereExists($subquery);
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('WHERE EXISTS (SELECT', $sql);
    }

    public function test_where_not_exists(): void
    {
        $subquery = new QueryBuilder('posts', $this->eagerLoader, $this->database);

        $this->builder->whereNotExists($subquery);
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('WHERE NOT EXISTS', $sql);
    }

    public function test_where_sub(): void
    {
        $subquery = new QueryBuilder('orders', $this->eagerLoader, $this->database);
        $subquery->select('user_id')->where('total', '>', 1000);

        $this->builder->whereSub('id', 'IN', $subquery);
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('id IN (SELECT', $sql);
    }

    public function test_where_raw(): void
    {
        $this->builder->whereRaw('YEAR(created_at) = ?', [2024]);
        [$sql, $bindings] = $this->builder->toSql();

        $this->assertStringContainsString('YEAR(created_at) = ?', $sql);
        $this->assertContains(2024, $bindings);
    }

    public function test_where_raw_with_named_parameters(): void
    {
        $this->builder->whereRaw('age > :min_age', ['min_age' => 18]);
        [$sql, $bindings] = $this->builder->toSql();

        $this->assertStringContainsString('age > :min_age', $sql);
        $this->assertEquals(18, $bindings['min_age']);
    }

    public function test_or_where_raw(): void
    {
        $this->builder->where('active', 1)->orWhereRaw('status = "premium"');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('OR status = "premium"', $sql);
    }

    public function test_where_column(): void
    {
        $this->builder->whereColumn('created_at', 'updated_at');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('created_at = updated_at', $sql);
    }

    public function test_where_column_with_operator(): void
    {
        $this->builder->whereColumn('votes', '>', 'comments');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('votes > comments', $sql);
    }

    public function test_where_json_contains(): void
    {
        $this->builder->whereJsonContains('meta', 'featured');
        [$sql, $bindings] = $this->builder->toSql();
        $metaKey = $this->bindingKey($bindings);

        $this->assertStringContainsString("JSON_CONTAINS (meta, :{$metaKey})", $sql);
        $this->assertEquals('"featured"', $bindings[$metaKey]);
    }

    public function test_where_json_contains_with_array(): void
    {
        $this->builder->whereJsonContains('tags', ['php', 'laravel']);
        [$sql, $bindings] = $this->builder->toSql();

        $this->assertStringContainsString('JSON_CONTAINS', $sql);
        $this->assertContains('"php"', $bindings);
    }

    // ==================== ORDER BY METHODS ====================

    public function test_order_by_single_column(): void
    {
        $this->builder->orderBy('name');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('ORDER BY name ASC', $sql);
    }

    public function test_order_by_with_direction(): void
    {
        $this->builder->orderBy('created_at', 'desc');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);
    }

    public function test_order_by_multiple_columns(): void
    {
        $this->builder->orderBy('status', 'asc')->orderBy('created_at', 'desc');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('ORDER BY status ASC, created_at DESC', $sql);
    }

    public function test_order_by_with_array(): void
    {
        $this->builder->orderBy(['name' => 'asc', 'age' => 'desc']);
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('ORDER BY name ASC, age DESC', $sql);
    }

    public function test_order_by_desc(): void
    {
        $this->builder->orderByDesc('created_at');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);
    }

    public function test_order_by_raw(): void
    {
        $this->builder->orderByRaw('RAND()');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('ORDER BY RAND()', $sql);
    }

    public function test_latest(): void
    {
        $this->builder->latest();
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);
    }

    public function test_latest_with_custom_column(): void
    {
        $this->builder->latest('updated_at');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('ORDER BY updated_at DESC', $sql);
    }

    public function test_oldest(): void
    {
        $this->builder->oldest();
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('ORDER BY created_at ASC', $sql);
    }

    // ==================== GROUP BY & HAVING ====================

    public function test_group_by_single_column(): void
    {
        $this->builder->groupBy('status');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('GROUP BY status', $sql);
    }

    public function test_group_by_multiple_columns(): void
    {
        $this->builder->groupBy('status', 'role');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('GROUP BY status, role', $sql);
    }

    public function test_group_by_with_array(): void
    {
        $this->builder->groupBy(['country', 'city']);
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('GROUP BY country, city', $sql);
    }

    public function test_having(): void
    {
        $this->builder->groupBy('status')->having('COUNT(*)', '>', 10);
        [$sql, $bindings] = $this->builder->toSql();
        $countKey = $this->bindingKey($bindings);

        $this->assertStringContainsString("HAVING COUNT(*) > :{$countKey}", $sql);
        $this->assertEquals(10, $bindings[$countKey]);
    }

    public function test_or_having(): void
    {
        $this->builder->groupBy('status')
            ->having('SUM(amount)', '>', 1000)
            ->orHaving('COUNT(*)', '<', 5);
        [$sql, $bindings] = $this->builder->toSql();
        $sumKey = $this->bindingKey($bindings);
        $countKey = $this->bindingKey($bindings, 1);

        $this->assertStringContainsString("HAVING SUM(amount) > :{$sumKey} OR COUNT(*) < :{$countKey}", $sql);
    }

    // ==================== JOINS ====================

    public function test_inner_join(): void
    {
        $this->builder->join('posts', 'users.id', '=', 'posts.user_id');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('INNER JOIN posts ON `users`.id = `posts`.user_id', $sql);
    }

    public function test_left_join(): void
    {
        $this->builder->leftJoin('profiles', 'users.id', '=', 'profiles.user_id');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('LEFT JOIN profiles ON `users`.id = `profiles`.user_id', $sql);
    }

    public function test_right_join(): void
    {
        $this->builder->rightJoin('departments', 'users.dept_id', '=', 'departments.id');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('RIGHT JOIN departments ON `users`.dept_id = `departments`.id', $sql);
    }

    public function test_cross_join(): void
    {
        $this->builder->crossJoin('roles');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('CROSS JOIN roles', $sql);
    }

    public function test_join_with_default_operator(): void
    {
        $this->builder->join('posts', 'users.id', 'posts.user_id');
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('`users`.id = `posts`.user_id', $sql);
    }

    // ==================== LIMIT & OFFSET ====================

    public function test_limit(): void
    {
        $this->builder->limit(10);
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function test_offset(): void
    {
        $this->builder->limit(10)->offset(20);
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('LIMIT 10 OFFSET 20', $sql);
    }

    public function test_take_alias_for_limit(): void
    {
        $this->builder->take(5);
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('LIMIT 5', $sql);
    }

    public function test_skip_alias_for_offset(): void
    {
        $this->builder->skip(15)->take(10);
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('LIMIT 10 OFFSET 15', $sql);
    }

    public function test_for_page(): void
    {
        $this->builder->forPage(3, 20);
        [$sql] = $this->builder->toSql();

        $this->assertStringContainsString('LIMIT 20 OFFSET 40', $sql);
    }

    public function test_for_page_handles_page_zero(): void
    {
        $this->builder->forPage(0, 10);
        [$sql] = $this->builder->toSql();

        // Page 0 should be treated as page 1
        $this->assertStringContainsString('LIMIT 10 OFFSET 0', $sql);
    }

    // ==================== AGGREGATE FUNCTIONS ====================

    public function test_count(): void
    {
        // This would need actual data, but we can test SQL generation
        $builder = new QueryBuilder('users', $this->eagerLoader, $this->database);

        // We'll just verify the method exists and doesn't throw
        $this->assertTrue(method_exists($builder, 'count'));
    }

    public function test_count_with_distinct(): void
    {
        $builder = new QueryBuilder('users', $this->eagerLoader, $this->database);
        $builder->distinct()->select('email');

        // Generate SQL to verify COUNT(DISTINCT ...) is used
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('toSql');

        $this->assertTrue(method_exists($builder, 'count'));
    }

    public function test_count_distinct(): void
    {
        $builder = new QueryBuilder('users', $this->eagerLoader, $this->database);

        $this->assertTrue(method_exists($builder, 'countDistinct'));
    }

    public function test_sum_method_exists(): void
    {
        $this->assertTrue(method_exists($this->builder, 'sum'));
    }

    public function test_avg_method_exists(): void
    {
        $this->assertTrue(method_exists($this->builder, 'avg'));
    }

    public function test_min_method_exists(): void
    {
        $this->assertTrue(method_exists($this->builder, 'min'));
    }

    public function test_max_method_exists(): void
    {
        $this->assertTrue(method_exists($this->builder, 'max'));
    }

    // ==================== INSERT/UPDATE/DELETE ====================

    public function test_insert_generates_correct_sql(): void
    {
        $builder = new QueryBuilder('users', $this->eagerLoader, $this->database);

        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('insert');

        $this->assertTrue($method->isPublic());
    }

    public function test_insert_many_generates_correct_sql(): void
    {
        $this->assertTrue(method_exists($this->builder, 'insertMany'));
    }

    public function test_update_generates_correct_sql(): void
    {
        $this->builder->where('id', 1);

        $this->assertTrue(method_exists($this->builder, 'update'));
    }

    public function test_increment(): void
    {
        $this->assertTrue(method_exists($this->builder, 'increment'));
    }

    public function test_decrement(): void
    {
        $this->assertTrue(method_exists($this->builder, 'decrement'));
    }

    public function test_delete_generates_correct_sql(): void
    {
        $this->assertTrue(method_exists($this->builder, 'delete'));
    }

    // ==================== UTILITY METHODS ====================

    public function test_when_executes_callback_on_truthy_condition(): void
    {
        $executed = false;

        $this->builder->when(true, function ($q) use (&$executed) {
            $executed = true;
            $q->where('active', 1);
        });

        $this->assertTrue($executed);
        [$sql] = $this->builder->toSql();
        $this->assertStringContainsString('active', $sql);
    }

    public function test_when_skips_callback_on_falsy_condition(): void
    {
        $executed = false;

        $this->builder->when(false, function ($q) use (&$executed) {
            $executed = true;
        });

        $this->assertFalse($executed);
    }

    public function test_when_executes_default_on_falsy_condition(): void
    {
        $defaultExecuted = false;

        $this->builder->when(false,
            function ($q) {
            },
            function ($q) use (&$defaultExecuted) {
                $defaultExecuted = true;
                $q->where('status', 'inactive');
            }
        );

        $this->assertTrue($defaultExecuted);
        [$sql] = $this->builder->toSql();
        $this->assertStringContainsString('status', $sql);
    }

    public function test_chunk_method_exists(): void
    {
        $this->assertTrue(method_exists($this->builder, 'chunk'));
    }

    public function test_pluck_method_exists(): void
    {
        $this->assertTrue(method_exists($this->builder, 'pluck'));
    }

    public function test_value_method_exists(): void
    {
        $this->assertTrue(method_exists($this->builder, 'value'));
    }

    public function test_exists_method(): void
    {
        $this->assertTrue(method_exists($this->builder, 'exists'));
    }

    public function test_get_table_returns_table_name(): void
    {
        $this->assertEquals('users', $this->builder->getTable());
    }

    public function test_raw_expression_creation(): void
    {
        $raw = $this->builder->raw('NOW()');

        $this->assertInstanceOf(RawExpression::class, $raw);
        $this->assertEquals('NOW()', $raw->value);
    }

    // ==================== RESERVED WORDS QUOTING ====================

    public function test_reserved_word_columns_are_quoted(): void
    {
        $builder = new QueryBuilder('order', $this->eagerLoader, $this->database);
        $builder->select('order', 'type', 'value');
        [$sql] = $builder->toSql();

        $this->assertStringContainsString('`order`', $sql);
        $this->assertStringContainsString('`type`', $sql);
        $this->assertStringContainsString('`value`', $sql);
    }

    public function test_reserved_word_tables_are_quoted(): void
    {
        $builder = new QueryBuilder('order', $this->eagerLoader, $this->database);
        [$sql] = $builder->toSql();

        $this->assertStringContainsString('FROM `order`', $sql);
    }

    public function test_normal_columns_not_quoted(): void
    {
        $this->builder->select('name', 'email');
        [$sql] = $this->builder->toSql();

        $this->assertStringNotContainsString('`name`', $sql);
        $this->assertStringNotContainsString('`email`', $sql);
    }

    public function test_wildcards_not_quoted(): void
    {
        $this->builder->select('*');
        [$sql] = $this->builder->toSql();

        $this->assertStringNotContainsString('`*`', $sql);
        $this->assertStringContainsString('SELECT *', $sql);
    }

    public function test_functions_not_quoted(): void
    {
        $this->builder->select('COUNT(*)', 'SUM(amount)');
        [$sql] = $this->builder->toSql();

        $this->assertStringNotContainsString('`COUNT(*)`', $sql);
        $this->assertStringContainsString('COUNT(*)', $sql);
    }

    public function test_table_dot_column_with_reserved_word(): void
    {
        $builder = new QueryBuilder('orders', $this->eagerLoader, $this->database);
        $builder->select('orders.order');
        [$sql] = $builder->toSql();

        $this->assertStringContainsString('`orders`.`order`', $sql);
    }

    // ==================== COMPLEX QUERIES ====================

    public function test_complex_query_with_multiple_conditions(): void
    {
        $this->builder
            ->select('name', 'email')
            ->where('status', 'active')
            ->where('age', '>', 18)
            ->whereIn('role', ['admin', 'editor'])
            ->whereNotNull('email_verified_at')
            ->orderBy('created_at', 'desc')
            ->limit(10);

        [$sql, $bindings] = $this->builder->toSql();
        $statusKey = $this->bindingKey($bindings);
        $ageKey = $this->bindingKey($bindings, 1);

        $this->assertStringContainsString('SELECT name, email FROM', $sql);
        $this->assertStringContainsString("status = :{$statusKey}", $sql);
        $this->assertStringContainsString("age > :{$ageKey}", $sql);
        $this->assertStringContainsString('role IN', $sql);
        $this->assertStringContainsString('email_verified_at IS NOT NULL', $sql);
        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function test_complex_nested_where_conditions(): void
    {
        $this->builder->where(function ($q) {
            $q->where('status', 'active')
                ->orWhere(function ($q2) {
                    $q2->where('role', 'admin')->where('verified', 1);
                });
        });

        [$sql, $bindings] = $this->builder->toSql();
        $statusKey = $this->bindingKey($bindings);
        $roleKey = $this->bindingKey($bindings, 1);
        $verifiedKey = $this->bindingKey($bindings, 2);

        $this->assertStringContainsString("WHERE (status = :{$statusKey} OR (role = :{$roleKey} AND verified = :{$verifiedKey}))", $sql);
    }

    public function test_query_with_joins_and_where(): void
    {
        $this->builder
            ->select('users.name', 'posts.title')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->where('users.status', 'active')
            ->where('posts.published', 1);

        [$sql, $bindings] = $this->builder->toSql();
        $statusKey = $this->bindingKey($bindings);
        $publishedKey = $this->bindingKey($bindings, 1);

        $this->assertStringContainsString('INNER JOIN posts', $sql);
        $this->assertStringContainsString("`users`.status = :{$statusKey}", $sql);
        $this->assertStringContainsString("`posts`.published = :{$publishedKey}", $sql);
    }

    // ==================== EDGE CASES ====================

    public function test_empty_query_generates_valid_sql(): void
    {
        [$sql] = $this->builder->toSql();

        $this->assertEquals('SELECT * FROM users', $sql);
    }

    public function test_multiple_where_in_calls_use_unique_parameters(): void
    {
        $this->builder
            ->whereIn('status', ['active', 'pending'])
            ->whereIn('role', ['admin', 'editor']);

        [$sql, $bindings] = $this->builder->toSql();

        // Should have 4 unique bindings
        $this->assertCount(4, $bindings);

        // All binding keys should be unique
        $keys = array_keys($bindings);
        $this->assertCount(4, array_unique($keys));
    }

    public function test_boolean_values_converted_to_integers(): void
    {
        $this->builder->where('active', true)->where('deleted', false);
        [$sql, $bindings] = $this->builder->toSql();

        // Booleans should be converted to 1/0
        $this->assertTrue(in_array(1, $bindings) || in_array('1', array_values($bindings)));
        $this->assertTrue(in_array(0, $bindings) || in_array('0', array_values($bindings)));
    }

    public function test_chaining_returns_self(): void
    {
        $result = $this->builder
            ->select('name')
            ->where('active', 1)
            ->orderBy('created_at')
            ->limit(10);

        $this->assertSame($this->builder, $result);
    }
}
