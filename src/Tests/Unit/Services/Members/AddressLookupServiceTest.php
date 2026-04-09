<?php

namespace App\Tests\Unit\Services\Members;

use App\Repositories\Members\AddressLookupRepository;
use App\Services\Members\AddressLookupService;
use Mockery;
use PHPUnit\Framework\TestCase;

class AddressLookupServiceTest extends TestCase
{
    public function testLookupReturnsMappedAddresses(): void
    {
        $repo = Mockery::mock(AddressLookupRepository::class);
        $repo->shouldReceive('lookup')
            ->once()
            ->with('SW1A1AA')
            ->andReturn([
                [
                    'bua' => '10 Downing Street',
                    'admin_district' => 'London',
                    'admin_county' => 'Greater London',
                    'postcode' => 'SW1A 1AA',
                    'country' => 'GB',
                ],
            ]);

        $service = $this->getService($repo);

        $result = $service->lookup('SW1A 1AA'); // input with space

        $this->assertCount(1, $result);
        $this->assertSame('10 Downing Street', $result[0]['address']);
        $this->assertSame('London', $result[0]['city']);
        $this->assertSame('Greater London', $result[0]['state']);
        $this->assertSame('SW1A 1AA', $result[0]['postal_code']);
        $this->assertSame('GB', $result[0]['country']);
    }

    private function getService($repo = null): AddressLookupService
    {
        $repo = $repo ?? Mockery::mock(AddressLookupRepository::class);
        return new AddressLookupService($repo);
    }

    public function testLookupReturnsEmptyArrayWhenNoResults(): void
    {
        $repo = Mockery::mock(AddressLookupRepository::class);
        $repo->shouldReceive('lookup')
            ->once()
            ->with('ZZ99ZZ')
            ->andReturn([]);

        $service = $this->getService($repo);

        $result = $service->lookup('ZZ99 ZZ');

        $this->assertSame([], $result);
    }

    public function testLookupBuildsAddressLineIfMissing(): void
    {
        $repo = Mockery::mock(AddressLookupRepository::class);
        $repo->shouldReceive('lookup')
            ->once()
            ->with('EC1A1BB')
            ->andReturn([
                [
                    'bua' => '1 Example St',
                    'admin_district' => 'London',
                    'postcode' => 'EC1A 1BB',
                ],
            ]);

        $service = $this->getService($repo);

        $result = $service->lookup('EC1A 1BB');

        $this->assertSame('1 Example St', $result[0]['address']);
        $this->assertSame('London', $result[0]['city']);
        $this->assertSame('EC1A 1BB', $result[0]['postal_code']);
        $this->assertSame('GB', $result[0]['country']); // default
    }

    public function testLookupFiltersOutEmptyItems(): void
    {
        $repo = Mockery::mock(AddressLookupRepository::class);
        $repo->shouldReceive('lookup')
            ->once()
            ->with('EC1A1CC')
            ->andReturn([
                [], // empty item
                ['bua' => '1 Good St', 'admin_district' => 'London', 'postcode' => 'EC1A 1CC'],
            ]);

        $service = $this->getService($repo);

        $result = $service->lookup('EC1A 1CC');

        $this->assertCount(1, $result);
        $this->assertSame('1 Good St', $result[0]['address']);
    }

    public function testUkRegionsMapToGB(): void
    {
        $regions = ['England', 'Scotland', 'Wales', 'Northern Ireland'];

        foreach ($regions as $region) {
            $repo = Mockery::mock(AddressLookupRepository::class);
            $repo->shouldReceive('lookup')
                ->once()
                ->andReturn([
                    [
                        'bua' => 'Example City',
                        'admin_district' => 'Example District',
                        'postcode' => 'AB1 2CD',
                        'country' => $region,
                    ],
                ]);

            $service = $this->getService($repo);
            $result = $service->lookup('AB1 2CD');

            $this->assertSame('GB', $result[0]['country'], "Region {$region} should map to GB");
        }
    }

    public function testKnownCountriesMapCorrectly(): void
    {
        $countries = [
            'United States' => 'US',
            'Canada' => 'CA',
            'Australia' => 'AU',
            'Ireland' => 'IE',
            'New Zealand' => 'NZ',
            'Germany' => 'DE',
            'France' => 'FR',
        ];

        foreach ($countries as $name => $code) {
            $repo = Mockery::mock(AddressLookupRepository::class);
            $repo->shouldReceive('lookup')
                ->once()
                ->andReturn([
                    [
                        'bua' => 'City',
                        'admin_district' => 'District',
                        'postcode' => 'XY1 2YZ',
                        'country' => $name,
                    ],
                ]);

            $service = $this->getService($repo);
            $result = $service->lookup('XY1 2YZ');

            $this->assertSame($code, $result[0]['country'], "Country {$name} should map to {$code}");
        }
    }

    public function testUnknownCountryDefaultsToGB(): void
    {
        $repo = Mockery::mock(AddressLookupRepository::class);
        $repo->shouldReceive('lookup')
            ->once()
            ->andReturn([
                [
                    'bua' => 'Mystery City',
                    'admin_district' => 'Mystery District',
                    'postcode' => 'ZZ9 9ZZ',
                    'country' => 'Atlantis',
                ],
            ]);

        $service = $this->getService($repo);
        $result = $service->lookup('ZZ9 9ZZ');

        $this->assertSame('GB', $result[0]['country'], "Unknown country should default to GB");
    }

    public function testEmptyResultReturnsEmptyArray(): void
    {
        $repo = Mockery::mock(AddressLookupRepository::class);
        $repo->shouldReceive('lookup')
            ->once()
            ->andReturn([]);

        $service = $this->getService($repo);
        $result = $service->lookup('ZZ9 9ZZ');

        $this->assertSame([], $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}