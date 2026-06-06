<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSegmentRuleGroupsTable extends Migration
{
    public function up(): void
    {
        Schema::create('segment_rule_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('segment_id')->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('boolean')->default('AND');   // AND | OR
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('segment_id')
                ->references('id')->on('segments')
                ->cascadeOnDelete();

            // Self-referential FK for nesting — nullable for root groups.
            $table->foreign('parent_id')
                ->references('id')->on('segment_rule_groups')
                ->cascadeOnDelete();
        });

        // Attach rules to groups (optional for legacy flat rules — null = root).
        Schema::table('segment_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')
                ->nullable()
                ->index()
                ->after('segment_id');

            $table->foreign('group_id')
                ->references('id')->on('segment_rule_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
