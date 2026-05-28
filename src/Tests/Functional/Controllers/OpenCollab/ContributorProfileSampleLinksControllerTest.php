<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Models\ContributorOnboardingStep;
use App\Models\ContributorProfile;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class ContributorProfileSampleLinksControllerTest extends FunctionalTestCase
{
    public function test_contributor_can_save_sample_links(): void
    {
        $this->authenticatedUser->update(['is_contributor' => true]);

        ContributorProfile::create([
            'user_id' => $this->authenticatedUser->id,
            'bio' => 'Existing bio',
        ]);

        $response = $this->putForSite('/api/open-collab/profile/sample-links', [
            'sample_links' => [
                [
                    'url' => 'https://medium.com/example/article',
                    'title' => ' Example article ',
                    'description' => ' Optional context ',
                ],
                [
                    'url' => '',
                    'title' => '',
                    'description' => '',
                ],
            ],
        ]);

        $payload = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertEquals('Existing bio', $payload['data']['profile']['bio']);
        $this->assertCount(1, $payload['data']['profile']['sample_links']);
        $this->assertEquals(1, $payload['data']['profile']['sample_links'][0]['sort_order']);
        $this->assertEquals('Example article', $payload['data']['profile']['sample_links'][0]['title']);

        $profile = ContributorProfile::where('user_id', $this->authenticatedUser->id)
            ->first();

        $this->assertEquals('Existing bio', $profile->bio);
        $this->assertEquals('https://medium.com/example/article', $profile->sample_links[0]['url']);
        $this->assertFalse(
            ContributorOnboardingStep::where('user_id', $this->authenticatedUser->id)
                ->where('site_id', $this->siteId)
                ->where('step', 'profile')
                ->where('status', 'completed')
                ->exists()
        );
    }

    public function test_invalid_sample_link_url_is_rejected(): void
    {
        $this->authenticatedUser->update(['is_contributor' => true]);

        $response = $this->putForSite('/api/open-collab/profile/sample-links', [
            'sample_links' => [
                ['url' => 'javascript:alert(1)', 'title' => 'Bad link'],
            ],
        ]);

        $payload = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('sample_links.0.url', $payload['errors']);
    }

    public function test_more_than_five_sample_links_are_rejected(): void
    {
        $this->authenticatedUser->update(['is_contributor' => true]);

        $response = $this->putForSite('/api/open-collab/profile/sample-links', [
            'sample_links' => [
                ['url' => 'https://example.com/1'],
                ['url' => 'https://example.com/2'],
                ['url' => 'https://example.com/3'],
                ['url' => 'https://example.com/4'],
                ['url' => 'https://example.com/5'],
                ['url' => 'https://example.com/6'],
            ],
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }
}
