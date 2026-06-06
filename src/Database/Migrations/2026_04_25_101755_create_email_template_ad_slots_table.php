<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateEmailTemplateAdSlotsTable extends Migration
{
    public function up(): void
    {
        Schema::create('email_template_ad_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->index();
            $table->enum('placement', ['top', 'mid', 'bottom'])->index();
            $table->text('content_html')->comment('Resolved ad HTML injected at render time');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'placement']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
