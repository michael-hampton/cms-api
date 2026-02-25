<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddPageIdToBriefs extends Migration
{
    public function up(): void
    {
        Schema::table('briefs', function (Blueprint $table) {
            $table->foreignId('page_id')->nullable();
            $table->foreign('page_id')->references('id')->on('pages');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
