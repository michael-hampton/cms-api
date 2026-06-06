<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateContributorDashboardWidgetsTable extends Migration
{
    public function up(): void
    {
        Schema::create('oc_contributor_dashboard_widgets', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->string('widget_key', 64);

            // Whether this widget is visible to the user.
            // Defaults true — user must explicitly disable.
            $table->boolean('enabled')->default(true);

            // Display order. Lower = higher up the dashboard.
            $table->unsignedSmallInteger('position')->default(0);

            // Widget-specific settings (future use: config per widget instance).
            $table->json('settings')->nullable();

            $table->timestamps();

            // One config row per user per widget
            $table->unique(['user_id', 'widget_key']);

            // Query path: always fetching all widgets for a user
            $table->index('user_id');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
