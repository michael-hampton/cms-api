<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class PageAccessRolesTable extends Migration
{
    public function up(): void
    {
        Schema::create('page_access_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id');
            $table->string('role');
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->index(['page_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
