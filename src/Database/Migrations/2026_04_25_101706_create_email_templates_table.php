<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateEmailTemplatesTable extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->index();
            $table->unsignedBigInteger('theme_id')->nullable()->index();
            $table->string('name', 255);
            $table->string('slug', 255)->index();
            $table->text('description')->nullable();
            $table->enum('category', ['transactional', 'marketing', 'system'])->default('transactional')->index();
            $table->json('blocks')->nullable()->comment('Array of {type, data, visible} block objects');
            $table->boolean('is_active')->default(true)->index();
            $table->string('thumbnail_url', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['site_id', 'slug']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('theme_id')->references('id')->on('email_themes')->onDelete('set null');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
