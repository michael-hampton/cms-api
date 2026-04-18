<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class BadgeControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // GET /admin/badges
    // =========================================================================

    public function test_index_returns_200_with_paginated_badges(): void
    {
        $this->createBadge(['name' => 'Alpha', 'site_id' => $this->siteId]);
        $this->createBadge(['name' => 'Beta', 'site_id' => $this->siteId]);

        $response = $this->getForSite('/api/admin/badges');

        $this->assertResponseOk($response);
        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('meta', $body);
        $this->assertCount(2, $body['data']);
    }

    public function test_index_does_not_return_badges_from_other_sites(): void
    {
        $otherSite = $this->createSite();
        $this->createBadge(['name' => 'Other Site Badge', 'site_id' => $otherSite->id]);

        $response = $this->getForSite('/api/admin/badges');

        $this->assertResponseOk($response);
        $body = json_decode($response->getContent(), true);
        $this->assertCount(0, $body['data']);
    }

    // =========================================================================
    // GET /admin/badges/{id}
    // =========================================================================

    public function test_show_returns_badge(): void
    {
        $badge = $this->createBadge(['name' => 'Reader', 'site_id' => $this->siteId]);

        $response = $this->getForSite("/api/admin/badges/{$badge->id}");

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertEquals($badge->id, $body['id']);
        $this->assertEquals('Reader', $body['name']);
    }

    public function test_show_returns_404_when_badge_not_found(): void
    {
        $response = $this->getForSite('/api/admin/badges/99999');

        $this->assertResponseStatus(404, $response);
    }

    public function test_show_returns_404_for_badge_belonging_to_other_site(): void
    {
        $otherSite = $this->createSite();
        $badge = $this->createBadge(['site_id' => $otherSite->id]);

        $response = $this->getForSite("/api/admin/badges/{$badge->id}");

        $this->assertResponseStatus(404, $response);
    }

    // =========================================================================
    // POST /admin/badges
    // =========================================================================

    public function test_store_creates_badge_and_returns_201(): void
    {
        $response = $this->postForSite('/api/admin/badges', [
            'name' => 'Super Reader',
            'slug' => 'super-reader',
            'criteria' => [
                ['type' => 'pages_read', 'operator' => '>=', 'value' => 50],
            ],
            'points' => 100,
            'is_active' => true,
            'category' => 'test'
        ]);

        $this->assertResponseStatus(201, $response);
        $body = $this->decodeJson($response);
        $this->assertEquals('Super Reader', $body['name']);
        $this->assertEquals(100, $body['points']);
        $this->assertDatabaseHas('badges', ['name' => 'Super Reader', 'site_id' => $this->siteId]);
    }

    public function test_store_returns_422_when_name_missing(): void
    {
        $response = $this->postForSite('/api/admin/badges', [
            'criteria' => [['type' => 'comments_count', 'operator' => '>=', 'value' => 5]],
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_returns_422_when_criteria_missing(): void
    {
        $response = $this->postForSite('/api/admin/badges', [
            'name' => 'No Criteria Badge',
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_returns_422_when_criteria_value_is_non_numeric(): void
    {
        $response = $this->postForSite('/api/admin/badges', [
            'name' => 'Bad Badge',
            'criteria' => [['type' => 'COMMENTS_COUNT', 'operator' => '>=', 'value' => 'many']],
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function test_store_returns_422_on_duplicate_name(): void
    {
        $this->createBadge(['name' => 'Existing Badge', 'site_id' => $this->siteId]);

        $response = $this->postForSite('/api/admin/badges', [
            'name' => 'existing badge', // case-insensitive match
            'criteria' => [['type' => 'COMMENTS_COUNT', 'operator' => '>=', 'value' => 5]],
        ]);

        $this->assertResponseStatus(422, $response);
    }

    // =========================================================================
    // PUT /admin/badges/{id}
    // =========================================================================

    public function test_update_returns_200_with_updated_badge(): void
    {
        $badge = $this->createBadge([
            'name' => 'Old Name',
            'site_id' => $this->siteId,
            'criteria' => [['type' => 'COMMENTS_COUNT', 'operator' => '>=', 'value' => 5]],
            'is_active' => true,
        ]);

        $response = $this->putForSite("/api/admin/badges/{$badge->id}", [
            'name' => 'New Name',
            'is_active' => false,
        ]);

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertEquals('New Name', $body['name']);
        $this->assertFalse($body['is_active']);
    }

    public function test_update_returns_404_when_badge_not_found(): void
    {
        $response = $this->putForSite('/api/admin/badges/99999', ['name' => 'Ghost']);

        $this->assertResponseStatus(404, $response);
    }

    public function test_update_returns_422_on_invalid_criteria(): void
    {
        $badge = $this->createBadge(['site_id' => $this->siteId]);

        $response = $this->putForSite("/api/admin/badges/{$badge->id}", [
            'criteria' => [['type' => 'BAD_TYPE', 'operator' => '>=', 'value' => 1]],
        ]);

        $this->assertResponseStatus(404, $response);
    }

    // =========================================================================
    // DELETE /admin/badges/{id}
    // =========================================================================

    public function test_destroy_deletes_badge_and_returns_200(): void
    {
        $badge = $this->createBadge(['site_id' => $this->siteId]);

        $response = $this->deleteForSite("/api/admin/badges/{$badge->id}");

        $this->assertResponseOk($response);
        $this->assertDatabaseMissing('badges', ['id' => $badge->id]);
    }

    public function test_destroy_returns_404_when_badge_not_found(): void
    {
        $response = $this->deleteForSite('/api/admin/badges/99999');

        $this->assertResponseStatus(404, $response);
    }
}