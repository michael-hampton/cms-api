<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddOnHoldStatusToBriefs extends Migration
{
    public function up(): void
    {
        Schema::table('briefs', function ($table) {
            $table->dropColumn('status');
            $table->enum('status', ['draft', 'in_review', 'ready', 'on_hold', 'converted', 'archived'])->default('draft');
        });
    }

    public function down(): void
    {
        Schema::table('briefs', function ($table) {
            $table->dropColumn('status');
            $table->enum('status', ['draft', 'in_review', 'ready', 'converted', 'archived'])->default('draft');
        });
    }
}
