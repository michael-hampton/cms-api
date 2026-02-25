<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddExpertiseToAuthors extends Migration
{
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->text('expertise')->nullable()->after('bio');
            $table->json('location')->nullable()->after('expertise');
            $table->json('education')->nullable()->after('location');
            $table->json('awards')->nullable()->after('education');
            $table->date('seniority_date')->nullable()->after('awards');
            $table->boolean('is_active')->default(true)->after('seniority_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
