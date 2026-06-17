<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddModerationNotesToPagesTable extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->text('moderation_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
