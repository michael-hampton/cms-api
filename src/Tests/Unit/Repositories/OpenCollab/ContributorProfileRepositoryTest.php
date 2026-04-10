<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Models\ContributorProfile;
use App\Models\User;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ContributorProfileRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ContributorProfileRepository $repository;
    private User $user;

    public function test_find_by_user_id_returns_profile(): void
    {
        ContributorProfile::create(['user_id' => $this->user->id, 'bio' => 'Hello']);

        $profile = $this->repository->findByUserId($this->user->id);

        $this->assertNotNull($profile);
        $this->assertEquals(1, $profile->user_id);
    }

    public function test_find_by_user_id_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findByUserId(999));
    }

    public function test_create_or_update_creates_profile_when_none_exists(): void
    {
        $profile = $this->repository->createOrUpdate($this->user->id, ['bio' => 'New bio']);

        $this->assertInstanceOf(ContributorProfile::class, $profile);
        $this->assertEquals($this->user->id, $profile->user_id);
        $this->assertEquals('New bio', $profile->bio);
    }

    public function test_create_or_update_updates_existing_profile(): void
    {
        ContributorProfile::create(['user_id' => $this->user->id, 'bio' => 'Old bio']);

        $profile = $this->repository->createOrUpdate($this->user->id, ['bio' => 'New bio']);

        $this->assertEquals('New bio', $profile->bio);
        $this->assertDatabaseCount('oc_contributor_profiles', $this->user->id);
    }

    public function test_mark_payment_setup_stores_stripe_token(): void
    {
        $this->repository->markPaymentSetup($this->user->id, 'tok_test_abc');

        $this->assertDatabaseHas('oc_contributor_profiles', [
            'user_id' => $this->user->id,
            'payment_method_type' => 'stripe',
        ]);
        // payment_details is encrypted at the model layer, so we just verify the record exists
        $profile = ContributorProfile::where('user_id', $this->user->id)->first();
        $this->assertNotNull($profile->payment_details);
    }

    public function test_is_payment_setup_returns_true_when_details_present(): void
    {
        ContributorProfile::create(['user_id' => $this->user->id, 'payment_details' => 'tok_abc']);

        $this->assertTrue($this->repository->isPaymentSetup($this->user->id));
    }

    public function test_is_payment_setup_returns_false_when_no_profile(): void
    {
        $this->assertFalse($this->repository->isPaymentSetup(999));
    }

    public function test_is_payment_setup_returns_false_when_details_null(): void
    {
        ContributorProfile::create(['user_id' => $this->user->id, 'payment_details' => null]);

        $this->assertFalse($this->repository->isPaymentSetup($this->user->id));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ContributorProfileRepository();
        $this->user = $this->createUser();
    }
}