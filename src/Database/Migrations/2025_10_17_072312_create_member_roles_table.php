<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateMemberRolesTable extends Migration
{
    public function up(): void
    {
        Schema::create('member_roles', function ($table) {
            $table->id();
            $table->foreignId('site_id');
            $table->string('name'); // 'basic', 'premium', 'vip', etc.
            $table->string('slug');
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
