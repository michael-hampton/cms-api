<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddActivityPrivacyToMembers extends Migration
{
    public function up(): void
    {
        Schema::table('members', function ($table) {
            $table->boolean('show_activity')->default(true);
            $table->boolean('show_badges')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
