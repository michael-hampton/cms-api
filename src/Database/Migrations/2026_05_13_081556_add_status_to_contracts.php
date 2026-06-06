<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddStatusToContracts extends Migration
{
    public function up(): void
    {
        Schema::table('oc_contracts', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->after('content');
            $table->timestamp('published_at')->nullable()->after('status');
            $table->unsignedBigInteger('published_by')->nullable()->after('published_at');
            $table->timestamp('archived_at')->nullable()->after('published_by');
            $table->unsignedBigInteger('archived_by')->nullable()->after('archived_at');
            $table->unsignedBigInteger('source_template_id')->nullable()->after('archived_by');
            $table->unsignedBigInteger('cloned_from_version_id')->nullable()->after('source_template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
