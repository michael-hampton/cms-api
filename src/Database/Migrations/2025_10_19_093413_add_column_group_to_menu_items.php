<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddColumnGroupToMenuItems extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->integer('column_group')->default(0);
        });

        Schema::table('menus', function (Blueprint $table) {
            $table->enum('menu_type', ['header', 'footer', 'sidebar'])->default('header');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
