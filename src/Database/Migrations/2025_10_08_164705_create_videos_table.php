<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateVideosTable extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_name')->nullable();
            $table->string('file_path');
            $table->string('url')->nullable();
            $table->string('mime_type')->nullable();
            $table->foreignId('file_size')->nullable(); // in bytes
            $table->decimal('duration', 8, 2)->nullable(); // for videos/audio, e.g. seconds
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->json('thumbnails')->nullable(); // JSON for multiple thumbnails
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('site_id')->nullable();

            $table->softDeletes(); // adds 'deleted_at' column
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
