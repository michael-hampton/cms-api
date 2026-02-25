<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSlugToNewsletters extends Migration
{
    public function up(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
