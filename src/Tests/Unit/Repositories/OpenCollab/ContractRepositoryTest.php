<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Enums\OpenCollab\ContractStatus;
use App\Models\Contract;
use App\Models\User;
use App\Models\UserContractSignature;
use App\Repositories\OpenCollab\ContractRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ContractRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ContractRepository $repository;
    private User $user;

    public function test_latest_for_site_returns_highest_version(): void
    {
        Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'v1']);
        Contract::create(['site_id' => $this->siteId, 'version' => 3, 'content' => 'v3']);
        Contract::create(['site_id' => $this->siteId, 'version' => 2, 'content' => 'v2']);

        $latest = $this->repository->latestForSite($this->siteId);

        $this->assertNotNull($latest);
        $this->assertEquals(3, $latest->version);
    }

    public function test_latest_for_site_returns_null_when_none_exist(): void
    {
        $this->assertNull($this->repository->latestForSite(999));
    }

    public function test_latest_for_site_does_not_return_other_site_contracts(): void
    {
        Contract::create(['site_id' => $this->siteId, 'version' => 5, 'content' => 'v5']);

        $otherSite = $this->createSite();
        $this->assertNull($this->repository->latestForSite($otherSite->id));
    }

    public function test_latest_published_for_site_excludes_drafts(): void
    {
        Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'v1', 'status' => ContractStatus::Published->value]);
        Contract::create(['site_id' => $this->siteId, 'version' => 2, 'content' => 'v2', 'status' => ContractStatus::Draft->value]);

        $latest = $this->repository->latestPublishedForSite($this->siteId);

        $this->assertNotNull($latest);
        $this->assertEquals(1, $latest->version);
        $this->assertEquals(ContractStatus::Published->value, $latest->status);
    }

    public function test_latest_published_for_site_excludes_archived(): void
    {
        Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'v1', 'status' => ContractStatus::Archived->value]);
        Contract::create(['site_id' => $this->siteId, 'version' => 2, 'content' => 'v2', 'status' => ContractStatus::Published->value]);

        $latest = $this->repository->latestPublishedForSite($this->siteId);

        $this->assertEquals(2, $latest->version);
    }

    public function test_latest_published_for_site_returns_null_when_only_drafts_exist(): void
    {
        Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'v1', 'status' => ContractStatus::Draft->value]);

        $this->assertNull($this->repository->latestPublishedForSite($this->siteId));
    }

    public function test_publish_transitions_draft_to_published(): void
    {
        $contract = Contract::create([
            'site_id' => $this->siteId,
            'version' => 1,
            'content' => 'test',
            'status' => ContractStatus::Draft->value,
        ]);

        $published = $this->repository->publish($contract, $this->user->id);

        $this->assertEquals(ContractStatus::Published->value, $published->status);
        $this->assertNotNull($published->published_at);
        $this->assertEquals($this->user->id, $published->published_by);
        $this->assertDatabaseHas('oc_contracts', [
            'id' => $contract->id,
            'status' => ContractStatus::Published->value,
        ]);
    }

    public function test_archive_transitions_published_to_archived(): void
    {
        $contract = Contract::create([
            'site_id' => $this->siteId,
            'version' => 1,
            'content' => 'test',
            'status' => ContractStatus::Published->value,
        ]);

        $archived = $this->repository->archive($contract, $this->user->id);

        $this->assertEquals(ContractStatus::Archived->value, $archived->status);
        $this->assertNotNull($archived->archived_at);
        $this->assertEquals($this->user->id, $archived->archived_by);
        $this->assertDatabaseHas('oc_contracts', [
            'id' => $contract->id,
            'status' => ContractStatus::Archived->value,
        ]);
    }

    public function test_next_version_number_returns_one_when_no_contracts_exist(): void
    {
        $this->assertEquals(1, $this->repository->nextVersionNumber(999));
    }

    public function test_next_version_number_returns_latest_plus_one(): void
    {
        Contract::create(['site_id' => $this->siteId, 'version' => 3, 'content' => 'v3', 'status' => ContractStatus::Published->value]);

        $this->assertEquals(4, $this->repository->nextVersionNumber($this->siteId));
    }

    public function test_has_signed_returns_true_when_signature_exists(): void
    {
        $contract = Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'test']);
        UserContractSignature::create([
            'user_id' => $this->user->id,
            'contract_id' => $contract->id,
            'signed_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        $this->assertTrue($this->repository->hasSigned($this->user->id, $contract->id));
    }

    public function test_has_signed_returns_false_when_no_signature(): void
    {
        $this->assertFalse($this->repository->hasSigned(1, 10));
    }

    public function test_has_any_signed_returns_false_for_unsigned_contract(): void
    {
        $contract = Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'test', 'status' => ContractStatus::Draft->value]);

        $this->assertFalse($this->repository->hasAnySigned($contract->id));
    }

    public function test_record_signature_persists_and_returns_signature(): void
    {
        $contract = Contract::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'test']);
        $signature = $this->repository->recordSignature($this->user->id, $contract->id, '192.168.1.1');

        $this->assertInstanceOf(UserContractSignature::class, $signature);
        $this->assertDatabaseHas('oc_user_contract_signatures', [
            'user_id' => $this->user->id,
            'contract_id' => $contract->id,
            'ip_address' => '192.168.1.1',
        ]);
        $this->assertNotNull($signature->signed_at);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ContractRepository();
        $this->user = $this->createUser();
    }
}