<?php

namespace App\Tests\Unit\Repositories;

use App\Framework\Container;
use App\Framework\Support\Cache\Cache;
use App\Models\Site;
use App\Tests\Functional\Controllers\FunctionalTestCase;

abstract class RepositoryTestCase extends FunctionalTestCase
{
    /**
     * Override to not create a site automatically - let each test control this
     */
    protected function ensureSiteExists(): void
    {
        // Do nothing - let tests create sites as needed
    }

    /**
     * Override default FTC seeding; still uses shared boot + transactional isolation.
     */
    protected function setUp(): void
    {
        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }

        Container::getInstance()->flush();
        Cache::flush();
        $this->cleanupServerGlobals();

        $_ENV['APP_ENV'] = 'testing';
        putenv('APP_ENV=testing');

        $this->bootFunctionalApplication();
        $this->beginTestDatabaseTransaction();

        $this->createTestSite();
        if ($this->authenticateDefaultUser) {
            $this->actingAs();
        }
    }

    /**
     * Create a test site for repository tests
     */
    protected function createTestSite(array $overrides = []): void
    {
        $site = $this->createSite($overrides);
        $this->siteSlug = $site->slug;
        $this->siteId = $site->id;
    }

    protected function createSite(array $overrides = []): Site
    {
        $siteData = array_merge([
            'name' => 'Test Site',
            'slug' => 'test-site-' . uniqid(),
            'is_default' => true,
        ], $overrides);

        return Site::create($siteData);
    }

    /**
     * Assert model has relation loaded
     */
    protected function assertRelationLoaded($model, string $relation): void
    {
        $this->assertTrue(
            $model->relationLoaded($relation),
            "Relation '{$relation}' was not loaded"
        );
    }

    /**
     * Assert relation is not loaded
     */
    protected function assertRelationNotLoaded($model, string $relation): void
    {
        $this->assertFalse(
            $model->relationLoaded($relation),
            "Relation '{$relation}' should not be loaded"
        );
    }

    /**
     * Assert collection contains model with attributes
     */
    protected function assertCollectionContains($collection, array $attributes): void
    {
        $found = false;
        foreach ($collection as $item) {
            $matches = true;
            foreach ($attributes as $key => $value) {
                if ($item->getAttribute($key) !== $value) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Collection does not contain expected item with attributes: ' . json_encode($attributes));
    }

    /**
     * Assert collection does not contain model with attributes
     */
    protected function assertCollectionDoesNotContain($collection, array $attributes): void
    {
        $found = false;
        foreach ($collection as $item) {
            $matches = true;
            foreach ($attributes as $key => $value) {
                if ($item->getAttribute($key) !== $value) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                $found = true;
                break;
            }
        }

        $this->assertFalse($found, 'Collection should not contain item with attributes: ' . json_encode($attributes));
    }

    /**
     * Assert two models are the same (by ID and class)
     */
    protected function assertModelsEqual($expected, $actual, string $message = ''): void
    {
        $this->assertInstanceOf(get_class($expected), $actual, $message);
        $this->assertEquals($expected->id, $actual->id, $message);
    }

    /**
     * Get fresh instance of model from database
     */
    protected function fresh($model)
    {
        $class = get_class($model);
        return $class::find($model->id);
    }
}
