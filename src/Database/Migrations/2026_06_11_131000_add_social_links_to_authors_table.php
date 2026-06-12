<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSocialLinksToAuthorsTable extends Migration
{
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table): void {
            if (!Schema::hasColumn('authors', 'instagram')) {
                $table->string('instagram', 500)->nullable()->after('linkedin');
            }

            if (!Schema::hasColumn('authors', 'tiktok')) {
                $table->string('tiktok', 500)->nullable()->after('instagram');
            }
        });
    }

    public function down(): void
    {
        Schema::table('authors', function (Blueprint $table): void {
            foreach (['tiktok', 'instagram'] as $column) {
                if (Schema::hasColumn('authors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
