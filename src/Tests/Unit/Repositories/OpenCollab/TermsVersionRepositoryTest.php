<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Enums\OpenCollab\TermsVersionStatus;
use App\Models\TermsVersion;
use App\Models\UserTermsAcceptance;
use App\Repositories\OpenCollab\TermsVersionRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class TermsVersionRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private TermsVersionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TermsVersionRepository();
    }

    public function test_latest_published_for_site_excludes_drafts_and_other_sites(): void
    {
        $otherSite = $this->createSite(['slug' => 'terms-other-' . uniqid()]);

        TermsVersion::create($this->attributes(['semantic_version' => '1.0.0', 'status' => TermsVersionStatus::Published->value, 'published_at' => '2026-01-01 10:00:00']));
        TermsVersion::create($this->attributes(['semantic_version' => '1.1.0', 'status' => TermsVersionStatus::Draft->value]));
        TermsVersion::create($this->attributes(['site_id' => $otherSite->id, 'semantic_version' => '9.0.0', 'status' => TermsVersionStatus::Published->value, 'published_at' => '2026-06-01 10:00:00']));
        $expected = TermsVersion::create($this->attributes(['semantic_version' => '2.0.0', 'status' => TermsVersionStatus::Published->value, 'published_at' => '2026-05-01 10:00:00']));

        $actual = $this->repository->latestPublishedForSite($this->siteId);

        $this->assertNotNull($actual);
        $this->assertSame($expected->id, $actual->id);
    }

    public function test_find_for_site_does_not_leak_other_site_version(): void
    {
        $otherSite = $this->createSite(['slug' => 'terms-isolation-' . uniqid()]);
        $terms = TermsVersion::create($this->attributes(['site_id' => $otherSite->id]));

        $this->assertNull($this->repository->findForSite((int)$terms->id, $this->siteId));
        $this->assertSame($terms->id, $this->repository->findForSite((int)$terms->id, (int)$otherSite->id)?->id);
    }

    public function test_record_acceptance_is_idempotent_for_user_site_and_version(): void
    {
        $user = $this->createUser();
        $terms = TermsVersion::create($this->attributes(['status' => TermsVersionStatus::Published->value, 'rendered_hash' => str_repeat('a', 64)]));
        $attributes = [
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'terms_version_id' => $terms->id,
            'rendered_hash' => str_repeat('a', 64),
            'accepted_at' => '2026-06-14 12:00:00',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'accepted_via' => 'onboarding',
        ];

        $first = $this->repository->recordAcceptance($attributes);
        $second = $this->repository->recordAcceptance($attributes);

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($this->repository->hasAccepted($user->id, $this->siteId, $terms->id));
        $this->assertSame(1, UserTermsAcceptance::where('user_id', $user->id)->where('terms_version_id', $terms->id)->count());
    }

    private function attributes(array $overrides = []): array
    {
        return array_merge([
            'site_id' => $this->siteId,
            'semantic_version' => '1.0.' . random_int(1, 9999),
            'title' => 'Contributor Terms',
            'source_format' => 'html',
            'source_content' => '<p>Terms content long enough for repository testing.</p>',
            'rendered_format' => 'html',
            'rendered_content' => null,
            'rendered_hash' => null,
            'status' => TermsVersionStatus::Draft->value,
            'is_material_change' => false,
            'source_type' => 'manual',
            'extraction_status' => 'not_required',
            'created_by_user_id' => 1,
        ], $overrides);
    }
}
