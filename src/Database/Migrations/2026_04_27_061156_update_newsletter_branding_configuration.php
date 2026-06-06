<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class UpdateNewsletterBrandingConfiguration extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_branding_configurations', function ($table) {
            // Make newsletter_id nullable — site-library branding rows have none
            //$table->integer('newsletter_id')->nullable()->change();

            $table->integer('site_id')->nullable()->after('id');
            $table->string('name', 255)->nullable()->after('site_id');
            $table->string('slug', 255)->nullable()->after('name');
            $table->text('description')->nullable()->after('slug');
            $table->tinyInteger('is_active')->default(1)->after('description');
            $table->tinyInteger('is_default')->default(0)->after('is_active');
            $table->string('type', 50)->default('newsletter')->after('is_default');
            $table->json('clone_history')->nullable()->after('type');
            $table->index(['site_id', 'slug'], 'nbc_site_slug_idx');
            $table->index(['site_id', 'is_default', 'is_active'], 'nbc_site_default_active_idx');
            $table->index(['site_id', 'type'], 'nbc_site_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
