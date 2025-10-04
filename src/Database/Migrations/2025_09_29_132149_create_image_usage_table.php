<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateImageUsageTable extends Migration
{
    public function up(): void
    {
        Schema::create('image_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('image_id');
            $table->string('usable_type');
            $table->bigInteger('usable_id')->unsigned();
            $table->string('context')->nullable();
            $table->timestamps();

            // Optional: add indexes for faster queries
            $table->index(['usable_type', 'usable_id']);
            $table->index('image_id');

            $table->foreign('image_id')->references('id')->on('images')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
