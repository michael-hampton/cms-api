<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddFieldsToPagesTable extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->text('listing_synopsis')->nullable();
            $table->string('listing_title')->nullable();
            $table->string('listing_label')->nullable();
            $table->json('crop_overrides')->nullable();
            $table->json('resolved_images')->nullable();
            $table->string('hero_type')->nullable();
            $table->string('hero_video_url')->nullable();
            $table->integer('listing_image_id')->nullable();
            $table->integer('hero_image_id')->nullable();
            $table->boolean('listing_use_as_hero')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
