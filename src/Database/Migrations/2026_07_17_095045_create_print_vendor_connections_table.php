<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreatePrintVendorConnectionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('print_vendor_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 100);
            $table->enum('connection_type', ['label', 'batch', 'both'])->default('label');
            $table->string('host', 255);
            $table->unsignedInteger('port')->default(22);
            $table->string('username', 255);
            $table->text('password');
            $table->string('remote_path', 500)->default('/');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamps();

            $table->unique('code');
            $table->index(['connection_type', 'is_active']);
            $table->index(['connection_type', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
