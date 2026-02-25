<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSiteIdToNewsletterLayoutsTable extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_layouts', function (Blueprint $table) {
            // null = system layout (globally available across all sites)
            // set = user layout belonging to a specific site
            $table->unsignedBigInteger('site_id')->nullable()->after('id');
            $table->index('site_id');

            // Drop the global unique slug constraint — slugs must now be unique per site.
            // System layouts (site_id = null) use a separate unique constraint enforced in service layer.
            $table->dropUnique(['slug']);
            $table->unique(['site_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
