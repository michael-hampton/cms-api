<?php

namespace App\Tests\Unit\Database\Migrations;

use PHPUnit\Framework\TestCase;

class FulfilmentSchedulingMigrationContractTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $path = dirname(__DIR__, 4)
            . '/Database/Migrations/2026_06_20_180000_add_fulfilment_scheduling_to_issues_delivered.php';

        $this->source = (string) file_get_contents($path);
    }

    public function test_backfill_and_duplicate_cleanup_happen_before_unique_constraint(): void
    {
        $backfillPosition = strpos($this->source, '$fulfilments = LegacyIssuesDelivered::orderBy');
        $duplicateCleanupPosition = strpos($this->source, '$fulfilment->delete()');
        $uniquePosition = strpos(
            $this->source,
            '$table->unique([\'subscription_id\', \'issue_delivery_id\']);'
        );

        $this->assertNotFalse($backfillPosition);
        $this->assertNotFalse($duplicateCleanupPosition);
        $this->assertNotFalse($uniquePosition);
        $this->assertLessThan($duplicateCleanupPosition, $backfillPosition);
        $this->assertLessThan($uniquePosition, $duplicateCleanupPosition);
    }

    public function test_rollback_drops_constraints_and_indexes_before_columns(): void
    {
        $dropUniquePosition = strpos(
            $this->source,
            '$table->dropUnique([\'subscription_id\', \'issue_delivery_id\']);'
        );
        $dropScheduleIndexPosition = strpos(
            $this->source,
            '$table->dropIndex([\'status\', \'scheduled_for\']);'
        );
        $dropDeferredIndexPosition = strpos(
            $this->source,
            '$table->dropIndex([\'status\', \'deferred_until\']);'
        );
        $dropScheduledColumnPosition = strpos($this->source, '$table->dropColumn(\'scheduled_for\');');
        $dropDeferredColumnPosition = strpos($this->source, '$table->dropColumn(\'deferred_until\');');

        $this->assertNotFalse($dropUniquePosition);
        $this->assertNotFalse($dropScheduleIndexPosition);
        $this->assertNotFalse($dropDeferredIndexPosition);
        $this->assertNotFalse($dropScheduledColumnPosition);
        $this->assertNotFalse($dropDeferredColumnPosition);
        $this->assertLessThan($dropScheduledColumnPosition, $dropUniquePosition);
        $this->assertLessThan($dropScheduledColumnPosition, $dropScheduleIndexPosition);
        $this->assertLessThan($dropDeferredColumnPosition, $dropDeferredIndexPosition);
    }
}
