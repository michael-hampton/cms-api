<?php

use App\Framework\Database\Database;
use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class BackfillPersonalAccessTokenTokenables extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('personal_access_tokens', 'tokenable_type')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->string('tokenable_type')->nullable();
            });
        }

        if (!Schema::hasColumn('personal_access_tokens', 'tokenable_id')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->unsignedBigInteger('tokenable_id')->nullable();
            });
        }

        $database = Database::getInstance();

        if (Schema::hasColumn('personal_access_tokens', 'user_id')) {
            $database->query(
                "UPDATE personal_access_tokens
                 SET tokenable_type = ?, tokenable_id = user_id
                 WHERE user_id IS NOT NULL
                 AND (tokenable_type IS NULL OR tokenable_type = '' OR tokenable_id IS NULL)",
                ['App\\Models\\User']
            );
        }

        $indexExists = $database->query("SHOW INDEX FROM personal_access_tokens WHERE Key_name = ?", ['idx_tokenable_type_tokenable_id']);
        if ($indexExists->rowCount() === 0) {
            $database->query(
                "ALTER TABLE personal_access_tokens ADD KEY `idx_tokenable_type_tokenable_id` (`tokenable_type`, `tokenable_id`)"
            );
        }
    }

    public function down(): void
    {
    }
}
