<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddForgottenColumnsToMembers extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
          $table->boolean('is_forgotten')->default(false)->after('show_badges');
          $table->dateTime('forgotten_at')->nullable()->after('is_forgotten');

          $table->index('is_forgotten');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
