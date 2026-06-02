<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\PrintOrder;

use App\DTO\Subscriptions\PrintOrder\PrintOrderRecord;
use App\DTO\Subscriptions\PrintOrder\PrintOrderResult;
use App\Enums\Subscriptions\PrintRegion;
use App\Services\Subscriptions\PrintOrder\PrintOrderCalculator;
use PHPUnit\Framework\TestCase;

final class PrintOrderCalculatorTest extends TestCase
{
    private PrintOrderCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PrintOrderCalculator();
    }

    // ── Non-regional ─────────────────────────────────────────────────────────

    public function test_non_regional_returns_single_record(): void
    {
        $result = $this->calculator->calculateNonRegional(
            issueDeliveryId:   1,
            ukSubscribers:     100,
            exportSubscribers: 50,
            printOverrun:      10,
            additionalStock:   5,
            exportOverrun:     8,
        );

        $this->assertInstanceOf(PrintOrderResult::class, $result);
        $this->assertCount(1, $result->records);
        $this->assertFalse($result->isRegional());
    }

    public function test_non_regional_uk_surplus_is_overrun_plus_additional_stock(): void
    {
        $result = $this->calculator->calculateNonRegional(
            issueDeliveryId:   1,
            ukSubscribers:     100,
            exportSubscribers: 0,
            printOverrun:      10,
            additionalStock:   5,
            exportOverrun:     0,
        );

        $uk = $result->records[0]->ukLine;

        $this->assertSame(15, $uk->surplus, 'UK surplus = print_overrun + additional_stock');
        $this->assertSame(PrintRegion::UK, $uk->region);
    }

    public function test_non_regional_export_surplus_is_export_overrun_only(): void
    {
        $result = $this->calculator->calculateNonRegional(
            issueDeliveryId:   1,
            ukSubscribers:     0,
            exportSubscribers: 50,
            printOverrun:      10,
            additionalStock:   5,
            exportOverrun:     8,
        );

        $export = $result->records[0]->exportLine;

        $this->assertSame(8, $export->surplus, 'Export surplus = export_overrun only');
        $this->assertSame(PrintRegion::Export, $export->region);
    }

    public function test_non_regional_totals_are_subscribers_plus_surplus(): void
    {
        $result = $this->calculator->calculateNonRegional(
            issueDeliveryId:   1,
            ukSubscribers:     100,
            exportSubscribers: 50,
            printOverrun:      10,
            additionalStock:   5,
            exportOverrun:     8,
        );

        $record = $result->records[0];

        $this->assertSame(115, $record->ukLine->total(),     'UK total = 100 + 15');
        $this->assertSame(58,  $record->exportLine->total(), 'Export total = 50 + 8');
        $this->assertSame(173, $record->grandTotal(),        'Grand total = 115 + 58');
    }

    public function test_non_regional_subscriber_total_excludes_surplus(): void
    {
        $result = $this->calculator->calculateNonRegional(
            issueDeliveryId:   1,
            ukSubscribers:     100,
            exportSubscribers: 50,
            printOverrun:      999,
            additionalStock:   999,
            exportOverrun:     999,
        );

        $this->assertSame(150, $result->totalSubscriberCopies(), 'Subscriber total = 100 + 50 (no surplus)');
    }

    public function test_non_regional_record_has_null_regional_edition_id(): void
    {
        $result = $this->calculator->calculateNonRegional(1, 10, 5, 0, 0, 0);

        $this->assertNull($result->records[0]->regionalEditionId);
        $this->assertFalse($result->records[0]->isRegional());
    }

    public function test_non_regional_zero_subscribers_still_produces_record(): void
    {
        $result = $this->calculator->calculateNonRegional(1, 0, 0, 0, 0, 0);

        $this->assertCount(1, $result->records);
        $this->assertSame(0, $result->totalSubscriberCopies());
        $this->assertSame(0, $result->records[0]->grandTotal());
    }

    public function test_non_regional_zero_surplus_fields(): void
    {
        $result = $this->calculator->calculateNonRegional(
            issueDeliveryId:   1,
            ukSubscribers:     200,
            exportSubscribers: 75,
            printOverrun:      0,
            additionalStock:   0,
            exportOverrun:     0,
        );

        $record = $result->records[0];
        $this->assertSame(0, $record->ukLine->surplus);
        $this->assertSame(0, $record->exportLine->surplus);
        $this->assertSame(275, $record->grandTotal());
    }

    // ── Regional ─────────────────────────────────────────────────────────────

    public function test_regional_produces_one_record_per_edition(): void
    {
        $regionData = [
            ['regional_edition_id' => 10, 'uk_subscribers' => 80, 'export_subscribers' => 20, 'uk_surplus' => 5, 'export_surplus' => 2],
            ['regional_edition_id' => 11, 'uk_subscribers' => 60, 'export_subscribers' => 10, 'uk_surplus' => 3, 'export_surplus' => 1],
        ];

        $result = $this->calculator->calculateRegional(1, $regionData);

        $this->assertCount(2, $result->records);
        $this->assertTrue($result->isRegional());
    }

    public function test_regional_surplus_comes_from_edition_fields(): void
    {
        $regionData = [
            ['regional_edition_id' => 10, 'uk_subscribers' => 80, 'export_subscribers' => 20, 'uk_surplus' => 7, 'export_surplus' => 3],
        ];

        $result   = $this->calculator->calculateRegional(1, $regionData);
        $record   = $result->records[0];

        $this->assertSame(7, $record->ukLine->surplus);
        $this->assertSame(3, $record->exportLine->surplus);
    }

    public function test_regional_record_has_correct_edition_id(): void
    {
        $regionData = [
            ['regional_edition_id' => 42, 'uk_subscribers' => 10, 'export_subscribers' => 5, 'uk_surplus' => 0, 'export_surplus' => 0],
        ];

        $result = $this->calculator->calculateRegional(1, $regionData);

        $this->assertSame(42, $result->records[0]->regionalEditionId);
        $this->assertTrue($result->records[0]->isRegional());
    }

    public function test_regional_subscriber_total_sums_across_all_editions(): void
    {
        $regionData = [
            ['regional_edition_id' => 10, 'uk_subscribers' => 80, 'export_subscribers' => 20, 'uk_surplus' => 999, 'export_surplus' => 999],
            ['regional_edition_id' => 11, 'uk_subscribers' => 60, 'export_subscribers' => 10, 'uk_surplus' => 999, 'export_surplus' => 999],
        ];

        $result = $this->calculator->calculateRegional(1, $regionData);

        $this->assertSame(170, $result->totalSubscriberCopies(), '(80+20) + (60+10) = 170, surplus excluded');
    }

    public function test_regional_grand_total_includes_all_editions_and_surplus(): void
    {
        $regionData = [
            ['regional_edition_id' => 10, 'uk_subscribers' => 80, 'export_subscribers' => 20, 'uk_surplus' => 5, 'export_surplus' => 2],
            ['regional_edition_id' => 11, 'uk_subscribers' => 60, 'export_subscribers' => 10, 'uk_surplus' => 3, 'export_surplus' => 1],
        ];

        $result = $this->calculator->calculateRegional(1, $regionData);

        // (80+5 + 20+2) + (60+3 + 10+1) = 107 + 74 = 181
        $totalGrand = array_sum(array_map(fn($r) => $r->grandTotal(), $result->records));
        $this->assertSame(181, $totalGrand);
    }

    public function test_regional_empty_region_data_produces_empty_records(): void
    {
        $result = $this->calculator->calculateRegional(1, []);

        $this->assertCount(0, $result->records);
        $this->assertSame(0, $result->totalSubscriberCopies());
    }

    // ── DTO helpers ───────────────────────────────────────────────────────────

    public function test_print_order_line_total_is_subscribers_plus_surplus(): void
    {
        $result = $this->calculator->calculateNonRegional(1, 100, 50, 10, 5, 8);
        $uk     = $result->records[0]->ukLine;

        $this->assertSame($uk->subscriberCopies + $uk->surplus, $uk->total());
    }

    public function test_to_array_contains_all_required_keys(): void
    {
        $result = $this->calculator->calculateNonRegional(
            issueDeliveryId:   5,
            ukSubscribers:     100,
            exportSubscribers: 50,
            printOverrun:      10,
            additionalStock:   5,
            exportOverrun:     8,
        );

        $arr = $result->toArray();

        $this->assertArrayHasKey('issue_delivery_id',       $arr);
        $this->assertArrayHasKey('is_regional',             $arr);
        $this->assertArrayHasKey('records',                 $arr);
        $this->assertArrayHasKey('total_subscriber_copies', $arr);

        $record = $arr['records'][0];
        $this->assertArrayHasKey('uk',     $record);
        $this->assertArrayHasKey('export', $record);
        $this->assertArrayHasKey('grand_total', $record);
        $this->assertArrayHasKey('subscriber_total', $record);

        foreach (['uk', 'export'] as $lineKey) {
            $this->assertArrayHasKey('region',            $record[$lineKey]);
            $this->assertArrayHasKey('subscriber_copies', $record[$lineKey]);
            $this->assertArrayHasKey('surplus',           $record[$lineKey]);
            $this->assertArrayHasKey('total',             $record[$lineKey]);
        }
    }

    public function test_issue_delivery_id_is_preserved_on_result(): void
    {
        $result = $this->calculator->calculateNonRegional(42, 10, 5, 0, 0, 0);
        $this->assertSame(42, $result->issueDeliveryId);
        $this->assertSame(42, $result->records[0]->issueDeliveryId);
    }
}