<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Models\Segment;
use App\Models\SegmentRule;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class SegmentAdminApiControllerTest extends FunctionalTestCase
{
    public function test_store_creates_segment_with_rules(): void
    {
        $response = $this->postForSite('/api/admin/segments', [
            'key' => 'churning',
            'name' => 'Churning',
            'rules' => [
                ['field' => 'scores.activity_score', 'operator' => '<=', 'value' => 5, 'boolean' => 'AND'],
            ],
        ]);

        $this->assertResponseStatus(201, $response);
        $segment = Segment::where('key', 'churning')->first();
        $this->assertNotNull($segment);
        $this->assertNotNull(SegmentRule::where('segment_id', $segment->id)->first());
    }

    public function test_show_returns_rules(): void
    {
        $segment = Segment::create([
            'key' => 'lurker',
            'name' => 'Lurker',
            'is_active' => true,
        ]);
        SegmentRule::create([
            'segment_id' => $segment->id,
            'field' => 'metrics.comments_count',
            'operator' => '=',
            'value' => 0,
            'boolean' => 'AND',
            'sort_order' => 0,
        ]);

        $response = $this->getForSite("/api/admin/segments/{$segment->id}");

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertCount(1, $body['rules']);
    }

    public function test_update_replaces_rules(): void
    {
        $segment = Segment::create([
            'key' => 'declining',
            'name' => 'Declining',
            'is_active' => true,
        ]);
        SegmentRule::create([
            'segment_id' => $segment->id,
            'field' => 'metrics.views',
            'operator' => '>',
            'value' => 2,
            'boolean' => 'AND',
            'sort_order' => 0,
        ]);

        $response = $this->putForSite("/api/admin/segments/{$segment->id}", [
            'rules' => [
                ['field' => 'metrics.views', 'operator' => '<=', 'value' => 2, 'boolean' => 'AND'],
            ],
        ]);

        $this->assertResponseOk($response);
        $this->assertDatabaseHas('segment_rules', ['segment_id' => $segment->id, 'operator' => '<=']);
        $this->assertDatabaseMissing('segment_rules', ['segment_id' => $segment->id, 'operator' => '>']);
    }

    public function test_destroy_deletes_segment(): void
    {
        $segment = Segment::create([
            'key' => 'power',
            'name' => 'Power',
            'is_active' => true,
        ]);

        $response = $this->deleteForSite("/api/admin/segments/{$segment->id}");

        $this->assertResponseOk($response);
        $this->assertDatabaseMissing('segments', ['id' => $segment->id]);
    }

    public function test_store_fails_with_duplicate_key(): void
    {
        Segment::create([
            'key' => 'existing',
            'name' => 'Existing',
        ]);

        $response = $this->postForSite('/api/admin/segments', [
            'key' => 'existing',
            'name' => 'New Name',
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function test_update_fails_with_invalid_rule_operator(): void
    {
        $segment = Segment::create([
            'key' => 'test',
            'name' => 'Test',
        ]);

        $response = $this->putForSite("/api/admin/segments/{$segment->id}", [
            'rules' => [
                ['field' => 'email', 'operator' => 'INVALID', 'value' => 'test'],
            ],
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function test_show_returns_404_when_not_found(): void
    {
        $response = $this->getForSite('/api/admin/segments/9999');

        $this->assertResponseStatus(404, $response);
    }

    public function test_update_returns_404_when_not_found(): void
    {
        $response = $this->putForSite('/api/admin/segments/9999', [
            'name' => 'New Name'
        ]);

        $this->assertResponseStatus(404, $response);
    }

    public function test_destroy_returns_404_when_not_found(): void
    {
        $response = $this->deleteForSite('/api/admin/segments/9999');

        $this->assertResponseStatus(404, $response);
    }
}
