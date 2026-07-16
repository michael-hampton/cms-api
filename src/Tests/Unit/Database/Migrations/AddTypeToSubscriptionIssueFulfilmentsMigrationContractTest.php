<?php

namespace App\Tests\Unit\Database\Migrations;

use PHPUnit\Framework\TestCase;

class AddTypeToSubscriptionIssueFulfilmentsMigrationContractTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $path = dirname(__DIR__, 4)
            . '/Database/Migrations/2026_07_11_090000_add_type_to_subscription_issue_fulfilments.php';

        $this->source = (string) file_get_contents($path);
    }

    public function test_type_column_defaults_to_standard(): void
    {
        $this->assertStringContainsString(
            "\$table->string('type')->default('standard');",
            $this->source
        );
    }

    public function test_column_additions_are_guarded_for_idempotent_reruns(): void
    {
        $this->assertStringContainsString(
            "Schema::hasColumn('subscription_issue_fulfilments', 'type')",
            $this->source
        );
        $this->assertStringContainsString(
            "Schema::hasColumn('subscription_issue_fulfilments', 'fulfilled_at')",
            $this->source
        );
    }

    public function test_status_enum_is_widened_to_include_fulfilled(): void
    {
        $this->assertStringContainsString(
            "['scheduled', 'delivered', 'failed', 'pending', 'superseded', 'fulfilled']",
            $this->source
        );
    }

    public function test_extraction_index_covers_type_and_fulfilled_at(): void
    {
        $this->assertStringContainsString(
            "\$table->index(['type', 'fulfilled_at']);",
            $this->source
        );
    }

    public function test_rollback_narrows_enum_before_dropping_columns(): void
    {
        $narrowEnumPosition = strpos(
            $this->source,
            "['scheduled', 'delivered', 'failed', 'pending', 'superseded'],"
        );
        $dropFulfilledAtPosition = strpos($this->source, "\$table->dropColumn('fulfilled_at');");
        $dropTypePosition = strpos($this->source, "\$table->dropColumn('type');");

        $this->assertNotFalse($narrowEnumPosition);
        $this->assertNotFalse($dropFulfilledAtPosition);
        $this->assertNotFalse($dropTypePosition);
        $this->assertLessThan($dropFulfilledAtPosition, $narrowEnumPosition);
        $this->assertLessThan($dropTypePosition, $narrowEnumPosition);
    }
}
