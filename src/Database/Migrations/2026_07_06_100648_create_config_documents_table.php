<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateConfigDocumentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('config_documents', function (Blueprint $table) {
            $table->string('type')->primary();
            $table->json('payload');
            $table->string('fingerprint');
            $table->string('updated_by')->nullable();
            $table->timestamp('updated_at');
            $table->timestamp('published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
