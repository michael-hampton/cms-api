<?php

namespace App\Tests\Unit\Services\Gdpr;

use App\Models\Member;
use App\Services\Gdpr\MemberExportService;
use App\Services\Gdpr\Exporters\MemberDataExporter;
use Mockery;
use PHPUnit\Framework\TestCase;

class MemberExportServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_builds_complete_export_bundle()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 123;

        $exporterA = Mockery::mock(MemberDataExporter::class);
        $exporterA->shouldReceive('key')->andReturn('profile');
        $exporterA->shouldReceive('export')->once()->with($member)->andReturn([
            'name' => 'Test User',
        ]);

        $exporterB = Mockery::mock(MemberDataExporter::class);
        $exporterB->shouldReceive('key')->andReturn('orders');
        $exporterB->shouldReceive('export')->once()->with($member)->andReturn([
            'orders' => [],
        ]);

        $service = new MemberExportService([
            $exporterA,
            $exporterB,
        ]);

        $result = $service->export($member);

        $this->assertEquals(123, $result['member_id']);
        $this->assertArrayHasKey('profile', $result['modules']);
        $this->assertArrayHasKey('orders', $result['modules']);
    }

    public function test_it_isolates_exporter_failures()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 10;

        $goodExporter = Mockery::mock(MemberDataExporter::class);
        $goodExporter->shouldReceive('key')->andReturn('good');
        $goodExporter->shouldReceive('export')->andReturn(['ok' => true]);

        $badExporter = Mockery::mock(MemberDataExporter::class);
        $badExporter->shouldReceive('key')->andReturn('bad');
        $badExporter->shouldReceive('export')
            ->andThrow(new \Exception('Boom'));

        $service = new MemberExportService([
            $goodExporter,
            $badExporter,
        ]);

        $result = $service->export($member);

        $this->assertArrayHasKey('good', $result['modules']);
        $this->assertArrayHasKey('bad', $result['modules']);
        $this->assertArrayHasKey('error', $result['modules']['bad']);
    }

    public function test_it_returns_metadata()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 999;

        $exporter = Mockery::mock(MemberDataExporter::class);
        $exporter->shouldReceive('key')->andReturn('x');
        $exporter->shouldReceive('export')->andReturn([]);

        $service = new MemberExportService([$exporter]);

        $result = $service->export($member);

        $this->assertEquals(999, $result['member_id']);
        $this->assertArrayHasKey('exported_at', $result);
        $this->assertArrayHasKey('modules', $result);
    }
}