<?php

namespace App\Tests\Unit\Repositories;

use App\ApiApplication;
use App\Framework\Container;
use App\Framework\Database\Database;
use App\Models\Site;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Stripe\StripeClient;

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
     * Override to not authenticate by default
     */
    protected function setUp(): void
    {
        $_ENV['APP_ENV'] = 'testing';
        putenv('APP_ENV=testing');

        $testConfig = [
            'driver' => 'mysql',
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'test_db',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASS') ?: 'rootsecret',
            'charset' => 'utf8mb4',
        ];

        $this->database = Database::getInstance($testConfig);
        $this->app = new ApiApplication($testConfig);
        Container::getInstance()->instance(StripeClient::class, $this->mockStripeClient());

        // Create a default site for repository tests
        $this->createTestSite();

        $this->actingAs();
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
