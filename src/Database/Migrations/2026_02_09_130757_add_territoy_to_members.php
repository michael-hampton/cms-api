<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddTerritoyToMembers extends Migration
{
    public function up(): void
    {
        Schema::table('members', function ($table) {
            $table->unsignedBigInteger('territory_id')->nullable()->after('region');
            $table->foreign('territory_id')
                ->references('id')
                ->on('territories')
                ->onDelete('set null');
            $table->index('territory_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
