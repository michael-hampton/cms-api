<?php

namespace App\Tests\Unit\Listeners\Members;

use App\Events\Members\MemberAddressImported;
use App\Events\Members\MemberCreated;
use App\Events\Members\MemberPostcodeUpdated;
use App\Framework\Support\Logger;
use App\Listeners\Members\AssignMemberTerritoryListener;
use App\Models\Address;
use App\Models\Member;
use App\Services\Members\TerritoryResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;
use Mockery\MockInterface;

/**
 * Tests that AssignMemberTerritoryListener correctly persists territory_id to
 * the Member record in the database.
 *
 * TerritoryResolver and Logger are mocked — they have independent reasons to
 * change and are tested separately. The Member is a real DB record so we can
 * assert the write actually happened.
 */
class AssignMemberTerritoryListenerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private TerritoryResolver|MockInterface $resolver;
    private Logger|MockInterface $logger;
    private AssignMemberTerritoryListener $listener;

    public function test_assigns_territory_to_member_on_member_created(): void
    {
        $territory = $this->createTerritory();
        $member = $this->createMember();

        $this->createAddress($member, 'CF10 3NQ');

        $this->resolver
            ->shouldReceive('resolve')
            ->once()
            ->with('CF10 3NQ')
            ->andReturn($territory);

        $this->listener->handle(new MemberCreated($member));

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'territory_id' => $territory->id,
        ]);
    }

    private function createAddress(Member $member, $postcode)
    {
        Address::create([
            'member_id' => $member->id,
            'type' => 'shipping',
            'is_default' => 1,
            'postcode' => $postcode,
            'address_line_1' => 'Test',
            'city' => 'Test',
            'country' => 'United Kingdom'
        ]);
    }

    public function test_skips_assignment_when_member_has_no_postcode_on_created(): void
    {
        $member = $this->createMember();

        $this->resolver->shouldNotReceive('resolve');

        $this->listener->handle(new MemberCreated($member));

        $member->refresh();

        $this->assertNull($member->territory_id);
    }

    // =========================================================================
    // MemberCreated
    // =========================================================================

    public function test_assigns_territory_to_member_on_postcode_updated(): void
    {
        $territory = $this->createTerritory();
        $member = $this->createMember();

        $this->createAddress($member, 'EH1 1AA');

        $this->resolver
            ->shouldReceive('resolve')
            ->once()
            ->with('EH1 1AA')
            ->andReturn($territory);

        $this->listener->handleMemberPostcodeUpdated(
            new MemberPostcodeUpdated($member, 'EH1 1AA', 'SW1A 1AA')
        );

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'territory_id' => $territory->id,
        ]);
    }

    public function test_logs_info_and_does_not_update_member_when_no_territory_found(): void
    {
        $member = $this->createMember();

        $this->createAddress($member, 'ZZ99 9ZZ');

        $this->resolver
            ->shouldReceive('resolve')
            ->once()
            ->andReturn(null);

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with(
                'AssignMemberTerritoryListener: no territory found for postcode prefix',
                Mockery::hasKey('member_id')
            );

        $this->listener->handleMemberPostcodeUpdated(
            new MemberPostcodeUpdated($member, 'ZZ99 9ZZ', null)
        );

        $member->refresh();

        $this->assertNull($member->territory_id);
    }

    // =========================================================================
    // MemberPostcodeUpdated
    // =========================================================================

    public function test_assigns_territory_to_member_on_address_imported(): void
    {
        $territory = $this->createTerritory();
        $member = $this->createMember();

        $this->createAddress($member, 'SW1A 2AA');

        $this->resolver
            ->shouldReceive('resolve')
            ->once()
            ->with('SW1A 2AA')
            ->andReturn($territory);

        $this->listener->handleMemberAddressImported(new MemberAddressImported($member));

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'territory_id' => $territory->id,
        ]);
    }

    public function test_overwrites_existing_territory_when_postcode_updated(): void
    {
        $wales = $this->createTerritory();
        $scotland = $this->createTerritory();
        $member = $this->createMember(['territory_id' => $wales->id]);

        $this->createAddress($member, 'CF10 3NQ');

        $this->resolver
            ->shouldReceive('resolve')
            ->once()
            ->with('EH1 1AA')
            ->andReturn($scotland);

        $this->listener->handleMemberPostcodeUpdated(
            new MemberPostcodeUpdated($member, 'EH1 1AA', 'CF10 3NQ')
        );

        $member->refresh();

        $this->assertEquals($scotland->id, $member->territory_id);

    }

    // =========================================================================
    // MemberAddressImported
    // =========================================================================

    public function test_catches_and_logs_exception_without_bubbling_on_member_created(): void
    {
        $member = $this->createMember(['postcode' => 'CF10 3NQ']);

        $this->createAddress($member, 'CF10 3NQ');

        $this->resolver
            ->shouldReceive('resolve')
            ->once()
            ->andThrow(new \RuntimeException('DB connection lost'));

        $this->logger
            ->shouldReceive('error')
            ->once()
            ->with(
                'AssignMemberTerritoryListener: failed to assign territory',
                Mockery::hasKey('error')
            );

        // Must not throw — this is a non-critical side effect
        $this->listener->handle(new MemberCreated($member));

        $member->refresh();

        $this->assertEquals(null, $member->territory_id);
    }

    // =========================================================================
    // Overwrites existing territory when postcode changes
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = Mockery::mock(TerritoryResolver::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $this->listener = new AssignMemberTerritoryListener($this->resolver, $this->logger);
    }

    // =========================================================================
    // Error handling — non-critical path must never throw
    // =========================================================================

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}