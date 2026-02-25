<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddDefaultToNewsletters extends Migration
{
    public function up(): void
    {
        Schema::table('newsletters', function ($table) {
            $table->boolean('is_default')->default(false)->after('active');
            $table->index(['site_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
