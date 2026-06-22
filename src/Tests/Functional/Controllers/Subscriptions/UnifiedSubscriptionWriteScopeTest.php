<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Models\Site;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

final class UnifiedSubscriptionWriteScopeTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_write_route_requires_matching_member_and_site(): void
    {
        $member = $this->createMember();
        $differentMember = $this->createMember();
        $differentSite = Site::create([
            'name' => 'Different Site',
            'slug' => 'different-site-' . uniqid(),
            'is_active' => true,
            'is_default' => false,
        ]);
        $memberMismatch = $this->createSubscription([
            'member_id' => $differentMember->id,
            'site_id' => $this->siteId,
            'status' => 'active',
        ]);
        $siteMismatch = $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $differentSite->id,
            'status' => 'active',
        ]);
        $this->actingAsMember($member);

        foreach ([$memberMismatch->id, $siteMismatch->id] as $id) {
            $response = $this->makeRequest(
                'POST',
                '/' . $this->siteSlug . '/member/subscriptions/unified/' . $id . '/auto-renew',
                ['auto_renew' => false],
                $this->getDefaultHeaders(['Accept' => 'application/json'], true),
            );

            $this->assertResponseStatus(404, $response);
        }
    }
}
