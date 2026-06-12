<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateOpenCollabDocumentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('open_collab_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('documentable_type')->nullable();
            $table->unsignedBigInteger('documentable_id')->nullable();
            $table->string('category', 80);
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('disk', 40)->default('local');
            $table->string('path', 1024);
            $table->string('mime_type', 160);
            $table->string('extension', 16);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 128)->nullable();
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index('site_id');
            $table->index(['documentable_type', 'documentable_id'], 'idx_oc_documents_documentable');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_collab_documents');
    }
}
