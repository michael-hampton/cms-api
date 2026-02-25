<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateNewsletterSnapshotsTable extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('newsletter_id');
            $table->unsignedBigInteger('layout_version_id')->nullable();
            $table->unsignedBigInteger('branding_version_id')->nullable();
            $table->longText('layout_html_snapshot');
            $table->json('branding_snapshot_json')->nullable();
            $table->string('view_token')->nullable()->unique();
            $table->timestamp('view_token_expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('newsletter_id');
            $table->index('view_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
