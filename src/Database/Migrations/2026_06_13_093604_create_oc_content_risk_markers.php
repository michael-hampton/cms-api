<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateOcContentRiskMarkers extends Migration
{
    public function up(): void
    {
        Schema::create('oc_content_risk_markers', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('page_id')->nullable();
            $table->unsignedBigInteger('page_version_id')->nullable();
            $table->unsignedBigInteger('cms_image_id')->nullable();

            $table->string('risk_type', 50);
            $table->string('source', 50);
            $table->string('severity', 20);
            $table->decimal('confidence', 4, 3)->nullable();

            $table->string('status', 20)->default('open');
            $table->json('details')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            $table->timestamps();

            $table->index(
                ['site_id', 'page_id'],
                'idx_site_page',
            );

            $table->index(
                ['site_id', 'status'],
                'idx_site_status',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
