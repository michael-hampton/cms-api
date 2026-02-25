<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddOwnerFieldToPages extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable();
            $table->foreign('owner_id')->references('id')->on('users');
            $table->index(['owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
