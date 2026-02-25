<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddAnonymousToMembersTable extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('anonymous')->default(false)->after('is_active');

            // Add unique constraint on (email, site_id) to prevent race conditions
            $table->unique(['email', 'site_id'], 'members_email_site_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
