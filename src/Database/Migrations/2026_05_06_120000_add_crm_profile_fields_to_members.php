<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddCrmProfileFieldsToMembers extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('phone', 50)->nullable()->after('display_name');
            $table->string('company_name')->nullable()->after('phone');
            $table->string('job_title')->nullable()->after('company_name');
            $table->string('vat_number', 100)->nullable()->after('job_title');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['phone', 'company_name', 'job_title', 'vat_number']);
        });
    }
}

return new AddCrmProfileFieldsToMembers();
