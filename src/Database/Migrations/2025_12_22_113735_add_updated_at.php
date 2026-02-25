<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddUpdatedAt extends Migration
{
    public function up(): void
    {
        Schema::table('editorial_overrides', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
