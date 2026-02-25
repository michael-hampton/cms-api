<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddPasswordSetAtToMembersTable extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->timestamp('password_set_at')
                ->nullable()
                ->after('password');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
