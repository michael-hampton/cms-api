<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateReplacementPoliciesTable extends Migration
{
    public function up(): void
    {
        Schema::create('replacement_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id');
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('allows_replacements')->default(false);
            $table->boolean('allows_extensions')->default(false);
            $table->unsignedInteger('max_replacements')->nullable();
            $table->unsignedInteger('max_extensions')->nullable();
            $table->boolean('require_stock')->default(true);
            $table->boolean('requires_manager_approval')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->index(['site_id', 'is_default']);
            $table->index(['site_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
