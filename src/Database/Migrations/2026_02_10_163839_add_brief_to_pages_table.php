<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddBriefToPagesTable extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('brief_id')->nullable();
            $table->foreign('brief_id')->references('id')->on('briefs');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
