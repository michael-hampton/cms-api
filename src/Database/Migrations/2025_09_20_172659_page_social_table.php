<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class PageSocialTable extends Migration
{
    public function up(): void
    {
        Schema::create('page_social', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id');
            $table->boolean('enable_sharing')->default(true);
            $table->json('platforms')->nullable();
            $table->string('share_text')->nullable();
            $table->string('share_hashtags')->nullable();
            $table->string('share_via')->nullable();
            $table->boolean('track_shares')->default(false);
            $table->boolean('track_clicks')->default(false);
            $table->json('pixel_ids')->nullable();
            $table->boolean('gtm_events')->default(false);
            $table->boolean('show_follower_count')->default(false);
            $table->boolean('show_share_count')->default(false);
            $table->boolean('show_recent_activity')->default(false);
            $table->boolean('testimonial_integration')->default(false);
            $table->boolean('auto_embed_links')->default(false);
            $table->string('embed_width')->default('100%');
            $table->string('embed_height')->default('400px');
            $table->boolean('lazy_load_embeds')->default(true);
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->unique('page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
