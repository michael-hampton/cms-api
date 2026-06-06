<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSubscriptionChangesTable extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_changes', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('subscription_id');
            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->cascadeOnDelete();

            // Discriminator — controls which nullable columns are populated.
            $table->string('change_type', 50);

            // Edition-change columns (both populated for edition_change and publication_change).
            $table->unsignedBigInteger('old_edition_id')->nullable();
            $table->unsignedBigInteger('new_edition_id')->nullable();

            // Publication-change-only columns.
            $table->unsignedBigInteger('old_publication_id')->nullable();
            $table->unsignedBigInteger('new_publication_id')->nullable();
            $table->unsignedInteger('remaining_issues_transferred')->nullable();

            $table->text('reason')->nullable();

            $table->unsignedBigInteger('created_by')
                ->comment('Agent/user ID who initiated the change.');

            $table->timestamps();

            // Fast look-ups by subscription (the most common query pattern).
            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
