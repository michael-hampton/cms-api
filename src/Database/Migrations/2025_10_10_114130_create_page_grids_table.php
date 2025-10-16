<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreatePageGridsTable extends Migration
{
    public function up(): void
    {
        Schema::create('page_grids', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('subtitle', 500)->nullable();
            $table->string('slug')->unique();
            $table->string('layout', 50)->default('grid');
            $table->integer('columns')->default(3);
            $table->boolean('show_excerpt')->default(true);
            $table->boolean('show_image')->default(true);
            $table->boolean('show_features')->default(true);
            $table->boolean('show_actions')->default(true);
            $table->json('pages')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('is_active');
            $table->index('created_at');
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
