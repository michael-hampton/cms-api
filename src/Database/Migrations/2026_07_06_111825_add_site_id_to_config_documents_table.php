<?php

use App\Framework\Database\Database;
use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSiteIdToConfigDocumentsTable extends Migration
{
    public function up(): void
    {

        $db = Database::getInstance();

        // Execute the unified statement combining the primary key, auto-increment, and positioning
        $db->exec("ALTER TABLE `config_documents` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");

        // 2. Resume using your framework's standard schema builder to handle the remaining columns and constraints
        Schema::table('config_documents', function (Blueprint $table) {
            // Add the site identifier column right after our newly minted primary id
            $table->foreignId('site_id')->after('id');

            // Establish the multi-tenant unique index check
            $table->unique(['site_id', 'type']);

            // Bind the relational foreign key constraint
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
