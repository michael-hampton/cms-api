<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateNewsletterLayoutVersionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_layout_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('layout_id');
            $table->unsignedInteger('version_number');
            $table->json('layout_definition_json');
            $table->string('migration_script_reference')->nullable();
            $table->string('state')->default('draft'); // draft | validated | published | deprecated
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['layout_id', 'version_number']);
            $table->index('layout_id');
            $table->index('state');

            $table->foreign('layout_id')
                ->references('id')
                ->on('newsletter_layouts')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
