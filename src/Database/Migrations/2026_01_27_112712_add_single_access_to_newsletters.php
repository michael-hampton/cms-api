<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSingleAccessToNewsletters extends Migration
{
    public function up(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            $table->boolean('allows_single_purchase')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
